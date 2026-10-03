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

namespace CCMBenchmark\Ting\Tests\Unit\Repository;

use CCMBenchmark\Ting\Repository\RepositoryFactory;
use CCMBenchmark\Ting\Services;
use CCMBenchmark\Ting\Tests\Support\TestCase;

class RepositoryFactoryTest extends TestCase
{
    public function testGet()
    {
        $services = new Services();

        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $repositoryFactory = new RepositoryFactory(
            $services->get('ConnectionPool'),
            $services->get('MetadataRepository'),
            $services->get('QueryFactory'),
            $services->get('CollectionFactory'),
            $services->get('UnitOfWork'),
            $services->get('Cache'),
            $services->get('SerializerFactory')
        );
        $repository = $repositoryFactory->get('\tests\fixtures\model\BouhRepository');

        $this->assertInstanceOf('\tests\fixtures\model\BouhRepository', $repository);
    }
}
