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
use CCMBenchmark\Ting\Driver\DriverInterface;
use CCMBenchmark\Ting\Driver\Mysqli\Driver;
use CCMBenchmark\Ting\Tests\Support\TestCase;

class ConnectionTest extends TestCase
{
    public function testPrimaryShouldReturnPrimaryDriver()
    {
        $mockDriver = $this->createStub(DriverInterface::class);
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('primary')->willReturn($mockDriver);

        $connection = new Connection($mockConnectionPool, 'main', 'db');
        $this->assertSame($mockDriver, $connection->primary());
    }

    public function testReplicaShouldReturnReplicaDriver()
    {
        $mockDriver = $this->createStub(DriverInterface::class);
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('replica')->willReturn($mockDriver);

        $connection = new Connection($mockConnectionPool, 'main', 'db');
        $this->assertSame($mockDriver, $connection->replica());
    }

    public function testStartTransactionShouldCallPrimaryStartTransaction()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockDriver->expects($this->once())->method('startTransaction');
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('primary')->willReturn($mockDriver);

        $connection = new Connection($mockConnectionPool, 'main', 'db');
        $connection->startTransaction();
    }

    public function testRollbackShouldCallPrimaryRollback()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockDriver->expects($this->once())->method('rollback');
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('primary')->willReturn($mockDriver);

        $connection = new Connection($mockConnectionPool, 'main', 'db');
        $connection->rollback();
    }

    public function testCommitShouldCallPrimaryCommit()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockDriver->expects($this->once())->method('commit');
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('primary')->willReturn($mockDriver);

        $connection = new Connection($mockConnectionPool, 'main', 'db');
        $connection->commit();
    }

    public function testGetNameShouldReturnTheNameOfTheConnection()
    {
        $connection = new Connection($this->createStub(ConnectionPool::class), 'main', 'db');

        $this->assertSame('main', $connection->getName());
    }
}
