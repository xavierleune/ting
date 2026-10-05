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
use CCMBenchmark\Ting\Tests\Support\TingServices;
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
        $services           = new TingServices();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['replica'])->getMock();
        $mockConnection     = new Connection($mockConnectionPool, 'main', 'bouh_world');
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriver         = new Driver($fakeDriver);

        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $mockQuery = $this->getMockBuilder(Query::class)
            ->setConstructorArgs(['', $mockConnection, $services->collectionFactory()])
            ->onlyMethods(['query'])
            ->getMock();
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['get'])->getMock();

        $mockQueryFactory->method('get')->willReturn($mockQuery);

        $mockConnectionPool->method('replica')->willReturn($mockDriver);

        $entity = new Bouh();
        $entity->setName('Bouh');

        $mockMysqliResult = $this->createMysqliResultMock();

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());

        $mockCollection = $this->getMockBuilder(Collection::class)
            ->setConstructorArgs([$hydrator])
            ->onlyMethods(['count'])
            ->getMock();
        $mockCollection->set($result);
        $mockCollection->method('count')->willReturn(1);
        $mockQuery->expects($this->once())->method('query')->willReturn($mockCollection);

        $repository = new BouhRepository(
            $mockConnectionPool,
            $services->metadataRepository(),
            $mockQueryFactory,
            $services->collectionFactory(),
            $services->cache(),
            $services->unitOfWork(),
            $services->serializerFactory()
        );
        $retrievedEntity = $repository->get([]);
        $this->assertSame($entity->getName(), $retrievedEntity->getName());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetOnPrimary()
    {
        $services           = new TingServices();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['primary'])->getMock();
        $mockConnection     = new Connection($mockConnectionPool, 'main', 'db');
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriver         = new Driver($fakeDriver);

        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        // Spy: selectPrimary() keeps its real implementation, query() is replaced
        $mockQuery = new class ('', $mockConnection, $services->collectionFactory()) extends Query {
            /** @var list<array<mixed>> */
            public array $selectPrimaryCalls = [];
            public int $queryCalls = 0;
            public $queryResult = null;

            public function selectPrimary(bool $usePrimary): static
            {
                $this->selectPrimaryCalls[] = func_get_args();
                return parent::selectPrimary($usePrimary);
            }

            public function query(?CollectionInterface $collection = null): CollectionInterface
            {
                $this->queryCalls++;
                return $this->queryResult;
            }
        };
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['get'])->getMock();

        $mockQueryFactory->method('get')->willReturn($mockQuery);

        $mockConnectionPool->method('primary')->willReturn($mockDriver);
        $mockQuery->queryResult = new Collection();

        $repository = new BouhRepository(
            $mockConnectionPool,
            $services->metadataRepository(),
            $mockQueryFactory,
            $services->collectionFactory(),
            $services->cache(),
            $services->unitOfWork(),
            $services->serializerFactory()
        );
        $this->assertNull($repository->get([], true));
        $this->assertCount(1, array_filter($mockQuery->selectPrimaryCalls, fn ($arguments) => $arguments == [true]));
        $this->assertSame(1, $mockQuery->queryCalls);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testStartTransactionShouldOpenTransaction()
    {
        $services           = new TingServices();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['primary'])->getMock();
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriver         = $this->createDriverSpy($fakeDriver);

        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $mockConnectionPool->method('primary')->willReturn($mockDriver);

        $bouhRepository = new BouhRepository(
            $mockConnectionPool,
            $services->metadataRepository(),
            $services->queryFactory(),
            $services->collectionFactory(),
            $services->cache(),
            $services->unitOfWork(),
            $services->serializerFactory()
        );
        $bouhRepository->startTransaction();
        $this->assertSame(1, $mockDriver->calls['startTransaction']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCommitShouldCloseTransaction()
    {
        $services           = new TingServices();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['primary'])->getMock();
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriver         = $this->createDriverSpy($fakeDriver);

        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $mockConnectionPool->method('primary')->willReturn($mockDriver);

        $bouhRepository = new BouhRepository(
            $mockConnectionPool,
            $services->metadataRepository(),
            $services->queryFactory(),
            $services->collectionFactory(),
            $services->cache(),
            $services->unitOfWork(),
            $services->serializerFactory()
        );
        $bouhRepository->startTransaction();
        $bouhRepository->commit();
        $this->assertSame(1, $mockDriver->calls['commit']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testRollbackShouldCloseTransaction()
    {
        $services           = new TingServices();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['primary'])->getMock();
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriver         = $this->createDriverSpy($fakeDriver);

        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $mockConnectionPool->method('primary')->willReturn($mockDriver);

        $bouhRepository = new BouhRepository(
            $mockConnectionPool,
            $services->metadataRepository(),
            $services->queryFactory(),
            $services->collectionFactory(),
            $services->cache(),
            $services->unitOfWork(),
            $services->serializerFactory()
        );
        $bouhRepository->startTransaction();
        $bouhRepository->rollback();
        $this->assertSame(1, $mockDriver->calls['rollback']);
    }

    public function testSaveShouldCallUnitOfWorkSaveThenProcess()
    {
        $services           = new TingServices();
        $mockConnectionPool = new ConnectionPool();
        $mockUnitOfWork     = $this->getMockBuilder(UnitOfWork::class)
            ->setConstructorArgs([
                $mockConnectionPool,
                $services->metadataRepository(),
                $services->queryFactory()
            ])
            ->onlyMethods(['pushSave', 'process'])
            ->getMock();
        $mockUnitOfWork->expects($this->once())->method('pushSave')->willReturn($mockUnitOfWork);
        // process() is declared void: atoum's "process = true" could not be returned anyway
        $mockUnitOfWork->expects($this->once())->method('process');

        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $entity = new Bouh();

        $bouhRepository = new BouhRepository(
            $mockConnectionPool,
            $services->metadataRepository(),
            $services->queryFactory(),
            $services->collectionFactory(),
            $services->cache(),
            $mockUnitOfWork,
            $services->serializerFactory()
        );
        $bouhRepository->save($entity);
    }

    public function testDeleteShouldCallUnitOfWorkDeleteThenProcess()
    {
        $services           = new TingServices();
        $mockConnectionPool = new ConnectionPool();
        $mockUnitOfWork     = $this->getMockBuilder(UnitOfWork::class)
            ->setConstructorArgs([
                $mockConnectionPool,
                $services->metadataRepository(),
                $services->queryFactory()
            ])
            ->onlyMethods(['pushDelete', 'process'])
            ->getMock();
        $mockUnitOfWork->expects($this->once())->method('pushDelete')->willReturn($mockUnitOfWork);
        // process() is declared void: atoum's "process = true" could not be returned anyway
        $mockUnitOfWork->expects($this->once())->method('process');

        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $entity = new Bouh();

        $bouhRepository = new BouhRepository(
            $mockConnectionPool,
            $services->metadataRepository(),
            $services->queryFactory(),
            $services->collectionFactory(),
            $services->cache(),
            $mockUnitOfWork,
            $services->serializerFactory()
        );
        $bouhRepository->delete($entity);
    }

    public function testGetQueryShouldCallQueryFactoryGet()
    {
        $services         = new TingServices();
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['get'])->getMock();

        $query            = new Query('QUERY', new Connection(new ConnectionPool(), 'main', 'db'));

        $mockQueryFactory->expects($this->once())->method('get')->willReturn($query);

        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $bouhRepository = new BouhRepository(
            $services->connectionPool(),
            $services->metadataRepository(),
            $mockQueryFactory,
            $services->collectionFactory(),
            $services->cache(),
            $services->unitOfWork(),
            $services->serializerFactory()
        );
        $this->assertSame($query, $bouhRepository->getQuery('QUERY'));
    }

    public function testGetPreparedQueryShouldCallQueryFactoryGetPrepared()
    {
        $services         = new TingServices();
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['getPrepared'])->getMock();

        $query            = new PreparedQuery('QUERY', new Connection(new ConnectionPool(), 'main', 'db'));

        $mockQueryFactory->expects($this->once())->method('getPrepared')->willReturn($query);

        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $bouhRepository = new BouhRepository(
            $services->connectionPool(),
            $services->metadataRepository(),
            $mockQueryFactory,
            $services->collectionFactory(),
            $services->cache(),
            $services->unitOfWork(),
            $services->serializerFactory()
        );
        $this->assertSame($query, $bouhRepository->getPreparedQuery('QUERY'));
    }

    public function testGetCachedQueryShouldCallQueryFactoryGetCached()
    {
        $services         = new TingServices();
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['getCached'])->getMock();

        $query            = new CachedQuery('QUERY', new Connection(new ConnectionPool(), 'main', 'db'));

        $mockQueryFactory->expects($this->once())->method('getCached')->willReturn($query);

        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $bouhRepository = new BouhRepository(
            $services->connectionPool(),
            $services->metadataRepository(),
            $mockQueryFactory,
            $services->collectionFactory(),
            $services->cache(),
            $services->unitOfWork(),
            $services->serializerFactory()
        );
        $this->assertSame($query, $bouhRepository->getCachedQuery('QUERY'));
    }

    public function testGetCachedPreparedQueryShouldCallQueryFactoryGetCachedPreparedQuery()
    {
        $services         = new TingServices();
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['getCachedPrepared'])->getMock();

        $query            = new CachedPreparedQuery('QUERY', new Connection(new ConnectionPool(), 'main', 'db'));

        $mockQueryFactory->expects($this->once())->method('getCachedPrepared')->willReturn($query);

        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $bouhRepository = new BouhRepository(
            $services->connectionPool(),
            $services->metadataRepository(),
            $mockQueryFactory,
            $services->collectionFactory(),
            $services->cache(),
            $services->unitOfWork(),
            $services->serializerFactory()
        );
        $this->assertSame($query, $bouhRepository->getCachedPreparedQuery('QUERY'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetAllShouldReturnAQuery()
    {
        $services           = new TingServices();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['replica'])->getMock();
        $mockConnection     = new Connection($mockConnectionPool, 'main', 'db');
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriver         = new Driver($fakeDriver);

        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $mockQuery = $this->getMockBuilder(Query::class)
            ->setConstructorArgs(['', $mockConnection, $services->collectionFactory()])
            ->onlyMethods(['query'])
            ->getMock();
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['get'])->getMock();

        $mockQueryFactory->method('get')->willReturn($mockQuery);

        $mockConnectionPool->method('replica')->willReturn($mockDriver);

        $entity = new Bouh();
        $entity->setName('Bouh');

        $collection = new Collection();

        $mockQuery->method('query')->willReturn($collection);

        $repository = new BouhRepository(
            $mockConnectionPool,
            $services->metadataRepository(),
            $mockQueryFactory,
            $services->collectionFactory(),
            $services->cache(),
            $services->unitOfWork(),
            $services->serializerFactory()
        );
        $this->assertInstanceOf(CollectionInterface::class, $repository->getAll());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetByCriteriaShouldReturnAQuery()
    {
        $services           = new TingServices();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['replica'])->getMock();
        $mockConnection     = new Connection($mockConnectionPool, 'main', 'db');
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriver         = new Driver($fakeDriver);

        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $mockQuery = $this->getMockBuilder(Query::class)
            ->setConstructorArgs(['', $mockConnection, $services->collectionFactory()])
            ->onlyMethods(['query'])
            ->getMock();
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['get'])->getMock();

        $mockQueryFactory->method('get')->willReturn($mockQuery);

        $mockConnectionPool->method('replica')->willReturn($mockDriver);

        $entity = new Bouh();
        $entity->setName('Bouh');

        $collection = new Collection();

        $mockQuery->method('query')->willReturn($collection);

        $repository = new BouhRepository(
            $mockConnectionPool,
            $services->metadataRepository(),
            $mockQueryFactory,
            $services->collectionFactory(),
            $services->cache(),
            $services->unitOfWork(),
            $services->serializerFactory()
        );
        $this->assertInstanceOf(CollectionInterface::class, $repository->getBy(['name' => 'bouh']));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetOneByCriteriaShouldReturnAnEntityOrNull()
    {
        $services           = new TingServices();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['replica'])->getMock();
        $mockConnection     = new Connection($mockConnectionPool, 'main', 'bouh_world');
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriver         = new Driver($fakeDriver);

        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/BouhRepository.php'
        );

        $mockQuery = $this->getMockBuilder(Query::class)
            ->setConstructorArgs(['', $mockConnection, $services->collectionFactory()])
            ->onlyMethods(['query'])
            ->getMock();
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['get'])->getMock();

        $mockQueryFactory->method('get')->willReturn($mockQuery);

        $mockConnectionPool->method('replica')->willReturn($mockDriver);

        $entity = new Bouh();
        $entity->setName('Bouh');

        $mockMysqliResult = $this->createMysqliResultMock();

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());

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
            $services->metadataRepository(),
            $mockQueryFactory,
            $services->collectionFactory(),
            $services->cache(),
            $services->unitOfWork(),
            $services->serializerFactory()
        );
        $this->assertInstanceOf($entity::class, $repository->getOneBy(['name' => 'Xavier']));
        $emptyCollection = new Collection();
        $queryResult = $emptyCollection;
        $this->assertNull($repository->getOneBy(['name' => 'Xavier']));
    }

    public function testGetQueryBuilderShouldThrowExceptionOnUnknownDriver()
    {
        $services = new TingServices();
        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $services->connectionPool()->setConfig([
            'main' => [
                'namespace' => '\Unknown\Driver\Mysqli'
            ]
        ]);

        $bouhRepository = $services->repositoryFactory()->get('\tests\fixtures\model\BouhRepository');
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
        $services = new TingServices();
        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $services->connectionPool()
            ->setConfig(['main' => ['namespace' => '\CCMBenchmark\Ting\Driver\SphinxQL']]);
        $bouhRepository = $services->repositoryFactory()->get('\tests\fixtures\model\BouhRepository');
        $this->assertInstanceOf(
            SelectInterface::class,
            $bouhRepository->getQueryBuilder($bouhRepository::QUERY_SELECT)
        );
        $services->connectionPool()
            ->setConfig(['main' => ['namespace' => 'CCMBenchmark\Ting\Driver\Pgsql']]);
        $this->assertInstanceOf(SelectInterface::class, $bouhRepository->getQueryBuilder("unkwnon"));
        $this->assertInstanceOf(
            UpdateInterface::class,
            $bouhRepository->getQueryBuilder($bouhRepository::QUERY_UPDATE)
        );
        $services->connectionPool()
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
        $services           = new TingServices();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)
            ->onlyMethods(['replica', 'primary'])
            ->getMock();
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriverReplica    = $this->getMockBuilder(Driver::class)
            ->setConstructorArgs([$fakeDriver])
            ->onlyMethods(['ping'])
            ->getMock();
        $mockDriverPrimary   = $this->getMockBuilder(Driver::class)
            ->setConstructorArgs([$fakeDriver])
            ->onlyMethods(['ping'])
            ->getMock();

        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../../fixtures/model/*Repository.php'
        );

        $mockConnectionPool->method('replica')->willReturn($mockDriverReplica);
        $mockConnectionPool->method('primary')->willReturn($mockDriverPrimary);
        $mockDriverPrimary->expects($primaryPing = $this->once())->method('ping')->willReturn(true);
        $mockDriverReplica->expects($replicaPing = $this->once())->method('ping')->willReturn(true);

        $bouhRepository = new BouhRepository(
            $mockConnectionPool,
            $services->metadataRepository(),
            $services->queryFactory(),
            $services->collectionFactory(),
            $services->cache(),
            $services->unitOfWork(),
            $services->serializerFactory()
        );
        $bouhRepository->ping();
        $this->assertSame(1, $replicaPing->numberOfInvocations());
        $bouhRepository->pingPrimary();
        $this->assertSame(1, $primaryPing->numberOfInvocations());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetMetadata()
    {
        $services           = new TingServices();
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)
            ->onlyMethods(['replica', 'primary'])
            ->getMock();
        $fakeDriver         = $this->createStub(Mysqli::class);
        $mockDriverReplica    = new Driver($fakeDriver);
        $mockDriverPrimary   = new Driver($fakeDriver);
        $metadataRepository = $this->getMockBuilder(MetadataRepository::class)
            ->setConstructorArgs([$services->serializerFactory()])
            ->onlyMethods(['findMetadataForRepository'])
            ->getMock();
        $metadata           = $this->getMockBuilder(Metadata::class)
            ->setConstructorArgs([$services->serializerFactory()])
            ->onlyMethods(['getConnection'])
            ->getMock();
        $metadata->method('getConnection')->willReturn(new Connection($mockConnectionPool, 'main', 'db'));

        $metadataRepository->method('findMetadataForRepository')->willReturnCallback(
            function ($repository, $callback, $error) use ($metadata): void {
                $callback($metadata);
            }
        );

        $mockConnectionPool->method('replica')->willReturn($mockDriverReplica);
        $mockConnectionPool->method('primary')->willReturn($mockDriverPrimary);

        $bouhRepository = new BouhRepository(
            $mockConnectionPool,
            $metadataRepository,
            $services->queryFactory(),
            $services->collectionFactory(),
            $services->cache(),
            $services->unitOfWork(),
            $services->serializerFactory()
        );
        $this->assertSame($metadata, $bouhRepository->getMetadata());
    }

    public function testResetShouldResetUnitOfWorkAndRenewConnection()
    {
        $services           = new TingServices();
        $mockConnectionPool = new ConnectionPool();
        $firstConnection    = new Connection($mockConnectionPool, 'main', 'db');
        $secondConnection   = new Connection($mockConnectionPool, 'main', 'db');

        $metadata = $this->getMockBuilder(Metadata::class)
            ->setConstructorArgs([$services->serializerFactory()])
            ->onlyMethods(['getConnection'])
            ->getMock();
        $metadata->setEntity(Bouh::class);
        $metadata->expects($this->exactly(2))
            ->method('getConnection')
            ->with($this->identicalTo($mockConnectionPool))
            ->willReturnOnConsecutiveCalls($firstConnection, $secondConnection);

        $metadataRepository = $this->getMockBuilder(MetadataRepository::class)
            ->setConstructorArgs([$services->serializerFactory()])
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
                $services->metadataRepository(),
                $services->queryFactory()
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
            $services->collectionFactory(),
            $services->cache(),
            $mockUnitOfWork,
            $services->serializerFactory()
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
