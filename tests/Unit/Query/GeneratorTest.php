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
use CCMBenchmark\Ting\Query\Generator;
use CCMBenchmark\Ting\Query\PreparedQuery;
use CCMBenchmark\Ting\Query\Query;
use CCMBenchmark\Ting\Query\QueryFactory;
use CCMBenchmark\Ting\Repository\CollectionFactoryInterface;
use CCMBenchmark\Ting\Services;
use CCMBenchmark\Ting\Tests\Support\TestCase;

class GeneratorTest extends TestCase
{
    protected Connection $mockConnection;
    protected QueryFactory $mockQueryFactory;

    protected function setUp(): void
    {
        // The driver is never configured: its real code (escapeField) builds the SQL
        $mockDriver = new Driver();

        // Counts calls to master() and slave(), which both return the driver
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $this->mockConnection = new class ($mockConnectionPool, 'main', 'db', $mockDriver) extends Connection {
            /** @var array<string, int> */
            public array $calls = ['master' => 0, 'slave' => 0];

            public function __construct(
                ConnectionPoolInterface $connectionPool,
                $name,
                $database,
                private readonly DriverInterface $driver
            ) {
                parent::__construct($connectionPool, $name, $database);
            }

            public function master(): DriverInterface
            {
                $this->calls['master']++;
                return $this->driver;
            }

            public function slave(): DriverInterface
            {
                $this->calls['slave']++;
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
        $services = new Services();

        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'population']
        );
        $this->assertInstanceOf(
            Query::class,
            $generator->getOneByCriteria(['id' => 1], $services->get('CollectionFactory'))
        );
        $this->assertSame(1, $this->mockConnection->calls['slave']);
        $this->assertInstanceOf(
            Query::class,
            $generator->getOneByCriteria(['id' => 1], $services->get('CollectionFactory'), true)
        );
        $this->assertSame(1, $this->mockConnection->calls['master']);
    }

    public function testGetAllShouldReturnAQuery()
    {
        $services = new Services();

        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'population']
        );
        $this->assertInstanceOf(Query::class, $generator->getAll($services->get('CollectionFactory'), true));
        $this->assertSame(1, $this->mockConnection->calls['master']);
    }

    public function testGetByCriteriaWithArrayValueShouldReturnAQuery()
    {
        $services = new Services();

        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'population']
        );
        $this->assertInstanceOf(
            Query::class,
            $generator->getByCriteria(['name' => ['Xavier', 'Olivier']], $services->get('CollectionFactory'), true)
        );
        $this->assertSame(1, $this->mockConnection->calls['master']);
    }

    public function testGetByCriteriaWithNullValueShouldReturnAQuery()
    {
        $services = new Services();

        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'population']
        );
        $this->assertInstanceOf(
            Query::class,
            $generator->getByCriteria(['name' => null], $services->get('CollectionFactory'))
        );
        // atoum withAtLeastArguments: only the first argument is checked, loosely (==)
        $matchingCalls = array_filter(
            $this->mockQueryFactory->getCalls,
            fn (array $args) => $args[0] == 'SELECT `id`, `population` FROM `table` WHERE name IS NULL'
        );
        $this->assertCount(1, $matchingCalls);
    }

    public function testGetByCriteriaShouldReturnAQuery()
    {
        $services = new Services();

        $generator = new Generator(
            $this->mockConnection,
            $this->mockQueryFactory,
            '',
            'table',
            ['id', 'population']
        );
        $this->assertInstanceOf(
            Query::class,
            $generator->getByCriteria(['name' => 'Xavier'], $services->get('CollectionFactory'), true)
        );
        $this->assertSame(1, $this->mockConnection->calls['master']);
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
        $services = new Services();

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
                $services->get('CollectionFactory'),
                true,
                ['name' => 'ASC']
            )
        );
        $this->assertSame(1, $this->mockConnection->calls['master']);
    }

    public function testGetByCriteriaWithArrayValueAndOrderLimitShouldReturnAQuery()
    {
        $services = new Services();

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
                $services->get('CollectionFactory'),
                true,
                ['name' => 'ASC'],
                1
            )
        );
        $this->assertSame(1, $this->mockConnection->calls['master']);
    }
}
