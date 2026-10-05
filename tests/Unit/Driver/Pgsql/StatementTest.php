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

namespace CCMBenchmark\Ting\Tests\Unit\Driver\Pgsql;

use CCMBenchmark\Ting\Driver\Pgsql\Result;
use CCMBenchmark\Ting\Driver\Pgsql\Statement;
use CCMBenchmark\Ting\Driver\QueryException;
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Tests\Support\NativeFunctionMock;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\FakeLogger\FakeDriverLogger;

class StatementTest extends TestCase
{
    public function testExecuteShouldCallTheRightConnection()
    {
        NativeFunctionMock::override(
            'pg_execute',
            function ($connection, $statementName, $values) use (&$outerConnection) {
                $outerConnection = $connection;
                return [];
            }
        );

        NativeFunctionMock::override('pg_num_fields', 0);
        NativeFunctionMock::override('pg_field_table', 'Bouh');
        NativeFunctionMock::override('pg_result_seek', 0);
        NativeFunctionMock::override('pg_fetch_array', false);
        NativeFunctionMock::override('pg_query', true);

        // The atoum mock overrode nothing: the real Collection is used
        $collection = new Collection();

        $statement = new Statement(
            'MyStatementName',
            [],
            'connectionName',
            'database'
        );
        $statement->setConnection('Awesome connection resource');
        $statement->setQuery('SELECT firstname FROM Bouh');
        $statement->execute([], $collection);

        $this->assertSame('Awesome connection resource', $outerConnection);
    }

    public function testExecuteShouldCallDriverExecuteWithParameters()
    {
        NativeFunctionMock::override('pg_num_fields', 0);
        NativeFunctionMock::override('pg_field_table', 'Bouh');
        NativeFunctionMock::override(
            'pg_execute',
            function ($connection, $statementName, $values) use (&$outerValues) {
                $outerValues = $values;
                return [];
            }
        );
        NativeFunctionMock::override('pg_result_seek', 0);
        NativeFunctionMock::override('pg_fetch_array', false);
        NativeFunctionMock::override('pg_query', true);

        // The atoum mock overrode nothing: the real Collection is used
        $collection      = new Collection();
        $params          = [
            'firstname'   => 'Sylvain',
            'id'          => 3,
            'old'         => 32.1,
            'description' => 'A very long description',
            'date' => '2014-03-01 14:02:05'
        ];

        $paramsOrder = ['firstname' => null, 'id' => null, 'description' => null, 'old' => null, 'date' => null];

        $statement = new Statement(
            'MyStatementName',
            $paramsOrder,
            'connectionName',
            'database'
        );
        $statement->setQuery('SELECT firstname FROM Bouh');
        $statement->execute($params, $collection);

        $this->assertSame(['Sylvain', 3, 'A very long description', 32.1, '2014-03-01 14:02:05'], $outerValues);
    }

    public function testSetCollectionWithResult()
    {
        $collection = $this->createMock(Collection::class);
        $result     = new Result();
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult([
            [
                'prenom' => 'Sylvain',
                'nom'    => 'Robez-Masson'
            ],
            [
                'prenom' => 'Xavier',
                'nom'    => 'Leune'
            ]
        ]);
        NativeFunctionMock::override('pg_query', true);

        $collection
            ->expects($this->once())
            ->method('set')
            ->willReturnCallback(function ($result) use (&$outerResult): void {
                $outerResult = $result;
            });

        NativeFunctionMock::override('pg_num_fields', 2);
        NativeFunctionMock::override('pg_field_table', 'Bouh');
        NativeFunctionMock::override('pg_field_name', fn ($result, $index) => match ($index) {
            0 => 'prenom',
            1 => 'nom',
            default => false,
        });

        $resultOk = new Result($result);
        $resultOk->setConnectionName('connectionName');
        $resultOk->setDatabase('database');
        $resultOk->setResult($result);
        $resultOk->setQuery('SELECT prenom, nom FROM Bouh');

        $statement = new Statement(
            'MyStatementName',
            [],
            'connectionName',
            'database'
        );
        $statement->setQuery('SELECT prenom, nom FROM Bouh');
        $statement->setCollectionWithResult($result, $collection);

        // atoum isCloneOf: equal but not the same instance
        $this->assertEquals($resultOk, $outerResult);
        $this->assertNotSame($resultOk, $outerResult);
    }

    public function testExecuteShouldRaiseQueryException()
    {
        // The atoum mock overrode nothing and is not used by execute()
        $collection = new Collection();
        NativeFunctionMock::override('pg_execute', false);
        NativeFunctionMock::override('pg_query', true);
        NativeFunctionMock::override('pg_last_error', 'unknown error');

        $statement = new Statement(
            'MyStatementName',
            [],
            'connectionName',
            'database'
        );

        $this->assertThrows(QueryException::class, function () use ($statement, $collection): void {
            $statement->execute([]);
        }, 'unknown error');
    }

    public function testExecuteShouldRaiseExceptionIfValueNotDefined()
    {
        NativeFunctionMock::override('pg_execute', true);
        NativeFunctionMock::override('pg_query', true);

        $statement = new Statement('MyStatementName', ['id' => 1], 'connectionName', 'database');

        $this->assertThrows(QueryException::class, function () use ($statement): void {
            $statement->execute([]);
        }, 'Value has not been set for param id');
    }

    public function testDestructWithoutConnectionShouldNotQuery()
    {
        $calls = 0;
        NativeFunctionMock::override('pg_query', function () use (&$calls): bool {
            $calls++;

            return true;
        });

        $statement = new Statement('MyStatementName', [], 'connectionName', 'database');
        unset($statement);

        $this->assertSame(0, $calls);
    }

    public function testDestructWithAClosedConnectionShouldNotThrow()
    {
        // pg_query() on a closed PgSql\Connection throws an Error
        NativeFunctionMock::override('pg_query', function (): never {
            throw new \Error('PostgreSQL connection has already been closed');
        });

        $thrown = null;
        try {
            $statement = new Statement('MyStatementName', [], 'connectionName', 'database');
            $statement->setConnection('closed connection');
            $statement = null; // runs the destructor
        } catch (\Throwable $throwable) {
            $thrown = $throwable;
        }

        $this->assertNull($thrown);
    }

    public function testExecuteShouldReturnTrueIfNoError()
    {
        NativeFunctionMock::override('pg_execute', true);
        NativeFunctionMock::override('pg_query', true);

        $statement = new Statement(
            'MyStatementName',
            [],
            'connectionName',
            'database'
        );

        $this->assertTrue($statement->execute([]));
    }

    public function testExecuteShouldLogQuery()
    {
        NativeFunctionMock::override('pg_execute', []);
        NativeFunctionMock::override('pg_result_seek', 0);
        NativeFunctionMock::override('pg_fetch_array', false);
        NativeFunctionMock::override('pg_query', true);

        $mockLogger = $this->createMock(FakeDriverLogger::class);
        $mockLogger->expects($this->once())->method('startStatementExecute');
        $mockLogger->expects($this->once())->method('stopStatementExecute');

        $statement = new Statement(
            'statementNameTest',
            [],
            'connectionName',
            'database'
        );
        $statement->setLogger($mockLogger);
        $statement->setQuery('SELECT firstname FROM Bouh');
        $statement->execute([]);
    }
}
