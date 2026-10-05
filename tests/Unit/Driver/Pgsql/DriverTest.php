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

use CCMBenchmark\Ting\Driver\DriverInterface;
use CCMBenchmark\Ting\Driver\Exception;
use CCMBenchmark\Ting\Driver\NeverConnectedException;
use CCMBenchmark\Ting\Driver\Pgsql\Driver;
use CCMBenchmark\Ting\Driver\QueryException;
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Driver\Pgsql\Statement;
use CCMBenchmark\Ting\Exceptions\DriverException;
use CCMBenchmark\Ting\Exceptions\TransactionException;
use CCMBenchmark\Ting\Tests\Support\NativeFunctionMock;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\Fake\Pgsql;
use tests\fixtures\FakeLogger\FakeDriverLogger;

class DriverTest extends TestCase
{
    public function testGetConnectionKeyShouldBeIdempotent()
    {
        $connectionConfig = ['host' => '127.0.0.1', 'user' => 'app_read', 'password' => 'pzefgdfg', 'port' => 3306];
        $driver = new Driver();

        $key = $driver->getConnectionKey($connectionConfig, 'myDatabase');
        $this->assertIsString($key);
        $this->assertSame($driver->getConnectionKey($connectionConfig, 'myDatabase'), $key);
        $this->assertSame($driver->getConnectionKey($connectionConfig, 'myDatabase'), $key);
    }

    public function testShouldImplementDriverInterface()
    {
        $this->assertInstanceOf(DriverInterface::class, new Driver());
    }

    public function testConnectShouldReturnSelf()
    {
        $driver = new Driver();

        $this->assertSame($driver, $driver->connect('hostname.test', 'user.test', 'password.test', 1234));
    }

    public function testConnectWithoutUserNorPasswordShouldLeaveThemOutOfTheDsn()
    {
        NativeFunctionMock::override('pg_connect', function ($dsn) use (&$outerDsn) {
            $outerDsn = $dsn;

            return false;
        });

        $driver = new Driver();
        $driver->connect('hostname.test', null, null, 1234);
        try {
            $driver->setDatabase('bouh');
        } catch (DriverException) {
        }

        // libpq then uses its defaults (current user, .pgpass)
        $this->assertSame('host=hostname.test port=1234 dbname=bouh', $outerDsn);
    }

    public function testCloseShouldReturnSelf()
    {
        $driver = new Driver();
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);

