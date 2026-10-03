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

namespace CCMBenchmark\Ting\Tests\Unit;

use CCMBenchmark\Ting\ConnectionPoolInterface;
use CCMBenchmark\Ting\ContainerInterface;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Query\QueryFactoryInterface;
use CCMBenchmark\Ting\Repository\CollectionFactory;
use CCMBenchmark\Ting\Repository\Hydrator;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;
use CCMBenchmark\Ting\Repository\RepositoryFactory;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;
use CCMBenchmark\Ting\Services;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use CCMBenchmark\Ting\UnitOfWork;

class ServicesTest extends TestCase
{
    public function testConstructShouldInitAllDependencies()
    {
        $services = new Services();

        $this->assertInstanceOf(ConnectionPoolInterface::class, $services->get('ConnectionPool'));
        $this->assertInstanceOf(MetadataRepository::class, $services->get('MetadataRepository'));
        $this->assertInstanceOf(UnitOfWork::class, $services->get('UnitOfWork'));
        $this->assertInstanceOf(CollectionFactory::class, $services->get('CollectionFactory'));
        $this->assertInstanceOf(QueryFactoryInterface::class, $services->get('QueryFactory'));
        $this->assertInstanceOf(SerializerFactoryInterface::class, $services->get('SerializerFactory'));
        $this->assertInstanceOf(Hydrator::class, $services->get('Hydrator'));
        $this->assertInstanceOf(HydratorSingleObject::class, $services->get('HydratorSingleObject'));
        $this->assertInstanceOf(RepositoryFactory::class, $services->get('RepositoryFactory'));
    }

    public function testShouldImplementsContainerInterface()
    {
        $this->assertInstanceOf(ContainerInterface::class, new Services());
    }

    public function testGetCallbackShouldBeSameCallbackUsedWithSet()
    {
        $callback = (fn ($bouh) => 'Bouh Wow');

        $services = new Services();
        $services->set('Bouh', $callback);

        $this->assertSame('Bouh Wow', $services->get('Bouh'));
    }

    public function testGetShouldReturnSameInstance()
    {
        $callback = (fn ($bouh) => new \stdClass());

        $services = new Services();
        $services->set('Bouh', $callback);

        $bouh = $services->get('Bouh');
        $this->assertIsObject($bouh);
        $bouh2 = $services->get('Bouh');
        $this->assertIsObject($bouh2);
        $this->assertSame($bouh, $bouh2);
    }

    public function testGetShouldReturnNewInstance()
    {
        $callback = (fn ($bouh) => new \stdClass());

        $services = new Services();
        $services->set('Bouh', $callback, true);

        $bouh = $services->get('Bouh');
        $this->assertIsObject($bouh);
        $bouh2 = $services->get('Bouh');
        $this->assertIsObject($bouh2);
        $this->assertNotSame($bouh, $bouh2);
    }

    public function testHasShouldReturnTrue()
    {
        $callback = (fn ($bouh) => 'Bouh Wow');

        $services = new Services();
        $services->set('Bouh', $callback);

        $this->assertTrue($services->has('Bouh'));
    }
}
