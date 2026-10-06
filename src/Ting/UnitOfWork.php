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
     * Mutable properties whose value is not known: left out of a partial read (a SELECT of some columns, a join), they
     * hold a PHP default (null, a value set by the constructor...), not the stored value. They are not written until
     * set through their setter. Only the entities read partially are listed, with property names only.
     *
     * @var WeakMap<NotifyPropertyInterface, array<string, true>> property name => true
     */
    protected WeakMap $mutablePropertiesNotRead;
    /**
     * Entities this unit of work listens to: a listener can't be removed from an entity, so it stays registered once
     * the entity is detached (its notifications are then ignored), and is not added again when it is managed again
     *
     * @var WeakMap<NotifyPropertyInterface, true>
     */
    protected WeakMap $listenedEntities;
    /**
     * Entities whose row has been deleted by process(): their primary key is still set, but saving them inserts them
     * again. Forgotten once they are managed again.
     *
     * @var WeakMap<NotifyPropertyInterface, true>
     */
    protected WeakMap $entitiesDeleted;
    /**
     * @var array<string, array{state: self::STATE_*, entity: NotifyPropertyInterface, metadata?: Metadata<object>|null}>
     *      by object hash; the metadata given to pushSave() or pushDelete() to write the entity with
     */
    protected array $entitiesShouldBePersisted = [];
    /** @var array<string, array<string, DriverInterface>>  */
    protected array $statements = [];

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
        $this->mutablePropertiesNotRead = new WeakMap();
        $this->listenedEntities = new WeakMap();
        $this->entitiesDeleted = new WeakMap();
    }

    /**
     * Record the mutable properties left out of the partial read of an entity: their value is not known, so they are
     * not written until set through their setter. Replaces the properties recorded before; none to forget them.
     *
     * @param list<string> $properties
     *
     * @internal called by the hydrators
     */
    public function setMutablePropertiesNotRead(NotifyPropertyInterface $entity, array $properties): void
    {
        if ($properties === []) {
            $this->mutablePropertiesNotRead->offsetUnset($entity);

            return;
        }

        $this->mutablePropertiesNotRead[$entity] = array_fill_keys($properties, true);
    }

    /**
     * Watch changes on provided entity.
     * The current values of its mutable primary keys are taken as the values stored in the database.
     *
     * @param NotifyPropertyInterface $entity
     */
    public function manage(NotifyPropertyInterface $entity): void
    {
        if (isset($this->entities[$entity]) === false) {
            $this->entities[$entity] = true;
            // Its row exists again
            $this->entitiesDeleted->offsetUnset($entity);
        }

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
     * A managed entity is updated with its changes. An entity not managed (a clone of a managed one, a detached one,
     * one built by hand or read from a cache) is taken as an existing row when its metadata has an autoincrement
     * primary key and its whole primary key is set: it is updated by that key with every readable field, as nothing
     * tells which ones changed, then managed. Otherwise, as an entity deleted by process(), it is new: inserted, then
     * managed. Until then, it is not managed.
     *
     * @param Metadata<object>|null $metadata the metadata to write the entity with (internal: given by
     *                                        Repository::save()), those registered for its class otherwise
     */
    public function pushSave(NotifyPropertyInterface $entity, ?Metadata $metadata = null): static
    {
        $state = self::STATE_MANAGED;

        if (isset($this->entities[$entity]) === false
            && (isset($this->entitiesDeleted[$entity]) || $this->isExistingRow($entity, $metadata) === false)
        ) {
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

        if (isset($this->mutablePropertiesNotRead[$entity][$propertyName])) {
            // Set through its setter: its value is known from now on
            $notRead = $this->mutablePropertiesNotRead[$entity];
            unset($notRead[$propertyName]);
            $this->setMutablePropertiesNotRead($entity, array_keys($notRead));
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

        if (isset($this->entities[$entity]) === false || isset($this->mutablePropertiesNotRead[$entity][$propertyName])) {
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
     * Stop watching changes on the entity. Saved again, it is saved as any entity not managed (see pushSave()).
     *
     * @param NotifyPropertyInterface $entity
     */
    public function detach(NotifyPropertyInterface $entity): void
    {
        unset($this->entitiesShouldBePersisted[spl_object_hash($entity)]);
        $this->entitiesChanged->offsetUnset($entity);
        $this->mutablePrimaryValues->offsetUnset($entity);
        $this->mutablePropertiesNotRead->offsetUnset($entity);
        $this->entities->offsetUnset($entity);
    }

    /**
     * Stop watching changes on all entities
     */
    public function detachAll(): void
    {
        $this->entitiesChanged = new WeakMap();
        $this->mutablePrimaryValues = new WeakMap();
        $this->mutablePropertiesNotRead = new WeakMap();
        $this->entitiesDeleted = new WeakMap();
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
     * become managed, so a later pushSave() replacing the deletion, or a save after a failed DELETE, saves it as any
     * entity not managed (see pushSave()). Once deleted, an entity is detached and saving it inserts it again.
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
     * @return bool true when the entity not managed is taken as an existing row: its primary key is generated by the
     *              database (autoincrement) and set. False without metadata: process() then reports the missing
     *              repository.
     * @param Metadata<object>|null $metadata
     */
    private function isExistingRow(NotifyPropertyInterface $entity, ?Metadata $metadata): bool
    {
        $isExistingRow = false;
        $this->findMetadataToWrite(
            $entity,
            $metadata,
            function (Metadata $metadata) use ($entity, &$isExistingRow): void {
                $isExistingRow = $metadata->hasAutoincrement() && $this->isPrimaryKeySet($entity, $metadata);
            },
            static function (): void {
                // Without metadata, process() throws
            }
        );

        return $isExistingRow;
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
                                $this->processNotManaged($details['entity']);
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
     * Update the row of an entity not managed, by its primary key, with every readable field: no change is tracked for
     * it. Then the entity is managed, as if just read. Inserted instead when its primary key is no longer set.
     *
     * @throws Exception
     * @throws QueryException
     */
    private function processNotManaged(NotifyPropertyInterface $entity): void
    {
        $this->findMetadataToWrite(
            $entity,
            $this->entitiesShouldBePersisted[spl_object_hash($entity)]['metadata'] ?? null,
            function (Metadata $metadata) use ($entity): void {
                if ($this->isPrimaryKeySet($entity, $metadata) === false) {
                    // Unset since pushSave(): no row to update
                    $this->processNew($entity);

                    return;
                }

                $properties = [];
                foreach ($metadata->getFields() as $field) {
                    $property = $field['fieldName'];
                    // The primary key targets the row. A public typed property not initialized: the column keeps
                    // its value
                    if (($field['primary'] ?? false) || $metadata->isEntityPropertyReadable($entity, $property) === false) {
                        continue;
                    }

                    $value = $metadata->getEntityPropertyByFieldName($entity, $property);
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
                // Every value written is known, the primary key is the one stored
                $this->mutablePrimaryValues->offsetUnset($entity);
                $this->mutablePropertiesNotRead->offsetUnset($entity);
                $this->entitiesChanged->offsetUnset($entity);
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
        $notRead = $this->mutablePropertiesNotRead[$entity] ?? [];
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
                $this->mutablePropertiesNotRead->offsetUnset($entity);
                $this->manage($entity);

                try {
                    $metadata->setEntityPropertyForAutoIncrement($entity, $connection->primary());
                } finally {
                    // The INSERT wrote every value: no change left, and the generated id is not one
                    $this->entitiesChanged->offsetUnset($entity);
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
                // Its primary key is still set, but there is no row to update anymore
                $this->entitiesDeleted[$entity] = true;
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
