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
use CCMBenchmark\Ting\Driver\Mysqli\Driver;
use CCMBenchmark\Ting\Tests\Support\TestCase;

class ConnectionTest extends TestCase
{
    public function testMasterShouldReturnMasterDriver()
    {
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('master')->willReturn('master');

        $connection = new Connection($mockConnectionPool, 'main', 'db');
        $this->assertSame('master', $connection->master());
    }

    public function testSlaveShouldReturnSlaveDriver()
    {
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('slave')->willReturn('slave');

        $connection = new Connection($mockConnectionPool, 'main', 'db');
        $this->assertSame('slave', $connection->slave());
    }

    public function testStartTransactionShouldCallMasterStartTransaction()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockDriver->expects($this->once())->method('startTransaction')->willReturn(true);
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('master')->willReturn($mockDriver);

        $connection = new Connection($mockConnectionPool, 'main', 'db');
        $connection->startTransaction();
    }

    public function testRollbackShouldCallMasterRollback()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockDriver->expects($this->once())->method('rollback')->willReturn(true);
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('master')->willReturn($mockDriver);

        $connection = new Connection($mockConnectionPool, 'main', 'db');
        $connection->rollback();
    }

    public function testCommitShouldCallMasterCommit()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockDriver->expects($this->once())->method('commit')->willReturn(true);
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockConnectionPool->method('master')->willReturn($mockDriver);

        $connection = new Connection($mockConnectionPool, 'main', 'db');
        $connection->commit();
    }
}
