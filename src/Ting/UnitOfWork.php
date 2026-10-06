<?php

/***********************************************************************
 *
 * Ting - PHP Datamapper
 * ==========================================
 *
 * Copyright (C) 2014 CCM Benchmark Group. (http://www.ccmbenchmark.com)
 * Copyright (C) 2026 Xavier Leune
 *
 ***********************************************************************
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you
 * may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
 * implied. See the License for the specific language governing
 * permissions and limitations under the License.
 *
 **********************************************************************/

namespace CCMBenchmark\Ting;

use CCMBenchmark\Ting\Driver\DriverInterface;
use CCMBenchmark\Ting\Driver\QueryException;
use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;
use CCMBenchmark\Ting\Entity\PropertyListenerInterface;
use CCMBenchmark\Ting\Query\QueryFactoryInterface;
use CCMBenchmark\Ting\Repository\Metadata;
use Closure;
use WeakMap;

class UnitOfWork implements PropertyListenerInterface, ResetInterface
{
    public const STATE_NEW     = 1;
    public const STATE_MANAGED = 2;
    public const STATE_DELETE  = 3;
    /** @var WeakMap<NotifyPropertyInterface, true> the managed entities */
    protected WeakMap $entities;
    /**
     * Changes notified by propertyChanged() since the last write
     *
     * @var WeakMap<NotifyPropertyInterface, array<string, array{0: mixed, 1: mixed}>> property => [old value, new value]
     */
    protected WeakMap $entitiesChanged;
    /**
     * Database values of the mutable primary keys of the managed entities, as read from or last written to the
     * database: a key modified in place still targets its row. Nothing else is kept: the other mutable fields are
     * written on every save, the immutable ones when notified.
     *
     * @var WeakMap<NotifyPropertyInterface, array<string, mixed>> property => database value
     */
    protected WeakMap $mutablePrimaryValues;
    /**
     * Properties whose value is not known: left out of a partial read (a SELECT of some columns, a join), they hold a
     * PHP default (null, a value set by the constructor...), not the stored value. They are not written until set
     * through their setter. Only the entities read partially are listed, with property names only.
     *
     * @var WeakMap<NotifyPropertyInterface, array<string, true>> property name => true
     */
    protected WeakMap $propertiesNotRead;
    /**
     * Entities this unit of work listens to: a listener can't be removed from an entity, so it stays registered once
     * the entity is detached (its notifications are then ignored), and is not added again when it is managed again
     *
     * @var WeakMap<NotifyPropertyInterface, true>
     */
    protected WeakMap $listenedEntities;
    /**
     * @var array<string, array{state: self::STATE_*, entity: NotifyPropertyInterface, metadata?: Metadata<object>|null}>
     *      by object hash; the metadata given to pushSave() or pushDelete() to write the entity with
     */
    protected array $entitiesShouldBePersisted = [];
    /** @var array<string, array<string, DriverInterface>>  */
    protected array $statements = [];
    /**
     * NotifyProperty::$listenersOwner, by entity class: null for a class which does not use the trait
     *
     * @var array<class-string, \ReflectionProperty|null>
     */
    private static array $listenersOwnerProperties = [];

    /**
     * @param ConnectionPool        $connectionPool
     * @param MetadataRepository    $metadataRepository
     * @param QueryFactoryInterface $queryFactory
     */
    public function __construct(
        protected ConnectionPool $connectionPool,
        protected MetadataRepository $metadataRepository,
        protected QueryFactoryInterface $queryFactory
    ) {
        $this->entities = new WeakMap();
        $this->entitiesChanged = new WeakMap();
        $this->mutablePrimaryValues = new WeakMap();
        $this->propertiesNotRead = new WeakMap();
        $this->listenedEntities = new WeakMap();
    }

    /**
     * Record the properties left out of the partial read of an entity: their value is not known, so they are not
     * written until set through their setter. Replaces the properties recorded before; none to forget them.
     *
     * @param array<string, true> $properties property name => true
     *
     * @internal called by the hydrators
     */
    public function setPropertiesNotRead(NotifyPropertyInterface $entity, array $properties): void
    {
        if ($properties === []) {
            $this->propertiesNotRead->offsetUnset($entity);

            return;
        }

        $this->propertiesNotRead[$entity] = $properties;
    }

