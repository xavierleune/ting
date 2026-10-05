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

use Aura\SqlQuery\Common\DeleteInterface;
use Aura\SqlQuery\Common\InsertInterface;
use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\Common\UpdateInterface;
use CCMBenchmark\Ting\Connection;
use CCMBenchmark\Ting\ConnectionPool;
use CCMBenchmark\Ting\Driver\Mysqli\Driver;
use CCMBenchmark\Ting\Driver\Mysqli\Result;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Query\Cached\PreparedQuery as CachedPreparedQuery;
use CCMBenchmark\Ting\Query\Cached\Query as CachedQuery;
use CCMBenchmark\Ting\Query\PreparedQuery;
use CCMBenchmark\Ting\Query\Query;
use CCMBenchmark\Ting\Query\QueryFactory;
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Repository\CollectionInterface;
use CCMBenchmark\Ting\Repository\Hydrator;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Services;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use CCMBenchmark\Ting\UnitOfWork;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use tests\fixtures\Fake\Mysqli;
use tests\fixtures\FakeDriver\MysqliResult;
use tests\fixtures\model\Bouh;
use tests\fixtures\model\BouhRepository;

class RepositoryTest extends TestCase
{
    #[AllowMockObjectsWithoutExpectations]
    public function testGet()
    {
        $services           = new Services();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['slave'])->getMock();
        $mockConnection     = new Connection($mockConnectionPool, 'main', 'bouh_world');
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriver         = new Driver($fakeDriver);

        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $mockQuery = $this->getMockBuilder(Query::class)
            ->setConstructorArgs(['', $mockConnection, $services->get('CollectionFactory')])
            ->onlyMethods(['query'])
            ->getMock();
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['get'])->getMock();

        $mockQueryFactory->method('get')->willReturn($mockQuery);

        $mockConnectionPool->method('slave')->willReturn($mockDriver);

        $entity = new Bouh();
        $entity->setName('Bouh');

        $mockMysqliResult = $this->createMysqliResultMock();

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->get('MetadataRepository'));
        $hydrator->setUnitOfWork($services->get('UnitOfWork'));

        $mockCollection = $this->getMockBuilder(Collection::class)
            ->setConstructorArgs([$hydrator])
            ->onlyMethods(['count'])
            ->getMock();
        $mockCollection->set($result);
        $mockCollection->method('count')->willReturn(1);
        $mockQuery->expects($this->once())->method('query')->willReturn($mockCollection);

        $repository = new BouhRepository(
            $mockConnectionPool,
            $services->get('MetadataRepository'),
            $mockQueryFactory,
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $services->get('UnitOfWork'),
            $services->get('SerializerFactory')
        );
        $retrievedEntity = $repository->get([]);
        $this->assertSame($entity->getName(), $retrievedEntity->getName());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetOnMaster()
    {
        $services           = new Services();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['master'])->getMock();
        $mockConnection     = new Connection($mockConnectionPool, 'main', 'db');
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriver         = new Driver($fakeDriver);

        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        // Spy: selectMaster() keeps its real implementation, query() is replaced
        $mockQuery = new class ('', $mockConnection, $services->get('CollectionFactory')) extends Query {
            /** @var list<array<mixed>> */
            public array $selectMasterCalls = [];
            public int $queryCalls = 0;
            public $queryResult = null;

            public function selectMaster(bool $useMaster): static
            {
                $this->selectMasterCalls[] = func_get_args();
                return parent::selectMaster($useMaster);
            }

            public function query(?CollectionInterface $collection = null): CollectionInterface
            {
                $this->queryCalls++;
                return $this->queryResult;
            }
        };
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['get'])->getMock();

        $mockQueryFactory->method('get')->willReturn($mockQuery);

        $mockConnectionPool->method('master')->willReturn($mockDriver);
        $mockQuery->queryResult = new Collection();

        $repository = new BouhRepository(
            $mockConnectionPool,
            $services->get('MetadataRepository'),
            $mockQueryFactory,
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $services->get('UnitOfWork'),
            $services->get('SerializerFactory')
        );
        $this->assertNull($repository->get([], true));
        $this->assertCount(1, array_filter($mockQuery->selectMasterCalls, fn ($arguments) => $arguments == [true]));
        $this->assertSame(1, $mockQuery->queryCalls);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testStartTransactionShouldOpenTransaction()
    {
        $services           = new Services();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['master'])->getMock();
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriver         = $this->createDriverSpy($fakeDriver);

        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $mockConnectionPool->method('master')->willReturn($mockDriver);

        $bouhRepository = new BouhRepository(
            $mockConnectionPool,
            $services->get('MetadataRepository'),
            $services->get('QueryFactory'),
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $services->get('UnitOfWork'),
            $services->get('SerializerFactory')
        );
        $bouhRepository->startTransaction();
        $this->assertSame(1, $mockDriver->calls['startTransaction']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCommitShouldCloseTransaction()
    {
        $services           = new Services();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['master'])->getMock();
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriver         = $this->createDriverSpy($fakeDriver);

        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $mockConnectionPool->method('master')->willReturn($mockDriver);

        $bouhRepository = new BouhRepository(
            $mockConnectionPool,
            $services->get('MetadataRepository'),
            $services->get('QueryFactory'),
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $services->get('UnitOfWork'),
            $services->get('SerializerFactory')
        );
        $bouhRepository->startTransaction();
        $bouhRepository->commit();
        $this->assertSame(1, $mockDriver->calls['commit']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRollbackShouldCloseTransaction()
    {
        $services           = new Services();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['master'])->getMock();
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriver         = $this->createDriverSpy($fakeDriver);

        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $mockConnectionPool->method('master')->willReturn($mockDriver);

        $bouhRepository = new BouhRepository(
            $mockConnectionPool,
            $services->get('MetadataRepository'),
            $services->get('QueryFactory'),
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $services->get('UnitOfWork'),
            $services->get('SerializerFactory')
        );
        $bouhRepository->startTransaction();
        $bouhRepository->rollback();
        $this->assertSame(1, $mockDriver->calls['rollback']);
    }

    public function testSaveShouldCallUnitOfWorkSaveThenProcess()
    {
        $services           = new Services();
        $mockConnectionPool = new ConnectionPool();
        $mockUnitOfWork     = $this->getMockBuilder(UnitOfWork::class)
            ->setConstructorArgs([
                $mockConnectionPool,
                $services->get('MetadataRepository'),
                $services->get('QueryFactory')
            ])
            ->onlyMethods(['pushSave', 'process'])
            ->getMock();
        $mockUnitOfWork->expects($this->once())->method('pushSave')->willReturn($mockUnitOfWork);
        // process() is declared void: atoum's "process = true" could not be returned anyway
        $mockUnitOfWork->expects($this->once())->method('process');

        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $entity = new Bouh();

        $bouhRepository = new BouhRepository(
            $mockConnectionPool,
            $services->get('MetadataRepository'),
            $services->get('QueryFactory'),
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $mockUnitOfWork,
            $services->get('SerializerFactory')
        );
        $bouhRepository->save($entity);
    }

    public function testDeleteShouldCallUnitOfWorkDeleteThenProcess()
    {
        $services           = new Services();
        $mockConnectionPool = new ConnectionPool();
        $mockUnitOfWork     = $this->getMockBuilder(UnitOfWork::class)
            ->setConstructorArgs([
                $mockConnectionPool,
                $services->get('MetadataRepository'),
                $services->get('QueryFactory')
            ])
            ->onlyMethods(['pushDelete', 'process'])
            ->getMock();
        $mockUnitOfWork->expects($this->once())->method('pushDelete')->willReturn($mockUnitOfWork);
        // process() is declared void: atoum's "process = true" could not be returned anyway
        $mockUnitOfWork->expects($this->once())->method('process');

        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $entity = new Bouh();

        $bouhRepository = new BouhRepository(
            $mockConnectionPool,
            $services->get('MetadataRepository'),
            $services->get('QueryFactory'),
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $mockUnitOfWork,
            $services->get('SerializerFactory')
        );
        $bouhRepository->delete($entity);
    }

    public function testGetQueryShouldCallQueryFactoryGet()
    {
        $services         = new Services();
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['get'])->getMock();

        $query            = new Query('QUERY', new Connection(new ConnectionPool(), 'main', 'db'));

        $mockQueryFactory->expects($this->once())->method('get')->willReturn($query);

        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $bouhRepository = new BouhRepository(
            $services->get('ConnectionPool'),
            $services->get('MetadataRepository'),
            $mockQueryFactory,
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $services->get('UnitOfWork'),
            $services->get('SerializerFactory')
        );
        $this->assertSame($query, $bouhRepository->getQuery('QUERY'));
    }

    public function testGetPreparedQueryShouldCallQueryFactoryGetPrepared()
    {
        $services         = new Services();
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['getPrepared'])->getMock();

        $query            = new PreparedQuery('QUERY', new Connection(new ConnectionPool(), 'main', 'db'));

        $mockQueryFactory->expects($this->once())->method('getPrepared')->willReturn($query);

        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $bouhRepository = new BouhRepository(
            $services->get('ConnectionPool'),
            $services->get('MetadataRepository'),
            $mockQueryFactory,
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $services->get('UnitOfWork'),
            $services->get('SerializerFactory')
        );
        $this->assertSame($query, $bouhRepository->getPreparedQuery('QUERY'));
    }

    public function testGetCachedQueryShouldCallQueryFactoryGetCached()
    {
        $services         = new Services();
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['getCached'])->getMock();

        $query            = new CachedQuery('QUERY', new Connection(new ConnectionPool(), 'main', 'db'));

        $mockQueryFactory->expects($this->once())->method('getCached')->willReturn($query);

        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $bouhRepository = new BouhRepository(
            $services->get('ConnectionPool'),
            $services->get('MetadataRepository'),
            $mockQueryFactory,
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $services->get('UnitOfWork'),
            $services->get('SerializerFactory')
        );
        $this->assertSame($query, $bouhRepository->getCachedQuery('QUERY'));
    }

    public function testGetCachedPreparedQueryShouldCallQueryFactoryGetCachedPreparedQuery()
    {
        $services         = new Services();
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['getCachedPrepared'])->getMock();

        $query            = new CachedPreparedQuery('QUERY', new Connection(new ConnectionPool(), 'main', 'db'));

        $mockQueryFactory->expects($this->once())->method('getCachedPrepared')->willReturn($query);

        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $bouhRepository = new BouhRepository(
            $services->get('ConnectionPool'),
            $services->get('MetadataRepository'),
            $mockQueryFactory,
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $services->get('UnitOfWork'),
            $services->get('SerializerFactory')
        );
        $this->assertSame($query, $bouhRepository->getCachedPreparedQuery('QUERY'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetAllShouldReturnAQuery()
    {
        $services           = new Services();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['slave'])->getMock();
        $mockConnection     = new Connection($mockConnectionPool, 'main', 'db');
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriver         = new Driver($fakeDriver);

        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $mockQuery = $this->getMockBuilder(Query::class)
            ->setConstructorArgs(['', $mockConnection, $services->get('CollectionFactory')])
            ->onlyMethods(['query'])
            ->getMock();
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['get'])->getMock();

        $mockQueryFactory->method('get')->willReturn($mockQuery);

        $mockConnectionPool->method('slave')->willReturn($mockDriver);

        $entity = new Bouh();
        $entity->setName('Bouh');

        $collection = new Collection();

        $mockQuery->method('query')->willReturn($collection);

        $repository = new BouhRepository(
            $mockConnectionPool,
            $services->get('MetadataRepository'),
            $mockQueryFactory,
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $services->get('UnitOfWork'),
            $services->get('SerializerFactory')
        );
        $this->assertInstanceOf(CollectionInterface::class, $repository->getAll());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetByCriteriaShouldReturnAQuery()
    {
        $services           = new Services();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['slave'])->getMock();
        $mockConnection     = new Connection($mockConnectionPool, 'main', 'db');
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriver         = new Driver($fakeDriver);

        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $mockQuery = $this->getMockBuilder(Query::class)
            ->setConstructorArgs(['', $mockConnection, $services->get('CollectionFactory')])
            ->onlyMethods(['query'])
            ->getMock();
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['get'])->getMock();

        $mockQueryFactory->method('get')->willReturn($mockQuery);

        $mockConnectionPool->method('slave')->willReturn($mockDriver);

        $entity = new Bouh();
        $entity->setName('Bouh');

        $collection = new Collection();

        $mockQuery->method('query')->willReturn($collection);

        $repository = new BouhRepository(
            $mockConnectionPool,
            $services->get('MetadataRepository'),
            $mockQueryFactory,
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $services->get('UnitOfWork'),
            $services->get('SerializerFactory')
        );
        $this->assertInstanceOf(CollectionInterface::class, $repository->getBy(['name' => 'bouh']));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetOneByCriteriaShouldReturnAnEntityOrNull()
    {
        $services           = new Services();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['slave'])->getMock();
        $mockConnection     = new Connection($mockConnectionPool, 'main', 'bouh_world');
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriver         = new Driver($fakeDriver);

        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/BouhRepository.php'
        );

        $mockQuery = $this->getMockBuilder(Query::class)
            ->setConstructorArgs(['', $mockConnection, $services->get('CollectionFactory')])
            ->onlyMethods(['query'])
            ->getMock();
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['get'])->getMock();

        $mockQueryFactory->method('get')->willReturn($mockQuery);

        $mockConnectionPool->method('slave')->willReturn($mockDriver);

        $entity = new Bouh();
        $entity->setName('Bouh');

        $mockMysqliResult = $this->createMysqliResultMock();

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->get('MetadataRepository'));
        $hydrator->setUnitOfWork($services->get('UnitOfWork'));

        $mockCollection = $this->getMockBuilder(Collection::class)
            ->setConstructorArgs([$hydrator])
            ->onlyMethods(['count'])
            ->getMock();
        $mockCollection->set($result);
        $mockCollection->method('count')->willReturn(1);

        // query() is redefined during the test
        $queryResult = $mockCollection;
        $mockQuery->method('query')->willReturnCallback(function () use (&$queryResult) {
            return $queryResult;
        });

        $repository = new BouhRepository(
            $mockConnectionPool,
            $services->get('MetadataRepository'),
            $mockQueryFactory,
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $services->get('UnitOfWork'),
            $services->get('SerializerFactory')
        );
        $this->assertInstanceOf($entity::class, $repository->getOneBy(['name' => 'Xavier']));
        $emptyCollection = new Collection();
        $queryResult = $emptyCollection;
        $this->assertNull($repository->getOneBy(['name' => 'Xavier']));
    }

    public function testGetQueryBuilderShouldThrowExceptionOnUnknownDriver()
    {
        $services = new Services();
        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $services->get('ConnectionPool')->setConfig([
            'main' => [
                'namespace' => '\Unknown\Driver\Mysqli'
            ]
        ]);

        $bouhRepository = $services->get('RepositoryFactory')->get('\tests\fixtures\model\BouhRepository');
        $this->assertThrows(
            \Throwable::class,
            function () use ($bouhRepository): void {
                $bouhRepository->getQueryBuilder($bouhRepository::QUERY_SELECT);
            },
            'Driver Unknown\Driver\Mysqli\Driver is unknown to build QueryBuilder'
        );
    }

    public function testGetQueryBuilder()
    {
        $services = new Services();
        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $services->get('ConnectionPool')
            ->setConfig(['main' => ['namespace' => '\CCMBenchmark\Ting\Driver\SphinxQL']]);
        $bouhRepository = $services->get('RepositoryFactory')->get('\tests\fixtures\model\BouhRepository');
        $this->assertInstanceOf(
            SelectInterface::class,
            $bouhRepository->getQueryBuilder($bouhRepository::QUERY_SELECT)
        );
        $services->get('ConnectionPool')
            ->setConfig(['main' => ['namespace' => 'CCMBenchmark\Ting\Driver\Pgsql']]);
        $this->assertInstanceOf(SelectInterface::class, $bouhRepository->getQueryBuilder("unkwnon"));
        $this->assertInstanceOf(
            UpdateInterface::class,
            $bouhRepository->getQueryBuilder($bouhRepository::QUERY_UPDATE)
        );
        $services->get('ConnectionPool')
            ->setConfig(['main' => ['namespace' => '\CCMBenchmark\Ting\Driver\Mysqli']]);
        $this->assertInstanceOf(
            DeleteInterface::class,
            $bouhRepository->getQueryBuilder($bouhRepository::QUERY_DELETE)
        );
        $this->assertInstanceOf(
            InsertInterface::class,
            $bouhRepository->getQueryBuilder($bouhRepository::QUERY_INSERT)
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPingShouldPingMethodsShouldCallPingOnTheGoodConnections()
    {
        $services           = new Services();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)
            ->onlyMethods(['slave', 'master'])
            ->getMock();
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriverSlave    = $this->getMockBuilder(Driver::class)
            ->setConstructorArgs([$fakeDriver])
            ->onlyMethods(['ping'])
            ->getMock();
        $mockDriverMaster   = $this->getMockBuilder(Driver::class)
            ->setConstructorArgs([$fakeDriver])
            ->onlyMethods(['ping'])
            ->getMock();

        $services->get('MetadataRepository')->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $mockConnectionPool->method('slave')->willReturn($mockDriverSlave);
        $mockConnectionPool->method('master')->willReturn($mockDriverMaster);
        $mockDriverMaster->expects($masterPing = $this->once())->method('ping')->willReturn(true);
        $mockDriverSlave->expects($slavePing = $this->once())->method('ping')->willReturn(true);

        $bouhRepository = new BouhRepository(
            $mockConnectionPool,
            $services->get('MetadataRepository'),
            $services->get('QueryFactory'),
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $services->get('UnitOfWork'),
            $services->get('SerializerFactory')
        );
        $bouhRepository->ping();
        $this->assertSame(1, $slavePing->numberOfInvocations());
        $bouhRepository->pingMaster();
        $this->assertSame(1, $masterPing->numberOfInvocations());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetMetadata()
    {
        $services           = new Services();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)
            ->onlyMethods(['slave', 'master'])
            ->getMock();
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriverSlave    = new Driver($fakeDriver);
        $mockDriverMaster   = new Driver($fakeDriver);
        $metadataRepository = $this->getMockBuilder(MetadataRepository::class)
            ->setConstructorArgs([$services->get('SerializerFactory')])
            ->onlyMethods(['findMetadataForRepository'])
            ->getMock();
        $metadata           = $this->getMockBuilder(Metadata::class)
            ->setConstructorArgs([$services->get('SerializerFactory')])
            ->onlyMethods(['getConnection'])
            ->getMock();
        $metadata->method('getConnection')->willReturn(new Connection($mockConnectionPool, 'main', 'db'));

        $metadataRepository->method('findMetadataForRepository')->willReturnCallback(
            function ($repository, $callback, $error) use ($metadata): void {
                $callback($metadata);
            }
        );

        $mockConnectionPool->method('slave')->willReturn($mockDriverSlave);
        $mockConnectionPool->method('master')->willReturn($mockDriverMaster);

        $bouhRepository = new BouhRepository(
            $mockConnectionPool,
            $metadataRepository,
            $services->get('QueryFactory'),
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $services->get('UnitOfWork'),
            $services->get('SerializerFactory')
        );
        $this->assertSame($metadata, $bouhRepository->getMetadata());
    }

    public function testResetShouldResetUnitOfWorkAndRenewConnection()
    {
        $services           = new Services();
        $mockConnectionPool = new ConnectionPool();
        $firstConnection    = new Connection($mockConnectionPool, 'main', 'db');
        $secondConnection   = new Connection($mockConnectionPool, 'main', 'db');

        $metadata = $this->getMockBuilder(Metadata::class)
            ->setConstructorArgs([$services->get('SerializerFactory')])
            ->onlyMethods(['getConnection'])
            ->getMock();
        $metadata->setEntity(Bouh::class);
        $metadata->expects($this->exactly(2))
            ->method('getConnection')
            ->with($this->identicalTo($mockConnectionPool))
            ->willReturnOnConsecutiveCalls($firstConnection, $secondConnection);

        $metadataRepository = $this->getMockBuilder(MetadataRepository::class)
            ->setConstructorArgs([$services->get('SerializerFactory')])
            ->onlyMethods(['findMetadataForRepository'])
            ->getMock();
        $metadataRepository->expects($this->once())->method('findMetadataForRepository')->willReturnCallback(
            function ($repository, $callback, $error) use ($metadata): void {
                $callback($metadata);
            }
        );

        $mockUnitOfWork = $this->getMockBuilder(UnitOfWork::class)
            ->setConstructorArgs([
                $mockConnectionPool,
                $services->get('MetadataRepository'),
                $services->get('QueryFactory')
            ])
            ->onlyMethods(['reset'])
            ->getMock();
        $mockUnitOfWork->expects($this->once())->method('reset');

        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['get'])->getMock();
        $mockQueryFactory->expects($this->once())
            ->method('get')
            ->with('QUERY', $this->identicalTo($secondConnection))
            ->willReturn(new Query('QUERY', $secondConnection));

        $bouhRepository = new BouhRepository(
            $mockConnectionPool,
            $metadataRepository,
            $mockQueryFactory,
            $services->get('CollectionFactory'),
            $services->get('Cache'),
            $mockUnitOfWork,
            $services->get('SerializerFactory')
        );
        $bouhRepository->reset();
        $bouhRepository->getQuery('QUERY');
    }

    /**
     * Partial mock of the fake mysqli result: only fetch_fields() is replaced.
     */
    private function createMysqliResultMock(): MysqliResult
    {
        $mockMysqliResult = new MysqliResult([['Bouh']]);
        $mockMysqliResult->setFieldsCallback(function () {
            $fields             = [];
            $stdClass           = new \stdClass();
            $stdClass->name     = 'name';
            $stdClass->orgname  = 'boo_name';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[]           = $stdClass;

            return $fields;
        });

        return $mockMysqliResult;
    }

    /**
     * Spy: counts transaction calls while keeping the real implementation.
     */
    private function createDriverSpy(object $fakeDriver): Driver
    {
        return new class ($fakeDriver) extends Driver {
            /** @var array<string, int> */
            public array $calls = ['startTransaction' => 0, 'commit' => 0, 'rollback' => 0];

            public function startTransaction(): void
            {
                $this->calls['startTransaction']++;
                parent::startTransaction();
            }

            public function commit(): void
            {
                $this->calls['commit']++;
                parent::commit();
            }

            public function rollback(): void
            {
                $this->calls['rollback']++;
                parent::rollback();
            }
        };
    }
}
