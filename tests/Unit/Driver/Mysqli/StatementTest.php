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

namespace CCMBenchmark\Ting\Tests\Unit\Driver\Mysqli;

use CCMBenchmark\Ting\Driver\Mysqli\Result;
use CCMBenchmark\Ting\Driver\Mysqli\Statement;
use CCMBenchmark\Ting\Driver\QueryException;
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use tests\fixtures\Fake\MysqliStatement;
use tests\fixtures\FakeDriver\MysqliResult;
use tests\fixtures\FakeLogger\FakeDriverLogger;

class StatementTest extends TestCase
{
    public function testExecuteShouldCallDriverStatementBindParams()
    {
        $driverStatement = $this->createMock(MysqliStatement::class);
        $driverStatement->method('close')->willReturn(true);
        $collection      = $this->createStub(Collection::class);
        $params          = [
            'firstname'   => 'Sylvain',
            'id'          => 3,
            'old'         => 32.1,
            'description' => 'A very long description',
            'date'        => '2014-03-01 14:02:05',
            'is_banned'   => false,
        ];
        $paramsOrder = ['firstname', 'id', 'description', 'old', 'date', 'is_banned'];

        // atoum returned a mock of \Iterator, which accepted the undeclared fetch_fields() call (returning null)
        $driverStatement->method('get_result')->willReturn($this->createStub(MysqliResult::class));
        $driverStatement->errno = 0;

        $driverStatement->expects($this->once())
            ->method('bind_param')
            ->with(
                $this->identicalTo('sisdsi'),
                $this->identicalTo('Sylvain'),
                $this->identicalTo(3),
                $this->identicalTo('A very long description'),
                $this->identicalTo(32.1),
                $this->identicalTo('2014-03-01 14:02:05'),
                $this->identicalTo(0)
            );

        $statement = new Statement(
            $driverStatement,
            $paramsOrder,
            'connectionName',
            'database'
        );
        $statement->execute($params, $collection);
    }

    public function testExecuteShouldCallDriverStatementExecute()
    {
        $driverStatement = $this->createMock(MysqliStatement::class);
        $driverStatement->method('close')->willReturn(true);
        $collection      = $this->createStub(Collection::class);

        // atoum returned a mock of \Iterator, which accepted the undeclared fetch_fields() call (returning null)
        $driverStatement->method('get_result')->willReturn($this->createStub(MysqliResult::class));
        $driverStatement->errno = 0;

        $driverStatement->expects($this->once())->method('execute');

        $statement = new Statement(
            $driverStatement,
            [],
            'connectionName',
            'database'
        );
        $statement->execute([], $collection);
    }

    // Partial mock without expectations (atoum mocks are partial)
    #[AllowMockObjectsWithoutExpectations]
    public function testSetCollectionWithResult()
    {
        $driverStatement = $this->createStub(MysqliStatement::class);
        $driverStatement->method('close')->willReturn(true);
        $collection      = $this->createMock(Collection::class);
        // Partial mock: Result uses the real methods of the fake, only fetch_fields is overridden
        $result          = $this->getMockBuilder(MysqliResult::class)
            ->setConstructorArgs([
                [
                    [
                        'prenom' => 'Sylvain',
                        'nom'    => 'Robez-Masson'
                    ],
                    [
                        'prenom' => 'Xavier',
                        'nom'    => 'Leune'
                    ]
                ]
            ])
            ->onlyMethods(['fetch_fields'])
            ->getMock();

        $result->method('fetch_fields')->willReturnCallback(function () {
            $fields = [];
            $stdClass = new \stdClass();
            $stdClass->name     = 'prenom';
            $stdClass->orgname  = 'firstname';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'nom';
            $stdClass->orgname  = 'name';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            return $fields;
        });

        $driverStatement->method('get_result')->willReturn($result);
        $collection->expects($this->once())
            ->method('set')
            ->willReturnCallback(function ($result) use (&$outerResult): void {
                $outerResult = $result;
            });

        $resultReference = new Result();
        $resultReference->setConnectionName('connectionName');
        $resultReference->setDatabase('database');
        $resultReference->setResult($result);

        $statement = new Statement(
            $driverStatement,
            [],
            'connectionName',
            'database'
        );
        $statement->setCollectionWithResult($result, $collection);

        // atoum isCloneOf: equal but not the same instance
        $this->assertEquals($resultReference, $outerResult);
        $this->assertNotSame($resultReference, $outerResult);
    }

    public function testExecuteShouldRaiseQueryExceptionOnError()
    {
        $driverStatement = $this->createStub(MysqliStatement::class);
        $driverStatement->method('close')->willReturn(true);
        $collection      = $this->createStub(Collection::class);

        $driverStatement->errno = 123;
        $driverStatement->error = 'unknown error';
        $driverStatement->method('get_result')->willReturn(false);

        $statement = new Statement(
            $driverStatement,
            [],
            'connectionName',
            'database'
        );

        $this->assertThrows(
            QueryException::class,
            function () use ($statement, $driverStatement, $collection): void {
                $statement->execute([], $collection);
            }
        );
    }

    public function testExecuteShouldReturnTrueIfNoError()
    {
        $driverStatement = $this->createStub(MysqliStatement::class);
        $driverStatement->method('close')->willReturn(true);

        $driverStatement->method('get_result')->willReturn(true);
        $driverStatement->errno = 0;

        $statement = new Statement(
            $driverStatement,
            [],
            'connectionName',
            'database'
        );

        $this->assertTrue($statement->execute([]));
    }

    public function testExecuteShouldLogQuery()
    {
        $driverStatement = $this->createStub(MysqliStatement::class);
        $driverStatement->method('close')->willReturn(true);
        $collection      = $this->createStub(Collection::class);
        $mockLogger      = $this->createMock(FakeDriverLogger::class);

        // atoum returned a mock of \Iterator, which accepted the undeclared fetch_fields() call (returning null)
        $driverStatement->method('get_result')->willReturn($this->createStub(MysqliResult::class));
        $driverStatement->errno = 0;

        $mockLogger->expects($this->once())->method('startStatementExecute');
        $mockLogger->expects($this->once())->method('stopStatementExecute');

        $statement = new Statement(
            $driverStatement,
            [],
            'connectionName',
            'database'
        );
        $statement->setLogger($mockLogger);
        $statement->execute([], $collection);
    }
}