    /**
     * Watch changes on provided entity.
     * The current values of its mutable primary keys are taken as the values stored in the database.
     *
     * @param NotifyPropertyInterface $entity
     */
    public function manage(NotifyPropertyInterface $entity): void
    {
        $this->entities[$entity] = true;

        if (isset($this->mutablePrimaryValues[$entity]) === false) {
            $this->metadataRepository->findMetadataForEntity(
                $entity,
                function (Metadata $metadata) use ($entity): void {
                    $this->keepMutablePrimaryValues($entity, $metadata);
                },
                static function (): void {
                    // Without metadata, the entity cannot be saved: there is nothing to keep
                }
            );
        }

        if (isset($this->listenedEntities[$entity]) === false) {
            $this->listenedEntities[$entity] = true;
            $entity->addPropertyListener($this);
        }
    }

    /**
     * @template E of object
     * @param Metadata<E> $metadata
     */
    private function keepMutablePrimaryValues(NotifyPropertyInterface $entity, Metadata $metadata): void
    {
        $values = $metadata->getEntityMutablePrimaryValues($entity);
        if ($values !== []) {
            $this->mutablePrimaryValues[$entity] = $values;
        }
    }

    /**
     * @param NotifyPropertyInterface $entity
     * @return bool - true if the entity is managed
     */
    public function isManaged(NotifyPropertyInterface $entity): bool
    {
        return isset($this->entities[$entity]);
    }

    /**
     * @param NotifyPropertyInterface $entity
     * @return bool - true if the entity is queued for its INSERT by pushSave()
     */
    public function isNew(NotifyPropertyInterface $entity): bool
    {
        $hash = spl_object_hash($entity);
        return isset($this->entitiesShouldBePersisted[$hash])
            && $this->entitiesShouldBePersisted[$hash]['state'] === self::STATE_NEW;
    }

    /**
     * Flag the entity to be persisted (insert or update) on next process.
     *
     * A managed entity is updated with its changes. An entity not managed is new: inserted (its autoincrement
     * property, if set, is left out of the INSERT then overwritten by the generated id), then managed. Except a clone
     * of an entity managed by this unit of work (see getManagedOriginal()): it is a copy of a row, updated by its
     * primary key, then managed. Until then, it is not managed.
     *
     * @param Metadata<object>|null $metadata the metadata to write the entity with (internal: given by
     *                                        Repository::save()), those registered for its class otherwise
     */
    public function pushSave(NotifyPropertyInterface $entity, ?Metadata $metadata = null): static
    {
        $state = self::STATE_MANAGED;

        if (isset($this->entities[$entity]) === false && $this->getManagedOriginal($entity) === null) {
            $state = self::STATE_NEW;
        }

        $hash = spl_object_hash($entity);
        $this->entitiesShouldBePersisted[$hash] = ['state' => $state, 'entity' => $entity, 'metadata' => $metadata];

        return $this;
    }

    /**
     * @param NotifyPropertyInterface $entity
     * @return bool
     */
    public function shouldBePersisted(NotifyPropertyInterface $entity): bool
    {
        $hash = spl_object_hash($entity);
        return isset($this->entitiesShouldBePersisted[$hash]);
    }

    /**
     * Record the change of a property: it is written on the next save of the entity, unless set back to its old
     * value. The same value given as old and new value, objects included, is not a change, and neither is the change
     * of an entity not managed (a new one is inserted whole, a detached one is no longer tracked).
     */
    public function propertyChanged(NotifyPropertyInterface $entity, string $propertyName, mixed $oldValue, mixed $newValue): void
    {
        if ($oldValue === $newValue || isset($this->entities[$entity]) === false) {
            return;
        }

        if (isset($this->propertiesNotRead[$entity][$propertyName])) {
            // Set through its setter: its value is known from now on
            $notRead = $this->propertiesNotRead[$entity];
            unset($notRead[$propertyName]);
            $this->setPropertiesNotRead($entity, $notRead);
        }

        if (isset($this->entitiesChanged[$entity]) === false) {
            $this->entitiesChanged[$entity] = [];
        }

        if (isset($this->entitiesChanged[$entity][$propertyName]) === false) {
            $this->entitiesChanged[$entity][$propertyName] = [$oldValue, null];
        }

        $this->entitiesChanged[$entity][$propertyName][1] = $newValue;
    }

