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

namespace CCMBenchmark\Ting\Query;

use CCMBenchmark\Ting\Connection;
use CCMBenchmark\Ting\Exception;
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Repository\CollectionInterface;
use CCMBenchmark\Ting\Repository\CollectionFactoryInterface;
use CCMBenchmark\Ting\Repository\HydratorArray;

/**
 * @template T type of the items of the collections built by the collection factory of the query
 *
 * @template-implements QueryInterface<T>
 */
class Query implements QueryInterface
{
    protected bool $selectPrimary = false;

    /**
     * @var array<string, mixed> parameter name => value
     */
    protected array $params = [];

    /**
     * @param string $sql
     * @param Connection $connection
     * @param CollectionFactoryInterface<T>|null $collectionFactory
     */
    public function __construct(
        protected string $sql,
        protected Connection $connection,
        protected ?CollectionFactoryInterface $collectionFactory = null
    ) {
    }

    /**
     * Force the query to be executed on the primary connection. Applicable only on a reading query.
     */
    public function selectPrimary(bool $usePrimary): static
    {
        $this->selectPrimary = $usePrimary;

        return $this;
    }

    /**
     * @param array<string, mixed> $params parameter name => value
     * @return $this
     */
    public function setParams(array $params): static
    {
        $this->params = $params;

        return $this;
    }

    /**
     * Execute a reading query
     * @template U
     * @param CollectionInterface<U>|null $collection null for a collection of the collection factory of the query
     * @return ($collection is null ? CollectionInterface<T> : CollectionInterface<U>)
     * @throws Exception
     * @throws QueryException
     */
    public function query(?CollectionInterface $collection = null): CollectionInterface
    {
        $collection ??= $this->newCollection();

        $driver = $this->selectPrimary === true ? $this->connection->primary() : $this->connection->replica();
        // The driver fills the collection: for a statement without result set, Mysqli returns true instead of it
        $driver->execute($this->sql, $this->params, $collection);

        return $collection;
    }

    /**
     * Execute a reading query and return the first column of its first row (a COUNT(*), a MAX()...), as the driver
     * returns it (a string with PostgreSQL and Mysqli non-prepared queries), or null without row (or when the value
     * is NULL)
     *
     * @throws Exception
     * @throws QueryException
     */
    public function queryValue(): mixed
    {
        // The first column of the raw row: HydratorArray keys by name, where two columns of the same name collapse
        $hydrator = new class () extends HydratorArray {
            /**
             * @return \Generator<int, array<string, mixed>>
             */
            public function getIterator(): \Generator
            {
                if ($this->result === null) {
                    return;
                }
                foreach ($this->result as $key => $row) {
                    yield $key => ['value' => reset($row)['value'] ?? null];
                }
            }
        };
        $row = $this->query(new Collection($hydrator))->first();

        return $row['value'] ?? null;
    }

    /**
     * @return CollectionInterface<T>
     * @throws QueryException when the query has no collection factory
     */
    protected function newCollection(): CollectionInterface
    {
        if ($this->collectionFactory === null) {
            throw new QueryException(
                'Cannot build the collection of the query: it has no collection factory, give a collection to query()'
            );
        }

        return $this->collectionFactory->get();
    }

    /**
     * Execute a writing query
     * @return mixed
     * @throws Exception
     * @throws QueryException
     */
    public function execute(): mixed
    {
        return $this->connection->primary()->execute($this->sql, $this->params);
    }

    /**
     * @throws Exception
     */
    public function getInsertedId(): int
    {
        return $this->connection->primary()->getInsertedId();
    }

    /**
     * @return int<0, max>|string
     * @throws Exception
     */
    public function getAffectedRows(): int|string
    {
        return $this->connection->primary()->getAffectedRows();
    }
}
