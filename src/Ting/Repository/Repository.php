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
use Aura\SqlQuery\Common\DeleteInterface;
use Aura\SqlQuery\Common\InsertInterface;
use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\Common\UpdateInterface;
use Aura\SqlQuery\QueryInterface;
use CCMBenchmark\Ting\Driver\Pgsql\Driver;
use CCMBenchmark\Ting\Connection;
use CCMBenchmark\Ting\ConnectionPool;
use CCMBenchmark\Ting\Driver\NeverConnectedException;
use CCMBenchmark\Ting\Driver\QueryException as DriverQueryException;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;
use CCMBenchmark\Ting\Driver\Mysqli;
use CCMBenchmark\Ting\Driver\SphinxQL;
use CCMBenchmark\Ting\Exceptions\DriverException;
use CCMBenchmark\Ting\Exceptions\RepositoryException;
use CCMBenchmark\Ting\Exceptions\TransactionException;
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
     *                                          the collections of the queries; the repository keeps a copy
     *                                          hydrating the tables it maps with its own metadata
     * @param CacheInterface $cache
     * @param UnitOfWork $unitOfWork
     *
     * @internal called by RepositoryFactory only; a repository may override it, with the same parameters, calling
     *           parent::__construct() (a supported extension point)
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
        // Another repository can map the same table (a lighter projection): the reads of this one hydrate it with
        // its own metadata
        $this->collectionFactory = $collectionFactory->forRepository($class);
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
     * @return ($type is self::QUERY_UPDATE ? UpdateInterface
     *     : ($type is self::QUERY_DELETE ? DeleteInterface
     *     : ($type is self::QUERY_INSERT ? InsertInterface : SelectInterface)))
     * @throws DriverException
     */
    public function getQueryBuilder(string $type): QueryInterface
    {
        $driver = $this->connectionPool->getDriverClass($this->connection->getName());
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
     * @param int $limit 0 for no limit
     * @param int $offset rows skipped before the first one returned, only with a limit
     * @return CollectionInterface<T>
     * @throws ValueException on an unknown property (or a column name), an invalid value, an invalid direction, or
     *                        an offset without limit
     */
    public function getBy(
        array $criteria,
        bool $forcePrimary = false,
        array $order = [],
        int $limit = 0,
        int $offset = 0
    ): CollectionInterface {
        $query = $this->metadata->getByCriteriaWithOrderAndLimit(
            $criteria,
            $order,
            $limit,
            $this->connection,
            $this->queryFactory,
            $this->collectionFactory,
            (bool)$forcePrimary,
            $offset
        );

        return $query->query($this->getCollection(new HydratorSingleObject()));
    }

    /**
     * @param array<string, mixed> $criteria property name => value, as in getBy()
     * @param array<string, string> $order property name => "ASC" or "DESC": the first entity in that order
     * @return T|null
     * @throws ValueException on an unknown property (or a column name), an invalid value or an invalid direction
     */
    public function getOneBy(array $criteria, bool $forcePrimary = false, array $order = []): ?object
    {
        $query = $this->metadata->getOneByCriteria(
            $this->connection,
            $this->queryFactory,
            $this->collectionFactory,
            $criteria,
            (bool)$forcePrimary,
            $order
        );
        $collection = $query->query();
        if ($collection->count() === 0) {
            return null;
        }
        $entity = $collection->first();

        return reset($entity);
    }

    /**
     * Run a SELECT of your own as a prepared query and hydrate, for each row, the entity of the table of its first
     * column: the entities of the repository when the columns of its table come first. Keep the SQL constant and vary
     * the parameters: each distinct SQL keeps a prepared statement for the life of the connection.
     *
     * @param array<string, mixed> $params parameter name => value
     * @return CollectionInterface<T>
     * @throws DriverQueryException on a missing parameter or a query refused by the database
     */
    public function getBySql(string $sql, array $params = [], bool $forcePrimary = false): CollectionInterface
    {
        $query = $this->getPreparedQuery($sql)->setParams($params);
        if ($forcePrimary === true) {
            $query->selectPrimary(true);
        }

        return $query->query($this->getCollection(new HydratorSingleObject()));
    }

    /**
     * Run a SELECT of your own as getBySql() and return the entity of its first row (add a LIMIT 1: the rows after the
     * first one are read for nothing)
     *
     * @param array<string, mixed> $params parameter name => value
     * @return T|null null without row, or when the first column of the first row comes from a table without match
     *                (an outer join)
     * @throws DriverQueryException on a missing parameter or a query refused by the database
     * @throws RepositoryException when the first column of the row is not one of the entity of the repository
     */
    public function getOneBySql(string $sql, array $params = [], bool $forcePrimary = false): ?object
    {
        $entity = $this->getBySql($sql, $params, $forcePrimary)->first();
        if ($entity === null || $entity === false) {
            return null;
        }

        $class = $this->metadata->getEntity();
        if ($class !== null && !is_a($entity, $class)) {
            throw new RepositoryException(sprintf(
                'Repository::getOneBySql() read %s instead of %s: select the columns of the table of the repository'
                . ' first',
                get_debug_type($entity),
                $class
            ));
        }

        return $entity;
    }

    /**
     * @param array<string, mixed> $criteria property name => value, as in getBy(); none to count every row
     * @throws ValueException on an unknown property (or a column name) or an invalid value
     */
    public function countBy(array $criteria = [], bool $forcePrimary = false): int
    {
        $query = $this->metadata->countByCriteria($criteria, $this->connection, $this->queryFactory, $forcePrimary);
        $row = $query->query(new Collection(new HydratorArray()))->first();

        // COUNT(*) is a string with PostgreSQL and Mysqli non-prepared queries
        return $row === null ? 0 : (int) reset($row);
    }

    /**
     * @param array<string, mixed> $criteria property name => value, as in getBy(); none to know if the table has a row
     * @throws ValueException on an unknown property (or a column name) or an invalid value
     */
    public function existsBy(array $criteria, bool $forcePrimary = false): bool
    {
        $query = $this->metadata->existsByCriteria($criteria, $this->connection, $this->queryFactory, $forcePrimary);

        return $query->query(new Collection(new HydratorArray()))->first() !== null;
    }

    /**
     * Delete the rows matching the criteria with one DELETE query, without the unit of work: an entity of a deleted
     * row already read stays managed (saved again, it updates no row)
     *
     * @param array<string, mixed> $criteria property name => value, as in getBy(); required
     * @return int the number of rows deleted
     * @throws ValueException on empty criteria, an unknown property (or a column name) or an invalid value
     */
    public function deleteBy(array $criteria): int
    {
        $query = $this->metadata->deleteByCriteria($criteria, $this->connection, $this->queryFactory);
        $query->execute();

        return (int) $this->connection->primary()->getAffectedRows();
    }

    /**
     * Save an entity in database (update or insert), with the metadata of this repository when they map its class
     * (another repository can map it too: another table, or a lighter projection)
     */
    public function save(NotifyPropertyInterface $entity): void
    {
        $this->unitOfWork->pushSave($entity, $this->metadataToWrite($entity))->process();
    }

    /**
     * Delete an entity from database, with the metadata of this repository when they map its class
     */
    public function delete(NotifyPropertyInterface $entity): void
    {
        $this->unitOfWork->pushDelete($entity, $this->metadataToWrite($entity))->process();
    }

    /**
     * @return Metadata<T>|null the metadata of this repository when they map the class of the entity, null to write it
     *                          with the metadata registered for its class
     */
    private function metadataToWrite(NotifyPropertyInterface $entity): ?Metadata
    {
        return $this->metadata->getEntity() === $entity::class ? $this->metadata : null;
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
     * Run the callback in a transaction on the primary connection: committed when the callback returns, rolled back
     * when it throws (the exception of the callback is then thrown again, even when the rollback fails). A failed
     * commit leaves no transaction open: it is not followed by a rollback.
     *
     * @template R
     * @param callable(): R $callback
     * @return R the value returned by the callback
     * @throws TransactionException when a transaction is already open on the connection, when the callback ended the
     *                              transaction itself, or when the database refuses the START TRANSACTION or the
     *                              COMMIT
     */
    public function transactional(callable $callback): mixed
    {
        $this->startTransaction();
        try {
            $result = $callback();
        } catch (\Throwable $exception) {
            try {
                $this->rollback();
            } catch (DriverException) {
                // The exception of the callback explains the failure better (a lost connection fails both);
                // a failed rollback leaves no transaction open either
            }
            throw $exception;
        }
        $this->commit();

        return $result;
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
