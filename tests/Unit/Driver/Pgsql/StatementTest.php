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
use tests\fixtures\Fake\Pgsql;
use tests\fixtures\Fake\PgsqlResult;
use tests\fixtures\FakeLogger\FakeDriverLogger;

class StatementTest extends TestCase
{
    public function testExecuteShouldCallTheRightConnection()
    {
        NativeFunctionMock::override(
            'pg_execute',
            function ($connection, $statementName, $values) use (&$outerConnection) {
                $outerConnection = $connection;
                return new PgsqlResult();
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
        $connection = new Pgsql();
        $statement->setConnection($connection);
        $statement->setQuery('SELECT firstname FROM Bouh');
        $statement->execute([], $collection);

        $this->assertSame($connection, $outerConnection);
    }

    public function testExecuteShouldCallDriverExecuteWithParameters()
    {
        NativeFunctionMock::override('pg_num_fields', 0);
        NativeFunctionMock::override('pg_field_table', 'Bouh');
        NativeFunctionMock::override(
            'pg_execute',
            function ($connection, $statementName, $values) use (&$outerValues) {
                $outerValues = $values;
                return new PgsqlResult();
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
        $statement->setConnection(new Pgsql());
        $statement->setQuery('SELECT firstname FROM Bouh');
        $statement->execute($params, $collection);

        $this->assertSame(['Sylvain', 3, 'A very long description', 32.1, '2014-03-01 14:02:05'], $outerValues);
    }

    /**
     * pg_execute() sends false as '', which PostgreSQL rejects for a boolean (or an integer)
     */
    public function testExecuteShouldSendBooleansAsValuesPostgresqlAccepts()
    {
        NativeFunctionMock::override(
            'pg_execute',
            function ($connection, $statementName, $values) use (&$outerValues) {
                $outerValues = $values;
                return new PgsqlResult();
            }
        );

        $statement = new Statement('MyStatementName', ['no' => null, 'yes' => null], 'connectionName', 'database');
        $statement->setConnection(new Pgsql());
        $statement->execute(['no' => false, 'yes' => true]);

        $this->assertSame(['0', '1'], $outerValues);
    }

    public function testSetCollectionWithResult()
    {
        $collection = $this->createMock(Collection::class);
        $result     = new Result();
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult(new PgsqlResult());
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
        $statement->setConnection(new Pgsql());

        $this->assertThrows(QueryException::class, function () use ($statement, $collection): void {
            $statement->execute([]);
        }, 'unknown error');
    }

    /**
     * With an error handler converting warnings to exceptions (Symfony debug), the warning of pg_execute() must not
     * escape instead of the QueryException, nor skip the end of the log
     */
    public function testAFailedExecuteShouldRaiseQueryExceptionAndStopTheLogEvenWithWarningsConvertedToExceptions()
    {
        NativeFunctionMock::override('pg_execute', static function (): bool {
            trigger_error('pg_execute(): Query failed: ERROR:  division by zero', E_USER_WARNING);

            return false;
        });
        NativeFunctionMock::override('pg_query', true);
        NativeFunctionMock::override('pg_last_error', 'ERROR:  division by zero');

        $mockLogger = $this->createMock(FakeDriverLogger::class);
        $mockLogger->expects($this->once())->method('startStatementExecute');
        $mockLogger->expects($this->once())->method('stopStatementExecute');

        $statement = new Statement('MyStatementName', [], 'connectionName', 'database');
        $statement->setConnection(new Pgsql());
        $statement->setLogger($mockLogger);

        $this->assertThrows(
            QueryException::class,
            fn () => $this->withErrorsAsExceptions(fn () => $statement->execute([])),
            'ERROR:  division by zero'
        );
    }

    public function testAFailedDeallocateShouldNotRaiseAWarning()
    {
        NativeFunctionMock::override('pg_query', static function (): bool {
            trigger_error('pg_query(): Query failed: ERROR:  current transaction is aborted', E_USER_WARNING);

            return false;
        });

        $this->withErrorsAsExceptions(function (): void {
            $statement = new Statement('MyStatementName', [], 'connectionName', 'database');
            $statement->setConnection(new Pgsql());
            $statement = null; // runs the destructor
        });
        $this->addToAssertionCount(1);
    }

    public function testARefusedDeallocateShouldBeReportedToTheHandler()
    {
        $refused = true;
        NativeFunctionMock::override('pg_query', function () use (&$refused): bool {
            return !$refused;
        });
        $reported = [];
        $handler = function (string $statementName) use (&$reported): void {
            $reported[] = $statementName;
        };

        $statement = new Statement('Refused', [], 'connectionName', 'database');
        $statement->setConnection(new Pgsql())->setDeallocationRefusedHandler($handler);
        $statement = null; // runs the destructor

        $refused = false;
        $statement = new Statement('Deallocated', [], 'connectionName', 'database');
        $statement->setConnection(new Pgsql())->setDeallocationRefusedHandler($handler);
        $statement = null;

        $this->assertSame(['Refused'], $reported);
    }

    public function testExecuteShouldRaiseExceptionIfValueNotDefined()
    {
        NativeFunctionMock::override('pg_execute', new PgsqlResult());
        NativeFunctionMock::override('pg_query', true);

        $statement = new Statement('MyStatementName', ['id' => 1], 'connectionName', 'database');
        $statement->setConnection(new Pgsql());

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
            $statement->setConnection(new Pgsql());
            $statement = null; // runs the destructor
        } catch (\Throwable $throwable) {
            $thrown = $throwable;
        }

        $this->assertNull($thrown);
    }

    public function testDetachedStatementShouldNotDeallocateNorExecute()
    {
        $calls = 0;
        NativeFunctionMock::override('pg_query', function () use (&$calls): bool {
            $calls++;

            return true;
        });
        NativeFunctionMock::override('pg_execute', function () use (&$calls): PgsqlResult {
            $calls++;

            return new PgsqlResult();
        });

        $statement = new Statement('MyStatementName', [], 'connectionName', 'database');
        $statement->setConnection(new Pgsql());
        $statement->detach();

        $this->assertThrows(
            QueryException::class,
            function () use ($statement): void {
                $statement->execute([]);
            },
            'The prepared statement MyStatementName is no longer valid: the connection was reset, prepare it again'
        );
        unset($statement);

        // The DEALLOCATE would run on the new session, where a statement of the same name may have been prepared
        $this->assertSame(0, $calls);
    }

    public function testADetachedStatementShouldBeStale()
    {
        NativeFunctionMock::override('pg_query', true);
        $statement = new Statement('MyStatementName', [], 'connectionName', 'database');
        $statement->setConnection(new Pgsql());

        $this->assertFalse($statement->isStale());
        $statement->detach();
        // A prepared query holding it prepares it again
        $this->assertTrue($statement->isStale());
    }

    public function testExecuteShouldReturnTrueIfNoError()
    {
        NativeFunctionMock::override('pg_execute', new PgsqlResult());
        NativeFunctionMock::override('pg_query', true);

        $statement = new Statement(
            'MyStatementName',
            [],
            'connectionName',
            'database'
        );
        $statement->setConnection(new Pgsql());

        $this->assertTrue($statement->execute([]));
    }

    public function testExecuteShouldLogQuery()
    {
        NativeFunctionMock::override('pg_execute', new PgsqlResult());
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
        $statement->setConnection(new Pgsql());
        $statement->setLogger($mockLogger);
        $statement->setQuery('SELECT firstname FROM Bouh');
        $statement->execute([]);
    }

    public function testExecuteBeforeSetConnectionShouldRaiseQueryException()
    {
        NativeFunctionMock::override('pg_execute', fn () => $this->fail('The statement should not be executed'));
        $statement = new Statement('MyStatementName', [], 'connectionName', 'database');
        $statement->setQuery('SELECT firstname FROM Bouh');

        $this->assertThrows(
            QueryException::class,
            fn () => $statement->execute([]),
            'The prepared statement MyStatementName has no connection: it must be prepared by the driver'
        );
    }

    public function testSetCollectionWithResultBeforeSetQueryShouldRaiseQueryException()
    {
        $statement = new Statement('MyStatementName', [], 'connectionName', 'database');

        $this->assertThrows(
            QueryException::class,
            fn () => $statement->setCollectionWithResult(new PgsqlResult(), new Collection()),
            'The prepared statement MyStatementName has no query: it must be prepared by the driver'
        );
    }
}
