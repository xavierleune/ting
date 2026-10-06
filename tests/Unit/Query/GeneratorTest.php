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
use CCMBenchmark\Ting\ConnectionPool;
use CCMBenchmark\Ting\ConnectionPoolInterface;
use CCMBenchmark\Ting\Driver\DriverInterface;
use CCMBenchmark\Ting\Driver\Mysqli\Driver;
use CCMBenchmark\Ting\Driver\Pgsql\Driver as PgsqlDriver;
use CCMBenchmark\Ting\Query\Generator;
use CCMBenchmark\Ting\Query\PreparedQuery;
use CCMBenchmark\Ting\Query\Query;
use CCMBenchmark\Ting\Query\QueryFactory;
use CCMBenchmark\Ting\Query\QueryInterface;
use CCMBenchmark\Ting\Repository\CollectionFactoryInterface;
use CCMBenchmark\Ting\Tests\Support\TingServices;
use CCMBenchmark\Ting\Tests\Support\TestCase;

class GeneratorTest extends TestCase
{
    protected Connection $mockConnection;
    protected QueryFactory $mockQueryFactory;

    protected function setUp(): void
    {
        // The driver is never configured: its real code (escapeField) builds the SQL
        $mockDriver = new Driver();

        // Counts calls to primary() and replica(), which both return the driver
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $this->mockConnection = new class ($mockConnectionPool, 'main', 'db', $mockDriver) extends Connection {
            /** @var array<string, int> */
            public array $calls = ['primary' => 0, 'replica' => 0];

            public function __construct(
                ConnectionPoolInterface $connectionPool,
                $name,
                $database,
                private readonly DriverInterface $driver
            ) {
                parent::__construct($connectionPool, $name, $database);
            }

            public function primary(): DriverInterface
            {
                $this->calls['primary']++;
                return $this->driver;
            }

            public function replica(): DriverInterface
            {
                $this->calls['replica']++;
                return $this->driver;
            }
        };

        // Spy: records calls to get() while keeping the real implementation
        $this->mockQueryFactory = new class () extends QueryFactory {
            /** @var list<array> */
            public array $getCalls = [];

            public function get($sql, Connection $connection, ?CollectionFactoryInterface $collectionFactory = null): Query
            {
                $this->getCalls[] = [$sql, $connection, $collectionFactory];
                return parent::get($sql, $connection, $collectionFactory);
            }
        };
    }