    /**
     * @return bool true if the property will be written by the next save of the entity: its change has been notified,
     *              or it is a mutable field of a managed entity (written on every save) whose value is known
     */
    public function isPropertyChanged(NotifyPropertyInterface $entity, string $propertyName): bool
    {
        if (isset($this->entitiesChanged[$entity][$propertyName])
            && $this->entitiesChanged[$entity][$propertyName][0] !== $this->entitiesChanged[$entity][$propertyName][1]
        ) {
            return true;
        }

        if (isset($this->entities[$entity]) === false || isset($this->propertiesNotRead[$entity][$propertyName])) {
            return false;
        }

        $mutable = false;
        $this->metadataRepository->findMetadataForEntity(
            $entity,
            function (Metadata $metadata) use ($propertyName, &$mutable): void {
                $mutable = $metadata->isMutable($propertyName);
            },
            static function (): void {
                // Without metadata, nothing is mutable
            }
        );

        return $mutable;
    }

    /**
     * Stop watching changes on the entity. Pushing it again with pushSave() would insert it.
     *
     * @param NotifyPropertyInterface $entity
     */
    public function detach(NotifyPropertyInterface $entity): void
    {
        unset($this->entitiesShouldBePersisted[spl_object_hash($entity)]);
        $this->entitiesChanged->offsetUnset($entity);
        $this->mutablePrimaryValues->offsetUnset($entity);
        $this->propertiesNotRead->offsetUnset($entity);
        $this->entities->offsetUnset($entity);
    }

    /**
     * Stop watching changes on all entities
     */
    public function detachAll(): void
    {
        $this->entitiesChanged = new WeakMap();
        $this->mutablePrimaryValues = new WeakMap();
        $this->propertiesNotRead = new WeakMap();
        $this->entitiesShouldBePersisted = [];
        $this->entities = new WeakMap();
    }

    /**
     * Reset state between requests (worker mode: FrankenPHP, Swoole, RoadRunner, etc.)
     */
    public function reset(): void
    {
        $this->detachAll();
        $this->statements = [];
    }

    /**
     * Flag the entity to be deleted on next process
     *
     * An entity never inserted (queued for its INSERT, or not managed and without primary key) has no row: it is
     * unqueued, no query is run. An entity not managed but with its primary key is deleted by that key: it does not
     * become managed, so a later pushSave() replacing the deletion, or a save after a failed DELETE, inserts it.
     *
     * @param Metadata<object>|null $metadata the metadata to delete the entity with (internal: given by
     *                                        Repository::delete()), those registered for its class otherwise
     */
    public function pushDelete(NotifyPropertyInterface $entity, ?Metadata $metadata = null): static
    {
        $hash = spl_object_hash($entity);
        if ($this->isNew($entity)
            || (isset($this->entities[$entity]) === false && $this->hasPrimaryKey($entity, $metadata) === false)
        ) {
            unset($this->entitiesShouldBePersisted[$hash]);

            return $this;
        }

        $this->entitiesShouldBePersisted[$hash] = ['state' => self::STATE_DELETE, 'entity' => $entity, 'metadata' => $metadata];

        return $this;
    }

    /**
     * @param Metadata<object>|null $metadata
     * @return bool false when a primary key of the entity is not set (not initialized or null); true without metadata,
     *              process() then reports the missing repository
     */
    private function hasPrimaryKey(NotifyPropertyInterface $entity, ?Metadata $metadata): bool
    {
        $hasPrimaryKey = true;
        $this->findMetadataToWrite(
            $entity,
            $metadata,
            function (Metadata $metadata) use ($entity, &$hasPrimaryKey): void {
                $hasPrimaryKey = $this->isPrimaryKeySet($entity, $metadata);
            },
            static function (): void {
                // Without metadata, process() throws
            }
        );

        return $hasPrimaryKey;
    }

    /**
     * The entity managed by this unit of work that $entity is a clone of. NotifyProperty keeps a reference to the
     * object its listeners were added to (by manage()): clone copies it, so in a clone it still points to the
     * original. Read by reflection: the reference is internal to the trait, and NotifyPropertyInterface does not
     * expose it (adding a method would break its implementations).
     *
     * @return NotifyPropertyInterface|null null when $entity is not a clone, its original is not managed (detached,
     *                                      freed, managed by another unit of work), or it does not use NotifyProperty
     *                                      (an unserialized entity has no reference)
     */
    private function getManagedOriginal(NotifyPropertyInterface $entity): ?NotifyPropertyInterface
    {
        $class = $entity::class;
        if (\array_key_exists($class, self::$listenersOwnerProperties) === false) {
            self::$listenersOwnerProperties[$class] = \in_array(NotifyProperty::class, $this->getTraits($class), true)
                ? new \ReflectionProperty($class, 'listenersOwner')
                : null;
        }

        $owner = self::$listenersOwnerProperties[$class]?->getValue($entity);
        $original = $owner instanceof \WeakReference ? $owner->get() : null;

        return $original !== $entity && $original instanceof NotifyPropertyInterface && isset($this->entities[$original])
            ? $original
            : null;
    }

