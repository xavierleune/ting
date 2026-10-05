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
use CCMBenchmark\Ting\Exceptions\ConfigException;
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
                $connectionPool->primary(
                    'bouh',
                    'bouhDb'
                );
            },
            'Connection not found: bouh'
        );
        $this->assertThrows(
            \Throwable::class,
            function () use ($connectionPool): void {
                $connectionPool->replica(
                    'bouh',
                    'bouhDb'
                );
            },
            'Connection not found: bouh'
        );
    }

    public function testConnectReplicaShouldAlwaysReturnTheSameInstance()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig(
            [
                'bouh' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'primary'   => [
                        'host'      => 'primary',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ],
                    'replicas'  => [
                        [
                            'host'      => 'replica1',
                            'user'      => 'test',
                            'password'  => 'test',
                            'port'      => 3306
                        ],
                        [
                            'host'      => 'replica2',
                            'user'      => 'test',
                            'password'  => 'test',
                            'port'      => 3306
                        ],
                    ]
                ]
            ]
        );

        $replica = $connectionPool->replica('bouh', 'bouhDb');
        $this->assertSame($replica, $connectionPool->replica('bouh', 'bouhDb'));
        $this->assertSame($replica, $connectionPool->replica('bouh', 'bouhDb'));
        $this->assertSame($replica, $connectionPool->replica('bouh', 'bouhDb'));
        $this->assertSame($replica, $connectionPool->replica('bouh', 'bouhDb'));
    }

    public function testConnectReplicaShouldReturnPrimaryIfNoReplicaDefined()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig(
            [
                'bouh' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'primary'   => [
                        'host'      => 'primary',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ],
                    'replicas'  => []
                ]
            ]
        );

        $this->assertSame($connectionPool->primary('bouh', 'bouhDb'), $connectionPool->replica('bouh', 'bouhDb'));
    }

    public function testConnectWithoutUserNorPasswordShouldReturnADriver()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig(
            [
                'bouh' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'primary'   => [
                        'host'      => 'primary',
                        'port'      => 3306
                    ]
                ]
            ]
        );

        $this->assertInstanceOf('\tests\fixtures\FakeDriver\Driver', $connectionPool->primary('bouh', 'bouhDb'));
    }

    public function testConnectShouldReturnADriver()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig(
            [
                'bouh' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'primary'   => [
                        'host'      => 'primary',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ],
                    'replicas'  => [
                        [
                            'host'      => 'replica1',
                            'user'      => 'test',
                            'password'  => 'test',
                            'port'      => 3306
                        ]
                    ]
                ]
            ]
        );

        $this->assertInstanceOf('\tests\fixtures\FakeDriver\Driver', $connectionPool->primary('bouh', 'bouhDb'));
        $this->assertInstanceOf('\tests\fixtures\FakeDriver\Driver', $connectionPool->replica('bouh', 'bouhDb'));
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
                    'primary'   => [
                        'host'      => 'primary',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ],
                    'replicas'  => [
                        [
                            'host'      => 'replica1',
                            'user'      => 'test',
                            'password'  => 'test',
                            'port'      => 3306
                        ]
                    ]
                ]
            ]
        );
        $connectionPool->primary('bouh', 'bouhDb');
        $connectionPool->replica('bouh', 'bouhDb');
        $connectionPool->closeAll();
        $connectionPool->primary('bouh', 'bouhDb');
        $connectionPool->replica('bouh', 'bouhDb');
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
                    'primary'   => [
                        'host'      => 'primary',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ]
                ]
            ]
        );
        $driver = $connectionPool->primary('bouh', 'bouhDb');
        $this->assertSame($driver, $connectionPool->primary('bouh', 'bouhDb'));
        $connectionPool->reset();
        $this->assertNotSame($driver, $connectionPool->primary('bouh', 'bouhDb'));
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
                    'primary'   => [
                        'host'      => 'primary',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ],
                    'replicas'  => [
                        [
                            'host'      => 'replica1',
                            'user'      => 'test',
                            'password'  => 'test',
                            'port'      => 3306
                        ]
                    ]
                ]
            ]
        );
        $connectionPool->primary('bouh', 'bouhDb');
        $this->assertSame(1, $addConnection->numberOfInvocations());
        $connectionPool->replica('bouh', 'bouhDb');
        $this->assertSame(2, $addConnection->numberOfInvocations());
    }

    public function testGetDriveClassShouldreturnFakeDriver()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig(
            [
                'connectionName' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'primary'   => [
                        'host'      => 'primary',
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
                    'primary'   => [
                        'host'      => '127.0.0.1',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ]
                ],
                'connection2' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'primary'   => [
                        'host'      => '127.0.0.1',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ]
                ]
            ]
        );

        $driver = $connectionPool->primary('connection1', 'databaseOnConnection1');
        $this->assertSame('connection1', $driver->getName());
        $driver2 = $connectionPool->primary('connection2', 'databaseOnConnection1');
        $this->assertSame('connection2', $driver2->getName());
    }

    public function testConnectionShouldRetrunDriverWhenTimezoneSetted()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig(
            [
                'bouh' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'primary'   => [
                        'host'      => 'primary',
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

        $this->assertInstanceOf('\tests\fixtures\FakeDriver\Driver', $connectionPool->primary('bouh', 'bouhDb'));
    }

    public function testSetConfigShouldRejectTheMasterKeyRemovedInTing4()
    {
        $connectionPool = new ConnectionPool();

        $this->assertThrows(
            ConfigException::class,
            fn () => $connectionPool->setConfig([
                'main' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'master'    => ['host' => 'primary', 'port' => 3306],
                ]
            ]),
            'Connection "main": the "master" key was renamed "primary" in Ting 4.0'
        );
    }

    public function testSetConfigShouldRejectTheSlavesKeyRemovedInTing4()
    {
        $connectionPool = new ConnectionPool();

        $this->assertThrows(
            ConfigException::class,
            fn () => $connectionPool->setConfig([
                'main' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'primary'   => ['host' => 'primary', 'port' => 3306],
                ],
                'other' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'primary'   => ['host' => 'primary', 'port' => 3306],
                    'slaves'    => [['host' => 'replica1', 'port' => 3306]],
                ]
            ]),
            'Connection "other": the "slaves" key was renamed "replicas" in Ting 4.0'
        );
    }

    public function testSetConfigShouldNotKeepARejectedConfig()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig([
            'main' => [
                'namespace' => '\tests\fixtures\FakeDriver',
                'primary'   => ['host' => 'primary', 'port' => 3306],
            ]
        ]);

        $this->assertThrows(
            ConfigException::class,
            fn () => $connectionPool->setConfig([
                'main' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'master'    => ['host' => 'primary', 'port' => 3306],
                ]
            ])
        );

        $this->assertInstanceOf('\tests\fixtures\FakeDriver\Driver', $connectionPool->primary('main', 'db'));
    }
}
