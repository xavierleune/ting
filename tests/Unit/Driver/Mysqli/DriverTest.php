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

use CCMBenchmark\Ting\Driver\DriverInterface;
use CCMBenchmark\Ting\Driver\Exception;
use CCMBenchmark\Ting\Driver\Mysqli\Driver;
use CCMBenchmark\Ting\Driver\Mysqli\Statement;
use CCMBenchmark\Ting\Driver\NeverConnectedException;
use CCMBenchmark\Ting\Driver\QueryException;
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Tests\Support\NativeFunctionMock;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\Fake\Mysqli;
use tests\fixtures\Fake\MysqliStatement;
use tests\fixtures\FakeDriver\MysqliResult;
use tests\fixtures\FakeLogger\FakeDriverLogger;

class DriverTest extends TestCase
{
    /**
     * Written by the select_db callbacks of the ping tests (atoum wrote it as a dynamic property of the test).
     */
    private $database = null;

    /**
     * Counts the recorded calls whose arguments are equal (==) to $arguments, like atoum's withArguments().
     */
    private static function countCalls(array $calls, array $arguments): int
    {
        return \count(array_filter($calls, fn (array $call): bool => $call == $arguments));
    }

    public function testGetConnectionKeyShouldBeIdempotent()
    {
        $mockDriver = $this->createStub(Mysqli::class);

        $connectionConfig = ['host' => '127.0.0.1', 'user' => 'app_read', 'password' => 'pzefgdfg', 'port' => 3306];
        $driver = new Driver($mockDriver);
        $connectionKey = $driver->getConnectionKey($connectionConfig, 'myDatabase');

        $this->assertIsString($connectionKey);
        $this->assertSame($driver->getConnectionKey($connectionConfig, 'myDatabase'), $connectionKey);
        $this->assertSame($driver->getConnectionKey($connectionConfig, 'myDatabase'), $connectionKey);
    }

    public function testShouldImplementDriverInterface()
    {
        $this->assertInstanceOf(DriverInterface::class, new Driver());
    }

    public function testShouldUseGivenDriver()
    {
        $mockPool = $this->createStub(Mysqli::class);
        $mockDriver = $this->createStub(Driver::class);

        $driver = new Driver($mockPool, $mockDriver);

        $this->assertInstanceOf(DriverInterface::class, $driver);
    }

    public function testConnectShouldReturnSelf()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->method('real_connect')->willReturn(true);

        $driver = new Driver($mockDriver);

