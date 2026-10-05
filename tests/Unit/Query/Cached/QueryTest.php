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
use CCMBenchmark\Ting\Query\Cached\Query;
use CCMBenchmark\Ting\Query\QueryException;
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Repository\CollectionFactory;
use CCMBenchmark\Ting\Repository\HydratorInterface;
use CCMBenchmark\Ting\Services;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use tests\fixtures\FakeDriver\MysqliResult;

class QueryTest extends TestCase
{
    public function testSetTTLShouldReturnThis()
    {
        $mockConnection = $this->createStub(Connection::class);

        $cachedQuery = new Query('', $mockConnection);
        $this->assertSame($cachedQuery, $cachedQuery->setTtl(10));
    }

    public function testSetCacheKeyShouldReturnThis()
    {
        $mockConnection = $this->createStub(Connection::class);

        $cachedQuery = new Query('', $mockConnection);
        $this->assertSame($cachedQuery, $cachedQuery->setCacheKey('myCacheKey'));
    }

    public function testSetVersionShouldReturnThis()
    {
        $mockConnection = $this->createStub(Connection::class);

        $cachedQuery = new Query('', $mockConnection);
        $this->assertSame($cachedQuery, $cachedQuery->setVersion(2));
    }

    public function testSetForceShouldReturnThis()
    {
        $mockConnection = $this->createStub(Connection::class);

        $cachedQuery = new Query('', $mockConnection);
        $this->assertSame($cachedQuery, $cachedQuery->setForce(true));
    }

    public function testQueryShouldCallOnlyCacheGetIfDataInCache()
    {
        $services       = new Services();
        $mockConnection = $this->createMock(Connection::class);
        $mockConnection->expects($this->never())->method('slave');
        // Spy: counts calls to get() while keeping the real implementation (a real Collection is expected)
        $mockCollectionFactory = new class (
            $services->get('MetadataRepository'),
            $services->get('UnitOfWork'),
            $services->get('Hydrator')
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
            'data'       =>
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

        $query = new Query('', $mockConnection, $mockCollectionFactory);
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
        $mockConnection   = $this->createStub(Connection::class);
        $mockDriver       = $this->createStub(Driver::class);
        $mockMysqliResult = $this->getMockBuilder(MysqliResult::class)
            ->onlyMethods(['getConnectionName', 'getDatabase'])
            ->getMock();

        $cache = new ArrayAdapter();
        $mockConnection->method('slave')->willReturn($mockDriver);
        $mockMysqliResult->method('getConnectionName')->willReturn('main');
        $mockMysqliResult->method('getDatabase')->willReturn('database');
        $mockDriver->method('execute')->willReturnCallback(
            function ($sql, array $params, $collection) use ($mockMysqliResult) {
                $collection->set($mockMysqliResult);
                return $collection;
            }
        );
        $collection = new Collection();

        $query = new Query('', $mockConnection);
        $query->setCache($cache);
        $query->setTtl(10)->setCacheKey('myCacheKey');
        $this->assertSame($collection, $query->query($collection));
        $this->assertFalse($collection->isFromCache());
        $this->assertSame($collection->toCache(), $cache->getItem('myCacheKey')->get());
    }

    public function testQueryWithoutTTLShouldRaiseException()
    {
        $mockConnection = $this->createStub(Connection::class);

        $cachedQuery = new Query('', $mockConnection);
        $cachedQuery->setCacheKey('myCacheKey');
        $this->assertThrows(
            QueryException::class,
            function () use ($cachedQuery): void {
                $cachedQuery->query();
            },
            'You should call setTtl to use query method'
        );
    }

    public function testQueryWithoutCacheKeyShouldRaiseException()
    {
        $mockConnection = $this->createStub(Connection::class);

        $cachedQuery = new Query('', $mockConnection);
        $cachedQuery->setTtl(10);
        $this->assertThrows(
            QueryException::class,
            function () use ($cachedQuery): void {
                $cachedQuery->query(new Collection());
            },
            'You must call setCacheKey to use query method'
        );
    }

    public function testQueryWithTtl0ShouldStoreTheResultWithoutExpiration()
    {
        $cache = new ArrayAdapter();
        $query = new Query('', $this->createConnectionReturning('Sylvain', $executions));
        $query->setCache($cache);
        $query->setTtl(0)->setCacheKey('myCacheKey');

        $query->query(new Collection());

        // With Symfony, expiresAfter(0) would expire the item at once: 0 must keep meaning "no expiration"
        $this->assertTrue($cache->getItem('myCacheKey')->isHit());
        $this->assertTrue($query->query(new Collection())->isFromCache());
        $this->assertSame(1, $executions);
    }

    public function testQueryWithForceShouldRecomputeACachedResult()
    {
        $cache = new ArrayAdapter();
        $cache->get('myCacheKey', fn () => ['connection' => 'main', 'database' => 'database', 'data' => [['old']]]);
        $query = new Query('', $this->createConnectionReturning('Sylvain', $executions));
        $query->setCache($cache);
        $query->setTtl(10)->setCacheKey('myCacheKey')->setForce(true);

        $collection = $query->query(new Collection());

        $this->assertSame(1, $executions);
        $this->assertFalse($collection->isFromCache());
        $this->assertSame($collection->toCache(), $cache->getItem('myCacheKey')->get());
    }

    /**
     * Connection whose slave driver fills the collection with one row, counting executions in $executions
     */
    private function createConnectionReturning(string $value, ?int &$executions): Connection
    {
        $executions = 0;
        $result = (new MysqliResult([[$value]]))->setConnectionName('main')->setDatabase('database');
        $driver = $this->createStub(Driver::class);
        $driver->method('execute')->willReturnCallback(
            function ($sql, array $params, $collection) use ($result, &$executions) {
                $executions++;
                $collection->set($result);

                return $collection;
            }
        );
        $connection = $this->createStub(Connection::class);
        $connection->method('slave')->willReturn($driver);

        return $connection;
    }
}
