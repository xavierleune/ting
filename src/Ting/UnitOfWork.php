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
use CCMBenchmark\Ting\Driver\RollbackListenerAwareDriverInterface;
use CCMBenchmark\Ting\Driver\RollbackListenerInterface;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;
use CCMBenchmark\Ting\Entity\PropertyListenerInterface;
use CCMBenchmark\Ting\Query\QueryFactoryInterface;
use CCMBenchmark\Ting\Repository\Metadata;
use Closure;
use WeakMap;

class UnitOfWork implements PropertyListenerInterface, ResetInterface, RollbackListenerInterface
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
     * Database values of the mutable fields of the managed entities: the raw value read (kept as the driver returned
     * it, nothing is serialized by a read), or the value serialized when the entity became managed without being
     * read (manage() by hand), then the value last written. A mutable field is written only when its database value
     * differs from it (a change made in place is not notified, see isStoredValue()), and a mutable primary key
     * targets its row by it. Only the entities with a mutable field known are listed. The values are packed into a
     * string by serialize() when they are all scalars or null, as they usually are: a short string takes about a
     * quarter of the memory of an array, and is not a candidate for the garbage collector (an array kept per entity
     * makes it run far more often, which costs much with large decoded values).
     *
     * @var WeakMap<NotifyPropertyInterface, string|array<string, mixed>> property => database value, or serialized
     */
    protected WeakMap $mutableValues;
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
     * Entities whose INSERT was rolled back (see rolledBack()): their autoincrement property still holds the id
     * generated, but no row has it, so their next save inserts them. Kept by detach(), detachAll() and reset().
     *
     * @var WeakMap<NotifyPropertyInterface, true>
     */
    protected WeakMap $insertsRolledBack;
    /**
     * The metadata each managed entity was read with (given to manage() by the hydrators), or written with when it
     * became managed by its INSERT or UPDATE: its fields not read and the stored values of its mutable fields are
     * those of these metadata. Written with other metadata (another repository mapping its class), the fields these
     * metadata do not have are taken as not read.
     *
     * @var WeakMap<NotifyPropertyInterface, Metadata<object>>
     */
    protected WeakMap $entitiesMetadata;
    /**
     * @var array<string, array{state: self::STATE_*, entity: NotifyPropertyInterface, metadata?: Metadata<object>|null}>
     *      by object hash; the metadata given to pushSave() or pushDelete() to write the entity with
     */
    protected array $entitiesShouldBePersisted = [];
    /** @var array<string, array<string, DriverInterface>>  */
    protected array $statements = [];
    /**
     * @var array<string, array{replaced: bool, done: bool, changed: array<string, true>}> by object hash, the queued
     *      entities a running process() is writing, until they are unqueued: a process() nested in it leaves them to it.
     *      replaced: pushed again (pushSave(), pushDelete()) during its step, it stays queued after it; done: its step
     *      wrote it; changed: the properties whose change was notified during its step, before it was written
     */
    private array $entitiesInProgress = [];
    /** Number of process() running, nested in one another: the outermost closes the statements */
    private int $processDepth = 0;

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
        $this->mutableValues = new WeakMap();
        $this->propertiesNotRead = new WeakMap();
        $this->listenedEntities = new WeakMap();
        $this->insertsRolledBack = new WeakMap();
        $this->entitiesMetadata = new WeakMap();
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
     * Keep the raw database values of the mutable fields read, as the driver returned them: compared at save with the
     * current values (see isStoredValue()), so that a read never serializes anything. Call before manage().
     *
     * @param array<string, mixed> $values property name => raw database value
     *
     * @internal called by the hydrators
     */
    public function keepRawValues(NotifyPropertyInterface $entity, array $values): void
    {
        $this->storeMutableValues($entity, $values);
    }

    /**
     * Watch changes on provided entity.
     * The current values of its mutable fields (but those not read) are taken as the values stored in the database.
     *
     * @param Metadata<object>|null $metadata the metadata the entity was read with (given by the hydrators), those
     *                                        registered for its class otherwise. Managed already, the entity keeps
     *                                        the metadata it was managed with, unless others are given.
     */
    public function manage(NotifyPropertyInterface $entity, ?Metadata $metadata = null): void
    {
        $this->listen($entity);
        // Read again: its row exists
        $this->insertsRolledBack->offsetUnset($entity);

        if ($metadata !== null) {
            $this->entitiesMetadata[$entity] = $metadata;
        } elseif (isset($this->entitiesMetadata[$entity]) === false) {
            $this->metadataRepository->findMetadataForEntity(
                $entity,
                                function (Metadata $metadata) use ($entity): void {
                    $this->setEntityMetadata($entity, $metadata);
                },
                static function (): void {
                    // Without metadata, the entity cannot be saved: there is nothing to keep
                }
            );
        }

        if (isset($this->mutableValues[$entity]) === false && isset($this->entitiesMetadata[$entity])) {
            $this->keepMutableValues($entity, $this->entitiesMetadata[$entity]);
        }
    }

    /**
     * The entity is managed: its changes are recorded
     */
    private function listen(NotifyPropertyInterface $entity): void
    {
        $this->entities[$entity] = true;

        if (isset($this->listenedEntities[$entity]) === false) {
            $this->listenedEntities[$entity] = true;
            $entity->addPropertyListener($this);
        }
    }

    /**
     * Take the current database values of the mutable fields of the entity, but those not read, as the stored ones
     *
     * @template E of object
     * @param Metadata<E> $metadata
     */
    private function keepMutableValues(NotifyPropertyInterface $entity, Metadata $metadata): void
    {
        $this->storeMutableValues(
            $entity,
            $metadata->getEntityMutableValues($entity, $this->propertiesNotRead[$entity] ?? [])
        );
    }

    /**
     * The properties have been written: the database values of the mutable ones are the stored ones from now on
     *
     * @template E of object
     * @param Metadata<E> $metadata
     * @param array<string, array{0: mixed, 1: mixed}> $properties property name => [old value, database value written]
     */
    private function keepMutableValuesWritten(
        NotifyPropertyInterface $entity,
        Metadata $metadata,
        array $properties
    ): void {
        $values = $this->getStoredMutableValues($entity);
        foreach ($properties as $property => [, $value]) {
            if ($metadata->isMutable($property)) {
                $values[$property] = $value;
            }
        }

        $this->storeMutableValues($entity, $values);
    }

    /**
     * @template E of object
     * @param Metadata<E> $metadata
     */
    private function setEntityMetadata(NotifyPropertyInterface $entity, Metadata $metadata): void
    {
        /** @var Metadata<object> $metadata the entities of a class are written with the metadata of any object type */
        $this->entitiesMetadata[$entity] = $metadata;
    }

    /**
     * @param array<string, mixed> $values property name => database value
     */
    private function storeMutableValues(NotifyPropertyInterface $entity, array $values): void
    {
        if ($values === []) {
            $this->mutableValues->offsetUnset($entity);

            return;
        }

        foreach ($values as $value) {
            if ($value !== null && \is_scalar($value) === false) {
                $this->mutableValues[$entity] = $values;

                return;
            }
        }

        $this->mutableValues[$entity] = serialize($values);
    }

    /**
     * @return array<string, mixed> property name => database value stored, for the mutable fields known
     */
    private function getStoredMutableValues(NotifyPropertyInterface $entity): array
    {
        $values = $this->mutableValues[$entity] ?? [];
        if (\is_string($values)) {
            $values = unserialize($values, ['allowed_classes' => false]);
        }

        return \is_array($values) ? $values : [];
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
     * A managed entity is updated with its changes. An entity not managed (built with its id, detached, read from a
     * cache, a clone...) is taken as a stored row when its metadata have an autoincrement field and its property holds
     * a value other than null (read from the property itself, not through its getter): it is updated by its primary
     * key with every readable mapped field, as no change is tracked for it, then managed (isNew() is false). Otherwise
     * (no autoincrement field, its property null or not initialized), or when its INSERT was rolled back (see
     * rolledBack()), it is new: inserted, then managed. Until then, it is not managed.
     *
     * @param Metadata<object>|null $metadata the metadata to write the entity with (internal: given by
     *                                        Repository::save()), those registered for its class otherwise
     */
    public function pushSave(NotifyPropertyInterface $entity, ?Metadata $metadata = null): static
    {
        $state = self::STATE_MANAGED;

        if (isset($this->entities[$entity]) === false
            && (isset($this->insertsRolledBack[$entity]) || $this->isStoredRow($entity, $metadata) === false)
        ) {
            $state = self::STATE_NEW;
        }

        $hash = spl_object_hash($entity);
        $this->entitiesShouldBePersisted[$hash] = ['state' => $state, 'entity' => $entity, 'metadata' => $metadata];
        $this->markReplaced($hash);

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

        $hash = spl_object_hash($entity);
        if (isset($this->entitiesInProgress[$hash]) && $this->entitiesInProgress[$hash]['done'] === false) {
            // Made while process() writes it: maybe after the property was read, it is not forgotten once written
            $this->entitiesInProgress[$hash]['changed'][$propertyName] = true;
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
     *              or it is a mutable field of a managed entity whose database value differs from the stored one (a
     *              change made in place)
     */
    public function isPropertyChanged(NotifyPropertyInterface $entity, string $propertyName): bool
    {
        if ($this->isChangeNotified($entity, $propertyName)) {
            return true;
        }

        // Without metadata, nothing is mutable
        $metadata = $this->entitiesMetadata[$entity] ?? null;
        if ($metadata === null || isset($this->entities[$entity]) === false) {
            return false;
        }

        if ($metadata->hasProperty($propertyName)) {
            return $this->isMutableFieldChanged($entity, $metadata, $propertyName);
        }

        // Not mapped by the metadata the entity was read with (a projection): every metadata of its class mapping it,
        // whichever repository registered them last, as a save through any of them would write it
        foreach ($this->metadataRepository->getMetadataMappingProperty($entity::class, $propertyName) as $registered) {
            if ($this->isMutableFieldChanged($entity, $registered, $propertyName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @template E of object
     * @param Metadata<E> $metadata
     */
    private function isMutableFieldChanged(NotifyPropertyInterface $entity, Metadata $metadata, string $propertyName): bool
    {
        return $metadata->isMutable($propertyName)
            && $this->getMutableChange($entity, $metadata, $propertyName, $this->getStoredMutableValues($entity)) !== null;
    }

    private function isChangeNotified(NotifyPropertyInterface $entity, string $propertyName): bool
    {
        return isset($this->entitiesChanged[$entity][$propertyName])
            && $this->entitiesChanged[$entity][$propertyName][0] !== $this->entitiesChanged[$entity][$propertyName][1];
    }

    /**
     * Stop watching changes on the entity. Pushed again with pushSave(), it is saved as an entity not managed: an
     * UPDATE of every field when it holds its autoincrement id, an INSERT otherwise (see pushSave()).
     *
     * @param NotifyPropertyInterface $entity
     */
    public function detach(NotifyPropertyInterface $entity): void
    {
        $this->unqueue($entity);
        $this->forget($entity);
    }

    /**
     * Forget everything tracked of the entity but its place in the queue
     */
    private function forget(NotifyPropertyInterface $entity): void
    {
        $this->entitiesChanged->offsetUnset($entity);
        $this->mutableValues->offsetUnset($entity);
        $this->propertiesNotRead->offsetUnset($entity);
        $this->entitiesMetadata->offsetUnset($entity);
        $this->entities->offsetUnset($entity);
    }

    /**
     * Stop watching changes on all entities
     */
    public function detachAll(): void
    {
        $this->entitiesChanged = new WeakMap();
        $this->mutableValues = new WeakMap();
        $this->propertiesNotRead = new WeakMap();
        $this->entitiesMetadata = new WeakMap();
        $this->entitiesShouldBePersisted = [];
        $this->entitiesInProgress = [];
        $this->entities = new WeakMap();
    }

    /**
     * Reset state between requests (worker mode: FrankenPHP, Swoole, RoadRunner, etc.)
     */
    public function reset(): void
    {
        // The entities whose INSERT was rolled back are kept: a fact which stays true, whatever the order in which the
        // connection pool and the unit of work are reset
        $this->detachAll();
        $this->statements = [];
        // A process() that never ended (suspended in a Fiber left behind) would otherwise keep the next ones from
        // closing their statements, for the life of the worker
        $this->processDepth = 0;
    }

    /**
     * Flag the entity to be deleted on next process
     *
     * An entity never inserted (queued for its INSERT, not managed and without primary key, or whose INSERT was rolled
     * back) has no row: it is unqueued, no query is run. An entity not managed but with its primary key is deleted by
     * that key: it does not become managed, so a later pushSave() replacing the deletion, or a save after a failed
     * DELETE, saves it as an entity not managed: an UPDATE of every field when it holds its autoincrement id, an INSERT
     * otherwise (see pushSave()).
     *
     * @param Metadata<object>|null $metadata the metadata to delete the entity with (internal: given by
     *                                        Repository::delete()), those registered for its class otherwise
     */
    public function pushDelete(NotifyPropertyInterface $entity, ?Metadata $metadata = null): static
    {
        $hash = spl_object_hash($entity);
        // Being written by process() (its INSERT included): the deletion is queued, for once its step is done
        if (isset($this->entitiesInProgress[$hash]) === false
            && ($this->isNew($entity)
                || (isset($this->entities[$entity]) === false
                    && (isset($this->insertsRolledBack[$entity]) || $this->hasPrimaryKey($entity, $metadata) === false)))
        ) {
            $this->unqueue($entity);

            return $this;
        }

        $this->entitiesShouldBePersisted[$hash] = ['state' => self::STATE_DELETE, 'entity' => $entity, 'metadata' => $metadata];
        $this->markReplaced($hash);

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
     * @param Metadata<object>|null $metadata
     * @return bool true when the entity not managed is taken as a stored row: its autoincrement property holds a
     *              value (see Metadata::hasAutoincrementValue()). False without metadata: process() then reports the
     *              missing repository.
     */
    private function isStoredRow(NotifyPropertyInterface $entity, ?Metadata $metadata): bool
    {
        $isStoredRow = false;
        $this->findMetadataToWrite(
            $entity,
            $metadata,
            function (Metadata $metadata) use ($entity, &$isStoredRow): void {
                $isStoredRow = $metadata->hasAutoincrementValue($entity);
            },
            static function (): void {
                // Without metadata, process() throws
            }
        );

        return $isStoredRow;
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
     * The transaction in which the entity was inserted ended without being committed (rolled back, failed COMMIT,
     * lost with the connection): its row does not exist. The entity is left as it is, its generated id included, but
     * is no longer managed, and its next save inserts it again. Queued for a save, it is queued for its INSERT, in its
     * place; queued for a deletion, it is unqueued.
     *
     * @internal called by the drivers implementing RollbackListenerAwareDriverInterface, see processNew()
     */
    public function rolledBack(object $subject): void
    {
        if ($subject instanceof NotifyPropertyInterface === false) {
            return;
        }

        $this->forget($subject);
        $this->insertsRolledBack[$subject] = true;
        // Queued, it keeps its place in the queue (a parent before its child)
        $hash = spl_object_hash($subject);
        if (isset($this->entitiesShouldBePersisted[$hash])) {
            if ($this->entitiesShouldBePersisted[$hash]['state'] === self::STATE_DELETE) {
                $this->unqueue($subject);
            } else {
                $this->entitiesShouldBePersisted[$hash]['state'] = self::STATE_NEW;
            }
        }
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
     * A process() nested in this one (called by a setter, a getter or a serializer) writes the entities still queued,
     * those this one has not reached yet included: they are not written a second time. It leaves the entity this one
     * is writing to it, until it is unqueued; pushed again during its step, that entity stays queued for the next
     * process(). The statements prepared are closed when the outermost process() ends.
     *
     * @throws Exception
     * @throws QueryException
     */
    public function process(): void
    {
        ++$this->processDepth;
        try {
            foreach (array_keys($this->entitiesShouldBePersisted) as $hash) {
                // A process() nested in this one (a save from a setter, a getter or a serializer) may have written it
                // already, or changed what to do with it; an entity a process() this one is nested in is writing is
                // left to it
                if (isset($this->entitiesShouldBePersisted[$hash]) === false
                    || isset($this->entitiesInProgress[$hash])
                ) {
                    continue;
                }

                $details = $this->entitiesShouldBePersisted[$hash];
                $this->entitiesInProgress[$hash] = ['replaced' => false, 'done' => false, 'changed' => []];
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
                } finally {
                    unset($this->entitiesInProgress[$hash]);
                }
            }
        } finally {
            // Not below 0: a process() forgotten by reset() may end afterwards (its Fiber destroyed)
            $this->processDepth = max(0, $this->processDepth - 1);
            if ($this->processDepth === 0) {
                $this->closeStatements();
            }
        }
    }

    /**
     * Unqueue the entity: no longer in progress either, a process() nested in the one that wrote it writes it again
     * if it is pushed again
     */
    private function unqueue(NotifyPropertyInterface $entity): void
    {
        $hash = spl_object_hash($entity);
        unset($this->entitiesShouldBePersisted[$hash], $this->entitiesInProgress[$hash]);
    }

    private function markReplaced(string $hash): void
    {
        if (isset($this->entitiesInProgress[$hash])) {
            $this->entitiesInProgress[$hash]['replaced'] = true;
        }
    }

    /**
     * The step of process() writing the entity is done: it is unqueued, unless pushed again during the step (by a
     * getter, a serializer...), when it stays queued for what was pushed (a save of the entity now managed, a
     * deletion), handled by the next process()
     */
    private function completeStep(NotifyPropertyInterface $entity): void
    {
        $hash = spl_object_hash($entity);
        if (($this->entitiesInProgress[$hash]['replaced'] ?? false) === false
            || isset($this->entitiesShouldBePersisted[$hash]) === false
        ) {
            $this->unqueue($entity);

            return;
        }

        $this->entitiesInProgress[$hash]['done'] = true;
        if ($this->entitiesShouldBePersisted[$hash]['state'] === self::STATE_NEW && isset($this->entities[$entity])) {
            // Inserted by the step
            $this->entitiesShouldBePersisted[$hash]['state'] = self::STATE_MANAGED;
        }
    }

    private function closeStatements(): void
    {
        $statements = $this->statements;
        $this->statements = [];
        foreach ($statements as $statementName => $connections) {
            foreach ($connections as $connection) {
                $connection->closeStatement($statementName);
            }
        }
    }

    /**
     * Update all applicable entities in database: the notified changes, and the mutable fields changed
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
                    $this->markSaved($entity, $metadata, []);
                    return;
                }

                $connection = $metadata->getConnection($this->connectionPool);
                $query = $metadata->generateQueryForUpdate(
                    $connection,
                    $this->queryFactory,
                    $entity,
                    $properties,
                    $this->getStoredPrimaries($entity, $metadata)
                );

                $query->prepareExecute();
                // Only a prepared statement can be closed
                $this->addStatementToClose($query->getStatementName(), $connection->primary());
                $query->execute();

                // The row is now stored with the values written
                $this->keepMutableValuesWritten($entity, $metadata, $properties);
                $this->markSaved($entity, $metadata, $properties);
            },
            function () use ($entity): void {
                if (isset($this->entitiesChanged[$entity]) === false) {
                    // Nothing to write
                    $this->markSaved($entity, null, []);
                    return;
                }

                throw new QueryException('Could not find repository matching entity "' . $entity::class . '"');
            }
        );
    }

    /**
     * Update the row of an entity not managed, by its primary key, with every readable mapped field: no change is
     * tracked for it. Then the entity is managed, with every field known. Inserted instead when its autoincrement
     * property no longer holds a value (set to null since pushSave()), or its INSERT was rolled back since.
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
                if (isset($this->insertsRolledBack[$entity]) || $metadata->hasAutoincrementValue($entity) === false) {
                    $this->processNew($entity);

                    return;
                }

                $properties = [];
                foreach ($metadata->getFields() as $field) {
                    $property = $field['fieldName'];
                    // The primary key targets the row
                    if ($field['primary'] ?? false) {
                        continue;
                    }

                    // A public typed property not initialized: the column keeps its value
                    [$readable, $value] = $metadata->getEntityPropertyIfReadable($entity, $property);
                    if ($readable) {
                        $properties[$property] = [$value, $value];
                    }
                }

                if ($properties !== []) {
                    $connection = $metadata->getConnection($this->connectionPool);
                    $query = $metadata->generateQueryForUpdate($connection, $this->queryFactory, $entity, $properties);
                    $query->prepareExecute();
                    // Only a prepared statement can be closed
                    $this->addStatementToClose($query->getStatementName(), $connection->primary());
                    $query->execute();
                }

                // Every value is known: written, or the primary key targeting the row
                $this->propertiesNotRead->offsetUnset($entity);
                $this->entitiesChanged->offsetUnset($entity);
                $this->mutableValues->offsetUnset($entity);
                $this->listen($entity);
                $this->setEntityMetadata($entity, $metadata);
                $this->completeStep($entity);
                $this->keepMutableValues($entity, $metadata);
            },
            function () use ($entity): void {
                throw new QueryException('Could not find repository matching entity "' . $entity::class . '"');
            }
        );
    }

    /**
     * The save of a managed entity is done: forget the changes written (and those set back to their old value) and
     * unqueue it (unless pushed again during its step, see completeStep()). A change notified during the step (by a
     * getter or a serializer read to build the query) is forgotten only when the database value of the property is
     * still the one written: otherwise it was made after the property was read, and is kept for the next save. That
     * comparison reads the property again while the entity is still in progress: a change those reads make is kept. The
     * changes of the properties some metadata of its class map are kept: not mapped by the metadata written with (a
     * lighter projection), they are written by a save through metadata mapping them. Those no metadata maps (a
     * relation whose setter notifies its changes, say) can never be written: they are forgotten.
     *
     * @template E of object
     * @param Metadata<E>|null $metadata the metadata written with, null when none was found
     * @param array<string, mixed> $written property name => anything, for the properties written; [database value
     *                                      stored before, database value written] for an UPDATE (see getChanges())
     */
    private function markSaved(NotifyPropertyInterface $entity, ?Metadata $metadata, array $written): void
    {
        $hash = spl_object_hash($entity);
        $inProgress = $this->entitiesInProgress[$hash] ?? null;
        // Changed during the step that wrote it: perhaps once read, the value written may not be the current one
        $changedDuringStep = $inProgress !== null && $inProgress['done'] === false ? $inProgress['changed'] : [];
        // Compared while the entity is still in progress: reading it (a getter, a serializer) may change it again
        $valuesWritten = [];
        foreach ($changedDuringStep as $property => $true) {
            if (isset($written[$property])) {
                $valuesWritten[$property] = $this->isValueWritten($entity, $metadata, $property, $written[$property]);
            }
        }
        // A property those reads changed is not known to be written: its change is kept
        foreach ($this->entitiesInProgress[$hash]['changed'] ?? [] as $property => $true) {
            if (isset($changedDuringStep[$property]) === false) {
                $valuesWritten[$property] = false;
            }
        }
        $this->completeStep($entity);

        $read = $this->entitiesMetadata[$entity] ?? null;
        $changes = $this->entitiesChanged[$entity] ?? [];
        foreach ($changes as $property => [$oldValue, $newValue]) {
            if ((isset($written[$property]) && ($valuesWritten[$property] ?? true))
                || $oldValue === $newValue
                || ($metadata?->hasProperty($property) !== true
                    && $read?->hasProperty($property) !== true
                    && $this->metadataRepository->isPropertyMapped($entity::class, $property) === false)
            ) {
                unset($changes[$property]);
            }
        }

        if ($changes === []) {
            $this->entitiesChanged->offsetUnset($entity);
        } else {
            $this->entitiesChanged[$entity] = $changes;
        }
    }

    /**
     * @template E of object
     * @param Metadata<E>|null $metadata
     * @return bool true when the database value of the property is the one written ($written: [database value stored
     *              before, database value written]); false when it differs, or cannot be read
     */
    private function isValueWritten(NotifyPropertyInterface $entity, ?Metadata $metadata, string $property, mixed $written): bool
    {
        if ($metadata === null || \is_array($written) === false || \array_key_exists(1, $written) === false) {
            return false;
        }

        try {
            [$readable, $value] = $metadata->getEntityPropertyIfReadable($entity, $property);
        } catch (\Throwable) {
            return false;
        }

        return $readable && $value === $written[1];
    }

    /**
     * Properties to write, with their database values: the notified changes of the immutable fields (a setter may
     * notify a property that is not mapped: it has no column, it is ignored), then the mutable fields changed (see
     * getMutableChange()).
     * A primary key whose value differs from the one stored in the database (old notified value, or stored value of
     * a mutable key) targets the row with the stored one.
     *
     * @template E of object
     * @param Metadata<E> $metadata
     * @param bool $withMutableFields false to leave out the fields that are not primary keys (DELETE)
     * @return array<string, array{0: mixed, 1: mixed}> property name => [database value stored before the change
     *                                                  (the current one when not known, or not a primary key),
     *                                                  current database value]
     */
    private function getChanges(NotifyPropertyInterface $entity, Metadata $metadata, bool $withMutableFields): array
    {
        $changes = [];
        $primaries = array_column($metadata->getPrimaries(), 'fieldName', 'fieldName');
        foreach ($this->entitiesChanged[$entity] ?? [] as $property => [$oldValue, $newValue]) {
            if ($oldValue === $newValue
                || $metadata->hasProperty($property) === false
                || $metadata->isMutable($property)
                || ($withMutableFields === false && isset($primaries[$property]) === false)
                // A public typed property not initialized: the column keeps its value
                || $metadata->isEntityPropertyReadable($entity, $property) === false
            ) {
                continue;
            }

            $value = $metadata->getEntityPropertyByFieldName($entity, $property);
            $changes[$property] = [
                // Only the old value of a primary key is written (it targets the row): that of another field may be
                // refused by its serializer (a PHP default not read, '' for Ip)
                isset($primaries[$property]) ? $metadata->getDatabaseValueOfProperty($property, $oldValue) : $value,
                $value,
            ];
        }

        $stored = null;
        foreach ($metadata->getMutableProperties() as $property) {
            if ($withMutableFields === false && isset($primaries[$property]) === false) {
                continue;
            }

            $stored ??= $this->getStoredMutableValues($entity);
            $change = $this->getMutableChange($entity, $metadata, $property, $stored);
            if ($change !== null) {
                $changes[$property] = $change;
            }
        }

        return $changes;
    }

    /**
     * A mutable field is written when its value is known (read, set through its setter, or inserted) and its database
     * value differs from the stored one, has none (not readable when the entity became managed), or its change was
     * notified. Comparing database values, not PHP values, leaves alone a value that does not survive a round trip
     * (microseconds, a time in a DST gap, a zero date, a JSON big integer...) as long as it is not modified.
     *
     * @template E of object
     * @param Metadata<E> $metadata
     * @param array<string, mixed> $stored the database values stored, see getStoredMutableValues()
     * @return array{0: mixed, 1: mixed}|null [stored database value (the current one when not known), current
     *                                        database value], null when the field is not written
     */
    private function getMutableChange(
        NotifyPropertyInterface $entity,
        Metadata $metadata,
        string $property,
        array $stored
    ): ?array {
        if (isset($this->propertiesNotRead[$entity][$property])) {
            return null;
        }

        $read = $this->entitiesMetadata[$entity] ?? $metadata;
        if ($read !== $metadata && $read->hasProperty($property) === false
            && array_key_exists($property, $stored) === false
            && $this->isChangeNotified($entity, $property) === false
        ) {
            // Neither read by the metadata the entity was read with, nor written since: its value is not known
            return null;
        }

        try {
            [$readable, $value] = $metadata->getEntityPropertyIfReadable($entity, $property);
        } catch (\Throwable $exception) {
            if ($this->isChangeNotified($entity, $property)) {
                throw $exception;
            }

            // Its getter or its serializer throws (a getter refusing null, a JSON value decoded to INF): its database
            // value cannot be computed, it is taken as unchanged (3.13 wrote notified changes only)
            return null;
        }

        if ($readable === false) {
            return null;
        }

        if (array_key_exists($property, $stored) === false) {
            return [$value, $value];
        }

        if ($this->isChangeNotified($entity, $property) === false
            && $this->isStoredValue($metadata, $property, $stored[$property], $value)
        ) {
            return null;
        }

        return [$stored[$property], $value];
    }

    /**
     * The current database value of a mutable field is the stored one: identical (the shortcut), or identical to the
     * stored value unserialized then serialized again (a raw value read: JSON spaces, microseconds...). A stored value
     * which cannot be normalized is not: the field is written.
     *
     * @template E of object
     * @param Metadata<E> $metadata
     */
    private function isStoredValue(Metadata $metadata, string $property, mixed $stored, mixed $value): bool
    {
        if ($stored === $value) {
            return true;
        }

        try {
            return $metadata->normalizeDatabaseValue($property, $stored) === $value;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @template E of object
     * @param Metadata<E> $metadata
     * @return array<string, mixed> property name => database value stored, for the mutable primary keys whose stored
     *                              value is known: the row is targeted by the value read (or last written)
     */
    private function getStoredPrimaries(NotifyPropertyInterface $entity, Metadata $metadata): array
    {
        if ($metadata->getMutableColumns() === []) {
            return [];
        }

        $storedPrimaries = [];
        $stored = null;
        foreach ($metadata->getPrimaries() as $primary) {
            if ($metadata->isMutable($primary['fieldName'])) {
                $stored ??= $this->getStoredMutableValues($entity);
                if (\array_key_exists($primary['fieldName'], $stored)) {
                    $storedPrimaries[$primary['fieldName']] = $stored[$primary['fieldName']];
                }
            }
        }

        return $storedPrimaries;
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
                // Every value written is known
                $this->propertiesNotRead->offsetUnset($entity);
                $this->listen($entity);
                $this->setEntityMetadata($entity, $metadata);
                $this->completeStep($entity);
                $this->insertsRolledBack->offsetUnset($entity);
                // Within a transaction: told if it is rolled back
                $driver = $connection->primary();
                if ($driver instanceof RollbackListenerAwareDriverInterface) {
                    $driver->addRollbackListener($this, $entity);
                }

                // The INSERT wrote every value: no change left, and the values of the mutable fields are stored
                $this->entitiesChanged->offsetUnset($entity);
                $written = $metadata->getEntityMutableValues($entity);
                // Read before the generated id is written back: an id held already (the one of an INSERT rolled back)
                // is not the one of the row inserted
                $idBefore = $metadata->getAutoincrementValue($entity);
                $insertedId = null;
                try {
                    $metadata->setEntityPropertyForAutoIncrement($entity, $connection->primary(), $insertedId);
                } catch (\Throwable $exception) {
                    if ($metadata->holdsGeneratedId($entity, $idBefore, $insertedId)) {
                        // The id is set before the failure: the changes are kept, as when the setter does not throw
                        $this->keepInsertedValues($entity, $metadata, $written);
                    } else {
                        // The id is not set (or is still the one held before): the entity has no key its row can be
                        // updated by. The changes made before the failure are dropped, and the current values of the
                        // mutable fields are the stored ones
                        $this->entitiesChanged->offsetUnset($entity);
                        $this->keepMutableValues($entity, $metadata);
                    }

                    throw $exception;
                }
                $this->keepInsertedValues($entity, $metadata, $written);
            },
            function () use ($entity): void {
                throw new QueryException('Could not find repository matching entity "' . $entity::class . '"');
            }
        );
    }

    /**
     * The generated id has been written back (even if its setter threw afterwards): the values inserted are the
     * stored ones, but for the autoincrement field, stored with its generated value (a mutable key: a value object
     * with a serializer of its own). The generated id is not a change; the changes its setter made to other mapped
     * fields (a slug named after the id...) were not inserted: they are kept, written by the next save (see
     * markSaved()).
     *
     * @template E of object
     * @param Metadata<E> $metadata
     * @param array<string, mixed> $written property name => database value inserted, for the mutable fields
     */
    private function keepInsertedValues(NotifyPropertyInterface $entity, Metadata $metadata, array $written): void
    {
        $autoincrement = $metadata->getAutoincrementProperty();
        if ($autoincrement !== null) {
            unset($written[$autoincrement]);
            $this->markSaved($entity, $metadata, [$autoincrement => true]);

            if ($metadata->isMutable($autoincrement)) {
                try {
                    [$readable, $value] = $metadata->getEntityPropertyIfReadable($entity, $autoincrement);
                    if ($readable) {
                        $written[$autoincrement] = $value;
                    }
                } catch (\Throwable) {
                    // Its database value cannot be computed: not stored, as by getEntityMutableValues()
                }
            }
        }

        $this->storeMutableValues($entity, $written);
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
                    $entity,
                    $this->getStoredPrimaries($entity, $metadata)
                );
                $query->prepareExecute();
                // Only a prepared statement can be closed
                $this->addStatementToClose($query->getStatementName(), $connection->primary());
                $query->execute();
                $this->forget($entity);
                $this->completeStep($entity);
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
