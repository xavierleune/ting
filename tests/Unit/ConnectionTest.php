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
use CCMBenchmark\Ting\ConnectionPoolInterface;
use CCMBenchmark\Ting\Driver\Mysqli\Driver;
use CCMBenchmark\Ting\Logger\DriverLoggerInterface;
use CCMBenchmark\Ting\Tests\Support\TestCase;

class ConnectionTest extends TestCase
{
    public function testPrimaryShouldReturnPrimaryDriver()
    {
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('primary')->willReturn('primary');

        $connection = new Connection($mockConnectionPool, 'main', 'db');
        $this->assertSame('primary', $connection->primary());
    }

    public function testReplicaShouldReturnReplicaDriver()
    {
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('replica')->willReturn('replica');

        $connection = new Connection($mockConnectionPool, 'main', 'db');
        $this->assertSame('replica', $connection->replica());
    }

    public function testStartTransactionShouldCallPrimaryStartTransaction()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockDriver->expects($this->once())->method('startTransaction')->willReturn(true);
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('primary')->willReturn($mockDriver);

        $connection = new Connection($mockConnectionPool, 'main', 'db');
        $connection->startTransaction();
    }

    public function testRollbackShouldCallPrimaryRollback()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockDriver->expects($this->once())->method('rollback')->willReturn(true);
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('primary')->willReturn($mockDriver);

        $connection = new Connection($mockConnectionPool, 'main', 'db');
        $connection->rollback();
    }

    public function testCommitShouldCallPrimaryCommit()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockDriver->expects($this->once())->method('commit')->willReturn(true);
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('primary')->willReturn($mockDriver);

        $connection = new Connection($mockConnectionPool, 'main', 'db');
        $connection->commit();
    }

    public function testDeprecatedMasterShouldReturnPrimaryDriverAndTriggerADeprecation()
    {
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('primary')->willReturn('primary');
        $connection = new Connection($mockConnectionPool, 'main', 'db');

        $driver       = null;
        $deprecations = $this->collectDeprecations(function () use ($connection, &$driver): void {
            $driver = $connection->master();
        });

        $this->assertSame('primary', $driver);
        $this->assertSame(
            ['Method "CCMBenchmark\Ting\Connection::master()" is deprecated since Ting 3.14, use "primary()" instead.'],
            $deprecations
        );
    }

    public function testDeprecatedSlaveShouldReturnReplicaDriverAndTriggerADeprecation()
    {
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('replica')->willReturn('replica');
        $connection = new Connection($mockConnectionPool, 'main', 'db');

        $driver       = null;
        $deprecations = $this->collectDeprecations(function () use ($connection, &$driver): void {
            $driver = $connection->slave();
        });

        $this->assertSame('replica', $driver);
        $this->assertSame(
            ['Method "CCMBenchmark\Ting\Connection::slave()" is deprecated since Ting 3.14, use "replica()" instead.'],
            $deprecations
        );
    }

    public function testPrimaryAndReplicaShouldNotTriggerDeprecations()
    {
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('primary')->willReturn('primary');
        $mockConnectionPool->method('replica')->willReturn('replica');
        $connection = new Connection($mockConnectionPool, 'main', 'db');

        $drivers      = [];
        $deprecations = $this->collectDeprecations(function () use ($connection, &$drivers): void {
            $drivers = [$connection->primary(), $connection->replica()];
        });

        $this->assertSame(['primary', 'replica'], $drivers);
        $this->assertSame([], $deprecations);
    }

    public function testPrimaryAndReplicaShouldWorkWithAPoolOnlyImplementingTheInterface()
    {
        $connectionPool = new class () implements ConnectionPoolInterface {
            public array $calls = [];

            public function __construct(?DriverLoggerInterface $logger = null)
            {
            }

            public function setConfig($config)
            {
            }

            public function master($name, $database)
            {
                $this->calls[] = ['master', $name, $database];

                return 'primary';
            }

            public function slave($name, $database)
            {
                $this->calls[] = ['slave', $name, $database];

                return 'replica';
            }

            public function closeAll()
            {
            }
        };
        $connection = new Connection($connectionPool, 'main', 'db');

        $drivers      = [];
        $deprecations = $this->collectDeprecations(function () use ($connection, &$drivers): void {
            $drivers = [$connection->primary(), $connection->replica()];
        });

        $this->assertSame(['primary', 'replica'], $drivers);
        $this->assertSame([['master', 'main', 'db'], ['slave', 'main', 'db']], $connectionPool->calls);
        $this->assertSame([], $deprecations);
    }
}
