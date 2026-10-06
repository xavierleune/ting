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
     * @template U
     * @param HydratorInterface<U>|null $hydrator null for a clone of the hydrator of the factory
     * @return ($hydrator is null ? Collection<T> : Collection<U>)
     */
    public function get(?HydratorInterface $hydrator = null): Collection
    {
        if (!$hydrator instanceof HydratorInterface) {
            return new Collection(clone $this->hydrator);
        }

        $hydrator->setMetadataRepository($this->metadataRepository);
        $hydrator->setUnitOfWork($this->unitOfWork);

        return new Collection($hydrator);
    }
}
