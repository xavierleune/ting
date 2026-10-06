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

namespace CCMBenchmark\Ting\Driver\Pgsql;

use CCMBenchmark\Ting\Driver\QueryException;
use CCMBenchmark\Ting\Driver\StatementInterface;
use CCMBenchmark\Ting\Logger\DriverLoggerInterface;
use CCMBenchmark\Ting\Repository\CollectionInterface;

class Statement implements StatementInterface
{
    /**
     * Natively typed object: PgSql\Connection is final and only built by a server, tests stand in for it
     * @var \PgSql\Connection|null
     */
    protected ?object $connection = null;
    protected ?string $query = null;

    protected ?DriverLoggerInterface $logger = null;

    /**
     * Receives the result of each execution
     * @var (\Closure(\PgSql\Result): void)|null
     */
    protected ?\Closure $resultHandler = null;

    /**
     * Receives the name of the statement when the server refuses its DEALLOCATE
     * @var (\Closure(string): void)|null
     */
    protected ?\Closure $deallocationRefusedHandler = null;

    /**
     * The session holding the prepared statement is gone (connection reset)
     */
    protected bool $detached = false;

    /**
     * @param string              $statementName
     * @param array<int|string, int> $paramsOrder position of each parameter by name (an integer for a numeric name)
     */
    public function __construct(
        protected string $statementName,
        protected array $paramsOrder,
        protected string $connectionName,
        protected string $database
    ) {
    }

    /**
     * @param \PgSql\Connection $connection
     * @return $this
     *
     * @internal
     */
    public function setConnection(object $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Forget the connection, whose session (and the prepared statement with it) is gone: the statement can no
     * longer be executed, and its destructor must not DEALLOCATE a statement of the same name in the new session
     *
     * @internal
     */
    public function detach(): void
    {
        $this->connection = null;
        $this->detached = true;
    }

    public function isStale(): bool
    {
        return $this->detached;
    }

    /**
     * Hand the result of each execution over, so that the driver reports its affected rows
     * @param \Closure(\PgSql\Result): void $resultHandler
     *
     * @internal
     */
    public function setResultHandler(\Closure $resultHandler): static
    {
        $this->resultHandler = $resultHandler;

        return $this;
    }

    /**
     * Hand the name of the statement over when the server refuses its DEALLOCATE (in an aborted transaction): the
     * statement stays in the session
     * @param \Closure(string): void $deallocationRefusedHandler
     *
     * @internal
     */
    public function setDeallocationRefusedHandler(\Closure $deallocationRefusedHandler): static
    {
        $this->deallocationRefusedHandler = $deallocationRefusedHandler;

        return $this;
    }

    /**
     * @internal
     */
    public function setQuery(string $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @param DriverLoggerInterface $logger
     * @return void
     */
    public function setLogger(?DriverLoggerInterface $logger = null): void
    {
        $this->logger = $logger;
    }


    /**
     * Execute the actual statement with the given parameters
     * @param array<string, mixed> $params parameter name => value
     * @param CollectionInterface<mixed>|null $collection filled with the result set
     * @return bool|CollectionInterface<mixed> true: the collection given is filled
     * @throws QueryException
     */
    public function execute(array $params, ?CollectionInterface $collection = null): bool|CollectionInterface
    {
        $connection = $this->connection;
        if ($this->detached) {
            throw new QueryException(
                'The prepared statement ' . $this->statementName
                . ' is no longer valid: the connection was reset, prepare it again'
            );
        }
        if ($connection === null) {
            throw new QueryException(
                'The prepared statement ' . $this->statementName . ' has no connection: it must be prepared by the driver'
            );
        }

        $values = [];
        foreach (array_keys($this->paramsOrder) as $key) {
            if (!\array_key_exists($key, $params)) {
                throw QueryException::missingParameter((string) $key);
            }
            $values[] = $params[$key];
        }

        if ($this->logger !== null) {
            $this->logger->startStatementExecute($this->statementName, $params);
        }
        // Silenced: a failed execution raises a warning, which an error handler may turn into an exception, before
        // the QueryException below
        try {
            $result = @pg_execute($connection, $this->statementName, $values);
        } finally {
            if ($this->logger !== null) {
                $this->logger->stopStatementExecute($this->statementName);
            }
        }

        if ($result === false) {
            throw new QueryException(pg_last_error($connection));
        }

        if ($this->resultHandler !== null) {
            ($this->resultHandler)($result);
        }

        if ($collection !== null) {
            return $this->setCollectionWithResult($result, $collection);
        }

        return true;
    }

    /**
     * @param \PgSql\Result $resultResource
     * @param CollectionInterface<mixed> $collection
     * @throws QueryException
     *
     * @internal
     */
    public function setCollectionWithResult(object $resultResource, CollectionInterface $collection): bool
    {
        if ($this->query === null) {
            throw new QueryException(
                'The prepared statement ' . $this->statementName . ' has no query: it must be prepared by the driver'
            );
        }

        $result = new Result();
        $result->setConnectionName($this->connectionName);
        $result->setDatabase($this->database);
        $result->setResult($resultResource);
        $result->setQuery($this->query);

        $collection->set($result);
        return true;
    }

    /**
     * Deallocate the current prepared statement
     */
    protected function close(): void
    {
        if ($this->connection === null) {
            return;
        }

        try {
            // Silenced: a warning turned into an exception would escape the destructor
            $deallocated = @pg_query($this->connection, 'DEALLOCATE "' . $this->statementName . '"') !== false;
        } catch (\Error) {
            // The connection is closed (close(), reconnect()): the prepared statement is gone with it
            return;
        }

        if ($deallocated === false && $this->deallocationRefusedHandler !== null) {
            ($this->deallocationRefusedHandler)($this->statementName);
        }
    }

    /**
     * @internal
     */
    public function __destruct()
    {
        $this->close();
    }
}
