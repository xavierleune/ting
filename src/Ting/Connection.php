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

class Connection
{
    /**
     * @var ConnectionPoolInterface|null
     */
    protected $connectionPool = null;

    /**
     * @var null|string
     */
    protected $database = null;

    /**
     * @var null|string
     */
    protected $name = null;

    /**
     * @param ConnectionPoolInterface $connectionPool
     * @param string $name
     * @param string $database
     * @throws \RuntimeException
     *
     * @internal
     */
    public function __construct(ConnectionPoolInterface $connectionPool, $name, $database)
    {
        if ($name === null || $database === null) {
            throw new \RuntimeException('Name and databases cannot be null on connection');
        }
        $this->connectionPool = $connectionPool;
        $this->name           = $name;
        $this->database       = $database;
    }

    /**
     * Return the primary connection
     * @throws Exception
     * @return Driver\DriverInterface
     */
    public function primary()
    {
        // A custom ConnectionPoolInterface implementation may not provide primary() before Ting 4.0
        if (method_exists($this->connectionPool, 'primary') === true) {
            return $this->connectionPool->primary($this->name, $this->database);
        }

        return $this->connectionPool->master($this->name, $this->database);
    }

    /**
     * Return a replica connection (the primary connection when no replica is configured)
     * @return Driver\DriverInterface
     * @throws Exception
     */
    public function replica()
    {
        // A custom ConnectionPoolInterface implementation may not provide replica() before Ting 4.0
        if (method_exists($this->connectionPool, 'replica') === true) {
            return $this->connectionPool->replica($this->name, $this->database);
        }

        return $this->connectionPool->slave($this->name, $this->database);
    }

    /**
     * Return the primary connection
     * @deprecated since Ting 3.14, use primary() instead
     * @throws Exception
     * @return Driver\DriverInterface
     */
    public function master()
    {
        @trigger_error(sprintf('Method "%s()" is deprecated since Ting 3.14, use "%s()" instead.', __METHOD__, 'primary'), E_USER_DEPRECATED);

        return $this->primary();
    }

    /**
     * Return a replica connection
     * @deprecated since Ting 3.14, use replica() instead
     * @return Driver\DriverInterface
     * @throws Exception
     */
    public function slave()
    {
        @trigger_error(sprintf('Method "%s()" is deprecated since Ting 3.14, use "%s()" instead.', __METHOD__, 'replica'), E_USER_DEPRECATED);

        return $this->replica();
    }

    /**
     * Start a transaction against the primary connection
     * @return mixed
     * @throws Exception
     */
    public function startTransaction()
    {
        return $this->primary()->startTransaction();
    }

    /**
     * Commit the opened transaction on the primary connection
     * @return mixed
     * @throws Exception
     */
    public function commit()
    {
        return $this->primary()->commit();
    }

    /**
     * Rollback the opened transaction on the primary connection
     * @return mixed
     * @throws Exception
     */
    public function rollback()
    {
        return $this->primary()->rollback();
    }
}