        $this->assertSame($driver, $driver->connect('hostname.test', 'user.test', 'password.test', 1234));
    }

    public function testConnectWithoutUserNorPasswordShouldUseTheDefaults()
    {
        $mockDriver = $this->createMock(Mysqli::class);
        $mockDriver->expects($this->once())
            ->method('real_connect')
            ->with('hostname.test', null, null, null, 1234)
            ->willReturn(true);

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', null, null, 1234);
    }

    public function testConnectParameters()
    {
        $mockDriver = $this->createMock(Mysqli::class);
        $mockDriver->expects($this->once())
            ->method('real_connect')
            ->with('hostname.test', 'user.test', 'password.test', null, 1234)
            ->willReturn(true);

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
    }

    public function testConnectWithWrongAuthOrPortShouldRaiseDriverException()
    {
        $driver = new Driver();

        $this->assertThrows(Exception::class, function () use ($driver): void {
            $driver->connect('localhost', 'user.test', 'password.test', 1234);
        });
    }

    public function testConnectWithUnresolvableHostShouldRaiseDriverException()
    {
        $driver = new Driver();

        $types = $this->collectErrorTypes(function () use ($driver): void {
            $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        }, $thrown);

        $this->assertInstanceOf(Exception::class, $thrown);
        $this->assertContains(E_WARNING, $types);
    }

    public function testCloseShouldReturnSelf()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->method('real_connect')->willReturn(true);

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);

        $this->assertSame($driver, $driver->close());
    }

    public function testIfNotConnectedCallbackAfterClosedConnection()
    {
        $called = false;

        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->method('real_connect')->willReturn(true);

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->close();
        $driver->ifIsNotConnected(function () use (&$called): void {
            $called = true;
        });

        $this->assertTrue($called);
    }

    public function testSetCharset()
    {
        $mockDriver = $this->createMock(Mysqli::class);
        $mockDriver->expects($this->once())
            ->method('set_charset')
            ->with('utf8')
            ->willReturn(true);

        $driver = new Driver($mockDriver);
        $driver->setCharset('utf8');
    }

    public function testSetCharsetCallingTwiceShouldCallMysqliSetCharsetOnce()
    {
        $mockDriver = $this->createMock(Mysqli::class);
        $mockDriver->expects($this->once())
            ->method('set_charset')
            ->willReturn(true);

        // atoum used a mock of Driver without any override nor check: the real class behaves the same
        $driver = new Driver($mockDriver);
        $driver->setCharset('utf8');
        $driver->setCharset('utf8');
    }

    public function testSetCharsetWithInvalidCharsetShouldThrowAnException()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->error = 'Invalid characterset or character set not supported';
        $mockDriver->method('set_charset')->willReturnCallback(fn ($charset) => false);

        $driver = new Driver($mockDriver);

        $this->assertThrows(
            \Throwable::class,
            function () use ($driver): void {
                $driver->setCharset('BadCharset');
            },
            'Can\'t set charset BadCharset (Invalid characterset or character set not supported)'
        );
    }

    public function testSetDatabase()
    {
        $mockDriver = $this->createMock(Mysqli::class);
        $mockDriver->error = '';
        $mockDriver->method('real_connect')->willReturn(true);
        $mockDriver->expects($this->once())
            ->method('select_db')
            ->with('bouh')
            ->willReturn(true);

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setDatabase('bouh');
    }

    public function testsetDatabaseWithDatabaseAlreadySetShouldDoNothing()
    {
        $mockDriver = $this->createMock(Mysqli::class);
        $mockDriver->error = '';
        $mockDriver->method('real_connect')->willReturn(true);
        $mockDriver->expects($this->once())
            ->method('select_db')
            ->willReturn(true);

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setDatabase('bouh');
        $driver->setDatabase('bouh');
    }

    public function testsetDatabaseShouldReturnSelf()
    {
        $mockDriver = $this->createMock(Mysqli::class);
        $mockDriver->error = '';
        $mockDriver->method('real_connect')->willReturn(true);
        $mockDriver->expects($this->once())
            ->method('select_db')
            ->with('bouh')
            ->willReturn(true);

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);

        $this->assertIsObject($driver->setDatabase('bouh'));
    }

    public function testsetDatabaseShouldRaiseDriverException()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->errno = 123;
        $mockDriver->error = 'unknown database';
        $mockDriver->method('real_connect')->willReturn(true);
        $mockDriver->method('select_db')->willReturn(true);

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);

        $this->assertThrows(Exception::class, function () use ($driver): void {
            $driver->setDatabase('bouh');
        });
    }

    public function testIfNotConnectedShouldCallCallback()
    {
        // Unused by the driver below, kept as in the original test
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->method('real_connect')->willReturn(false);

        $driver = new Driver();

        $types = $this->collectErrorTypes(function () use ($driver): void {
            $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        }, $thrown);

        $this->assertInstanceOf(\Throwable::class, $thrown);
        $this->assertContains(E_WARNING, $types);

        $driver->ifIsNotConnected(function () use (&$callable): void {
            $callable = true;
        });

        $this->assertTrue($callable);
    }

    public function testIfIsErrorShouldCallCallable()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->errno = 123;
        $mockDriver->error = 'unknown error';
        $mockDriver->method('real_connect')->willReturn(true);

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->ifIsError(function () use (&$callable): void {
            $callable = true;
        });

        $this->assertTrue($callable);
    }

    public function testPrepareShouldRaiseQueryException()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->errno = 123;
        $mockDriver->error = 'unknown error';
        $mockDriver->method('real_connect')->willReturn(true);
        $mockDriver->method('prepare')->willReturn(false);

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);

        $this->assertThrows(QueryException::class, function () use ($driver): void {
            $driver->prepare(
                'SELECT 1 FROM bouh WHERE first = :first AND second = :second'
            );
        });
    }

    public function testExecuteShouldCallDriverQuery()
    {
        $driverFake          = $this->createMock(Mysqli::class);
        // atoum mock without override: the real fake is used
        $mockMysqliResult    = new MysqliResult([]);

        $driverFake->expects($this->once())->method('query')->willReturn($mockMysqliResult);

        $driver = new Driver($driverFake);
        $driver->execute('Empty query');
    }

    public function testExecuteShouldRaiseExceptionIfValueNotDefined()
    {
        $driver = new Driver();

        $this->assertThrows(QueryException::class, function () use ($driver): void {
            $driver->execute('SELECT * WHERE id = :id');
        });
    }

    public function testExecuteShouldOnlyReplaceParameters()
    {
        $driverFake = $this->createMock(Mysqli::class);
        $driverFake->error = 'none';
        $driverFake->errno = 0;
        $driverFake->expects($this->once())
            ->method('query')
            ->with($this->identicalTo("SELECT 'Bouh:Ting', ' ::Ting', ADDTIME('23:59:59', '1:1:1') '
                . ' FROM Bouh WHERE id = 3 AND login = 'Sylvain' AND is_banned = 0"))
            ->willReturn(false);
        $driverFake->method('real_escape_string')->willReturnCallback(fn ($value) => $value);

        $driver = new Driver($driverFake);

        $exception = $this->assertThrows(\Throwable::class, function () use ($driver): void {
            $driver->execute(
                "SELECT 'Bouh:Ting', ' ::Ting', ADDTIME('23:59:59', '1:1:1') '
                . ' FROM Bouh WHERE id = :id AND login = :login AND is_banned = :is_banned",
                ['id' => 3, 'login' => 'Sylvain', 'is_banned' => false]
            );
        });
        $this->assertSame(0, $exception->getCode());
    }

    public function testExecuteShouldReturnACollection()
    {
        $driverFake          = $this->createStub(Mysqli::class);
        $driverFake->error   = '';
        // atoum mock only overriding fetch_fields: the real fake is used
        $mockMysqliResult    = (new MysqliResult([]))->setFields([]);
        $driverFake->method('real_connect')->willReturn(true);
        $driverFake->method('select_db')->willReturn(true);

        $collection = new Collection();

        $driver = new Driver($driverFake);
        $driver->setDatabase('database');
        $driver->setName('foo');
        $driverFake->method('real_escape_string')->willReturnCallback(function ($value) {
            if ($value instanceof \DateTime) {
                $value = $value->format('Y-m-d H:i:s');
            }
            return addcslashes($value, "'");
        });
        $driverFake->method('query')->willReturnCallback(function ($sql) use (&$outerSql, $mockMysqliResult) {
            $outerSql = $sql;
            return $mockMysqliResult;
        });

        $this->assertInstanceOf(
            Collection::class,
            $driver->execute(
                'SELECT population FROM T_CITY_CIT WHERE id = :id
                    AND name = :name AND age = :age AND last_modified = :date',
                [
                    'id' => 12,
                    'name' => 'L\'étang du lac',
                    'age' => 12.6,
                    'date' => \DateTime::createFromFormat('Y-m-d H:i:s', '2014-03-01 14:02:05')
                ],
                $collection
            )
        );
    }

    public function testDriverWithoutNameNorDatabaseShouldExecuteAndPrepare()
    {
        $driverFake = $this->createStub(Mysqli::class);
        $driverFake->error = '';
        $driverFake->method('query')->willReturn((new MysqliResult([]))->setFields([]));
        $driverStatement = $this->createStub(MysqliStatement::class);
        $driverStatement->method('close')->willReturn(true);
        $driverFake->method('prepare')->willReturn($driverStatement);

        // Used directly, without ConnectionPool (which always sets them)
        $driver = new Driver($driverFake);
        $driver->setLogger(new FakeDriverLogger());

        $this->assertInstanceOf(Collection::class, $driver->execute('SELECT 1', [], new Collection()));
        $this->assertInstanceOf(Statement::class, $driver->prepare('SELECT 1'));
    }

    public function testExecuteShouldThrowExceptionOnErrorWithQuery()
    {
        $driverFake          = $this->createStub(Mysqli::class);
        // atoum mock without override: the real fake is used
        $mockMysqliResult    = new MysqliResult([]);

        $collection = new Collection();

        $driver = new Driver($driverFake);
        $driverFake->method('real_escape_string')->willReturnCallback(function ($value) {
            if ($value instanceof \DateTime) {
                $value = $value->format('Y-m-d H:i:s');
            }
            return addcslashes($value, "'");
        });
        $driverFake->method('query')->willReturn(false);
        $driverFake->error = 'Undefined Error';
        $driverFake->errno = 127;

        $this->assertThrows(
            QueryException::class,
            function () use ($driver, $collection): void {
                $driver->execute(
                    'SELECT population FROM T_CITY_CIT WHERE id = :id AND name = :name AND age = :age AND last_modified = :date',
                    [
                        'id' => 12,
                        'name' => 'L\'étang du lac',
                        'age' => 12.6,
                        'date' => \DateTime::createFromFormat('Y-m-d H:i:s', '2014-03-01 14:02:05')
                    ],
                    $collection
                );
            },
            'Undefined Error (Query: SELECT population FROM T_CITY_CIT WHERE id = 12 AND name = \'L\\\'étang du lac\' AND age = 12.6 AND last_modified = \'2014-03-01 14:02:05\')'
        );
    }

    public function testExecuteShouldBuildACorrectQuery()
    {
        $driverFake       = $this->createMock(Mysqli::class);
        // atoum mock without override: the real fake is used
        $mockMysqliResult = new MysqliResult(['hop' => 'la']);

        $driver = new Driver($driverFake);
        $driverFake->method('real_escape_string')->willReturnCallback(function ($value) {
            if ($value instanceof \DateTime) {
                $value = $value->format('Y-m-d H:i:s');
            }

            return addcslashes($value, "'");
        });
        $driverFake->expects($this->once())
            ->method('query')
            ->willReturnCallback(function ($sql) use (&$outerSql, $mockMysqliResult) {
                $outerSql = $sql;
                return $mockMysqliResult;
            });

        $this->assertSame(
            ['hop' => 'la'],
            $driver->execute(
                'SELECT population FROM T_CITY_CIT WHERE id = :id
                    AND name = :name AND age = :age AND last_modified = :date',
                [
                    'id' => 12,
                    'name' => 'L\'étang du lac',
                    'age' => 12.6,
                    'date' => \DateTime::createFromFormat('Y-m-d H:i:s', '2014-03-01 14:02:05')
                ]
            )
        );
        $this->assertEquals(
            'SELECT population FROM T_CITY_CIT WHERE id = 12
                    AND name = \'L\\\'étang du lac\' AND age = 12.6 AND last_modified = \'2014-03-01 14:02:05\'',
            $outerSql
        );
    }


    public function testStringValuesShouldBeQuotedWithSingleQuotes()
    {
        $driverFake = $this->createStub(Mysqli::class);
        // real_escape_string under sql_mode NO_BACKSLASH_ESCAPES: only single quotes are doubled
        $driverFake->method('real_escape_string')->willReturnCallback(fn ($value) => str_replace("'", "''", $value));
        $driverFake->method('query')->willReturnCallback(function ($sql) use (&$outerSql) {
            $outerSql = $sql;

            return true;
        });

        $driver = new Driver($driverFake);
        $driver->execute('SELECT * FROM T_USER_USE WHERE login = :login', ['login' => 'zzz" OR "1"="1']);

        // Double quotes stay inside the literal; a double-quoted value would also be an identifier under ANSI_QUOTES
        $this->assertSame('SELECT * FROM T_USER_USE WHERE login = \'zzz" OR "1"="1\'', $outerSql);
    }

    public function testNullValueShouldNotBeQuoted()
    {
        $driverFake       = $this->createMock(Mysqli::class);
        // atoum mock without override: the real fake is used
        $mockMysqliResult = new MysqliResult(['hop' => 'la']);

        $driver = new Driver($driverFake);
        $driverFake->method('real_escape_string')->willReturnCallback(fn ($value) => addcslashes($value, "'"));
        $driverFake->expects($this->once())
            ->method('query')
            ->willReturnCallback(function ($sql) use (&$outerSql, $mockMysqliResult) {
                $outerSql = $sql;
                return $mockMysqliResult;
            });

        $this->assertSame(
            ['hop' => 'la'],
            $driver->execute(
                'SELECT name FROM T_COUNTRY_COU WHERE indepYear = :indepYear',
                [
                    'indepYear' => null
                ]
            )
        );
        $this->assertEquals(
            'SELECT name FROM T_COUNTRY_COU WHERE indepYear = null',
            $outerSql
        );
    }

    public function testExecuteShouldReturnTrue()
    {
        $driverFake = $this->createStub(Mysqli::class);
        $driverFake->method('query')->willReturnCallback(fn ($sql) => true);

        $driver = new Driver($driverFake);

        $this->assertTrue($driver->execute('UPDATE Bouh SET id = 3'));
    }

    public function testPrepareShouldNotTransformEscapedColon()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->error = '';
        $mockDriver->method('real_connect')->willReturn(true);
        $driverStatement = $this->createStub(MysqliStatement::class);
        $driverStatement->method('close')->willReturn(true);

        $mockDriver->method('prepare')->willReturnCallback(function ($sql) use (&$outerSql, $driverStatement) {
            $outerSql = $sql;

            return $driverStatement;
        });

        $driver = new Driver($mockDriver);
        $driver->setName('foo');
        $driver->setDatabase('T_BOUH_BOO');
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->prepare(
            'SELECT * FROM T_BOUH_BOO WHERE name = "\:bim"'
        );

        $this->assertSame('SELECT * FROM T_BOUH_BOO WHERE name = ":bim"', $outerSql);
    }

    public function testPreparedStatementShouldBindTheValuesInTheQueryOrder()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->error = '';
        $driverStatement = $this->createMock(MysqliStatement::class);
        $driverStatement->method('close')->willReturn(true);
        $driverStatement->method('get_result')->willReturn(true);
        $driverStatement->errno = 0;
        $mockDriver->method('prepare')->willReturn($driverStatement);

        $driverStatement->expects($this->once())
            ->method('bind_param')
            ->with(
                $this->identicalTo('isi'),
                $this->identicalTo(3),
                $this->identicalTo('Sylvain'),
                $this->identicalTo(3)
            );

        $driver = new Driver($mockDriver);
        $driver->setName('foo');
        $driver->setDatabase('T_BOUH_BOO');
        $driver->prepare('SELECT * FROM T_BOUH_BOO WHERE id = :id AND name = :name OR parent_id = :id')
            ->execute(['name' => 'Sylvain', 'id' => 3]);
    }

    public function testExecuteShouldNotTransformEscapedColon()
    {
        $driverFake = $this->createStub(Mysqli::class);
        $driverFake->method('real_escape_string')->willReturnCallback(fn ($value) => $value);
        $driverFake->method('query')->willReturnCallback(function ($sql) use (&$outerSql) {
            $outerSql = $sql;

            return true;
        });

        $driver = new Driver($driverFake);
        $driver->execute('SELECT * FROM T_BOUH_BOO WHERE name = "\:bim" AND login = :login', ['login' => 'a\:b']);

        // The escape is removed from the query, not from the values
        $this->assertSame('SELECT * FROM T_BOUH_BOO WHERE name = ":bim" AND login = \'a\:b\'', $outerSql);
    }

    public function testPrepareCalledTwiceShouldReturnTheSameObject()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->error = '';
        $mockDriver->method('real_connect')->willReturn(true);
        $driverStatement = $this->createStub(MysqliStatement::class);
        $driverStatement->method('close')->willReturn(true);

        $mockDriver->method('prepare')->willReturnCallback(function ($sql) use (&$outerSql, $driverStatement) {
            $outerSql = $sql;

            return $driverStatement;
        });

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setName('foo');
        $driver->setDatabase('T_BOUH_BOO');
        $statement = $driver->prepare(
            'SELECT * FROM T_BOUH_BOO WHERE name = "\:bim"'
        );

        $this->assertSame(
            $statement,
            $driver->prepare(
                'SELECT * FROM T_BOUH_BOO WHERE name = "\:bim"'
            )
        );
    }

    public function testEscapeFieldShouldEscapeField()
    {
        $mockDriver = $this->createStub(Mysqli::class);

        $driver = new Driver($mockDriver);

        $this->assertSame('`Bouh`', $driver->escapeField('Bouh'));
    }

    public function testStartTransactionShouldRaiseExceptionIfCalledTwice()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $driver = new Driver($mockDriver);
        $driver->startTransaction();

        $this->assertThrows(Exception::class, function () use ($driver): void {
            $driver->startTransaction();
        });
    }

    public function testCommitShouldCloseTransaction()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $driver = new Driver($mockDriver);
        $driver->startTransaction();
        $driver->commit();

        $this->assertThrows(
            \Throwable::class,
            function () use ($driver): void {
                $driver->commit();
            },
            'Cannot commit no transaction'
        );
    }

    public function testCommitShouldRaiseExceptionIfNoTransaction()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $driver = new Driver($mockDriver);

        $this->assertThrows(Exception::class, function () use ($driver): void {
            $driver->commit();
        });
    }

    public function testRollbackShouldCloseTransaction()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $driver = new Driver($mockDriver);
        $driver->startTransaction();
        $driver->rollback();

        $this->assertThrows(
            Exception::class,
            function () use ($driver): void {
                $driver->rollback();
            },
            'Cannot rollback no transaction'
        );
    }

    public function testRollbackShouldRaiseExceptionIfNoTransaction()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $driver = new Driver($mockDriver);

        $this->assertThrows(Exception::class, function () use ($driver): void {
            $driver->rollback();
        });
    }

    public function testGetInsertedIdShouldReturnInsertedId()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->method('real_connect')->willReturn(true);
        $mockDriver->insert_id = 3;

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);

        $this->assertSame(3, $driver->getInsertedId());
    }

    public function testGetAffectedRowsShouldReturnAffectedRows()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->method('real_connect')->willReturn(true);
        $mockDriver->affected_rows = 12;

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);

        $this->assertSame(12, $driver->getAffectedRows());
    }

    public function testGetAffectedRowsShouldReturn0OnError()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->method('real_connect')->willReturn(true);
        $mockDriver->affected_rows = -1;

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);

        $this->assertSame(0, $driver->getAffectedRows());
    }

    public function testExecuteMustLogQuery()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->error = '';
        $mockLogger = $this->createMock(FakeDriverLogger::class);

        $mockDriver->method('query')->willReturn(true);
        $mockDriver->method('select_db')->willReturn(true);

        $mockLogger->expects($this->once())->method('startQuery');
        $mockLogger->expects($this->once())->method('stopQuery');

        $driver = new Driver($mockDriver);
        $driver->setDatabase('db');
        $driver->setLogger($mockLogger);
        $driver->execute('Empty query');
    }

    public function testPrepareShouldLogQuery()
    {
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->error = '';
        $driverStatement = $this->createStub(MysqliStatement::class);
        $mockDriver->method('prepare')->willReturn($driverStatement);
        $mockDriver->method('select_db')->willReturn(true);
        $driverStatement->method('close')->willReturn(true);

        $mockLogger = $this->createMock(FakeDriverLogger::class);
        $mockLogger->expects($this->once())->method('startPrepare');
        $mockLogger->expects($this->once())->method('stopPrepare');

        $driver = new Driver($mockDriver);
        $driver->setDatabase('db');
        $driver->setName('foo');
        $driver->setLogger($mockLogger);
        $driver->prepare('Empty query');
    }

    public function testCloseStatementShouldRaiseExceptionOnNonExistentStatement()
    {
        $mockDriver = $this->createStub(Mysqli::class);

        $driver = new Driver($mockDriver);

        $this->assertThrows(Exception::class, function () use ($driver): void {
            $driver->closeStatement('NonExistentStatementName');
        });
    }

    public function testPingShouldCallPingIfConnected()
    {
        $mockDriver = $this->createMock(Mysqli::class);
        $mockDriver->method('real_connect')->willReturn(true);
        $mockDriver->expects($this->once())->method('query')->willReturn(true);

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);

        $this->assertTrue($driver->ping());
    }

    public function testPingShouldReconnect()
    {
        $realConnectCalls = [];
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->method('query')->willReturn(false);
        $mockDriver->method('real_connect')->willReturnCallback(function (...$arguments) use (&$realConnectCalls) {
            $realConnectCalls[] = $arguments;

            return true;
        });
        NativeFunctionMock::override('mysqli_init', $mockDriver);
        $mockDriver->method('select_db')->willReturnCallback(function ($database): void {
            $this->database = $database;
        });
        $mockDriver->error = '';

        $hostName = 'hostname.test';
        $userName = 'user.test';
        $password = 'password.test';
        $database = uniqid('database');
        $port = 1234;

        $driver = new Driver($mockDriver);
        $driver->connect($hostName, $userName, $password, $port);
        $driver->setDatabase($database);

        $this->assertTrue($driver->ping());
        // 1 call for connect()
        $this->assertSame(1, self::countCalls($realConnectCalls, [$hostName, $userName, $password, null, $port]));
        // 1 call for ping()
        $this->assertSame(1, self::countCalls($realConnectCalls, [$hostName, $userName, $password, $database, $port]));
    }

    public function testPingShouldSetTimezone()
    {
        $realConnectCalls = [];
        $queryCalls = [];
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->method('query')->willReturnCallback(function (...$arguments) use (&$queryCalls) {
            $queryCalls[] = $arguments;

            return false;
        });
        $mockDriver->method('real_connect')->willReturnCallback(function (...$arguments) use (&$realConnectCalls) {
            $realConnectCalls[] = $arguments;

            return true;
        });
        NativeFunctionMock::override('mysqli_init', $mockDriver);
        $mockDriver->method('select_db')->willReturnCallback(function ($database): void {
            $this->database = $database;
        });
        $mockDriver->error = '';

        $hostName = 'hostname.test';
        $userName = 'user.test';
        $password = 'password.test';
        $database = uniqid('database');
        $port = 1234;
        $timezone = 'timezone';

        $driver = new Driver($mockDriver);
        $driver->connect($hostName, $userName, $password, $port);
        $driver->setDatabase($database);
        $driver->setTimezone($timezone);

        $this->assertTrue($driver->ping());
        // 1 call for connect()
        $this->assertSame(1, self::countCalls($realConnectCalls, [$hostName, $userName, $password, null, $port]));
        // 1 call for ping()
        $this->assertSame(1, self::countCalls($realConnectCalls, [$hostName, $userName, $password, $database, $port]));
        $this->assertSame(2, self::countCalls($queryCalls, ['SET time_zone = "' . $timezone . '";']));
    }

    public function testPingShouldNotSetTimezone()
    {
        $realConnectCalls = [];
        $queryCalls = [];
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->method('query')->willReturnCallback(function (...$arguments) use (&$queryCalls) {
            $queryCalls[] = $arguments;

            return false;
        });
        $mockDriver->method('real_connect')->willReturnCallback(function (...$arguments) use (&$realConnectCalls) {
            $realConnectCalls[] = $arguments;

            return true;
        });
        NativeFunctionMock::override('mysqli_init', $mockDriver);
        $mockDriver->method('select_db')->willReturnCallback(function ($database): void {
            $this->database = $database;
        });
        $mockDriver->error = '';

        $hostName = 'hostname.test';
        $userName = 'user.test';
        $password = 'password.test';
        $database = uniqid('database');
        $port = 1234;

        $driver = new Driver($mockDriver);
        $driver->connect($hostName, $userName, $password, $port);
        $driver->setDatabase($database);
        $driver->setTimezone(null);

        $this->assertTrue($driver->ping());
        // 1 call for connect()
        $this->assertSame(1, self::countCalls($realConnectCalls, [$hostName, $userName, $password, null, $port]));
        // 1 call for ping()
        $this->assertSame(1, self::countCalls($realConnectCalls, [$hostName, $userName, $password, $database, $port]));
        $this->assertSame(1, self::countCalls($queryCalls, ['SELECT 1']));
    }

    public function testPingShouldReconnectWithCharset()
    {
        $realConnectCalls = [];
        $setCharsetCalls = [];
        $mockDriver = $this->createStub(Mysqli::class);
        $mockDriver->method('query')->willReturn(false);
        $mockDriver->method('real_connect')->willReturnCallback(function (...$arguments) use (&$realConnectCalls) {
            $realConnectCalls[] = $arguments;

            return true;
        });
        $mockDriver->method('select_db')->willReturn(true);
        $mockDriver->method('set_charset')->willReturnCallback(function (...$arguments) use (&$setCharsetCalls) {
            $setCharsetCalls[] = $arguments;

            return null;
        });
        NativeFunctionMock::override('mysqli_init', $mockDriver);
        $mockDriver->error = '';

        $hostName = 'hostname.test';
        $userName = 'user.test';
        $password = 'password.test';
        $database = uniqid('database');
        $charset = 'utf8';
        $port = 1234;

        $driver = new Driver($mockDriver);
        $driver->connect($hostName, $userName, $password, $port);
        $driver->setDatabase($database);
        $driver->setCharset($charset);

        $this->assertTrue($driver->ping());
        // 1 call for connect()
        $this->assertSame(1, self::countCalls($realConnectCalls, [$hostName, $userName, $password, null, $port]));
        // 1 call for ping()
        $this->assertSame(1, self::countCalls($realConnectCalls, [$hostName, $userName, $password, $database, $port]));
        // 1 call for setChartset & 1 call for ping
        $this->assertSame(2, self::countCalls($setCharsetCalls, [$charset]));
    }

    public function testPingShouldCallRaiseAnExceptionWhenNotConnected()
    {
        $mockDriver = $this->createStub(Mysqli::class);

        $driver = new Driver($mockDriver);

        $this->assertThrows(NeverConnectedException::class, function () use ($driver): void {
            $driver->ping();
        });
    }

    public function testPingShouldReconnectIfConnectionHasGone()
    {
        $realConnectCalls = [];
        $mockDriver = $this->createMock(Mysqli::class);
        $queryCall = 0;
        $mockDriver->expects($this->once())
            ->method('query')
            ->willReturnCallback(function ($query) use (&$queryCall) {
                $queryCall++;
                if ($queryCall === 1) {
                    throw new \mysqli_sql_exception("MySQL server has gone away");
                }
                if ($queryCall === 2) {
                    return true;
                }

                return null;
            });
        $mockDriver->method('real_connect')->willReturnCallback(function (...$arguments) use (&$realConnectCalls) {
            $realConnectCalls[] = $arguments;

            return true;
        });
        NativeFunctionMock::override('mysqli_init', $mockDriver);
        $mockDriver->error = '';

        $hostName = 'hostname.test';
        $userName = 'user.test';
        $password = 'password.test';
        $database = uniqid('database');
        $port = 1234;

        $driver = new Driver($mockDriver);
        $driver->connect($hostName, $userName, $password, $port);
        $driver->setDatabase($database);

        $this->assertTrue($driver->ping());
        // 1 call for connect() = try to reconnect
        $this->assertSame(1, self::countCalls($realConnectCalls, [$hostName, $userName, $password, null, $port]));
    }

    public function testPingException()
    {
        $realConnectCalls = [];
        $mockDriver = $this->createStub(Mysqli::class);
        $queryCall = 0;
        $mockDriver->method('query')->willReturnCallback(function () use (&$queryCall) {
            $queryCall++;

            return $queryCall === 1 ? false : null;
        });
        NativeFunctionMock::override('mysqli_init', $mockDriver);
        $mockDriver->method('real_connect')->willReturnCallback(function (...$arguments) use (&$realConnectCalls) {
            $realConnectCalls[] = $arguments;
            if (\count($realConnectCalls) === 1) {
                return true;
            }
            if (\count($realConnectCalls) === 2) {
                throw new \mysqli_sql_exception("MySQL server has gone away");
            }

            return null;
        });

        $hostName = 'hostname.test';
        $userName = 'user.test';
        $password = 'password.test';
        $database = uniqid('database');
        $port = 1234;

        $driver = new Driver($mockDriver);
        $driver->connect($hostName, $userName, $password, $port);

        $this->assertFalse($driver->ping());
        // 1 call for connect()
        $this->assertSame(2, self::countCalls($realConnectCalls, [$hostName, $userName, $password, null, $port]));
    }

    public function testTimezone()
    {
        $mockDriver = $this->createMock(Mysqli::class);
        $mockDriver->method('real_connect')->willReturn(true);
        $mockDriver->expects($this->once())->method('query');

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setTimezone('timezone');
    }

    public function testDefaultTimezone()
    {
        $mockDriver = $this->createMock(Mysqli::class);
        $mockDriver->method('real_connect')->willReturn(true);
        $mockDriver->expects($this->never())->method('query');

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setTimezone(null);
    }

    public function testSetTimezoneThenDefaultTimezone()
    {
        $queryCalls = [];
        $mockDriver = $this->createMock(Mysqli::class);
        $mockDriver->method('real_connect')->willReturn(true);
        $mockDriver->expects($this->exactly(2))
            ->method('query')
            ->willReturnCallback(function (...$arguments) use (&$queryCalls) {
                $queryCalls[] = $arguments;

                return null;
            });

        $driver = new Driver($mockDriver);
        $driver->connect('hostname.test', 'user.test', 'password.test', 1234);
        $driver->setTimezone('timezone');
        $this->assertSame(1, self::countCalls($queryCalls, ['SET time_zone = "timezone";']));
        $driver->setTimezone(null);
        $this->assertSame(1, self::countCalls($queryCalls, ['SET time_zone = DEFAULT;']));
        $driver->setTimezone(null);
        $this->assertCount(2, $queryCalls);
    }
}
