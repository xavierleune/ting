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

    public function testConnectReplicaShouldReturnPrimaryIfNoPrimaryDefined()
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

    public function testPrimaryAndReplicaShouldNotTriggerDeprecations()
    {
        $connectionPool = new ConnectionPool();

        $deprecations = $this->collectDeprecations(function () use ($connectionPool): void {
            $connectionPool->setConfig($this->getConnectionConfig('primary', 'replicas'));
            $connectionPool->primary('bouh', 'bouhDb');
            $connectionPool->replica('bouh', 'bouhDb');
        });

        $this->assertSame([], $deprecations);
    }

    public function testReplicaShouldConnectToAReplicaAndPrimaryToThePrimary()
    {
        $hosts          = [];
        $connectionPool = new ConnectionPool($this->createHostRecordingLogger($hosts));
        $connectionPool->setConfig($this->getConnectionConfig('primary', 'replicas'));

        $replica = $connectionPool->replica('bouh', 'bouhDb');
        $this->assertNotSame($replica, $connectionPool->primary('bouh', 'bouhDb'));
        $this->assertSame(['replica1', 'primary'], $hosts);
    }

    public function testDeprecatedMasterShouldReturnThePrimaryAndTriggerADeprecation()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig($this->getConnectionConfig('primary', 'replicas'));

        $driver       = null;
        $deprecations = $this->collectDeprecations(function () use ($connectionPool, &$driver): void {
            $driver = $connectionPool->master('bouh', 'bouhDb');
        });

        $this->assertSame($connectionPool->primary('bouh', 'bouhDb'), $driver);
        $this->assertSame(
            ['Method "CCMBenchmark\Ting\ConnectionPool::master()" is deprecated since Ting 3.14, use "primary()" instead.'],
            $deprecations
        );
    }

    public function testDeprecatedSlaveShouldReturnTheReplicaAndTriggerADeprecation()
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig($this->getConnectionConfig('primary', 'replicas'));

        $driver       = null;
        $deprecations = $this->collectDeprecations(function () use ($connectionPool, &$driver): void {
            $driver = $connectionPool->slave('bouh', 'bouhDb');
        });

        $this->assertSame($connectionPool->replica('bouh', 'bouhDb'), $driver);
        $this->assertSame(
            ['Method "CCMBenchmark\Ting\ConnectionPool::slave()" is deprecated since Ting 3.14, use "replica()" instead.'],
            $deprecations
        );
    }

    public function testDeprecatedConfigKeysShouldStillWorkAndTriggerDeprecations()
    {
        $hosts          = [];
        $connectionPool = new ConnectionPool($this->createHostRecordingLogger($hosts));
        $config          = $this->getConnectionConfig('master', 'slaves');
        $config['other'] = $config['bouh'];

        $deprecations = $this->collectDeprecations(fn () => $connectionPool->setConfig($config));

        $this->assertSame(
            [
                'The "master" connection key is deprecated since Ting 3.14, use "primary" instead.',
                'The "slaves" connection key is deprecated since Ting 3.14, use "replicas" instead.',
                'The "master" connection key is deprecated since Ting 3.14, use "primary" instead.',
                'The "slaves" connection key is deprecated since Ting 3.14, use "replicas" instead.',
            ],
            $deprecations
        );
        $connectionPool->primary('bouh', 'bouhDb');
        $connectionPool->replica('bouh', 'bouhDb');
        $this->assertSame(['primary', 'replica1'], $hosts);
    }

    public function testNewConfigKeysShouldWinOverDeprecatedOnes()
    {
        $hosts          = [];
        $connectionPool = new ConnectionPool($this->createHostRecordingLogger($hosts));
        $config         = $this->getConnectionConfig('primary', 'replicas');
        $config['bouh']['master'] = ['host' => 'oldPrimary', 'port' => 3306];
        $config['bouh']['slaves'] = [['host' => 'oldReplica', 'port' => 3306]];

        $deprecations = $this->collectDeprecations(fn () => $connectionPool->setConfig($config));

        $this->assertCount(2, $deprecations);
        $connectionPool->primary('bouh', 'bouhDb');
        $connectionPool->replica('bouh', 'bouhDb');
        $this->assertSame(['primary', 'replica1'], $hosts);
    }

    /**
     * @param list<string> $hosts receives the host of each opened connection
     */
    private function createHostRecordingLogger(array &$hosts): FakeDriverLogger
    {
        $logger = $this->createStub(FakeDriverLogger::class);
        $logger->method('addConnection')->willReturnCallback(
            function ($name, $connection, array $config) use (&$hosts): void {
                $hosts[] = $config['host'];
            }
        );

        return $logger;
    }

    private function getConnectionConfig(string $primaryKey, string $replicasKey): array
    {
        return [
            'bouh' => [
                'namespace'  => '\tests\fixtures\FakeDriver',
                $primaryKey  => [
                    'host'      => 'primary',
                    'user'      => 'test',
                    'password'  => 'test',
                    'port'      => 3306
                ],
                $replicasKey => [
                    [
                        'host'      => 'replica1',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ]
                ]
            ]
        ];
    }
}
