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

namespace CCMBenchmark\Ting\Tests\Unit\Query;

use CCMBenchmark\Ting\Connection;
use CCMBenchmark\Ting\Driver\Mysqli\Driver;
use CCMBenchmark\Ting\Query\Cached\PreparedQuery as CachedPreparedQuery;
use CCMBenchmark\Ting\Query\Cached\Query as CachedQuery;
use CCMBenchmark\Ting\Query\PreparedQuery;
use CCMBenchmark\Ting\Query\Query;
use CCMBenchmark\Ting\Query\QueryException;
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Repository\CollectionFactory;
use CCMBenchmark\Ting\Tests\Support\TingServices;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class QueryTest extends TestCase
{
    public function testSetParamsShouldReturnThis()
    {
        $mockConnection = $this->createStub(Connection::class);

        $query = new Query('SELECT', $mockConnection);
        $this->assertSame($query, $query->setParams([]));
    }

    public function testExecuteShouldCallExecuteOnPrimaryDriver()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockConnection = $this->createMock(Connection::class);

        $mockConnection->expects($this->once())->method('primary')->willReturn($mockDriver);
        $mockDriver->expects($this->once())->method('execute')->willReturn(true);

        $query = new Query('INSERT', $mockConnection);
        $query->execute();
    }

    public function testQueryShouldCallExecuteOnReplicaDriver()
    {
        $services              = new TingServices();
        $mockDriver            = $this->createMock(Driver::class);
        $mockConnection        = $this->createMock(Connection::class);
        $mockCollectionFactory = $this->createMock(CollectionFactory::class);

        $collection = new Collection($services->hydrator());

        $mockConnection->expects($this->once())->method('replica')->willReturn($mockDriver);
        $mockDriver->expects($this->once())->method('execute')->willReturn($collection);
        $mockCollectionFactory
            ->expects($this->once())
            ->method('get')
            ->willReturn($collection);

        $query = new Query('SELECT', $mockConnection, $mockCollectionFactory);
        $query->query();
    }

    public function testQueryShouldCallExecuteOnPrimaryDriver()
    {
        $mockDriver            = $this->createMock(Driver::class);
        $mockConnection        = $this->createMock(Connection::class);
        $mockCollectionFactory = $this->createMock(CollectionFactory::class);

        $mockConnection->expects($this->once())->method('primary')->willReturn($mockDriver);
        $mockDriver->expects($this->once())->method('execute')->willReturn(new Collection());
        $mockCollectionFactory->expects($this->once())->method('get')->willReturn(new Collection());

        $query = new Query('SELECT', $mockConnection, $mockCollectionFactory);
        $query->selectPrimary(true);
        $query->query();
    }

    public function testQueryShouldReturnTheCollectionForAStatementWithoutResultSet()
    {
        // Mysqli\Driver::execute() returns true instead of the collection for a statement without result set
        $driver = $this->createStub(Driver::class);
        $driver->method('execute')->willReturn(true);
        $connection = $this->createStub(Connection::class);
        $connection->method('replica')->willReturn($driver);
        $collection = new Collection();

        $query = new Query('UPDATE Bouh SET id = 3', $connection);

        $this->assertSame($collection, $query->query($collection));
    }

    public function testGetInsertIdShouldCallPrimaryDriver()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockConnection = $this->createMock(Connection::class);

        $mockConnection->expects($this->once())->method('primary')->willReturn($mockDriver);
        $mockDriver->expects($this->once())->method('getInsertedId')->willReturn(1);

        $query = new Query('INSERT', $mockConnection);
        $this->assertSame(1, $query->getInsertedId());
    }

    public function testGetAffectedRowsShouldCallPrimaryDriver()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockConnection = $this->createMock(Connection::class);

        $mockConnection->expects($this->once())->method('primary')->willReturn($mockDriver);
        $mockDriver->expects($this->once())->method('getAffectedRows')->willReturn(4);

        $query = new Query('INSERT', $mockConnection);
        $this->assertSame(4, $query->getAffectedRows());
    }

    public static function queriesWithoutCollectionFactoryProvider(): array
    {
        return [
            'Query' => [Query::class],
            'PreparedQuery' => [PreparedQuery::class],
            'Cached\\Query' => [CachedQuery::class],
            'Cached\\PreparedQuery' => [CachedPreparedQuery::class],
        ];
    }

    /**
     * @param class-string<Query> $class
     */
    #[DataProvider('queriesWithoutCollectionFactoryProvider')]
    public function testQueryWithoutCollectionNorCollectionFactoryShouldRaiseQueryException(string $class)
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('replica');
        $query = new $class('SELECT', $connection);
        if ($query instanceof CachedQuery) {
            $query->setCache(new ArrayAdapter());
            $query->setTtl(10)->setCacheKey('myCacheKey');
        }

        $this->assertThrows(
            QueryException::class,
            fn () => $query->query(),
            'Cannot build the collection of the query: it has no collection factory, give a collection to query()'
        );
    }
}