        $this->assertSame($driver, $driver->close());
    }

    public function testIfNotConnectedCallbackAfterClosedConnection()
    {
        $called = false;

        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_close', true);

        $driver = new Driver();
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setDatabase('database.test');
        $driver->close();
        $driver->ifIsNotConnected(function () use (&$called): void {
            $called = true;
        });

        $this->assertTrue($called);
    }

    public function testSetCharset()
    {
        $mockDriver = new Pgsql();
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override(
            'pg_set_client_encoding',
            function ($connection, $charset) use (&$outerCharset): void {
                $outerCharset = $charset;
            }
        );

        $driver = new Driver($mockDriver);
        $driver->setDatabase('database.test');
        $driver->setCharset('utf8');

        $this->assertSame('utf8', $outerCharset);
    }

    public function testSetCharsetCallingTwiceShouldCallMysqliSetCharsetOnce()
    {
        $mockDriver = new Pgsql();
        $called = 0;
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_set_client_encoding', function () use (&$called): void {
            $called++;
        });

        $driver = new Driver($mockDriver);
        $driver->setDatabase('database.test');
        $driver->setCharset('utf8');
        $driver->setCharset('utf8');

        $this->assertSame(1, $called);
    }

    public function testSetCharsetWithInvalidCharsetShouldThrowAnException()
    {
        $mockDriver = new Pgsql();
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_set_client_encoding', -1);
        NativeFunctionMock::override(
            'pg_last_error',
            'ERROR:  invalid value for parameter "client_encoding": "utf8x"'
        );

        $driver = new Driver($mockDriver);
        $driver->setDatabase('database.test');

        $this->assertThrows(
            \Throwable::class,
            function () use ($driver): void {
                $driver->setCharset('BadCharset');
            },
            'Can\'t set charset BadCharset (ERROR:  invalid value for parameter "client_encoding": "utf8x")'
        );
    }

    public function testSetDatabaseShouldCompleteGeneratedDsnByConnect()
    {
        NativeFunctionMock::override('pg_connect', function ($dsn) use (&$outerDsn): void {
            $outerDsn = $dsn;
        });

        $driver = new Driver();
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setDatabase('database.test');

        $this->assertSame(
            'host=hostname.test user=user.test password=password.test port=1234 dbname=database.test',
            $outerDsn
        );
    }

    public function testSetDatabaseWhenWrongAuthOrPortShouldRaiseDriverException()
    {
        NativeFunctionMock::override('pg_connect', false);

        $driver = new Driver();
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);

        $this->assertThrows(Exception::class, function () use ($driver): void {
            $driver->setDatabase('bouh');
        });
    }

    public function testsetDatabaseWithDatabaseAlreadySetShouldDoNothing()
    {
        $outerCount = 0;
        NativeFunctionMock::override('pg_connect', function ($dsn) use (&$outerCount) {
            $outerCount++;
            return true;
        });

        $driver = new Driver();
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setDatabase('bouh');
        $driver->setDatabase('bouh');

        $this->assertSame(1, $outerCount);
    }

    public function testsetDatabaseShouldReturnSelf()
    {
        NativeFunctionMock::override('pg_connect', true);

        $driver = new Driver();
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);

        $this->assertSame($driver, $driver->setDatabase('bouh'));
    }

    public function testIfNotConnectedShouldCallCallback()
    {
        NativeFunctionMock::override('pg_connect', false);
        $called = false;

        $driver = new Driver();
        $driver->ifIsNotConnected(function () use (&$called): void {
            $called = true;
        });

        $this->assertTrue($called);
    }

    public function testIfIsErrorShouldCallCallable()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_last_error', 'unknown error');

        $called = false;

        $driver = new Driver();
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setDatabase('database.test');
        $driver->ifIsError(function () use (&$called): void {
            $called = true;
        });

        $this->assertTrue($called);
    }

    public function testPrepareShouldRaiseQueryException()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query', true);
        NativeFunctionMock::override('pg_prepare', false);
        NativeFunctionMock::override('pg_last_error', 'unknown error');

        $driver = new Driver();
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setName('foo');
        $driver->setDatabase('database.test');

        $this->assertThrows(QueryException::class, function () use ($driver): void {
            $driver->prepare(
                'SELECT 1 FROM bouh WHERE first = :first AND second = :second'
            );
        });
    }

    public function testPrepareShouldNotTransformEscapedColon()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query', true);
        NativeFunctionMock::override('pg_prepare', function ($resource, $statementName, $sql) use (&$outerSql): void {
            $outerSql = $sql;
        });

        $driver = new Driver();
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setName('foo');
        $driver->setDatabase('myDatabase');
        $driver->prepare(
            'SELECT * FROM T_BOUH_BOO WHERE name = "\:bim"'
        );

        $this->assertSame('SELECT * FROM T_BOUH_BOO WHERE name = ":bim"', $outerSql);
    }

    public function testPrepareShouldHandleMultipleNamedPatternWithSameName()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query', true);
        NativeFunctionMock::override('pg_prepare', function ($resource, $statementName, $sql) use (&$outerSql): void {
            $outerSql = $sql;
        });

        $driver = new Driver();
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setName('foo');
        $driver->setDatabase('myDatabase');
        $driver->prepare(
            'SELECT * FROM T_BOUH_BOO WHERE name = ":name" OR firstname = ":name" OR lastname = ":lastname"'
        );

        $this->assertSame(
            'SELECT * FROM T_BOUH_BOO WHERE name = "$1" OR firstname = "$1" OR lastname = "$2"',
            $outerSql
        );
    }

    public function testEscapeFieldShouldReturnEscapedField()
    {
        NativeFunctionMock::override('pg_connect', true);

        $driver = new Driver();

        $this->assertSame('"Bouh"', $driver->escapeField('Bouh'));
    }

    public function testStartTransactionShouldExecuteQueryBegin()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query', function ($connection, $query) use (&$outerQuery): void {
            $outerQuery = $query;
        });

        $driver = new Driver();
        $driver->setDatabase('myDatabase');
        $driver->startTransaction();

        $this->assertSame('BEGIN', $outerQuery);
    }

    public function testStartTransactionShouldRaiseException()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query', function ($connection, $query) use (&$outerQuery): void {
            $outerQuery = $query;
        });

        $driver = new Driver();
        $driver->setDatabase('myDatabase');
        $driver->startTransaction();

        $this->assertThrows(
            \Throwable::class,
            function () use ($driver): void {
                $driver->startTransaction();
            },
            'Cannot start another transaction'
        );
    }

    public function testCommitShouldExecuteQueryCommit()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query', function ($connection, $query) use (&$outerQuery): void {
            $outerQuery = $query;
        });

        NativeFunctionMock::override('pg_result_status', 'COMMIT');

        $driver = new Driver();
        $driver->setDatabase('myDatabase');
        $driver->startTransaction();
        $driver->commit();

        $this->assertSame('COMMIT', $outerQuery);
    }

    public function testCommitShouldRaiseException()
    {
        NativeFunctionMock::override('pg_query', function ($connection, $query) use (&$outerQuery): void {
            $outerQuery = $query;
        });

        $driver = new Driver();

        $this->assertThrows(
            \Throwable::class,
            function () use ($driver): void {
                $driver->commit();
            },
            'Cannot commit no transaction'
        );
    }

    public function testRollbackShouldExecuteQueryRollback()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query', function ($connection, $query) use (&$outerQuery): void {
            $outerQuery = $query;
        });

        $driver = new Driver();
        $driver->setDatabase('myDatabase');
        $driver->startTransaction();
        $driver->rollback();

        $this->assertSame('ROLLBACK', $outerQuery);
    }

    public function testRollbackShouldRaiseException()
    {
        NativeFunctionMock::override('pg_query', function ($connection, $query) use (&$outerQuery): void {
            $outerQuery = $query;
        });

        $driver = new Driver();

        $this->assertThrows(
            \Throwable::class,
            function () use ($driver): void {
                $driver->rollback();
            },
            'Cannot rollback no transaction'
        );
    }

    public function testStartTransactionFailureShouldRaiseTransactionException()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query', false);
        NativeFunctionMock::override('pg_last_error', 'server closed the connection unexpectedly');

        $driver = new Driver();
        $driver->setDatabase('myDatabase');

        $this->assertThrows(
            TransactionException::class,
            function () use ($driver): void {
                $driver->startTransaction();
            },
            'Cannot start transaction: server closed the connection unexpectedly'
        );

        // No transaction was opened
        $this->assertThrows(TransactionException::class, function () use ($driver): void {
            $driver->commit();
        }, 'Cannot commit no transaction');
    }

    public function testCommitOfAnAbortedTransactionShouldRaiseTransactionException()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query', true);
        // PostgreSQL answers ROLLBACK, without error, to the COMMIT of a transaction aborted by a failed query
        NativeFunctionMock::override('pg_result_status', function ($result, $mode) {
            return $mode === \PGSQL_STATUS_STRING ? 'ROLLBACK' : \PGSQL_COMMAND_OK;
        });

        $driver = new Driver();
        $driver->setDatabase('myDatabase');
        $driver->startTransaction();

        $this->assertThrows(
            TransactionException::class,
            function () use ($driver): void {
                $driver->commit();
            },
            'Cannot commit transaction: the transaction was aborted and has been rolled back'
        );
        // The server rolled the transaction back: none is left open
        $driver->startTransaction();
    }

    public function testCommitFailureShouldRaiseTransactionExceptionAndCloseTheTransaction()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query', fn ($connection, $query) => $query === 'BEGIN');
        NativeFunctionMock::override('pg_last_error', 'server closed the connection unexpectedly');

        $driver = new Driver();
        $driver->setDatabase('myDatabase');
        $driver->startTransaction();

        $this->assertThrows(
            TransactionException::class,
            function () use ($driver): void {
                $driver->commit();
            },
            'Cannot commit transaction: server closed the connection unexpectedly'
        );
        $driver->startTransaction();
    }

    public function testRollbackFailureShouldRaiseTransactionExceptionAndCloseTheTransaction()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query', fn ($connection, $query) => $query === 'BEGIN');
        NativeFunctionMock::override('pg_last_error', 'server closed the connection unexpectedly');

        $driver = new Driver();
        $driver->setDatabase('myDatabase');
        $driver->startTransaction();

        $this->assertThrows(
            TransactionException::class,
            function () use ($driver): void {
                $driver->rollback();
            },
            'Cannot rollback transaction: server closed the connection unexpectedly'
        );
        $driver->startTransaction();
    }

    public function testGetAffectedRowsWithoutResultShouldReturn0()
    {
        NativeFunctionMock::override('pg_affected_rows', 12);
        NativeFunctionMock::override('pg_affected_rows', 12);

        $driver = new Driver();

        $this->assertSame(0, $driver->getAffectedRows());
    }

    public function testgetInsertedIdShouldReturnInsertedId()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query', function ($connection, $query) use (&$outerQuery): void {
            $outerQuery = $query;
        });
        NativeFunctionMock::override('pg_fetch_row', [8]);

        $driver = new Driver();
        $driver->setDatabase('myDatabase');

        $this->assertSame(8, $driver->getInsertedId());
        $this->assertSame('SELECT lastval()', $outerQuery);
    }

    public function testgetInsertedIdForSequenceShouldReturnInsertedIdForSequence()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override(
            'pg_query_params',
            function ($connection, $query, $params) use (&$outerQuery, &$outerParams): void {
                $outerQuery = $query;
                $outerParams = $params;
            }
        );

        NativeFunctionMock::override('pg_fetch_row', [4]);

        $driver = new Driver();
        $driver->setDatabase('myDatabase');

        $this->assertSame(4, $driver->getInsertedIdForSequence('sequenceName'));
        $this->assertSame('SELECT currval($1)', $outerQuery);
        $this->assertSame(['sequenceName'], $outerParams);
    }

    public function testgetInsertedIdForSequenceWithWrongSequenceShouldThrowAnException()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query_params', false);
        NativeFunctionMock::override('pg_last_error', 'A PGSQL error');

        $driver = new Driver();
        $driver->setDatabase('myDatabase');

        $this->assertThrows(
            QueryException::class,
            function () use ($driver): void {
                $driver->getInsertedIdForSequence('sequenceName');
            },
            'A PGSQL error (Query: SELECT currval($1))'
        );
    }

    public function testExecuteShouldCallPGQueryParams()
    {
        NativeFunctionMock::override('pg_connect', true);
        $count = 0;
        $outerSql = '';
        $outerValues = '';
        NativeFunctionMock::override('pg_query_params', function (
            $connection,
            $sql,
            $values
        ) use (
            &$count,
            &$outerSql,
            &$outerValues
        ): void {
            $count++;
            $outerSql = $sql;
            $outerValues = $values;
        });
        NativeFunctionMock::override('pg_result_status', \PGSQL_TUPLES_OK);
        NativeFunctionMock::override('pg_fetch_assoc', null);

        $driver = new Driver();
        $driver->setDatabase('myDatabase');

        $driver->execute('SELECT 1 FROM "myTable" WHERE id = :id', ['id' => 12]);
        $this->assertSame([0 => 12], $outerValues);
        $this->assertSame('SELECT 1 FROM "myTable" WHERE id = $1', $outerSql);
        $this->assertSame(1, $count);

        $driver->execute(
            'INSERT INTO "myTable" (date_field) VALUES (:date)',
            ['date' => '2014-12-31 23:59:59']
        );
        $this->assertSame([0 => '2014-12-31 23:59:59'], $outerValues);
        $this->assertSame('INSERT INTO "myTable" (date_field) VALUES ($1)', $outerSql);
        $this->assertSame(2, $count);
    }

    public function testExecuteShouldRaiseExceptionIfValueNotDefined()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query_params', true);
        NativeFunctionMock::override('pg_result_status', \PGSQL_TUPLES_OK);
        NativeFunctionMock::override('pg_fetch_assoc', null);

        $driver = new Driver();
        $driver->setDatabase('myDatabase');

        $this->assertThrows(QueryException::class, function () use ($driver): void {
            $driver->execute('SELECT 1 FROM "myTable" WHERE id = :id', []);
        }, 'Value has not been set for param id');
    }

    public function testExecuteWithoutParametersShouldCallPGQuery()
    {
        NativeFunctionMock::override('pg_connect', true);
        $pgQueryCalled = false;
        NativeFunctionMock::override('pg_query', function () use (&$pgQueryCalled): void {
            $pgQueryCalled = true;
        });

        NativeFunctionMock::override('pg_result_status', \PGSQL_TUPLES_OK);
        NativeFunctionMock::override('pg_fetch_assoc', null);

        $driver = new Driver();
        $driver->setDatabase('myDatabase');
        $driver->execute('SELECT 1 FROM "myTable"');

        $this->assertTrue($pgQueryCalled);
    }

    public function testExecuteShouldCallSetOnCollection()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query_params', true);
        NativeFunctionMock::override('pg_fetch_array', 'data');
        NativeFunctionMock::override('pg_result_seek', true);
        NativeFunctionMock::override('pg_num_fields', 1);
        NativeFunctionMock::override('pg_field_table', 'myTable');
        NativeFunctionMock::override('pg_field_name', '1');

        $mockCollection = $this->createMock(Collection::class);
        $mockCollection->expects($this->once())->method('set');

        $driver = new Driver();
        $driver->setName('foo');
        $driver->setDatabase('myDatabase');
        $driver->execute('SELECT 1 FROM myTable WHERE id = :id', ['id' => 12], $mockCollection);
    }

    public function testDriverWithoutNameShouldExecuteAndPrepare()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query_params', true);
        NativeFunctionMock::override('pg_prepare', true);
        NativeFunctionMock::override('pg_query', true);
        NativeFunctionMock::override('pg_fetch_array', 'data');
        NativeFunctionMock::override('pg_result_seek', true);
        NativeFunctionMock::override('pg_num_fields', 1);
        NativeFunctionMock::override('pg_field_table', 'myTable');
        NativeFunctionMock::override('pg_field_name', '1');

        // Used directly, without ConnectionPool (which always sets the name)
        $driver = new Driver();
        $driver->setDatabase('myDatabase');

        $this->assertInstanceOf(
            Collection::class,
            $driver->execute('SELECT 1 FROM myTable WHERE id = :id', ['id' => 12], new Collection())
        );
        $this->assertInstanceOf(Statement::class, $driver->prepare('SELECT 1'));
    }

    public function testExecuteShouldReturnArray()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query_params', true);
        NativeFunctionMock::override('pg_fetch_assoc', ['Bouh' => 'Hop']);
        NativeFunctionMock::override('pg_result_status', \PGSQL_TUPLES_OK);

        $driver = new Driver();
        $driver->setDatabase('myDatabase');

        $this->assertSame(
            ['Bouh' => 'Hop'],
            $driver->execute('SELECT 1 FROM myTable WHERE id = :id', ['id' => 12])
        );
    }

    public function testExecuteShouldOnlyReplaceParameters()
    {
        $outerSql = true;
        $outerValues = [];
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override(
            'pg_query_params',
            function ($connection, $sql, $values) use (&$outerSql, &$outerValues): void {
                $outerSql = $sql;
                $outerValues = $values;
            }
        );
        NativeFunctionMock::override('pg_fetch_assoc', ['Bouh' => 'Hop']);
        NativeFunctionMock::override('pg_result_status', \PGSQL_TUPLES_OK);

        $driver = new Driver();
        $driver->setDatabase('myDatabase');
        $driver->execute(
            "SELECT 'Bouh:Ting', ' ::Ting', ADDTIME('23:59:59', '1:1:1') '
                . ' FROM Bouh WHERE id = :id AND login = :login",
            ['id' => 3, 'login' => 'Sylvain']
        );

        $this->assertSame(
            "SELECT 'Bouh:Ting', ' ::Ting', ADDTIME('23:59:59', '1:1:1') '
                . ' FROM Bouh WHERE id = $1 AND login = $2",
            $outerSql
        );
        $this->assertSame([3, 'Sylvain'], $outerValues);
    }

    public function testExecuteShouldRaiseExceptionWhenErrorHappensWithQuery()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query_params', false);
        NativeFunctionMock::override('pg_fetch_array', 'data');
        NativeFunctionMock::override('pg_result_seek', true);
        NativeFunctionMock::override('pg_field_table', 'myTable');
        NativeFunctionMock::override('pg_last_error', 'Unknown Error');

        $mockCollection = $this->createStub(Collection::class);
        $mockCollection->method('set');

        $driver = new Driver();
        $driver->setDatabase('myDatabase');

        $this->assertThrows(
            QueryException::class,
            function () use ($driver): void {
                $driver->execute('SELECT 1 FROM myTable WHERE id = :id', ['id' => 12]);
            },
            'Unknown Error (Query: SELECT 1 FROM myTable WHERE id = $1)'
        );
    }

    public function testExecuteShouldLogQuery()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query_params', true);
        NativeFunctionMock::override('pg_fetch_array', 'data');
        NativeFunctionMock::override('pg_result_seek', true);
        NativeFunctionMock::override('pg_field_table', 'myTable');
        NativeFunctionMock::override('pg_result_status', \PGSQL_TUPLES_OK);
        NativeFunctionMock::override('pg_fetch_assoc', null);

        $mockLogger = $this->createMock(FakeDriverLogger::class);
        $mockLogger->expects($this->once())->method('startQuery');
        $mockLogger->expects($this->once())->method('stopQuery');

        $driver = new Driver();
        $driver->setDatabase('myDatabase');
        $driver->setLogger($mockLogger);
        $driver->execute('SELECT 1 FROM myTable WHERE id = :id', ['id' => 12]);
    }

    public function testExecuteShouldLogTheOriginalQuery()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query_params', true);
        NativeFunctionMock::override('pg_result_status', \PGSQL_TUPLES_OK);
        NativeFunctionMock::override('pg_fetch_assoc', null);

        $mockLogger = $this->createMock(FakeDriverLogger::class);
        $mockLogger->expects($this->once())->method('startQuery')->with(
            $this->identicalTo('SELECT 1 FROM myTable WHERE id = :id'),
            $this->identicalTo(['id' => 12]),
            $this->anything(),
            $this->identicalTo('myDatabase')
        );

        $driver = new Driver();
        $driver->setDatabase('myDatabase');
        $driver->setLogger($mockLogger);
        $driver->execute('SELECT 1 FROM myTable WHERE id = :id', ['id' => 12]);
    }

    public function testExecuteShouldStopTheQueryLogWhenTheQueryFails()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query_params', false);
        NativeFunctionMock::override('pg_last_error', 'unknown error');

        $mockLogger = $this->createMock(FakeDriverLogger::class);
        $mockLogger->expects($this->once())->method('startQuery');
        $mockLogger->expects($this->once())->method('stopQuery');

        $driver = new Driver();
        $driver->setDatabase('myDatabase');
        $driver->setLogger($mockLogger);

        $this->assertThrows(QueryException::class, function () use ($driver): void {
            $driver->execute('SELECT 1 FROM myTable WHERE id = :id', ['id' => 12]);
        });
    }

    public function testPrepareShouldLogQuery()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_prepare', true);
        NativeFunctionMock::override('pg_query', true);
        NativeFunctionMock::override('pg_fetch_array', 'data');
        NativeFunctionMock::override('pg_result_seek', true);
        NativeFunctionMock::override('pg_field_table', 'myTable');

        $mockLogger = $this->createMock(FakeDriverLogger::class);
        $mockLogger->expects($this->once())->method('startPrepare');
        $mockLogger->expects($this->once())->method('stopPrepare');

        $driver = new Driver();
        $driver->setName('foo');
        $driver->setDatabase('myDatabase');
        $driver->setLogger($mockLogger);
        $driver->prepare('SELECT 1 FROM myTable WHERE id = :id');
    }

    public function testPrepareCalledTwiceShouldReturnTheSameObject()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_prepare', true);
        NativeFunctionMock::override('pg_query', true);

        $driver = new Driver();
        $driver->setName('foo');
        $driver->setDatabase('myDatabase');
        $statement = $driver->prepare('SELECT 1 FROM myTable WHERE id = :id');

        $this->assertSame($statement, $driver->prepare('SELECT 1 FROM myTable WHERE id = :id'));
    }

    public function testCloseStatementShouldRaiseExceptionOnNonExistentStatement()
    {
        NativeFunctionMock::override('pg_prepare', true);

        $driver = new Driver();

        $this->assertThrows(Exception::class, function () use ($driver): void {
            $driver->closeStatement('NonExistentStatement');
        });
    }

    public function testPingShouldCallPingIfConnected()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_ping', true);

        $driver = new Driver();
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setDatabase('myDatabase');

        $this->assertTrue($driver->ping());
    }

    public function testPingShouldCallPingWithCharset()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_ping', true);
        NativeFunctionMock::override(
            'pg_set_client_encoding',
            function ($connection, $charset) use (&$outerCharset, &$called): void {
                $called++;
                $outerCharset = $charset;
            }
        );

        $mockDriver = new Pgsql();

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setDatabase('myDatabase');
        $driver->setCharset('UTF8');

        $this->assertTrue($driver->ping());
        $this->assertSame(2, $called);
    }

    public function testPingShouldCallPingWithTimezone()
    {
        $called = 0;
        $outerArgs = [];
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_ping', true);
        NativeFunctionMock::override('pg_query', function () use (&$called, &$outerArgs) {
            $outerArgs[] = func_get_args();
            $called++;
            return true;
        });

        $mockDriver = new Pgsql();

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setDatabase('myDatabase');
        $driver->setTimezone('timezone');

        $this->assertTrue($driver->ping());
        $this->assertSame(2, $called);
        $this->assertSame(array_fill(0, 2, 'SET timezone = "timezone";'), array_column($outerArgs, 1));
    }

    public function testPingShouldCallRaiseAnExceptionWhenNotConnected()
    {
        $driver = new Driver(new Pgsql());
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);

        $this->assertThrows(NeverConnectedException::class, function () use ($driver): void {
            $driver->ping();
        });
    }

    public function testTimezone()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query', true);

        $driver = new Driver();
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setDatabase('myDatabase');

        $this->assertNull($driver->setTimezone('timezone'));
    }

    public function testDefaultTimezone()
    {
        NativeFunctionMock::override('pg_connect', true);
        NativeFunctionMock::override('pg_query', true);

        $driver = new Driver();
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setDatabase('myDatabase');

        $this->assertNull($driver->setTimezone(null));
    }

    public function testSetTimezoneThenDefaultTimezone()
    {
        NativeFunctionMock::override('pg_connect', true);
        $outerArgs = [];
        NativeFunctionMock::override('pg_query', function () use (&$outerArgs) {
            $outerArgs[] = func_get_args();
            return true;
        });

        $driver = new Driver();
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setDatabase('myDatabase');
        $driver->setTimezone('timezone');
        $this->assertSame('SET timezone = "timezone";', $outerArgs[0][1]);
        $driver->setTimezone(null);
        $this->assertSame('SET timezone = DEFAULT;', $outerArgs[1][1]);
        $this->assertCount(2, $outerArgs);
    }
}
