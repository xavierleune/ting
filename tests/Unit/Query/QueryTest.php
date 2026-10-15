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
use CCMBenchmark\Ting\Driver\Mysqli\Result;
use CCMBenchmark\Ting\Driver\Pgsql\Driver as PgsqlDriver;
use CCMBenchmark\Ting\Driver\SphinxQL\Driver as SphinxQLDriver;
use CCMBenchmark\Ting\Query\Cached\PreparedQuery as CachedPreparedQuery;
use CCMBenchmark\Ting\Query\Cached\Query as CachedQuery;
use CCMBenchmark\Ting\Query\PreparedQuery;
use CCMBenchmark\Ting\Query\Query;
use CCMBenchmark\Ting\Query\QueryException;
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Repository\CollectionFactory;
use CCMBenchmark\Ting\Tests\Support\NativeFunctionMock;
use CCMBenchmark\Ting\Tests\Support\TingServices;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use tests\fixtures\FakeDriver\MysqliResult;
use tests\fixtures\ColorsEnum;
use tests\fixtures\Fake\Mysqli;
use tests\fixtures\Fake\Pgsql;
use tests\fixtures\Fake\PgsqlResult;
use tests\fixtures\PriorityEnum;

class QueryTest extends TestCase
{
    public function testSetParamsShouldReturnThis()
    {
        $mockConnection = $this->createStub(Connection::class);

        $query = new Query('SELECT', $mockConnection);
        $this->assertSame($query, $query->setParams([]));
    }

    public function testQueryValueShouldReturnTheFirstColumnOfTheFirstRowOrNull()
    {
        $driver = $this->createStub(Driver::class);
        $rows = [['42', 'other'], ['7', 'next']];
        $fill = function (string $sql, array $params, Collection $collection) use (&$rows) {
            $mysqliResult = new MysqliResult($rows);
            $mysqliResult->setFieldsCallback(fn () => array_map(function (string $name) {
                $field = new \stdClass();
                $field->name = $name;
                $field->orgname = '';
                $field->table = '';
                $field->orgtable = '';
                $field->type = MYSQLI_TYPE_VAR_STRING;

                return $field;
            }, ['total', 'total']));
            $result = new Result();
            $result->setConnectionName('main');
            $result->setDatabase('db');
            $result->setResult($mysqliResult);
            $collection->set($result);

            return $collection;
        };
        $driver->method('execute')->willReturnCallback($fill);
        $connection = $this->createStub(Connection::class);
        $connection->method('replica')->willReturn($driver);

        // Two columns of the same name: the first one
        $query = new Query('SELECT COUNT(*) AS total, MAX(label) AS total FROM t', $connection);
        $this->assertSame('42', $query->queryValue());

        $rows = [];
        $this->assertNull($query->queryValue());
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

    public function testScalarParametersShouldReachTheDriverUnchanged()
    {
        $params = ['id' => 3, 'name' => 'a', 'rate' => 1.5, 'enabled' => true, 'parent' => null];
        $driver = $this->createMock(Driver::class);
        $driver->expects($this->once())->method('execute')
            ->with($this->identicalTo('UPDATE t SET name = :name WHERE id = :id'), $this->identicalTo($params))
            ->willReturn(true);
        $connection = $this->createStub(Connection::class);
        $connection->method('primary')->willReturn($driver);

        (new Query('UPDATE t SET name = :name WHERE id = :id', $connection))->setParams($params)->execute();
    }

    /**
     * @return array<string, array{0: class-string<Driver>}>
     */
    public static function mysqliDriversProvider(): array
    {
        return [
            'Mysqli' => [Driver::class],
            'SphinxQL' => [SphinxQLDriver::class],
        ];
    }

    /**
     * @param class-string<Driver> $driverClass
     */
    #[DataProvider('mysqliDriversProvider')]
    public function testTypedParametersShouldBeQuotedInTheSqlByMysqli(string $driverClass)
    {
        $mysqli = $this->createStub(Mysqli::class);
        $mysqli->method('real_escape_string')->willReturnCallback(fn (string $value) => addslashes($value));
        $mysqli->method('query')->willReturnCallback(function (string $sql) use (&$sent): bool {
            $sent = $sql;

            return true;
        });
        $connection = $this->createStub(Connection::class);
        $connection->method('primary')->willReturn(new $driverClass($mysqli));

        (new Query('UPDATE t SET d = :d, c = :c WHERE id IN (:ids) OR parent_id IN (:ids)', $connection))
            ->setParams([
                'd' => new \DateTimeImmutable('2026-01-02 03:04:05'),
                'c' => ColorsEnum::RED,
                'ids' => [PriorityEnum::LOW, 7, "o'k"],
            ])
            ->execute();

        $this->assertSame(
            "UPDATE t SET d = '2026-01-02 03:04:05', c = 'red' WHERE id IN (1, 7, 'o\\'k') OR parent_id IN (1, 7, 'o\\'k')",
            $sent
        );
    }

    public function testTypedParametersShouldBeBoundByPgsql()
    {
        NativeFunctionMock::override('pg_connect', new Pgsql());
        NativeFunctionMock::override(
            'pg_query_params',
            function ($connection, string $sql, array $values) use (&$sent, &$sentValues): PgsqlResult {
                $sent = $sql;
                $sentValues = $values;

                return new PgsqlResult();
            }
        );
        NativeFunctionMock::override('pg_result_status', \PGSQL_COMMAND_OK);
        $driver = new PgsqlDriver();
        $driver->setDatabase('db');
        $connection = $this->createStub(Connection::class);
        $connection->method('primary')->willReturn($driver);

        (new Query('UPDATE t SET d = :d WHERE id IN (:ids) OR parent_id IN (:ids::int)', $connection))
            ->setParams(['d' => new \DateTime('2026-01-02 03:04:05'), 'ids' => [ColorsEnum::BLUE, 'x']])
            ->execute();

        $this->assertSame('UPDATE t SET d = $1 WHERE id IN ($2, $3) OR parent_id IN ($2, $3::int)', $sent);
        $this->assertSame(['2026-01-02 03:04:05', 'blue', 'x'], $sentValues);
    }

    /**
     * @param class-string<Query> $class
     */
    #[DataProvider('queriesWithoutCollectionFactoryProvider')]
    public function testAParameterThatCannotBeSentShouldFailBeforeReachingTheDatabase(string $class)
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('primary');
        $connection->expects($this->never())->method('replica');
        $query = new $class('SELECT * FROM t WHERE id IN (:ids)', $connection);
        if ($query instanceof CachedQuery) {
            $cache = new ArrayAdapter();
            // A result is cached under the key: the parameters are still checked
            $cache->get('myCacheKey', fn () => []);
            $query->setCache($cache);
            $query->setTtl(10)->setCacheKey('myCacheKey');
        }
        $query->setParams(['ids' => []]);

        $this->assertThrows(QueryException::class, fn () => $query->query(new Collection()));
        $this->assertThrows(QueryException::class, fn () => $query->execute());
    }
}
