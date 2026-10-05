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

namespace CCMBenchmark\Ting\Tests\Unit\Query\Cached;

use CCMBenchmark\Ting\Connection;
use CCMBenchmark\Ting\Driver\Mysqli\Driver;
use CCMBenchmark\Ting\Driver\Mysqli\Statement;
use CCMBenchmark\Ting\Query\Cached\PreparedQuery;
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Repository\CollectionFactory;
use CCMBenchmark\Ting\Repository\CollectionInterface;
use CCMBenchmark\Ting\Repository\HydratorInterface;
use CCMBenchmark\Ting\Tests\Support\TingServices;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use tests\fixtures\Fake\MysqliStatement;
use tests\fixtures\FakeDriver\MysqliResult;

class PreparedQueryTest extends TestCase
{
    public function testQueryShouldCallOnlyCacheGetIfDataInCache()
    {
        $services       = new TingServices();
        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->expects($this->never())->method('replica');
        // Spy: counts calls to get() while keeping the real implementation (a real Collection is expected)
        $mockCollectionFactory = new class (
            $services->metadataRepository(),
            $services->unitOfWork(),
            $services->hydrator()
        ) extends CollectionFactory {
            public int $getCalls = 0;

            public function get(?HydratorInterface $hydrator = null): Collection
            {
                $this->getCalls++;
                return parent::get($hydrator);
            }
        };

        $cache = new ArrayAdapter();
        $cache->get('myCacheKey', fn () => [
            'connection' => 'connectionName',
            'database'   => 'database',
            'data' =>
                [
                    [
                        [
                            'name'     => 'prenom',
                            'orgName'  => 'firstname',
                            'table'    => 'bouh',
                            'orgTable' => 'T_BOUH_BOO',
                            'type'     => MYSQLI_TYPE_VAR_STRING,
                            'value'    => 'Xavier',
                        ]
                    ]
                ]
        ]);

        $collection = new Collection();

        $query = new PreparedQuery('', $mockConnection, $mockCollectionFactory);
        $query->setCache($cache);
        $query->setTtl(10)->setCacheKey('myCacheKey');
        $this->assertSame($collection, $query->query($collection));
        $this->assertTrue($collection->isFromCache());
        $this->assertSame(0, $mockCollectionFactory->getCalls);
        $this->assertInstanceOf(Collection::class, $query->query());
        $this->assertSame(1, $mockCollectionFactory->getCalls);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testQueryShouldCallCacheGetThenStoreIfDataNotInCache()
    {
        $mockConnection      = $this->createStub(Connection::class);
        $mockDriver          = $this->createStub(Driver::class);
        $mockMysqliStatement = $this->createStub(MysqliStatement::class);
        $mockStatement       = $this->getMockBuilder(Statement::class)
            ->setConstructorArgs([$mockMysqliStatement, [], 'connectionName', 'database'])
            ->onlyMethods(['execute'])
            ->getMock();
        $mockMysqliResult    = $this->getMockBuilder(MysqliResult::class)
            ->onlyMethods(['getConnectionName', 'getDatabase'])
            ->getMock();
        $cache = new ArrayAdapter();

        $mockConnection->method('replica')->willReturn($mockDriver);
        $mockDriver->method('execute')->willReturn(true);
        $mockDriver->method('prepare')->willReturn($mockStatement);
        $mockMysqliResult->method('getConnectionName')->willReturn('connectionName');
        $mockMysqliResult->method('getDatabase')->willReturn('database');
        $mockStatement->method('execute')->willReturnCallback(
            function (array $params, $collection) use ($mockMysqliResult) {
                $collection->set($mockMysqliResult);
                return true;
            }
        );

        $collection = new Collection();

        $query = new PreparedQuery('', $mockConnection);
        $query->setCache($cache);
        $query->setTtl(10)->setCacheKey('myCacheKey');
        $query->prepareQuery();
        $this->assertSame($collection, $query->query($collection));
        $this->assertFalse($collection->isFromCache());
        $this->assertSame($collection->toCache(), $cache->getItem('myCacheKey')->get());
    }

    public function testPrepareExecuteShouldCallConnectionPrepare()
    {
        $mockConnection      = $this->createStub(Connection::class);
        $mockDriver          = $this->createMock(Driver::class);
        $mockMysqliStatement = $this->createStub(MysqliStatement::class);
        // atoum mock without any overridden method: the real class is enough
        $mockStatement       = new Statement($mockMysqliStatement, [], 'connectionName', 'database');
        // atoum used a \mock\CCMBenchmark\Ting\Cache\Memcached ghost mock (get/store) that was never given
        // to the query: it has no effect and is not converted

        $mockConnection->method('primary')->willReturn($mockDriver);
        $mockDriver->method('execute')->willReturn(true);
        $mockDriver->expects($this->once())->method('prepare')->willReturn($mockStatement);

        $query = new PreparedQuery('SELECT', $mockConnection);
        $query->setTtl(0)->setCacheKey('myCacheKey');
        $prepared = $query->prepareExecute();
        $this->assertSame($query->prepareExecute(), $prepared);
    }

    public function testPrepareQueryShouldUseThePrimaryWhenSelected()
    {
        $statement = new Statement($this->createStub(MysqliStatement::class), [], 'connectionName', 'database');
        $primary = $this->createMock(Driver::class);
        $primary->expects($this->once())->method('prepare')->with('SELECT')->willReturn($statement);
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('replica');
        $connection->method('primary')->willReturn($primary);

        $query = new PreparedQuery('SELECT', $connection);
        $query->selectPrimary(true)->prepareQuery();
    }

    public function testExecuteShouldCallStatementExecute()
    {
        $mockConnection      = $this->createStub(Connection::class);
        $mockDriver          = $this->createStub(Driver::class);
        $mockMysqliStatement = $this->createStub(MysqliStatement::class);
        // Spy: execute() is not overridden by the atoum test, its real code runs while calls are counted
        $mockStatement       = new class ($mockMysqliStatement, [], 'connectionName', 'database') extends Statement {
            public int $executeCalls = 0;

            public function execute(array $params, ?CollectionInterface $collection = null): bool|CollectionInterface
            {
                $this->executeCalls++;
                return parent::execute($params, $collection);
            }
        };
        // atoum used a \mock\CCMBenchmark\Ting\Cache\Memcached ghost mock (get/store) that was never given
        // to the query: it has no effect and is not converted

        $mockConnection->method('primary')->willReturn($mockDriver);
        $mockDriver->method('execute')->willReturn(true);
        $mockDriver->method('prepare')->willReturn($mockStatement);
        $mockMysqliStatement->errno = 0;

        $query = new PreparedQuery('SELECT', $mockConnection);
        $query->setTtl(0)->setCacheKey('myCacheKey');
        $query->execute();
        $this->assertSame(1, $mockStatement->executeCalls);
    }
}
