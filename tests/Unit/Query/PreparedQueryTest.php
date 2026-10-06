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
use CCMBenchmark\Ting\ConnectionPoolInterface;
use CCMBenchmark\Ting\Driver\Mysqli\Driver;
use CCMBenchmark\Ting\Driver\Mysqli\Statement;
use CCMBenchmark\Ting\Query\PreparedQuery;
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Repository\CollectionFactory;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use CCMBenchmark\Ting\Tests\Support\TingServices;
use tests\fixtures\Fake\MysqliStatement;
use tests\fixtures\FakeDriver\RecordingDriver;

class PreparedQueryTest extends TestCase
{
    public function testPrepareQueryShouldCallReplicaPrepare()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockConnection = $this->createMock(Connection::class);
        $mockMysqliStatement = $this->createStub(MysqliStatement::class);
        $mockStatement = new Statement($mockMysqliStatement, [], 'connectionName', 'database');

        $mockConnection->expects($this->once())->method('replica')->willReturn($mockDriver);
        $mockDriver->expects($this->once())->method('prepare')->willReturn($mockStatement);

        $query = new PreparedQuery('SELECT', $mockConnection);
        $this->assertSame($query, $query->prepareQuery());
    }

    public function testPrepareQueryShouldCallPrimaryPrepare()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockConnection = $this->createMock(Connection::class);
        $mockMysqliStatement = $this->createStub(MysqliStatement::class);
        $mockStatement = new Statement($mockMysqliStatement, [], 'connectionName', 'database');

        // The target driver is looked up on each call, the statement is only prepared once on it
        $mockConnection->expects($this->exactly(2))->method('primary')->willReturn($mockDriver);
        $mockDriver->expects($this->once())->method('prepare')->willReturn($mockStatement);

        $query = new PreparedQuery('SELECT', $mockConnection);
        $query->selectPrimary(true);
        $this->assertSame($query, $query->prepareQuery());
        $this->assertSame($query, $query->prepareQuery());
    }

    public function testPrepareExecuteShouldCallPrimaryPrepare()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockConnection = $this->createMock(Connection::class);
        $mockMysqliStatement = $this->createStub(MysqliStatement::class);
        $mockStatement = new Statement($mockMysqliStatement, [], 'connectionName', 'database');

        $mockConnection->expects($this->exactly(2))->method('primary')->willReturn($mockDriver);
        $mockDriver->expects($this->once())->method('prepare')->willReturn($mockStatement);

        $query = new PreparedQuery('SELECT', $mockConnection);
        $query->selectPrimary(true);
        $this->assertSame($query, $query->prepareExecute());
        $this->assertSame($query, $query->prepareExecute());
    }

    public function testExecuteShouldCallStatementExecute()
    {
        $mockDriver = $this->createStub(Driver::class);
        $mockConnection = $this->createStub(Connection::class);
        $mockMysqliStatement = $this->createStub(MysqliStatement::class);
        $mockStatement = $this->getMockBuilder(Statement::class)
            ->setConstructorArgs([$mockMysqliStatement, [], 'connectionName', 'database'])
            ->onlyMethods(['execute'])
            ->getMock();

        $mockStatement->expects($this->once())->method('execute')->willReturn(true);
        $mockDriver->method('prepare')->willReturn($mockStatement);
        $mockConnection->method('primary')->willReturn($mockDriver);

        $query = new PreparedQuery('SELECT', $mockConnection);
        $this->assertSame($query, $query->prepareExecute());
        $query->setParams(['id' => 12]);
        $query->execute();
    }

    public function testQueryShouldCallStatementExecuteAndReturnCollection()
    {
        $mockDriver            = $this->createStub(Driver::class);
        $mockConnection        = $this->createStub(Connection::class);
        $mockMysqliStatement   = $this->createStub(MysqliStatement::class);
        $mockStatement         = $this->getMockBuilder(Statement::class)
            ->setConstructorArgs([$mockMysqliStatement, [], 'connectionName', 'database'])
            ->onlyMethods(['execute'])
            ->getMock();
        $mockCollectionFactory = $this->createStub(CollectionFactory::class);

        $collection = new Collection();

        $mockStatement->expects($this->once())->method('execute')->willReturn($collection);
        $mockDriver->method('prepare')->willReturn($mockStatement);
        $mockConnection->method('replica')->willReturn($mockDriver);
        $mockCollectionFactory->method('get')->willReturn($collection);

        $query = new PreparedQuery('SELECT', $mockConnection, $mockCollectionFactory);
        $this->assertSame($query, $query->prepareQuery());
        $query->setParams(['id' => 12]);
        $this->assertSame($collection, $query->query());
    }

    public function testExecuteAfterQueryShouldPrepareAgainOnThePrimary()
    {
        $log = new \ArrayObject();
        $query = new PreparedQuery('UPDATE t SET a = 1', $this->connectionWithReplica($log), (new TingServices())->collectionFactory());

        $query->query();
        $query->execute();
        $query->execute();

        $this->assertSame(
            ['prepare on replica', 'execute on replica', 'prepare on primary', 'execute on primary', 'execute on primary'],
            $log->getArrayCopy()
        );
    }

    public function testSelectPrimaryAfterQueryShouldPrepareAgainOnThePrimary()
    {
        $log = new \ArrayObject();
        $query = new PreparedQuery('SELECT 1', $this->connectionWithReplica($log), (new TingServices())->collectionFactory());

        $query->query();
        $query->selectPrimary(true)->query();
        $query->selectPrimary(false)->query();

        $this->assertSame(
            [
                'prepare on replica', 'execute on replica',
                'prepare on primary', 'execute on primary',
                'prepare on replica', 'execute on replica',
            ],
            $log->getArrayCopy()
        );
    }

    public function testQueryAfterExecuteShouldPrepareAgainOnTheReplica()
    {
        $log = new \ArrayObject();
        $query = new PreparedQuery('SELECT 1', $this->connectionWithReplica($log), (new TingServices())->collectionFactory());

        $query->execute();
        $query->query();

        $this->assertSame(
            ['prepare on primary', 'execute on primary', 'prepare on replica', 'execute on replica'],
            $log->getArrayCopy()
        );
    }

    public function testQueryThenExecuteWithoutReplicaShouldPrepareOnce()
    {
        $log = new \ArrayObject();
        $primary = new RecordingDriver('primary', $log);
        $pool = $this->createStub(ConnectionPoolInterface::class);
        $pool->method('primary')->willReturn($primary);
        $pool->method('replica')->willReturn($primary);
        $query = new PreparedQuery('SELECT 1', new Connection($pool, 'main', 'db'), (new TingServices())->collectionFactory());

        $query->query();
        $query->execute();
        $query->selectPrimary(true)->query();

        $this->assertSame(
            ['prepare on primary', 'execute on primary', 'execute on primary', 'execute on primary'],
            $log->getArrayCopy()
        );
    }

    /**
     * @param \ArrayObject<int, string> $log
     */
    private function connectionWithReplica(\ArrayObject $log): Connection
    {
        $pool = $this->createStub(ConnectionPoolInterface::class);
        $pool->method('primary')->willReturn(new RecordingDriver('primary', $log));
        $pool->method('replica')->willReturn(new RecordingDriver('replica', $log));

        return new Connection($pool, 'main', 'db');
    }

    public function testGetStatementNameShouldReturnAString()
    {
        $mockConnection        = $this->createStub(Connection::class);
        $mockCollectionFactory = $this->createStub(CollectionFactory::class);

        $query = new PreparedQuery('SELECT', $mockConnection, $mockCollectionFactory);
        $this->assertIsString($query->getStatementName());
        $this->assertNotEmpty($query->getStatementName());
    }
}
