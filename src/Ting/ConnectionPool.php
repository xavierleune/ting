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

namespace CCMBenchmark\Ting;

use CCMBenchmark\Ting\Driver\DriverInterface;
use CCMBenchmark\Ting\Driver\Pgsql\Driver;
use CCMBenchmark\Ting\Exceptions\ConfigException;
use CCMBenchmark\Ting\Exceptions\ConnectionException;
use CCMBenchmark\Ting\Logger\DriverLoggerInterface;

class ConnectionPool implements ConnectionPoolInterface, ResetInterface
{
    /**
     * @var array
     */
    protected $connectionConfig = [];

    /**
     * @var array
     */
    protected $databaseOptions = [];

    /**
     * @var array
     */
    protected $connectionReplicas = [];

    /**
     * @var array<string, DriverInterface>
     */
    protected $connections = [];

    public function __construct(protected ?DriverLoggerInterface $logger = null)
    {
    }

    /**
     * @throws ConfigException when a connection still uses the "master" or "slaves" keys removed in Ting 4.0
     */
    public function setConfig(array $config): void
    {
        $renamedKeys = ['master' => 'primary', 'slaves' => 'replicas'];
        foreach ($config as $name => $connection) {
            if (is_array($connection) === false) {
                continue;
            }
            foreach ($renamedKeys as $oldKey => $newKey) {
                if (array_key_exists($oldKey, $connection)) {
                    throw new ConfigException(sprintf(
                        'Connection "%s": the "%s" key was renamed "%s" in Ting 4.0',
                        $name,
                        $oldKey,
                        $newKey
                    ));
                }
            }
        }

        $this->connectionConfig = $config;
    }

    public function setDatabaseOptions(array $options): void
    {
        $this->databaseOptions = $options;
    }

    /**
     * Return the primary connection
     *
     * @throws ConnectionException
     */
    public function primary(string $name, string $database): DriverInterface
    {
        if (isset($this->connectionConfig[$name]['primary']) === false) {
            throw new ConnectionException('Connection not found: ' . $name);
        }
        $config = $this->connectionConfig[$name]['primary'];
        /** @var class-string<DriverInterface> $driverClass */
        $driverClass = $this->connectionConfig[$name]['namespace'] . '\\Driver';

        $charset = null;

        if (isset($this->connectionConfig[$name]['charset'])) {
            $charset = $this->connectionConfig[$name]['charset'];
        }

        return $this->connect($config, $driverClass, $database, $name, $charset);
    }

    /**
     * Return always the same replica connection, or the primary connection when no replica is configured
     *
     * @throws ConnectionException
     */
    public function replica(string $name, string $database): DriverInterface
    {
        if (isset($this->connectionConfig[$name]) === false) {
            throw new ConnectionException('Connection not found: ' . $name);
        }
        /** @var class-string<DriverInterface> $driverClass */
        $driverClass = $this->connectionConfig[$name]['namespace'] . '\\Driver';

        if (isset($this->connectionConfig[$name]['replicas']) === false
            || $this->connectionConfig[$name]['replicas'] === []
        ) {
            return $this->primary($name, $database);
        }

        if (isset($this->connectionReplicas[$name]) === false) {
            /**
             * No replica has been chosen yet for this connection: pick one randomly and keep it,
             * so that we don't open one connection per replica.
             */

            $randomKey = array_rand($this->connectionConfig[$name]['replicas']);
            $this->connectionReplicas[$name] = $this->connectionConfig[$name]['replicas'][$randomKey];
        }

        $connectionConfig = $this->connectionReplicas[$name];

        $charset = null;

        if (isset($this->connectionConfig[$name]['charset'])) {
            $charset = $this->connectionConfig[$name]['charset'];
        }

        return $this->connect($connectionConfig, $driverClass, $database, $name, $charset);
    }

    /**
     * @param array{host: string, port: int, user?: string, password?: string} $config
     * @param class-string<DriverInterface> $driverClass
     * @throws Exception
     */
    protected function connect(array $config, string $driverClass, string $database, string $name, ?string $charset = null): DriverInterface
    {

        if (isset($config['user']) === false) {
            $config['user'] = null;
        }

        if (isset($config['password']) === false) {
            $config['password'] = null;
        }

        $connectionKey = $driverClass::getConnectionKey($config, $database);

        if (isset($this->connections[$connectionKey]) === false) {
            /** @var DriverInterface $driver */
            $driver = new $driverClass();

            if ($this->logger !== null) {
                $this->logger->addConnection($name, spl_object_hash($driver), $config);
                $driver->setLogger($this->logger);
            }

            $driver->connect(
                $config['host'],
                $config['user'],
                $config['password'],
                $config['port']
            );
            $this->connections[$connectionKey] = $driver;
        }

        /*
         * Methods setDatabase, setCharset and setTimezone have no impact unless the parameter's value is different
         * than the staled value.
         */
        $this->connections[$connectionKey]->setName($name);
        $this->connections[$connectionKey]->setDatabase($database);

        if ($charset !== null) {
            $this->connections[$connectionKey]->setCharset($charset);
        }

        $timezone = $this->databaseOptions[$database]['timezone'] ?? null;
        $this->connections[$connectionKey]->setTimezone($timezone);

        return $this->connections[$connectionKey];
    }

    /**
     * Close all opened connections
     */
    public function closeAll(): void
    {
        foreach ($this->connections as $connectionKey => $connection) {
            $connection->close();
            unset($this->connections[$connectionKey]);
        }
    }

    /**
     * @param string $name connection name
     * @return string
     * @throws ConnectionException
     */
    public function getDriverClass(string $name): string
    {
        if (isset($this->connectionConfig[$name]) === false) {
            throw new ConnectionException('Connection not found: ' . $name);
        }

        return $this->connectionConfig[$name]['namespace'] . '\\Driver';
    }

    public function reset(): void
    {
        $this->closeAll();
    }
}
