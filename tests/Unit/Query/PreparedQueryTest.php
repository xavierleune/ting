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
use CCMBenchmark\Ting\Driver\Mysqli\Statement;
use CCMBenchmark\Ting\Query\PreparedQuery;
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Repository\CollectionFactory;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\Fake\MysqliStatement;

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

        $mockConnection->expects($this->once())->method('primary')->willReturn($mockDriver);
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

        $mockConnection->expects($this->once())->method('primary')->willReturn($mockDriver);
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

    public function testGetStatementNameShouldReturnAString()
    {
        $mockConnection        = $this->createStub(Connection::class);
        $mockCollectionFactory = $this->createStub(CollectionFactory::class);

        $query = new PreparedQuery('SELECT', $mockConnection, $mockCollectionFactory);
        $this->assertIsString($query->getStatementName());
        $this->assertNotEmpty($query->getStatementName());
    }
}
