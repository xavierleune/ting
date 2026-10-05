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

use CCMBenchmark\Ting\ConnectionPool;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\FakeLogger\FakeDriverLogger;

class ConnectionPoolTest extends TestCase
{
    public function testConnectShouldRaiseExceptionWhenConnectionNotFound()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig(['connectionName' => []]);

        $this->assertThrows(
            \Throwable::class,
            function () use ($connectionPool): void {
                $connectionPool->master(
                    'bouh',
                    'bouhDb'
                );
            },
            'Connection not found: bouh'
        );
        $this->assertThrows(
            \Throwable::class,
            function () use ($connectionPool): void {
                $connectionPool->slave(
                    'bouh',
                    'bouhDb'
                );
            },
            'Connection not found: bouh'
        );
    }

    public function testConnectSlaveShouldAlwaysReturnTheSameInstance()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig(
            [
                'bouh' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'master'    => [
                        'host'      => 'master',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ],
                    'slaves'    => [
                        [
                            'host'      => 'slave1',
                            'user'      => 'test',
                            'password'  => 'test',
                            'port'      => 3306
                        ],
                        [
                            'host'      => 'slave2',
                            'user'      => 'test',
                            'password'  => 'test',
                            'port'      => 3306
                        ],
                    ]
                ]
            ]
        );

        $slave = $connectionPool->slave('bouh', 'bouhDb');
        $this->assertSame($slave, $connectionPool->slave('bouh', 'bouhDb'));
        $this->assertSame($slave, $connectionPool->slave('bouh', 'bouhDb'));
        $this->assertSame($slave, $connectionPool->slave('bouh', 'bouhDb'));
        $this->assertSame($slave, $connectionPool->slave('bouh', 'bouhDb'));
    }

    public function testConnectSlaveShouldReturnMasterIfNoMasterDefined()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig(
            [
                'bouh' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'master'    => [
                        'host'      => 'master',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ],
                    'slaves'    => []
                ]
            ]
        );

        $this->assertSame($connectionPool->master('bouh', 'bouhDb'), $connectionPool->slave('bouh', 'bouhDb'));
    }

    public function testConnectWithoutUserNorPasswordShouldReturnADriver()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig(
            [
                'bouh' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'master'    => [
                        'host'      => 'master',
                        'port'      => 3306
                    ]
                ]
            ]
        );

        $this->assertInstanceOf('\tests\fixtures\FakeDriver\Driver', $connectionPool->master('bouh', 'bouhDb'));
    }

    public function testConnectShouldReturnADriver()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig(
            [
                'bouh' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'master'    => [
                        'host'      => 'master',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ],
                    'slaves'    => [
                        [
                            'host'      => 'slave1',
                            'user'      => 'test',
                            'password'  => 'test',
                            'port'      => 3306
                        ]
                    ]
                ]
            ]
        );

        $this->assertInstanceOf('\tests\fixtures\FakeDriver\Driver', $connectionPool->master('bouh', 'bouhDb'));
        $this->assertInstanceOf('\tests\fixtures\FakeDriver\Driver', $connectionPool->slave('bouh', 'bouhDb'));
    }


    public function testCloseAllConnections()
    {
        $mockLogger = $this->createMock(FakeDriverLogger::class);
        $mockLogger->expects($this->exactly(4))->method('addConnection');

        $connectionPool = new ConnectionPool($mockLogger);
        $connectionPool->setConfig(
            [
                'bouh' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'master'    => [
                        'host'      => 'master',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ],
                    'slaves'    => [
                        [
                            'host'      => 'slave1',
                            'user'      => 'test',
                            'password'  => 'test',
                            'port'      => 3306
                        ]
                    ]
                ]
            ]
        );
        $connectionPool->master('bouh', 'bouhDb');
        $connectionPool->slave('bouh', 'bouhDb');
        $connectionPool->closeAll();
        $connectionPool->master('bouh', 'bouhDb');
        $connectionPool->slave('bouh', 'bouhDb');
    }

    public function testResetShouldCloseAllConnections()
    {
        $mockLogger = $this->createMock(FakeDriverLogger::class);
        $mockLogger->expects($this->exactly(2))->method('addConnection');

        $connectionPool = new ConnectionPool($mockLogger);
        $connectionPool->setConfig(
            [
                'bouh' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'master'    => [
                        'host'      => 'master',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ]
                ]
            ]
        );
        $driver = $connectionPool->master('bouh', 'bouhDb');
        $this->assertSame($driver, $connectionPool->master('bouh', 'bouhDb'));
        $connectionPool->reset();
        $this->assertNotSame($driver, $connectionPool->master('bouh', 'bouhDb'));
    }

    public function testConnectionPoolShouldLogConnections()
    {
        $mockLogger = $this->createMock(FakeDriverLogger::class);
        $mockLogger->expects($addConnection = $this->exactly(2))->method('addConnection');

        $connectionPool = new ConnectionPool($mockLogger);
        $connectionPool->setConfig(
            [
                'bouh' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'master'    => [
                        'host'      => 'master',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ],
                    'slaves'    => [
                        [
                            'host'      => 'slave1',
                            'user'      => 'test',
                            'password'  => 'test',
                            'port'      => 3306
                        ]
                    ]
                ]
            ]
        );
        $connectionPool->master('bouh', 'bouhDb');
        $this->assertSame(1, $addConnection->numberOfInvocations());
        $connectionPool->slave('bouh', 'bouhDb');
        $this->assertSame(2, $addConnection->numberOfInvocations());
    }

    public function testGetDriveClassShouldreturnFakeDriver()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig(
            [
                'connectionName' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'master'    => [
                        'host'      => 'master',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ]
                ]
            ]
        );

        $this->assertSame('\tests\fixtures\FakeDriver\Driver', $connectionPool->getDriverClass('connectionName'));
    }

    public function testConnectShouldReturnADriverWithTheRightConnectionNameWhenManyConnectionsHaveSameParameter()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig(
            [
                'connection1' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'master'    => [
                        'host'      => '127.0.0.1',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ]
                ],
                'connection2' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'master'    => [
                        'host'      => '127.0.0.1',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ]
                ]
            ]
        );

        $driver = $connectionPool->master('connection1', 'databaseOnConnection1');
        $this->assertSame('connection1', $driver->getName());
        $driver2 = $connectionPool->master('connection2', 'databaseOnConnection1');
        $this->assertSame('connection2', $driver2->getName());
    }

    public function testConnectionShouldRetrunDriverWhenTimezoneSetted()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig(
            [
                'bouh' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'master'    => [
                        'host'      => 'master',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ],
                ]
            ]
        );
        $connectionPool->setDatabaseOptions([
            "bouhDb" => [
                'timezone' => 'UTF-8'
            ]
        ]);

        $this->assertInstanceOf('\tests\fixtures\FakeDriver\Driver', $connectionPool->master('bouh', 'bouhDb'));
    }
}
