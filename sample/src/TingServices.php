<?php

/***********************************************************************
 *
 * Ting - PHP Datamapper
 * ==========================================
 *
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

namespace sample\src;

use CCMBenchmark\Ting\Cache\Cache;
use CCMBenchmark\Ting\ConnectionPool;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Query\QueryFactory;
use CCMBenchmark\Ting\Repository\CollectionFactory;
use CCMBenchmark\Ting\Repository\Hydrator;
use CCMBenchmark\Ting\Repository\HydratorAggregator;
use CCMBenchmark\Ting\Repository\HydratorRelational;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;
use CCMBenchmark\Ting\Repository\RepositoryFactory;
use CCMBenchmark\Ting\Serializer\SerializerFactory;
use CCMBenchmark\Ting\UnitOfWork;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Wires Ting objects without a framework. With Symfony, ting_bundle does it for you.
 *
 * Stateful objects are shared within an instance; collection factories and hydrators are new on each call.
 */
final class TingServices
{
    private ?MetadataRepository $metadataRepository = null;
    private ?UnitOfWork $unitOfWork = null;
    private ?QueryFactory $queryFactory = null;
    private ?SerializerFactory $serializerFactory = null;
    private ?Cache $cache = null;
    private ?RepositoryFactory $repositoryFactory = null;

    /**
     * @param ConnectionPool|null $connectionPool a configured pool, a new one is created otherwise
     */
    public function __construct(private ?ConnectionPool $connectionPool = null)
    {
    }

    public function connectionPool(): ConnectionPool
    {
        return $this->connectionPool ??= new ConnectionPool();
    }

    public function metadataRepository(): MetadataRepository
    {
        return $this->metadataRepository ??= new MetadataRepository($this->serializerFactory());
    }

    public function unitOfWork(): UnitOfWork
    {
        return $this->unitOfWork ??= new UnitOfWork(
            $this->connectionPool(),
            $this->metadataRepository(),
            $this->queryFactory()
        );
    }

    public function queryFactory(): QueryFactory
    {
        return $this->queryFactory ??= new QueryFactory();
    }

    public function serializerFactory(): SerializerFactory
    {
        return $this->serializerFactory ??= new SerializerFactory();
    }

    public function cache(): Cache
    {
        if ($this->cache === null) {
            $this->cache = new Cache();
            // Any Symfony cache pool works (Redis, Memcached, APCu...): an ArrayAdapter only lives for the process
            $this->cache->setCache(new ArrayAdapter());
        }

        return $this->cache;
    }

    public function repositoryFactory(): RepositoryFactory
    {
        return $this->repositoryFactory ??= new RepositoryFactory(
            $this->connectionPool(),
            $this->metadataRepository(),
            $this->queryFactory(),
            $this->collectionFactory(),
            $this->unitOfWork(),
            $this->cache()
        );
    }

    public function collectionFactory(): CollectionFactory
    {
        return new CollectionFactory($this->metadataRepository(), $this->unitOfWork(), $this->hydrator());
    }

    public function hydrator(): Hydrator
    {
        return $this->wire(new Hydrator());
    }

    public function hydratorSingleObject(): HydratorSingleObject
    {
        return $this->wire(new HydratorSingleObject());
    }

    public function hydratorAggregator(): HydratorAggregator
    {
        return $this->wire(new HydratorAggregator());
    }

    public function hydratorRelational(): HydratorRelational
    {
        return $this->wire(new HydratorRelational());
    }

    /**
     * @template T of Hydrator
     * @param T $hydrator
     * @return T
     */
    private function wire(Hydrator $hydrator): Hydrator
    {
        $hydrator->setMetadataRepository($this->metadataRepository());
        $hydrator->setUnitOfWork($this->unitOfWork());

        return $hydrator;
    }
}
