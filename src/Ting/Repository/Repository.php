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

namespace CCMBenchmark\Ting\Repository;

use Aura\SqlQuery\QueryFactory as AuraQueryFactory;
use Aura\SqlQuery\QueryInterface;
use CCMBenchmark\Ting\Driver\Pgsql\Driver;
use CCMBenchmark\Ting\Connection;
use CCMBenchmark\Ting\ConnectionPool;
use CCMBenchmark\Ting\Driver\NeverConnectedException;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;
use CCMBenchmark\Ting\Driver\Mysqli;
use CCMBenchmark\Ting\Driver\SphinxQL;
use CCMBenchmark\Ting\Exceptions\DriverException;
use CCMBenchmark\Ting\Exceptions\RepositoryException;
use CCMBenchmark\Ting\Exceptions\ValueException;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Query\QueryFactory;
use CCMBenchmark\Ting\Query\Query;
use CCMBenchmark\Ting\Query\PreparedQuery;
use CCMBenchmark\Ting\ResetInterface;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;
use CCMBenchmark\Ting\UnitOfWork;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @template T of object entity type (not necessarily a NotifyPropertyInterface: read-only entities can use public properties)
 */
abstract class Repository implements ResetInterface
{
    public const QUERY_SELECT = 'select';
    public const QUERY_INSERT = 'insert';
    public const QUERY_UPDATE = 'update';
    public const QUERY_DELETE = 'delete';

    /**
     * @var Metadata<T>
     */
    protected Metadata $metadata;

    protected Connection $connection;

    /**
     * @param ConnectionPool $connectionPool
     * @param MetadataRepository $metadataRepository
     * @param QueryFactory $queryFactory
     * @param CollectionFactory<mixed> $collectionFactory its hydrator, shared by the repositories, hydrates
     *                                          the collections of the queries
     * @param CacheInterface $cache
     * @param UnitOfWork $unitOfWork
     *
     * @internal
     */
    public function __construct(
        protected ConnectionPool $connectionPool,
        protected MetadataRepository $metadataRepository,
        protected \CCMBenchmark\Ting\Query\QueryFactory $queryFactory,
        protected CollectionFactory $collectionFactory,
        protected CacheInterface $cache,
        protected UnitOfWork $unitOfWork
    ) {
        $class = static::class;
        $this->metadataRepository->findMetadataForRepository(
            $class,
            function (Metadata $metadata): void {
                $this->metadata = $metadata;
            },
            function () use ($class): void {
                throw new RepositoryException(
                    'Metadata not found for ' . $class
                    . ', you probably forgot to call MetadataRepository::batchLoadMetadata'
                );
            }
        );
        $this->connection = $this->metadata->getConnection($this->connectionPool);
        $this->metadataRepository->addMetadata($class, $this->metadata);
    }


    /**
     * @param HydratorInterface<U>|null $hydrator null for a clone of the hydrator of the collection factory
     * @return ($hydrator is null ? Collection<mixed> : Collection<U>)
     *
     * @template U
     */
    public function getCollection(?HydratorInterface $hydrator = null): Collection
    {
        return $this->collectionFactory->get($hydrator);
    }

    /**
     * @return Query<mixed> its collections are hydrated by the hydrator of the collection factory
     */
    public function getQuery(string $sql): Query
    {
        return $this->queryFactory->get($sql, $this->connection, $this->collectionFactory);
    }

    /**
     * @return PreparedQuery<mixed> its collections are hydrated by the hydrator of the collection factory
     */
    public function getPreparedQuery(string $sql): PreparedQuery
    {
        return $this->queryFactory->getPrepared($sql, $this->connection, $this->collectionFactory);
    }

    /**
     * @return \CCMBenchmark\Ting\Query\Cached\Query<mixed> its collections are hydrated by the hydrator of the
     *                                                    collection factory
     */
    public function getCachedQuery(string $sql): \CCMBenchmark\Ting\Query\Cached\Query
    {
        return $this->queryFactory->getCached(
            $sql,
            $this->connection,
            $this->cache,
            $this->collectionFactory
        );
    }

    /**
     * @return \CCMBenchmark\Ting\Query\Cached\PreparedQuery<mixed> its collections are hydrated by the hydrator of
     *                                                            the collection factory
     */
    public function getCachedPreparedQuery(string $sql): \CCMBenchmark\Ting\Query\Cached\PreparedQuery
    {
        return $this->queryFactory->getCachedPrepared(
            $sql,
            $this->connection,
            $this->cache,
            $this->collectionFactory
        );
    }


