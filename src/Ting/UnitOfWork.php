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
use WeakMap;

class UnitOfWork implements PropertyListenerInterface, ResetInterface
{
    public const STATE_NEW     = 1;
    public const STATE_MANAGED = 2;
    public const STATE_DELETE  = 3;
    /** @var WeakMap<NotifyPropertyInterface, NotifyPropertyInterface|bool> */
    protected WeakMap $entities;
    /** @var WeakMap<NotifyPropertyInterface, array<string, true>> properties notified by propertyChanged() since the last write */
    protected WeakMap $entitiesChanged;
    /**
     * Database values of the managed entities (serialized by the serializer of their field), as last read from or
     * written to the database: a notified property changed when its current database value differs.
     *
     * @var WeakMap<NotifyPropertyInterface, array<string, mixed>> property => database value
     */
    protected WeakMap $databaseValues;
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
        $this->databaseValues = new WeakMap();
    }

    /**
     * Watch changes on provided entity.
     * Its current values are taken as the values stored in the database: changes are detected against them.
     *
     * @param NotifyPropertyInterface $entity
     */
    public function manage(NotifyPropertyInterface $entity): void
    {
        if (isset($this->entities[$entity]) === false) {
            $this->entities[$entity] = true;
        }

        if (isset($this->databaseValues[$entity]) === false) {
            $this->metadataRepository->findMetadataForEntity(
                $entity,
                function (Metadata $metadata) use ($entity): void {
                    $this->databaseValues[$entity] = $metadata->getEntityDatabaseValues($entity);
                },
                static function (): void {
                    // Without metadata, the entity cannot be saved: there is nothing to compare
                }
            );
        }

        $entity->addPropertyListener($this);
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
     * @return bool - true if the entity has not been persisted yet
     */
    public function isNew(NotifyPropertyInterface $entity): bool
    {
        $hash = spl_object_hash($entity);
        return isset($this->entitiesShouldBePersisted[$hash])
            && $this->entitiesShouldBePersisted[$hash]['state'] === self::STATE_NEW;
    }

    /**
     * Flag the entity to be persisted (insert or update) on next process.
     * A new entity becomes managed only once inserted: until then, it stays new.
     */
    public function pushSave(NotifyPropertyInterface $entity): static
    {
        $state = self::STATE_MANAGED;

        if (isset($this->entities[$entity]) === false) {
            $state = self::STATE_NEW;
        }

        $hash = spl_object_hash($entity);
        $this->entitiesShouldBePersisted[$hash] = ['state' => $state, 'entity' => $entity];

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
     * Record that the property may have changed: whether it did is decided on save, by comparing its database value
     * with the one read from or last written to the database.
     * The same object given as old and new value is recorded: it may have been modified in place.
     */
    public function propertyChanged(NotifyPropertyInterface $entity, string $propertyName, mixed $oldValue, mixed $newValue): void
    {
        if ($oldValue === $newValue && is_object($newValue) === false) {
            return;
        }

        if (isset($this->entitiesChanged[$entity]) === false) {
            $this->entitiesChanged[$entity] = [];
        }

        $this->entitiesChanged[$entity][$propertyName] = true;
    }

    /**
     * @return bool true if the property has been notified as changed and its database value differs from the one read
     *              from or last written to the database (when known)
     */
    public function isPropertyChanged(NotifyPropertyInterface $entity, string $propertyName): bool
    {
        if (isset($this->entitiesChanged[$entity][$propertyName]) === false) {
            return false;
        }

        $changed = true;
        $this->metadataRepository->findMetadataForEntity(
            $entity,
            function (Metadata $metadata) use ($entity, $propertyName, &$changed): void {
                $changed = $this->getChange($entity, $metadata, $propertyName) !== null;
            },
            static function (): void {
                // Without metadata, the notification is all there is
            }
        );

        return $changed;
    }

    /**
     * Stop watching changes on the entity
     *
     * @param NotifyPropertyInterface $entity
     */
    public function detach(NotifyPropertyInterface $entity): void
    {
        unset($this->entitiesShouldBePersisted[spl_object_hash($entity)]);
        $this->entitiesChanged->offsetUnset($entity);
        $this->databaseValues->offsetUnset($entity);
        $this->entities->offsetUnset($entity);
    }

    /**
     * Stop watching changes on all entities
     */
    public function detachAll(): void
    {
        $this->entitiesChanged = new WeakMap();
        $this->databaseValues = new WeakMap();
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
     */
    public function pushDelete(NotifyPropertyInterface $entity): static
    {
        $hash = spl_object_hash($entity);
        $this->entitiesShouldBePersisted[$hash] = ['state' => self::STATE_DELETE, 'entity' => $entity];
        $this->entities[$entity] = $entity;

        return $this;
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
                            $this->processManaged($details['entity']);
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
     * Update all applicable entities in database
     *
     * @param NotifyPropertyInterface $entity
     * @throws Exception
     * @throws QueryException
     */
    protected function processManaged(NotifyPropertyInterface $entity): void
    {
        if (isset($this->entitiesChanged[$entity]) === false) {
            $this->markSaved($entity);
            return;
        }

        $this->metadataRepository->findMetadataForEntity(
            $entity,
            function (Metadata $metadata) use ($entity): void {
                $properties = $this->getChanges($entity, $metadata);
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

                foreach ($properties as $property => [, $value]) {
                    $this->databaseValues[$entity][$property] = $value;
                }
                $this->markSaved($entity);
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
     * @return array<string, array{0: mixed, 1: mixed}> changed properties: name => [database value before the change,
     *                                                  current database value]
     */
    private function getChanges(NotifyPropertyInterface $entity, Metadata $metadata): array
    {
        $changes = [];
        foreach (array_keys($this->entitiesChanged[$entity] ?? []) as $property) {
            $change = $this->getChange($entity, $metadata, $property);
            if ($change !== null) {
                $changes[$property] = $change;
            }
        }

        return $changes;
    }

    /**
     * @return array{0: mixed, 1: mixed}|null [database value before the change, current database value], or null when
     *                                       the property did not change, is not mapped (a setter may notify such a
     *                                       property: it has no column) or cannot be read
     */
    private function getChange(NotifyPropertyInterface $entity, Metadata $metadata, string $property): ?array
    {
        if ($metadata->hasProperty($property) === false
            || $metadata->isEntityPropertyReadable($entity, $property) === false
        ) {
            return null;
        }

        $value = $metadata->getEntityPropertyByFieldName($entity, $property);
        if (isset($this->databaseValues[$entity]) === false
            || array_key_exists($property, $this->databaseValues[$entity]) === false
        ) {
            // Database value unknown (e.g. a typed property not initialized when the entity became managed)
            return [$value, $value];
        }

        $databaseValue = $this->databaseValues[$entity][$property];

        return $databaseValue === $value ? null : [$databaseValue, $value];
    }

    /**
     * Insert all applicable entities in database
     * @param  NotifyPropertyInterface $entity
     * @throws Exception
     * @throws QueryException
     */
    protected function processNew(NotifyPropertyInterface $entity): void
    {
        $this->metadataRepository->findMetadataForEntity(
            $entity,
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

                $metadata->setEntityPropertyForAutoIncrement($entity, $connection->primary());

                $this->entitiesChanged->offsetUnset($entity);
                unset($this->entitiesShouldBePersisted[spl_object_hash($entity)]);

                $this->databaseValues[$entity] = $metadata->getEntityDatabaseValues($entity);
                $this->manage($entity);
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
        $this->metadataRepository->findMetadataForEntity(
            $entity,
            function (Metadata $metadata) use ($entity): void {
                $connection = $metadata->getConnection($this->connectionPool);
                $query = $metadata->generateQueryForDelete(
                    $connection,
                    $this->queryFactory,
                    // A changed primary key: the row is still stored with the old one
                    $this->getChanges($entity, $metadata),
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
