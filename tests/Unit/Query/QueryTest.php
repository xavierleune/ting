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
use CCMBenchmark\Ting\Query\Query;
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Repository\CollectionFactory;
use CCMBenchmark\Ting\Services;
use CCMBenchmark\Ting\Tests\Support\TestCase;

class QueryTest extends TestCase
{
    public function testSetParamsShouldReturnThis()
    {
        $mockConnection = $this->createStub(Connection::class);

        $query = new Query('SELECT', $mockConnection);
        $this->assertSame($query, $query->setParams([]));
    }

    public function testExecuteShouldCallExecuteOnMasterDriver()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockConnection = $this->createMock(Connection::class);

        $mockConnection->expects($this->once())->method('master')->willReturn($mockDriver);
        $mockDriver->expects($this->once())->method('execute')->willReturn(true);

        $query = new Query('INSERT', $mockConnection);
        $query->execute();
    }

    public function testQueryShouldCallExecuteOnSlaveDriver()
    {
        $services              = new Services();
        $mockDriver            = $this->createMock(Driver::class);
        $mockConnection        = $this->createMock(Connection::class);
        $mockCollectionFactory = $this->createMock(CollectionFactory::class);

        $mockConnection->expects($this->once())->method('slave')->willReturn($mockDriver);
        $mockDriver->expects($this->once())->method('execute')->willReturn(true);
        $mockCollectionFactory
            ->expects($this->once())
            ->method('get')
            ->willReturn(new Collection($services->get('Hydrator')));

        $query = new Query('SELECT', $mockConnection, $mockCollectionFactory);
        $query->query();
    }

    public function testQueryShouldCallExecuteOnMasterDriver()
    {
        $mockDriver            = $this->createMock(Driver::class);
        $mockConnection        = $this->createMock(Connection::class);
        $mockCollectionFactory = $this->createMock(CollectionFactory::class);

        $mockConnection->expects($this->once())->method('master')->willReturn($mockDriver);
        $mockDriver->expects($this->once())->method('execute')->willReturn(true);
        $mockCollectionFactory->expects($this->once())->method('get')->willReturn(new Collection());

        $query = new Query('SELECT', $mockConnection, $mockCollectionFactory);
        $query->selectMaster(true);
        $query->query();
    }

    public function testGetInsertIdShouldCallMasterDriver()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockConnection = $this->createMock(Connection::class);

        $mockConnection->expects($this->once())->method('master')->willReturn($mockDriver);
        $mockDriver->expects($this->once())->method('getInsertedId')->willReturn(1);

        $query = new Query('INSERT', $mockConnection);
        $this->assertSame(1, $query->getInsertedId());
    }

    public function testGetAffectedRowsShouldCallMasterDriver()
    {
        $mockDriver = $this->createMock(Driver::class);
        $mockConnection = $this->createMock(Connection::class);

        $mockConnection->expects($this->once())->method('master')->willReturn($mockDriver);
        $mockDriver->expects($this->once())->method('getAffectedRows')->willReturn(4);

        $query = new Query('INSERT', $mockConnection);
        $this->assertSame(4, $query->getAffectedRows());
    }
}
