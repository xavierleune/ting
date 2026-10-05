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

use CCMBenchmark\Ting\Connection;
use CCMBenchmark\Ting\ConnectionPool;
use CCMBenchmark\Ting\Driver\Mysqli\Driver;
use CCMBenchmark\Ting\Exception;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Query\PreparedQuery;
use CCMBenchmark\Ting\Query\QueryFactory;
use CCMBenchmark\Ting\Services;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use CCMBenchmark\Ting\UnitOfWork;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use tests\fixtures\model\Bouh;
use tests\fixtures\model\BouhRepository;

class UnitOfWorkTest extends TestCase
{
    protected $services = null;

    protected function setUp(): void
    {
        $this->services = new Services();
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig(
            [
                'main' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'primary'   => [
                        'host'      => 'localhost.test',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ]
                ]
            ]
        );

        $this->services->set('ConnectionPool', fn ($container) => $connectionPool);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testManageShouldAddPropertyListener()
    {
        $mockEntity = $this->getMockBuilder(Bouh::class)
            ->onlyMethods(['addPropertyListener'])
            ->getMock();
        $mockEntity->method('addPropertyListener')->willReturnCallback(
            function ($unitOfWork) use (&$outerUnitOfWork): void {
                $outerUnitOfWork = $unitOfWork;
            }
        );

        $unitOfWork = new UnitOfWork(
            $this->services->get('ConnectionPool'),
            $this->services->get('MetadataRepository'),
            $this->services->get('QueryFactory')
        );
        $unitOfWork->manage($mockEntity);
        $this->assertSame($unitOfWork, $outerUnitOfWork);
        $this->assertTrue($unitOfWork->isManaged($mockEntity));
    }