    /**
     * @param class-string $class
     * @return list<string> the traits used by the class, its parents and their traits
     */
    private function getTraits(string $class): array
    {
        $traits = [];
        foreach ([$class, ...array_values(class_parents($class))] as $name) {
            $toVisit = array_values(class_uses($name));
            while ($toVisit !== []) {
                $trait = array_pop($toVisit);
                if (\in_array($trait, $traits, true) === false) {
                    $traits[] = $trait;
                    array_push($toVisit, ...array_values(class_uses($trait)));
                }
            }
        }

        return $traits;
    }

    /**
     * @template E of object
     * @param Metadata<E> $metadata
     * @return bool false when a primary key of the entity is not set (not initialized or null)
     */
    private function isPrimaryKeySet(NotifyPropertyInterface $entity, Metadata $metadata): bool
    {
        foreach ($metadata->getPrimaries() as $primary) {
            if ($metadata->isEntityPropertyReadable($entity, $primary['fieldName']) === false
                || $metadata->getEntityPropertyByFieldName($entity, $primary['fieldName']) === null
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns true if delete($entity) has been called
     *
     * @param NotifyPropertyInterface $entity
     * @return bool
     */
    public function shouldBeRemoved(NotifyPropertyInterface $entity): bool
    {
        $hash = spl_object_hash($entity);
        return isset($this->entitiesShouldBePersisted[$hash])
            && $this->entitiesShouldBePersisted[$hash]['state'] === self::STATE_DELETE;
    }

    /**
     * Apply flagged changes against the database:
     * Save flagged new entities
     * Update flagged entities
     * Delete flagged entities
     *
     * When a query fails, the entities already processed are done, the failing one is unqueued (its tracked
     * changes are kept, so it can be saved again) and the following ones stay queued; the exception is rethrown.
     *
     * @throws Exception
     * @throws QueryException
     */
    public function process(): void
    {
        try {
            foreach ($this->entitiesShouldBePersisted as $hash => $details) {
                try {
                    switch ($details['state']) {
                        case self::STATE_MANAGED:
                            if (isset($this->entities[$details['entity']])) {
                                $this->processManaged($details['entity']);
                            } else {
                                $this->processClone($details['entity']);
                            }
                            break;

                        case self::STATE_NEW:
                            $this->processNew($details['entity']);
                            break;

                        case self::STATE_DELETE:
                            $this->processDelete($details['entity']);
                            break;
                    }
                } catch (\Throwable $exception) {
                    // Otherwise every later process() would replay the failed query first
                    unset($this->entitiesShouldBePersisted[$hash]);

                    throw $exception;
                }
            }
        } finally {
            $statements = $this->statements;
            $this->statements = [];
            foreach ($statements as $statementName => $connections) {
                foreach ($connections as $connection) {
                    $connection->closeStatement($statementName);
                }
            }
        }
    }

    /**
     * Update all applicable entities in database: the notified changes, and every mutable field
     *
     * @param NotifyPropertyInterface $entity
     * @throws Exception
     * @throws QueryException
     */
    protected function processManaged(NotifyPropertyInterface $entity): void
    {
        $this->findMetadataToWrite(
            $entity,
            $this->entitiesShouldBePersisted[spl_object_hash($entity)]['metadata'] ?? null,
            function (Metadata $metadata) use ($entity): void {
                $properties = $this->getChanges($entity, $metadata, true);
                if ($properties === []) {
                    $this->markSaved($entity);
                    return;
                }

                $connection = $metadata->getConnection($this->connectionPool);
                $query = $metadata->generateQueryForUpdate(
                    $connection,
                    $this->queryFactory,
                    $entity,
                    $properties
                );

                $query->prepareExecute();
                // Only a prepared statement can be closed
                $this->addStatementToClose($query->getStatementName(), $connection->primary());
                $query->execute();

                // The row is now stored with the values written
                foreach (array_keys($this->mutablePrimaryValues[$entity] ?? []) as $property) {
                    if (isset($properties[$property])) {
                        $this->mutablePrimaryValues[$entity][$property] = $properties[$property][1];
                    }
                }
                $this->markSaved($entity);
            },
            function () use ($entity): void {
                if (isset($this->entitiesChanged[$entity]) === false) {
                    // Nothing to write
                    $this->markSaved($entity);
                    return;
                }

                throw new QueryException('Could not find repository matching entity "' . $entity::class . '"');
            }
        );
    }

    /**
     * Update the row of a clone of a managed entity, by its primary key, with every readable field: its changes are
     * not tracked. The fields its original did not read are left out, unless the clone holds another value. Then the
     * clone is managed, as if just read. Inserted instead when its original is no longer managed (detached or deleted
     * since pushSave()) or its primary key is no longer set.
     *
     * @throws Exception
     * @throws QueryException
     */
    private function processClone(NotifyPropertyInterface $entity): void
    {
        $this->findMetadataToWrite(
            $entity,
            $this->entitiesShouldBePersisted[spl_object_hash($entity)]['metadata'] ?? null,
            function (Metadata $metadata) use ($entity): void {
                $original = $this->getManagedOriginal($entity);
                if ($original === null || $this->isPrimaryKeySet($entity, $metadata) === false) {
                    $this->processNew($entity);

                    return;
                }

                $originalNotRead = $this->propertiesNotRead[$original] ?? [];
                $notRead = [];
                $properties = [];
                foreach ($metadata->getFields() as $field) {
                    $property = $field['fieldName'];
                    // The primary key targets the row. A public typed property not initialized: the column keeps
                    // its value
                    if (($field['primary'] ?? false) || $metadata->isEntityPropertyReadable($entity, $property) === false) {
                        continue;
                    }

                    $value = $metadata->getEntityPropertyByFieldName($entity, $property);
                    if (isset($originalNotRead[$property])
                        && $metadata->getEntityPropertyByFieldName($original, $property) === $value
                    ) {
                        // Not read: the value of the original, not the stored one
                        $notRead[$property] = true;
                        continue;
                    }

                    $properties[$property] = [$value, $value];
                }

                if ($properties !== []) {
                    $connection = $metadata->getConnection($this->connectionPool);
                    $query = $metadata->generateQueryForUpdate($connection, $this->queryFactory, $entity, $properties);
                    $query->prepareExecute();
                    // Only a prepared statement can be closed
                    $this->addStatementToClose($query->getStatementName(), $connection->primary());
                    $query->execute();
                }

                unset($this->entitiesShouldBePersisted[spl_object_hash($entity)]);
                $this->setPropertiesNotRead($entity, $notRead);
                $this->manage($entity);
            },
            function () use ($entity): void {
                throw new QueryException('Could not find repository matching entity "' . $entity::class . '"');
            }
        );
    }

    /**
     * The save of a managed entity is done: forget its changes and unqueue it
     */
    private function markSaved(NotifyPropertyInterface $entity): void
    {
        $this->entitiesChanged->offsetUnset($entity);
        unset($this->entitiesShouldBePersisted[spl_object_hash($entity)]);
    }

    /**
     * Properties to write, with their database values: the notified changes of the immutable fields (a setter may
     * notify a property that is not mapped: it has no column, it is ignored), then the mutable fields whose value is
     * known (read, set through their setter, or inserted), written whether they changed or not.
     * A primary key whose value differs from the one stored in the database (old notified value, or source value of
     * a mutable key) targets the row with the stored one.
     *
     * @template E of object
     * @param Metadata<E> $metadata
     * @param bool $withMutableFields false to leave out the mutable fields that are not primary keys (DELETE)
     * @return array<string, array{0: mixed, 1: mixed}> property name => [database value stored before the change
     *                                                  (the current one when not known), current database value]
     */
    private function getChanges(NotifyPropertyInterface $entity, Metadata $metadata, bool $withMutableFields): array
    {
        $changes = [];
        foreach ($this->entitiesChanged[$entity] ?? [] as $property => [$oldValue, $newValue]) {
            if ($oldValue === $newValue
                || $metadata->hasProperty($property) === false
                || $metadata->isMutable($property)
                // A public typed property not initialized: the column keeps its value
                || $metadata->isEntityPropertyReadable($entity, $property) === false
            ) {
                continue;
            }

            $changes[$property] = [
                $metadata->getDatabaseValueOfProperty($property, $oldValue),
                $metadata->getEntityPropertyByFieldName($entity, $property),
            ];
        }

        $sourceValues = $this->mutablePrimaryValues[$entity] ?? [];
        $notRead = $this->propertiesNotRead[$entity] ?? [];
        foreach ($metadata->getMutableProperties() as $property) {
            if (($withMutableFields === false && array_key_exists($property, $sourceValues) === false)
                || isset($notRead[$property])
                || $metadata->isEntityPropertyReadable($entity, $property) === false
            ) {
                continue;
            }

            $value = $metadata->getEntityPropertyByFieldName($entity, $property);
            $changes[$property] = [
                array_key_exists($property, $sourceValues) ? $sourceValues[$property] : $value,
                $value,
            ];
        }

        return $changes;
    }

    /**
     * Insert all applicable entities in database
     * @param  NotifyPropertyInterface $entity
     * @throws Exception
     * @throws QueryException
     */
    protected function processNew(NotifyPropertyInterface $entity): void
    {
        $this->findMetadataToWrite(
            $entity,
            $this->entitiesShouldBePersisted[spl_object_hash($entity)]['metadata'] ?? null,
            function (Metadata $metadata) use ($entity): void {
                $connection = $metadata->getConnection($this->connectionPool);
                $query = $metadata->generateQueryForInsert(
                    $connection,
                    $this->queryFactory,
                    $entity
                );
                $query->prepareExecute();
                // Only a prepared statement can be closed
                $this->addStatementToClose($query->getStatementName(), $connection->primary());
                $query->execute();

                // The row exists: the entity is managed before its generated id is written back, so that it is not
                // inserted a second time if that fails (the error is rethrown, the entity stays managed)
                unset($this->entitiesShouldBePersisted[spl_object_hash($entity)]);
                $this->mutablePrimaryValues->offsetUnset($entity);
                // Every value written is known
                $this->propertiesNotRead->offsetUnset($entity);
                $this->manage($entity);

                try {
                    $metadata->setEntityPropertyForAutoIncrement($entity, $connection->primary());
                } finally {
                    // The INSERT wrote every value: no change left, and the generated id is not one
                    $this->entitiesChanged->offsetUnset($entity);
                    // Kept by manage() before the generated id was written back: a mutable autoincrement key (a value
                    // object with a serializer of its own) is stored with the generated value
                    $this->mutablePrimaryValues->offsetUnset($entity);
                    $this->keepMutablePrimaryValues($entity, $metadata);
                }
            },
            function () use ($entity): void {
                throw new QueryException('Could not find repository matching entity "' . $entity::class . '"');
            }
        );
    }

    /**
     * Delete all flagged entities from database
     *
     * @param NotifyPropertyInterface $entity
     * @throws Exception
     * @throws QueryException
     */
    protected function processDelete(NotifyPropertyInterface $entity): void
    {
        $this->findMetadataToWrite(
            $entity,
            $this->entitiesShouldBePersisted[spl_object_hash($entity)]['metadata'] ?? null,
            function (Metadata $metadata) use ($entity): void {
                $connection = $metadata->getConnection($this->connectionPool);
                $query = $metadata->generateQueryForDelete(
                    $connection,
                    $this->queryFactory,
                    // A changed primary key: the row is still stored with the old one
                    $this->getChanges($entity, $metadata, false),
                    $entity
                );
                $query->prepareExecute();
                // Only a prepared statement can be closed
                $this->addStatementToClose($query->getStatementName(), $connection->primary());
                $query->execute();
                $this->detach($entity);
            },
            function () use ($entity): void {
                throw new QueryException('Could not find repository matching entity "' . $entity::class . '"');
            }
        );
    }

    /**
     * Calls $callbackFound with the metadata given, or else with those registered for the class of the entity
     *
     * @param Metadata<object>|null $metadata
     */
    private function findMetadataToWrite(
        NotifyPropertyInterface $entity,
        ?Metadata $metadata,
        Closure $callbackFound,
        Closure $callbackNotFound
    ): void {
        if ($metadata !== null) {
            $callbackFound($metadata);

            return;
        }

        $this->metadataRepository->findMetadataForEntity($entity, $callbackFound, $callbackNotFound);
    }

    protected function addStatementToClose(string $statementName, DriverInterface $connection): void
    {
        if (isset($this->statements[$statementName]) === false) {
            $this->statements[$statementName] = [];
        }
        if (isset($this->statements[$statementName][spl_object_hash($connection)]) === false) {
            $this->statements[$statementName][spl_object_hash($connection)] = $connection;
        }
    }
}
