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
use Doctrine\Common\Cache\MemcachedCache;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
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
        $mockConnection = $this->createStub(Connection::class);
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

        $mockMemcached = $this->createMock(MemcachedCache::class);
        $mockMemcached->expects($this->exactly(2))->method('fetch')->willReturnCallback(fn () => [
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
        $mockMemcached->expects($this->never())->method('save');

        $collection = new Collection();

        $query = new Query('', $mockConnection, $mockCollectionFactory);
        $query->setCache($mockMemcached);
        $query->setTtl(10)->setCacheKey('myCacheKey');
        $this->assertSame($collection, $query->query($collection));
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

        $mockMemcached = $this->createMock(MemcachedCache::class);
        $mockMemcached->expects($this->once())->method('fetch')->willReturn(false);
        $mockMemcached->expects($this->once())->method('save')->willReturn(true);
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
        $query->setCache($mockMemcached);
        $query->setTtl(10)->setCacheKey('myCacheKey');
        $this->assertSame($collection, $query->query($collection));
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
}