    public function testGetByPrimariesShouldReturnAQuery()
    {
        $services = new TingServices();

        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'population']
        );
        $this->assertInstanceOf(
            Query::class,
            $generator->getOneByCriteria(['id' => 1], $services->collectionFactory())
        );
        $this->assertSame(1, $this->mockConnection->calls['replica']);
        $this->assertInstanceOf(
            Query::class,
            $generator->getOneByCriteria(['id' => 1], $services->collectionFactory(), true)
        );
        $this->assertSame(1, $this->mockConnection->calls['primary']);
    }

    public function testGetAllShouldReturnAQuery()
    {
        $services = new TingServices();

        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'population']
        );
        $this->assertInstanceOf(Query::class, $generator->getAll($services->collectionFactory(), true));
        $this->assertSame(1, $this->mockConnection->calls['primary']);
    }

    public function testGetByCriteriaWithArrayValueShouldReturnAQuery()
    {
        $services = new TingServices();

        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'population']
        );
        $this->assertInstanceOf(
            Query::class,
            $generator->getByCriteria(['name' => ['Xavier', 'Olivier']], $services->collectionFactory(), true)
        );
        $this->assertSame(1, $this->mockConnection->calls['primary']);
    }

    public function testGetByCriteriaWithNullValueShouldReturnAQuery()
    {
        $services = new TingServices();

        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'population']
        );
        $this->assertInstanceOf(
            Query::class,
            $generator->getByCriteria(['name' => null], $services->collectionFactory())
        );
        // atoum withAtLeastArguments: only the first argument is checked, loosely (==)
        $matchingCalls = array_filter(
            $this->mockQueryFactory->getCalls,
            fn (array $args) => $args[0] == 'SELECT `id`, `population` FROM `table` WHERE `name` IS NULL'
        );
        $this->assertCount(1, $matchingCalls);
    }

    public function testGetByCriteriaShouldReturnAQuery()
    {
        $services = new TingServices();

        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'population']
        );
        $this->assertInstanceOf(
            Query::class,
            $generator->getByCriteria(['name' => 'Xavier'], $services->collectionFactory(), true)
        );
        $this->assertSame(1, $this->mockConnection->calls['primary']);
    }

    public function testInsertShouldReturnAPreparedQuery()
    {
        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'population']
        );
        $this->assertInstanceOf(PreparedQuery::class, $generator->insert(['id' => 1]));
    }

    public function testUpdateShouldReturnAPreparedQuery()
    {
        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'population']
        );
        $this->assertInstanceOf(
            PreparedQuery::class,
            $generator->update(['id' => [0 => 1, 1 => 2], 'name' => ['oldValue', 'newValue']], ['id' => 1])
        );
    }

    public function testDeleteShouldReturnAPreparedQuery()
    {
        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'population']
        );
        $this->assertInstanceOf(PreparedQuery::class, $generator->delete(['id' => 1]));
    }

    public function testGetByCriteriaWithArrayValueAndOrderShouldReturnAQuery()
    {
        $services = new TingServices();

        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'population']
        );
        $this->assertInstanceOf(
            Query::class,
            $generator->getByCriteria(
                ['name' => ['Xavier', 'Olivier']],
                $services->collectionFactory(),
                true,
                ['name' => 'ASC']
            )
        );
        $this->assertSame(1, $this->mockConnection->calls['primary']);
    }

    public function testGetByCriteriaShouldNotAddAnEmptyOrderClauseWhenEveryDirectionIsIgnored()
    {
        $services = new TingServices();

        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'population']
        );
        $generator->getByCriteria(['name' => 'Xavier'], $services->collectionFactory(), false, ['name' => 'UP'], 5);

        $this->assertSame(
            'SELECT `id`, `population` FROM `table` WHERE `name` = :w1_name LIMIT 5',
            $this->mockQueryFactory->getCalls[0][0]
        );
    }

    public function testGetByCriteriaShouldEscapeTheColumnsOfTheWhereClause()
    {
        $services = new TingServices();

        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'order']
        );
        // "order" is a reserved word: unescaped, the WHERE clause is invalid SQL
        $generator->getByCriteria(['order' => 1, 'id' => [1, 2], 'name' => null], $services->collectionFactory());

        $this->assertSame(
            'SELECT `id`, `order` FROM `table` WHERE `order` = :w1_order AND `id` IN (:w2_id__1,:w2_id__2) AND `name` IS NULL',
            $this->mockQueryFactory->getCalls[0][0]
        );
    }

    public function testGetByCriteriaWithArrayValueAndOrderLimitShouldReturnAQuery()
    {
        $services = new TingServices();

        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'population']
        );
        $this->assertInstanceOf(
            Query::class,
            $generator->getByCriteria(
                ['name' => ['Xavier', 'Olivier']],
                $services->collectionFactory(),
                true,
                ['name' => 'ASC'],
                1
            )
        );
        $this->assertSame(1, $this->mockConnection->calls['primary']);
    }

    /**
     * @return array{0: string, 1: array<string, mixed>} the SQL and the parameters of the query
     */
    private function readQuery(QueryInterface $query): array
    {
        return (fn () => [$this->sql, $this->params])->call($query);
    }

    /**
     * Every placeholder found by the parameter parsing of both drivers is a parameter of the query, and every
     * parameter is a placeholder of the SQL.
     */
    private function assertPlaceholdersMatchParams(string $sql, array $params): void
    {
        $mysqliPattern = (fn () => $this->parameterMatching)->call(new Driver());
        preg_match_all('/' . $mysqliPattern . '/', $sql, $matches);
        $mysqliNames = array_values(array_unique($matches[1]));

        [, $pgsqlOrder] = (fn (string $sql) => $this->convertParameters($sql))->call(new PgsqlDriver(), $sql);
        $pgsqlNames = array_map('strval', array_keys($pgsqlOrder));

        $paramNames = array_map('strval', array_keys($params));
        sort($mysqliNames);
        sort($pgsqlNames);
        sort($paramNames);
        $this->assertSame($paramNames, $mysqliNames, 'Mysqli placeholders');
        $this->assertSame($paramNames, $pgsqlNames, 'Pgsql placeholders');
    }

    public function testGetByCriteriaShouldNameTheParametersOfColumnsWithSpecialCharacters()
    {
        $services = new TingServices();
        $generator = new Generator($this->mockConnection, $this->mockQueryFactory, '', 'table', ['user id']);

        [$sql, $params] = $this->readQuery($generator->getByCriteria(
            ['user id' => 1, 'prénom' => 'Xavier', 'x.y' => ['p', 'q'], 'a-b' => 2],
            $services->collectionFactory()
        ));

        $this->assertSame(
            'SELECT `user id` FROM `table` WHERE `user id` = :w1_user_id AND `prénom` = :w2_pr__nom'
            . ' AND `x.y` IN (:w3_x_y__1,:w3_x_y__2) AND `a-b` = :w4_a_b',
            $sql
        );
        $this->assertSame(
            ['w1_user_id' => 1, 'w2_pr__nom' => 'Xavier', 'w3_x_y__1' => 'p', 'w3_x_y__2' => 'q', 'w4_a_b' => 2],
            $params
        );
        $this->assertPlaceholdersMatchParams($sql, $params);
    }

    public function testGetOneByCriteriaShouldNameTheParametersOfColumnsWithSpecialCharacters()
    {
        $services = new TingServices();
        $generator = new Generator($this->mockConnection, $this->mockQueryFactory, '', 'table', ['user id']);

        [$sql, $params] = $this->readQuery(
            $generator->getOneByCriteria(['user id' => 1], $services->collectionFactory())
        );

        $this->assertSame('SELECT `user id` FROM `table` WHERE `user id` = :w1_user_id LIMIT 1', $sql);
        $this->assertSame(['w1_user_id' => 1], $params);
        $this->assertPlaceholdersMatchParams($sql, $params);
    }

    public function testGetByCriteriaShouldNotMixAnInListWithAColumnNamedLikeItsParameters()
    {
        $services = new TingServices();
        $generator = new Generator($this->mockConnection, $this->mockQueryFactory, '', 'table', ['a', 'a__1']);

        [$sql, $params] = $this->readQuery(
            $generator->getByCriteria(['a' => [10, 20], 'a__1' => 99], $services->collectionFactory())
        );
        $this->assertSame(
            'SELECT `a`, `a__1` FROM `table` WHERE `a` IN (:w1_a__1,:w1_a__2) AND `a__1` = :w2_a__1',
            $sql
        );
        $this->assertSame(['w1_a__1' => 10, 'w1_a__2' => 20, 'w2_a__1' => 99], $params);
        $this->assertPlaceholdersMatchParams($sql, $params);

        [$sql, $params] = $this->readQuery(
            $generator->getByCriteria(['a__1' => [99], 'a' => [10, 20]], $services->collectionFactory())
        );
        $this->assertSame(
            'SELECT `a`, `a__1` FROM `table` WHERE `a__1` IN (:w1_a__1__1) AND `a` IN (:w2_a__1,:w2_a__2)',
            $sql
        );
        $this->assertSame(['w1_a__1__1' => 99, 'w2_a__1' => 10, 'w2_a__2' => 20], $params);
        $this->assertPlaceholdersMatchParams($sql, $params);
    }

    public function testGetByCriteriaShouldNotMixColumnsWhoseSanitizedNamesAreEqual()
    {
        $services = new TingServices();
        $generator = new Generator($this->mockConnection, $this->mockQueryFactory, '', 'table', ['a b', 'a_b']);

        [$sql, $params] = $this->readQuery(
            $generator->getByCriteria(['a b' => 1, 'a_b' => 2], $services->collectionFactory())
        );

        $this->assertSame(['w1_a_b' => 1, 'w2_a_b' => 2], $params);
        $this->assertPlaceholdersMatchParams($sql, $params);
    }

    public function testInsertShouldNameTheParametersOfColumnsWithSpecialCharacters()
    {
        $generator = new Generator($this->mockConnection, $this->mockQueryFactory, '', 'table', []);

        [$sql, $params] = $this->readQuery(
            $generator->insert(['user id' => 1, 'prénom' => 'Xavier', 'x.y' => 'z', '2024' => 7])
        );

        $this->assertSame(
            'INSERT INTO `table` (`user id`, `prénom`, `x.y`, `2024`) VALUES (:v1_user_id, :v2_pr__nom, :v3_x_y, :v4_2024)',
            $sql
        );
        $this->assertSame(['v1_user_id' => 1, 'v2_pr__nom' => 'Xavier', 'v3_x_y' => 'z', 'v4_2024' => 7], $params);
        $this->assertPlaceholdersMatchParams($sql, $params);
    }

    public function testAColumnWithAColonShouldNotBeTakenForAParameter()
    {
        $generator = new Generator($this->mockConnection, $this->mockQueryFactory, '', 'table', ['x :y', 'a::b']);

        [$sql, $params] = $this->readQuery($generator->update(['x :y' => 1, 'a::b' => 2], ['x :y' => 3]));

        $this->assertSame(['v1_x__y' => 1, 'v2_a__b' => 2, 'w1_x__y' => 3], $params);
        $this->assertPlaceholdersMatchParams($sql, $params);
    }

    public function testInsertWithoutValueShouldInsertTheDefaultValuesWithMysql()
    {
        $generator = new Generator($this->mockConnection, $this->mockQueryFactory, '', 'table', ['id']);

        [$sql, $params] = $this->readQuery($generator->insert([]));

        // INSERT INTO ... DEFAULT VALUES is not MySQL syntax
        $this->assertSame('INSERT INTO `table` () VALUES ()', $sql);
        $this->assertSame([], $params);
    }

    public function testInsertWithoutValueShouldInsertTheDefaultValuesWithPostgresql()
    {
        $connectionClass = $this->mockConnection::class;
        $connection = new $connectionClass($this->createStub(ConnectionPool::class), 'main', 'db', new PgsqlDriver());
        $generator = new Generator($connection, $this->mockQueryFactory, 'public', 'table', ['id']);

        [$sql, $params] = $this->readQuery($generator->insert([]));

        // INSERT INTO t () VALUES () is a syntax error in PostgreSQL
        $this->assertSame('INSERT INTO "public"."table" DEFAULT VALUES', $sql);
        $this->assertSame([], $params);
    }

    public function testUpdateShouldNameTheParametersOfColumnsWithSpecialCharacters()
    {
        $generator = new Generator($this->mockConnection, $this->mockQueryFactory, '', 'table', []);

        [$sql, $params] = $this->readQuery(
            $generator->update(['prénom' => 'Xavier', 'user id' => 2], ['user id' => 1, 'x.y' => ['p', 'q']])
        );

        $this->assertSame(
            'UPDATE `table` SET `prénom` = :v1_pr__nom, `user id` = :v2_user_id'
            . ' WHERE `user id` = :w1_user_id AND `x.y` IN (:w2_x_y__1,:w2_x_y__2)',
            $sql
        );
        $this->assertSame(
            [
                'v1_pr__nom' => 'Xavier',
                'v2_user_id' => 2,
                'w1_user_id' => 1,
                'w2_x_y__1' => 'p',
                'w2_x_y__2' => 'q',
            ],
            $params
        );
        $this->assertPlaceholdersMatchParams($sql, $params);
    }

    public function testUpdateShouldKeepTheParameterOfANumericColumn()
    {
        $generator = new Generator($this->mockConnection, $this->mockQueryFactory, '', 'table', []);

        // '2024' is an integer key in a PHP array: array_merge() would renumber it
        [$sql, $params] = $this->readQuery($generator->update(['2024' => 7, 'name' => 'n'], ['id' => 5]));

        $this->assertSame('UPDATE `table` SET `2024` = :v1_2024, `name` = :v2_name WHERE `id` = :w1_id', $sql);
        $this->assertSame(['v1_2024' => 7, 'v2_name' => 'n', 'w1_id' => 5], $params);
        $this->assertPlaceholdersMatchParams($sql, $params);
    }

    public function testDeleteShouldNameTheParametersOfColumnsWithSpecialCharacters()
    {
        $generator = new Generator($this->mockConnection, $this->mockQueryFactory, '', 'table', []);

        [$sql, $params] = $this->readQuery($generator->delete(['user id' => 1, 'prénom' => ['a', 'b'], '2024' => 3]));

        $this->assertSame(
            'DELETE FROM `table` WHERE `user id` = :w1_user_id AND `prénom` IN (:w2_pr__nom__1,:w2_pr__nom__2)'
            . ' AND `2024` = :w3_2024',
            $sql
        );
        $this->assertSame(
            ['w1_user_id' => 1, 'w2_pr__nom__1' => 'a', 'w2_pr__nom__2' => 'b', 'w3_2024' => 3],
            $params
        );
        $this->assertPlaceholdersMatchParams($sql, $params);
    }
}