    public function testIsManagedShouldReturnFalse()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->get('ConnectionPool'),
            $this->services->get('MetadataRepository'),
            $this->services->get('QueryFactory')
        );
        $this->assertFalse($unitOfWork->isManaged($mockEntity));
    }

    public function testSave()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->get('ConnectionPool'),
            $this->services->get('MetadataRepository'),
            $this->services->get('QueryFactory')
        );
        $unitOfWork->pushSave($mockEntity);
        $this->assertTrue($unitOfWork->shouldBePersisted($mockEntity));
        $this->assertTrue($unitOfWork->isNew($mockEntity));
    }

    public function testIsPersistedShouldReturnFalse()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->get('ConnectionPool'),
            $this->services->get('MetadataRepository'),
            $this->services->get('QueryFactory')
        );
        $this->assertFalse($unitOfWork->shouldBePersisted($mockEntity));
    }

    public function testPersistManagedEntityShouldNotMarkedNew()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->get('ConnectionPool'),
            $this->services->get('MetadataRepository'),
            $this->services->get('QueryFactory')
        );
        $unitOfWork->manage($mockEntity);
        $unitOfWork->pushSave($mockEntity);
        $this->assertTrue($unitOfWork->shouldBePersisted($mockEntity));
        $this->assertFalse($unitOfWork->isNew($mockEntity));
    }

    public function testIsPropertyChangedWithUUIDShouldReturnFalse()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->get('ConnectionPool'),
            $this->services->get('MetadataRepository'),
            $this->services->get('QueryFactory')
        );
        $unitOfWork->propertyChanged($mockEntity, 'firstname', 'Sylvain', 'Sylvain');
        $this->assertFalse($unitOfWork->isPropertyChanged($mockEntity, 'firstname'));
    }

    public function testPropertyChangedShouldDoNothing()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->get('ConnectionPool'),
            $this->services->get('MetadataRepository'),
            $this->services->get('QueryFactory')
        );
        $unitOfWork->propertyChanged($mockEntity, 'firstname', 'Sylvain', 'Sylvain');
        $this->assertFalse($unitOfWork->isPropertyChanged($mockEntity, 'firstname'));
    }

    public function testPropertyChangedShouldMarkedChanged()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->get('ConnectionPool'),
            $this->services->get('MetadataRepository'),
            $this->services->get('QueryFactory')
        );
        $unitOfWork->propertyChanged($mockEntity, 'firstname', 'Sylvain', 'Sylvain 2');
        $this->assertTrue($unitOfWork->isPropertyChanged($mockEntity, 'firstname'));
    }

    public function testDetach()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->get('ConnectionPool'),
            $this->services->get('MetadataRepository'),
            $this->services->get('QueryFactory')
        );
        $unitOfWork->pushSave($mockEntity);
        $this->assertTrue($unitOfWork->shouldBePersisted($mockEntity));
        $unitOfWork->detach($mockEntity);
        $this->assertFalse($unitOfWork->shouldBePersisted($mockEntity));
    }

    public function testDetachAll()
    {
        $entity1 = new Bouh();
        $entity2 = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->get('ConnectionPool'),
            $this->services->get('MetadataRepository'),
            $this->services->get('QueryFactory')
        );
        $unitOfWork->pushSave($entity1);
        $unitOfWork->pushSave($entity2);
        $unitOfWork->detachAll();
        $this->assertFalse($unitOfWork->shouldBePersisted($entity1));
        $this->assertFalse($unitOfWork->shouldBePersisted($entity2));
    }

    public function testShouldBeRemovedWithUUIDShouldReturnFalse()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->get('ConnectionPool'),
            $this->services->get('MetadataRepository'),
            $this->services->get('QueryFactory')
        );
        $this->assertFalse($unitOfWork->shouldBeRemoved($mockEntity));
    }

    public function testRemove()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->get('ConnectionPool'),
            $this->services->get('MetadataRepository'),
            $this->services->get('QueryFactory')
        );
        $unitOfWork->pushDelete($mockEntity);
        $this->assertTrue($unitOfWork->shouldBeRemoved($mockEntity));
    }

    public function testRemoveShouldReturnFalse()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->get('ConnectionPool'),
            $this->services->get('MetadataRepository'),
            $this->services->get('QueryFactory')
        );
        $this->assertFalse($unitOfWork->shouldBeRemoved($mockEntity));
    }

    public function testIsNewWithoutUUIDShouldReturnFalse()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->get('ConnectionPool'),
            $this->services->get('MetadataRepository'),
            $this->services->get('QueryFactory')
        );
        $this->assertFalse($unitOfWork->isNew($mockEntity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testIsNewAfterProcessShouldReturnFalse()
    {
        $entity = new Bouh();
        $mockMetadataRepository = new MetadataRepository($this->services->get('SerializerFactory'));

        $mockMetadataRepository->addMetadata(
            'tests\fixtures\model\BouhRepository',
            BouhRepository::initMetadata($this->services->get('SerializerFactory'))
        );

        [$mockConnectionPool, $mockQueryFactory] = $this->createProcessMocks(true);

        $unitOfWork = new UnitOfWork(
            $mockConnectionPool,
            $mockMetadataRepository,
            $mockQueryFactory
        );
        $unitOfWork->pushSave($entity);
        $this->assertTrue($unitOfWork->shouldBePersisted($entity));
        $this->assertTrue($unitOfWork->isNew($entity));
        $this->assertNull($unitOfWork->process());
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
        $this->assertFalse($unitOfWork->isNew($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testShouldBePersistedAfterProcessShouldReturnFalse()
    {
        $entity = new Bouh();
        $mockMetadataRepository = new MetadataRepository($this->services->get('SerializerFactory'));

        $mockMetadataRepository->addMetadata(
            'tests\fixtures\model\BouhRepository',
            BouhRepository::initMetadata($this->services->get('SerializerFactory'))
        );

        [$mockConnectionPool, $mockQueryFactory] = $this->createProcessMocks(true);

        $unitOfWork = new UnitOfWork(
            $mockConnectionPool,
            $mockMetadataRepository,
            $mockQueryFactory
        );
        $unitOfWork->manage($entity);
        $entity->setName('newName');
        $unitOfWork->pushSave($entity);
        $this->assertTrue($unitOfWork->shouldBePersisted($entity));
        $this->assertNull($unitOfWork->process());
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
        $unitOfWork->pushDelete($entity);
        $this->assertTrue($unitOfWork->shouldBePersisted($entity));
        $entity->setId(1);
        $this->assertNull($unitOfWork->process());
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testTryingToProcessAnEntityWithoutRepositoryShouldRaiseAnException()
    {
        $entity = new Bouh();
        $mockMetadataRepository = new MetadataRepository($this->services->get('SerializerFactory'));

        [$mockConnectionPool, $mockQueryFactory] = $this->createProcessMocks(false);

        $unitOfWork = new UnitOfWork(
            $mockConnectionPool,
            $mockMetadataRepository,
            $mockQueryFactory
        );
        $unitOfWork->pushSave($entity);
        $this->assertThrows(Exception::class, function () use ($unitOfWork): void {
            $unitOfWork->process();
        });
        $unitOfWork->pushDelete($entity);
        $this->assertThrows(Exception::class, function () use ($unitOfWork): void {
            $unitOfWork->process();
        });
        $unitOfWork->manage($entity);
        $entity->setName('newName');
        $unitOfWork->pushSave($entity);
        $this->assertThrows(Exception::class, function () use ($unitOfWork): void {
            $unitOfWork->process();
        });
    }

    /**
     * Builds the connection pool and query factory partial mocks shared by the process() tests.
     *
     * @return array{0: ConnectionPool, 1: QueryFactory}
     */
    private function createProcessMocks(bool $mockCloseStatement): array
    {
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)
            ->onlyMethods(['primary'])
            ->getMock();
        $mockConnection = new Connection($mockConnectionPool, 'main', 'db');

        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)
            ->onlyMethods(['getPrepared'])
            ->getMock();

        $mockPreparedQuery = $this->getMockBuilder(PreparedQuery::class)
            ->setConstructorArgs(['', $mockConnection])
            ->onlyMethods(['prepareExecute', 'execute'])
            ->getMock();
        $mockPreparedQuery->method('prepareExecute')->willReturn($mockPreparedQuery);
        $mockPreparedQuery->method('execute')->willReturn(true);

        $driverMethods = $mockCloseStatement ? ['getInsertedId', 'closeStatement'] : ['getInsertedId'];
        $mockDriver = $this->getMockBuilder(Driver::class)
            ->onlyMethods($driverMethods)
            ->getMock();
        $mockDriver->method('getInsertedId')->willReturn(1);
        if ($mockCloseStatement) {
            $mockDriver->method('closeStatement')->willReturn(true);
        }

        $mockQueryFactory->method('getPrepared')->willReturn($mockPreparedQuery);
        $mockConnectionPool->method('primary')->willReturn($mockDriver);

        return [$mockConnectionPool, $mockQueryFactory];
    }
}
