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

namespace CCMBenchmark\Ting\Query\Cached;

use CCMBenchmark\Ting\Exception;
use CCMBenchmark\Ting\Query\QueryException;
use CCMBenchmark\Ting\Repository\CollectionInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @template T type of the items of the collections built by the collection factory of the query
 *
 * @template-extends \CCMBenchmark\Ting\Query\Query<T>
 */
class Query extends \CCMBenchmark\Ting\Query\Query
{
    protected ?CacheInterface $cache = null;

    protected ?int $ttl = null;

    protected ?string $cacheKey = null;

    protected bool $force = false;

    /**
     * Set the cache interface to the actual query
     * @param CacheInterface $cache
     * @return void
     */
    public function setCache(CacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Define the ttl for the current query, in seconds (0: no expiration)
     * @param int $ttl
     * @return $this
     */
    public function setTtl(int $ttl): static
    {
        $this->ttl = $ttl;
        return $this;
    }

    /**
     * Define the cache key for the current query
     *
     * @return $this
     */
    public function setCacheKey(string $cacheKey): static
    {
        $this->cacheKey = $cacheKey;

        return $this;
    }

    /**
     * Set force mode. If enabled : always do query
     * @param bool $value
     * @return $this
     */
    public function setForce(bool $value): static
    {
        $this->force = $value;
        return $this;
    }

    /**
     * Check if query is in cache or execute the query and store the result
     * @template U
     * @param CollectionInterface<U>|null $collection null for a collection of the collection factory of the query
     * @return ($collection is null ? CollectionInterface<T> : CollectionInterface<U>)
     * @throws Exception
     * @throws QueryException
     */
    public function query(?CollectionInterface $collection = null): CollectionInterface
    {
        $this->checkTtl();

        if (!$collection instanceof CollectionInterface) {
            $collection = $this->collectionFactory->get();
        }

        $this->queryThroughCache($collection, function (CollectionInterface $collection): void {
            parent::query($collection);
        });

        return $collection;
    }

    /**
     * Fill the collection from cache, or run $execute to fill it and store the result
     *
     * @template U
     * @param CollectionInterface<U> $collection
     * @param \Closure(CollectionInterface<U>): void $execute runs the actual query into the collection
     * @return CollectionInterface<U>
     * @throws QueryException
     */
    protected function queryThroughCache(CollectionInterface $collection, \Closure $execute): CollectionInterface
    {
        if ($this->cacheKey === null) {
            throw new QueryException('You must call setCacheKey to use query method');
        }

        $collection->setFromCache(false);
        $computed = false;
        $result = $this->cache->get(
            $this->cacheKey,
            function (ItemInterface $item) use ($collection, $execute, &$computed): array {
                $computed = true;
                // 0 meant "no expiration" with doctrine/cache, Symfony would expire the item immediately
                $item->expiresAfter($this->ttl === 0 ? null : $this->ttl);
                $execute($collection);

                return $collection->toCache();
            },
            // INF forces the value to be recomputed
            $this->force === true ? INF : null
        );

        if ($computed === false) {
            $collection->fromCache($result);
        }

        return $collection;
    }

    /**
     * @throws QueryException
     */
    protected function checkTtl(): void
    {
        if ($this->ttl === null) {
            throw new QueryException("You should call setTtl to use query method");
        }
    }
}
