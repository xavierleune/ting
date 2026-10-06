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

use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\UnitOfWork;

/**
 * @template T
 *
 * @template-implements CollectionFactoryInterface<T>
 */
class CollectionFactory implements CollectionFactoryInterface
{
    /**
     * The repository whose metadata hydrate the tables it maps, see forRepository()
     */
    private ?string $repository = null;

    /**
     * @param HydratorInterface<T> $hydrator
     */
    public function __construct(
        protected MetadataRepository $metadataRepository,
        protected UnitOfWork $unitOfWork,
        protected HydratorInterface $hydrator
    ) {
        $this->hydrator->setMetadataRepository($this->metadataRepository);
        $this->hydrator->setUnitOfWork($this->unitOfWork);
    }

    /**
     * A factory of the collections read through a repository: their hydrator, when it is a Hydrator, prefers the
     * metadata of the repository for the tables it maps (Hydrator::setQueryRepository()). This factory is left as is.
     *
     * @param string $repositoryClass a repository, or the class initializing metadata used for hydration only
     *
     * @internal
     */
    public function forRepository(string $repositoryClass): static
    {
        $factory = clone $this;
        $factory->repository = $repositoryClass;

        return $factory;
    }

    /**
     * @template U
     * @param HydratorInterface<U>|null $hydrator null for a clone of the hydrator of the factory
     * @return ($hydrator is null ? Collection<T> : Collection<U>)
     */
    public function get(?HydratorInterface $hydrator = null): Collection
    {
        if (!$hydrator instanceof HydratorInterface) {
            return new Collection($this->preferRepository(clone $this->hydrator));
        }

        $hydrator->setMetadataRepository($this->metadataRepository);
        $hydrator->setUnitOfWork($this->unitOfWork);

        return new Collection($this->preferRepository($hydrator));
    }

    /**
     * @template H of HydratorInterface<mixed>
     * @param H $hydrator
     * @return H
     */
    private function preferRepository(HydratorInterface $hydrator): HydratorInterface
    {
        // A hydrator given to the collections of several factories prefers the repository of the last one only
        if ($hydrator instanceof Hydrator) {
            $hydrator->setQueryRepository($this->repository);
        }

        return $hydrator;
    }
}
