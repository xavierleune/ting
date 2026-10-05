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
     * @var array
     */
    protected $connections = [];

    /**
     * @var DriverLoggerInterface|null
     */
    protected $logger = null;

    /**
     * @param DriverLoggerInterface $logger
     */
    public function __construct(?DriverLoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * @param array $config
     */
    public function setConfig($config)
    {
        if (is_array($config) === true) {
            foreach ($config as $name => $connectionConfig) {
                if (is_array($connectionConfig) === true) {
                    $config[$name] = $this->normalizeConnectionConfig($connectionConfig);
                }
            }
        }

        $this->connectionConfig = $config;
    }

    /**
     * Convert the deprecated "master" / "slaves" keys to "primary" / "replicas"
     *
     * @param array $connectionConfig
     * @return array
     */
    private function normalizeConnectionConfig(array $connectionConfig)
    {
        $deprecatedKeys = ['master' => 'primary', 'slaves' => 'replicas'];

        foreach ($deprecatedKeys as $deprecatedKey => $key) {
            if (array_key_exists($deprecatedKey, $connectionConfig) === false) {
                continue;
            }

            @trigger_error(
                sprintf(
                    'The "%s" connection key is deprecated since Ting 3.14, use "%s" instead.',
                    $deprecatedKey,
                    $key
                ),
                E_USER_DEPRECATED
            );

            if (array_key_exists($key, $connectionConfig) === false) {
                $connectionConfig[$key] = $connectionConfig[$deprecatedKey];
            }
            unset($connectionConfig[$deprecatedKey]);
        }

        return $connectionConfig;
    }

    public function setDatabaseOptions($options)
    {
        $this->databaseOptions = $options;
    }

    /**
     * Return the primary connection
     *
     * @param string $name
     * @param string $database
     * @return DriverInterface
     * @throws ConnectionException
     */
    public function primary($name, $database)
    {
        if (isset($this->connectionConfig[$name]['primary']) === false) {
            throw new ConnectionException('Connection not found: ' . $name);
        }
        $config = $this->connectionConfig[$name]['primary'];
        $driverClass = $this->connectionConfig[$name]['namespace'] . '\\Driver';

        $charset = null;

        if (isset($this->connectionConfig[$name]['charset']) === true) {
            $charset = $this->connectionConfig[$name]['charset'];
        }

        return $this->connect($config, $driverClass, $database, $name, $charset);
    }

    /**
     * Return always the same replica connection, or the primary connection when no replica is configured
     *
     * @param string $name
     * @param string $database
     * @return DriverInterface
     * @throws ConnectionException
     */
    public function replica($name, $database)
    {
        if (isset($this->connectionConfig[$name]) === false) {
            throw new ConnectionException('Connection not found: ' . $name);
        }
        $driverClass = $this->connectionConfig[$name]['namespace'] . '\\Driver';

        if (isset($this->connectionConfig[$name]['replicas']) === false
            || $this->connectionConfig[$name]['replicas'] === []
        ) {
            return $this->primary($name, $database);
        }

        if (isset($this->connectionReplicas[$name]) === false) {
            /**
             * It's a replica connection and we have not chosen a replica yet. We randomly take one & store it.
             * In this way we avoid opening one connection per replica because of round-robin.
             */

            $randomKey = array_rand($this->connectionConfig[$name]['replicas']);
            $this->connectionReplicas[$name] = $this->connectionConfig[$name]['replicas'][$randomKey];
        }

        $connectionConfig = $this->connectionReplicas[$name];

        $charset = null;

        if (isset($this->connectionConfig[$name]['charset']) === true) {
            $charset = $this->connectionConfig[$name]['charset'];
        }

        return $this->connect($connectionConfig, $driverClass, $database, $name, $charset);
    }

    /**
     * Return the primary connection
     *
     * @deprecated since Ting 3.14, use primary() instead
     *
     * @param string $name
     * @param string $database
     * @return DriverInterface
     * @throws ConnectionException
     */
    public function master($name, $database)
    {
        @trigger_error(sprintf('Method "%s()" is deprecated since Ting 3.14, use "%s()" instead.', __METHOD__, 'primary'), E_USER_DEPRECATED);

        return $this->primary($name, $database);
    }

    /**
     * Return always the same replica connection
     *
     * @deprecated since Ting 3.14, use replica() instead
     *
     * @param string $name
     * @param string $database
     * @return DriverInterface
     * @throws ConnectionException
     */
    public function slave($name, $database)
    {
        @trigger_error(sprintf('Method "%s()" is deprecated since Ting 3.14, use "%s()" instead.', __METHOD__, 'replica'), E_USER_DEPRECATED);

        return $this->replica($name, $database);
    }

    /**
     * @param array $config
     * @param string $driverClass
     * @param string $database
     * @param string $name connection name
     * @param string $charset
     * @return DriverInterface
     * @throws Exception
     */
    protected function connect($config, $driverClass, $database, $name, $charset = null)
    {

        if (isset($config['user']) === false) {
            $config['user'] = null;
        }

        if (isset($config['password']) === false) {
            $config['password'] = null;
        }

        $connectionKey = $driverClass::getConnectionKey($config, $database);

        if (isset($this->connections[$connectionKey]) === false) {
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

        if (method_exists($this->connections[$connectionKey], 'setTimezone')) {
            $timezone = isset($this->databaseOptions[$database]['timezone']) !== false ? $this->databaseOptions[$database]['timezone'] : null;
            $this->connections[$connectionKey]->setTimezone($timezone);
        }

        return $this->connections[$connectionKey];
    }

    /**
     * Close all opened connections
     */
    public function closeAll()
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
    public function getDriverClass($name)
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
