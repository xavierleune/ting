<?php

/***********************************************************************
 *
 * Ting - PHP Datamapper
 * ==========================================
 *
 * Copyright (C) 2014 CCM Benchmark Group. (http://www.ccmbenchmark.com)
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
use Symfony\Contracts\Cache\CacheInterface;
use CCMBenchmark\Ting\Repository\CollectionFactoryInterface;

interface QueryFactoryInterface
{
    /**
     * @template T = mixed type of the items of the collections built by $collectionFactory
     * @param string $sql
     * @param Connection $connection
     * @param CollectionFactoryInterface<T>|null $collectionFactory
     * @return QueryInterface<T>
     */
    public function get(string $sql, Connection $connection, ?CollectionFactoryInterface $collectionFactory = null): QueryInterface;

    /**
     * @template T = mixed type of the items of the collections built by $collectionFactory
     * @param string $sql
     * @param Connection $connection
     * @param CollectionFactoryInterface<T>|null $collectionFactory
     * @return PreparedQuery<T>
     */
    public function getPrepared(string $sql, Connection $connection, ?CollectionFactoryInterface $collectionFactory = null): PreparedQuery;

    /**
     * @template T = mixed type of the items of the collections built by $collectionFactory
     * @param string $sql
     * @param Connection $connection
     * @param CacheInterface $cache
     * @param CollectionFactoryInterface<T>|null $collectionFactory
     * @return Cached\Query<T>
     */
    public function getCached(
        string $sql,
        Connection $connection,
        CacheInterface $cache,
        ?CollectionFactoryInterface $collectionFactory = null
    ): \CCMBenchmark\Ting\Query\Cached\Query;

    /**
     * @template T = mixed type of the items of the collections built by $collectionFactory
     * @param string $sql
     * @param Connection $connection
     * @param CacheInterface $cache
     * @param CollectionFactoryInterface<T>|null $collectionFactory
     * @return Cached\PreparedQuery<T>
     */
    public function getCachedPrepared(
        string $sql,
        Connection $connection,
        CacheInterface $cache,
        ?CollectionFactoryInterface $collectionFactory = null
    ): \CCMBenchmark\Ting\Query\Cached\PreparedQuery;
}