    /**
     * @param string $type One of the QUERY_ constant
     * @throws DriverException
     */
    public function getQueryBuilder(string $type): QueryInterface
    {
        $driver = $this->connectionPool->getDriverClass($this->metadata->getConnectionName());
        $driver = ltrim($driver, '\\');

        switch ($driver) {
            case Driver::class:
                $queryFactory = new AuraQueryFactory('pgsql');
                break;
            case SphinxQL\Driver::class:
                // SphinxQL and Mysqli are sharing the same driver
            case Mysqli\Driver::class:
                $queryFactory = new AuraQueryFactory('mysql');
                break;
            default:
                throw new DriverException('Driver ' . $driver . ' is unknown to build QueryBuilder');
        }

        $queryBuilder = match ($type) {
            self::QUERY_UPDATE => $queryFactory->newUpdate(),
            self::QUERY_DELETE => $queryFactory->newDelete(),
            self::QUERY_INSERT => $queryFactory->newInsert(),
            default => $queryFactory->newSelect(),
        };

        return $queryBuilder;
    }

    /**
     * Retrieve one object from database
     *
     * @param mixed $primariesKeyValue property name => value, or just the value when there is one primary key.
     *                                 Values are converted like the criteria of getBy().
     * @return T|null
     * @throws ValueException on an unknown property (or a column name) or an invalid value
     */
    public function get(mixed $primariesKeyValue, bool $forcePrimary = false): ?object
    {
        $query = $this->metadata->getByPrimaries(
            $this->connection,
            $this->queryFactory,
            $this->collectionFactory,
            $primariesKeyValue,
            (bool)$forcePrimary
        );

        $collection = $query->query();
        if ($collection->count() === 0) {
            return null;
        }
        $entity = $collection->getIterator()->current();

        return reset($entity);
    }

    /**
     * @param bool $forcePrimary
     * @return CollectionInterface<T>
     */
    public function getAll(bool $forcePrimary = false): CollectionInterface
    {
        $query = $this->metadata->getAll(
            $this->connection,
            $this->queryFactory,
            $this->collectionFactory,
            (bool)$forcePrimary
        );

        return $query->query($this->getCollection(new HydratorSingleObject()));
    }

    /**
     * @param array<string, mixed> $criteria property name => value: null (IS NULL), a scalar (serialized when the
     *                                       serializer implements ScalarValueInterface), an object converted by
     *                                       the serializer of the field, or an array (IN list, or a single value
     *                                       serialized as a whole when the serializer implements ArrayValueInterface)
     * @param array<string, string> $order property name => "ASC" or "DESC"
     * @return CollectionInterface<T>
     * @throws ValueException on an unknown property (or a column name), an invalid value or an invalid direction
     */
    public function getBy(array $criteria, bool $forcePrimary = false, array $order = [], int $limit = 0): CollectionInterface
    {
        $query = $this->metadata->getByCriteriaWithOrderAndLimit(
            $criteria,
            $order,
            $limit,
            $this->connection,
            $this->queryFactory,
            $this->collectionFactory,
            (bool)$forcePrimary
        );

        return $query->query($this->getCollection(new HydratorSingleObject()));
    }

    /**
     * @param array<string, mixed> $criteria property name => value, as in getBy()
     * @return T|null
     * @throws ValueException on an unknown property (or a column name) or an invalid value
     */
    public function getOneBy(array $criteria, bool $forcePrimary = false): ?object
    {
        $query = $this->metadata->getOneByCriteria(
            $this->connection,
            $this->queryFactory,
            $this->collectionFactory,
            $criteria,
            (bool)$forcePrimary
        );
        $collection = $query->query();
        if ($collection->count() === 0) {
            return null;
        }
        $entity = $collection->first();

        return reset($entity);
    }

    /**
     * Save an entity in database (update or insert)
     */
    public function save(NotifyPropertyInterface $entity): void
    {
        $this->unitOfWork->pushSave($entity)->process();
    }

    /**
     * Delete an entity from database
     */
    public function delete(NotifyPropertyInterface $entity): void
    {
        $this->unitOfWork->pushDelete($entity)->process();
    }

    /**
     * Start a transaction against the primary connection
     *
     * @return void
     */
    public function startTransaction(): void
    {
        $this->connection->primary()->startTransaction();
    }

    /**
     * Rollback the transaction opened on the primary connection
     *
     * @return void
     */
    public function rollback(): void
    {
        $this->connection->primary()->rollback();
    }

    /**
     * Commit the transaction opened on the primary connection
     *
     * @return void
     */
    public function commit(): void
    {
        $this->connection->primary()->commit();
    }

    /**
     * @throws NeverConnectedException when you have not been connected to your database before trying to ping it.
     */
    public function ping(): bool
    {
        return $this->connection->replica()->ping();
    }

    /**
     * @throws NeverConnectedException when you have not been connected to your database before trying to ping it.
     * @return bool
     */
    public function pingPrimary(): bool
    {
        return $this->connection->primary()->ping();
    }

    /**
     * Returns the repository's corresponding metadata
     *
     * @return Metadata<T>
     */
    public function getMetadata(): Metadata
    {
        return $this->metadata;
    }

    public function reset(): void
    {
        $this->unitOfWork->reset();
        $this->connection = $this->metadata->getConnection($this->connectionPool);
    }
}
