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

use PgSql\Connection;
use CCMBenchmark\Ting\Driver\DriverInterface;
use CCMBenchmark\Ting\Driver\Exception;
use CCMBenchmark\Ting\Driver\NeverConnectedException;
use CCMBenchmark\Ting\Driver\QueryException;
use CCMBenchmark\Ting\Driver\StatementInterface;
use CCMBenchmark\Ting\Exceptions\DriverException;
use CCMBenchmark\Ting\Exceptions\StatementException;
use CCMBenchmark\Ting\Exceptions\TransactionException;
use CCMBenchmark\Ting\Logger\DriverLoggerInterface;
use CCMBenchmark\Ting\Repository\CollectionInterface;

class Driver implements DriverInterface
{
    /**
     * @var string
     */
    protected string $name = '';

    protected string $database  = '';

    protected ?string $currentCharset = null;

    protected ?string $currentTimezone = null;

    /**
     * @var Connection|null
     */
    protected $connection = null;

    protected bool $transactionOpened = false;

    protected ?DriverLoggerInterface $logger = null;

    /**
     * spl_object_hash of current object
     */
    protected string $objectHash = '';

    /**
     * @var \PgSql\Result|null
     */
    protected $result = null;

    /**
     * @var array<string, StatementInterface>
     */
    protected array $preparedQueries = [];

    /**
     * Names of the statements forgotten when the connection was reset, that closeStatement() still accepts
     * @var array<string, true>
     */
    protected array $forgottenPreparedQueries = [];

    /**
     * @var string
     */
    protected $dsn;

    public static function getConnectionKey(array $connectionConfig, string $database): string
    {
        return
            $connectionConfig['host'] . '|' .
            $connectionConfig['port'] . '|' .
            $connectionConfig['user'] . '|' .
            $connectionConfig['password'] . '|' .
            $database;
    }

    /**
     * Construct connection information
     */
    public function connect(string $hostname, ?string $username, ?string $password, int $port): static
    {
        // Without user or password, libpq uses its defaults (current user, .pgpass)
        $this->dsn = 'host=' . self::quoteDsnValue($hostname)
            . ($username !== null ? ' user=' . self::quoteDsnValue($username) : '')
            . ($password !== null ? ' password=' . self::quoteDsnValue($password) : '')
            . ' port=' . self::quoteDsnValue((string) $port);
        return $this;
    }

    /**
     * Quote a value of the connection string: empty values and values with spaces, quotes
     * or backslashes are only read as a whole by libpq when single-quoted and escaped
     */
    private static function quoteDsnValue(string $value): string
    {
        return "'" . addcslashes($value, "'\\") . "'";
    }

    /**
     * Close the connection to the database
     * @return $this
     */
    public function close(): static
    {
        if ($this->connection !== null) {
            pg_close($this->connection);
            $this->connection = null;
            $this->forgetPreparedQueries();
        }

        return $this;
    }

    /**
     * @throws DriverException
     */
    public function setCharset(string $charset): void
    {
        if ($this->currentCharset === $charset) {
            return;
        }

        if ($this->connection === null) {
            // pg_last_error() without connection is deprecated, and throws an Error when no connection was opened
            throw new DriverException('Can\'t set charset ' . $charset . ' (not connected)');
        }

        if (pg_set_client_encoding($this->connection, $charset) === -1) {
            throw new DriverException('Can\'t set charset ' . $charset . ' (' . pg_last_error($this->connection) . ')');
        }

        $this->currentCharset = $charset;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Connect the driver to the given database
     * @throws DriverException
     */
    public function setDatabase(string $database): static
    {
        if ($this->connection !== null) {
            return $this;
        }

        $dsn = $this->dsn . ' dbname=' . self::quoteDsnValue($database);
        $resource = @pg_connect($dsn);
        $this->database = $database;

        if ($resource === false) {
            // Values are quoted by connect(): redact the whole quoted value, escaped quotes included
            $dsn = preg_replace("/\\b(user|password)='(?:[^'\\\\]|\\\\.)*'/", '$1=<REDACTED>', $dsn);
            throw new DriverException('Connect Error: ' . $dsn);
        }
        $this->connection = $resource;

        return $this;
    }

    public function setLogger(?DriverLoggerInterface $logger = null): static
    {
        $this->logger = $logger;
        $this->objectHash = spl_object_hash($this);

        return $this;
    }


    /**
     * Execute the given query on the actual connection
     * @throws QueryException
     */
    public function execute(string $sql, array $params = [], ?CollectionInterface $collection = null): string|int|bool|array|CollectionInterface|null
    {
        [$convertedSql, $paramsOrder] = $this->convertParameters($sql);

        $this->validateConnection();

        $values = [];
        foreach (array_keys($paramsOrder) as $key) {
            if (!\array_key_exists($key, $params)) {
                throw QueryException::missingParameter((string) $key);
            }
            $values[] = $params[$key];
        }

        if ($this->logger !== null) {
            $this->logger->startQuery($sql, $params, $this->objectHash, $this->database);
        }

        // Silenced: a failed query raises a warning, which an error handler may turn into an exception, before
        // the QueryException below
        try {
            if ($values === []) {
                $result = @pg_query($this->connection, $convertedSql);
            } else {
                $result = @pg_query_params($this->connection, $convertedSql, $values);
            }
        } finally {
            if ($this->logger !== null) {
                $this->logger->stopQuery();
            }
        }

        if ($result === false) {
            throw new QueryException(pg_last_error($this->connection) . ' (Query: ' . $convertedSql . ')');
        }
        $this->result = $result;


        if (!$collection instanceof CollectionInterface) {
            $resultStatus = pg_result_status($this->result);
            if ($resultStatus === \PGSQL_TUPLES_OK) {
                return pg_fetch_assoc($this->result);
            }
            return $resultStatus;
        }

        return $this->setCollectionWithResult($convertedSql, $collection);
    }

    /**
     * @param string $sql
     * @param CollectionInterface $collection
     * @return CollectionInterface
     * @throws QueryException
     *
     * @internal
     */
    protected function setCollectionWithResult($sql, CollectionInterface $collection): CollectionInterface
    {
        $result = new Result();
        $result->setConnectionName($this->name);
        $result->setDatabase($this->database);
        $result->setResult($this->result);
        $result->setQuery($sql);
        $collection->set($result);

        return $collection;
    }

    /**
     * Prepare the given query against the current connection
     * @param string $originalSQL
     * @return Statement|StatementInterface
     * @throws QueryException
     */
    public function prepare(string $originalSQL): StatementInterface
    {
        [$sql, $paramsOrder] = $this->convertParameters($originalSQL);

        $statementName = sha1($originalSQL);

        if (isset($this->preparedQueries[$statementName])) {
            return $this->preparedQueries[$statementName];
        }

        $this->validateConnection();

        $statement = new Statement($statementName, $paramsOrder, $this->name, $this->database);

        if ($this->logger !== null) {
            $this->logger->startPrepare($originalSQL, $this->objectHash, $this->database);
            $statement->setLogger($this->logger);
        }
        try {
            $result = @pg_prepare($this->connection, $statementName, $sql);
        } finally {
            if ($this->logger !== null) {
                $this->logger->stopPrepare($statementName);
            }
        }

        if ($result === false) {
            throw new QueryException(pg_last_error($this->connection) . ' (Query: ' . $sql . ')');
        }

        // getAffectedRows() reports the last query, prepared or not. Weak: the driver holds the statement
        $driver = \WeakReference::create($this);
        $statement
            ->setConnection($this->connection)
            ->setQuery($sql)
            ->setResultHandler(static function ($result) use ($driver): void {
                $driver = $driver->get();
                if ($driver !== null) {
                    $driver->result = $result;
                }
            });

        $this->preparedQueries[$statementName] = $statement;

        return $statement;
    }

    /**
     * @return array
     */
    private function convertParameters(string $sql): array
    {
        $i           = 1;
        $paramsOrder = [];

        /**
         * Match : values (:name)
         * Don't match : values (\:name)
         * Don't match : HH:MI:SS
         * Don't match : ::string
         */
        $sql = preg_replace_callback(
            '/(?<!\b)(?<![:\\\]):(#?[a-zA-Z0-9_-]+)/',
            function (array $match) use (&$i, &$paramsOrder): string {
                if (isset($paramsOrder[$match[1]]) === false) {
                    $paramsOrder[$match[1]] = $i++;
                }

                return '$' . $paramsOrder[$match[1]];
            },
            (string) $sql
        );

        $sql = str_replace('\:', ':', $sql);

        return [$sql, $paramsOrder];
    }

    /**
     * Execute callback if an error has been encountered
     * @param callable $callback
     */
    public function ifIsError(callable $callback): static
    {
        $error = '';
        if ($this->connection !== null) {
            $error = pg_last_error($this->connection);
        }

        if ($error !== '') {
            $callback();
        }

        return $this;
    }

    /**
     * Execute the callback if the driver is not connected
     * @param callable $callback
     */
    public function ifIsNotConnected(callable $callback): static
    {
        if ($this->connection === null) {
            $callback();
        }

        return $this;
    }

    /**
     * Escape the given field name according to PGSQL Standards
     */
    public function escapeField(mixed $field = null): string
    {
        return '"' . $field . '"';
    }

    /**
     * Start a transaction against the current connection
     * @throws TransactionException
     */
    public function startTransaction(): void
    {
        if ($this->transactionOpened === true) {
            throw new TransactionException('Cannot start another transaction');
        }
        $this->validateConnection();
        if (@pg_query($this->connection, 'BEGIN') === false) {
            throw new TransactionException('Cannot start transaction: ' . pg_last_error($this->connection));
        }
        $this->transactionOpened = true;
    }

    /**
     * Commit the transaction against the current connection
     * @throws TransactionException
     */
    public function commit(): void
    {
        if ($this->transactionOpened === false) {
            throw new TransactionException('Cannot commit no transaction');
        }
        $this->validateConnection();
        // Even when the COMMIT fails, the transaction is over: rolled back by the server or lost with the connection
        $this->transactionOpened = false;
        $result = @pg_query($this->connection, 'COMMIT');
        if ($result === false) {
            throw new TransactionException('Cannot commit transaction: ' . pg_last_error($this->connection));
        }
        // The COMMIT of a transaction aborted by a failed statement succeeds, but answers ROLLBACK
        if (pg_result_status($result, \PGSQL_STATUS_STRING) !== 'COMMIT') {
            throw new TransactionException(
                'Cannot commit transaction: the transaction was aborted and has been rolled back'
            );
        }
    }

    /**
     * Rollback the actual opened transaction
     * @throws TransactionException
     */
    public function rollback(): void
    {
        if ($this->transactionOpened === false) {
            throw new TransactionException('Cannot rollback no transaction');
        }
        $this->validateConnection();
        $this->transactionOpened = false;
        if (@pg_query($this->connection, 'ROLLBACK') === false) {
            throw new TransactionException('Cannot rollback transaction: ' . pg_last_error($this->connection));
        }
    }

    /**
     * Return the last inserted id
     * @return int
     */
    public function getInsertedId(): int
    {
        $this->validateConnection();
        $resultResource = @pg_query($this->connection, 'SELECT lastval()');
        if ($resultResource === false) {
            throw new DriverException('Could not fetch last inserted id.');
        }
        $row = pg_fetch_row($resultResource);
        if ($row === false) {
            throw new DriverException('Could not fetch last inserted id.');
        }
        return (int) $row[0];
    }

    /**
     * Return the last inserted id for a sequence
     * @throws Exception
     */
    public function getInsertedIdForSequence(string $sequenceName): int
    {
        $this->validateConnection();
        $sql = "SELECT currval($1)";
        $resultResource = @pg_query_params($this->connection, $sql, [$sequenceName]);

        if ($resultResource === false) {
            throw new QueryException(pg_last_error($this->connection) . ' (Query: ' . $sql . ')');
        }

        $row = pg_fetch_row($resultResource);
        if ($row === false) {
            throw new QueryException('Could not fetch last inserted id. Details: '. pg_last_error($this->connection));
        }
        return (int) $row[0];
    }

    /**
     * Give the number of affected rows
     */
    public function getAffectedRows(): int
    {
        if ($this->result === null) {
            return 0;
        }

        return pg_affected_rows($this->result);
    }

    /**
     * @param $statement
     * @throws StatementException
     */
    public function closeStatement(string $statement): void
    {
        if (!isset($this->preparedQueries[$statement]) && !isset($this->forgottenPreparedQueries[$statement])) {
            throw new StatementException('Cannot close non prepared statement');
        }
        unset($this->preparedQueries[$statement], $this->forgottenPreparedQueries[$statement]);
    }

    /**
     * The prepared statements live in the server session: forget them when the connection is reset or closed
     */
    private function forgetPreparedQueries(): void
    {
        foreach ($this->preparedQueries as $statementName => $statement) {
            if ($statement instanceof Statement) {
                $statement->detach();
            }
            $this->forgottenPreparedQueries[$statementName] = true;
        }
        $this->preparedQueries = [];
    }

    /**
     * @return bool true on success, false on failure
     * @throws NeverConnectedException when you have not been connected to your database before trying to pint it.
     */
    public function ping(): bool
    {
        $this->validateConnection();

        // pg_ping() re-establishes a lost connection: the new backend has none of the prepared statements
        $backendPid = pg_get_pid($this->connection);
        $result = pg_ping($this->connection);
        if ($result === false || pg_get_pid($this->connection) !== $backendPid) {
            $this->forgetPreparedQueries();
        }

        if ($result && $this->currentCharset !== null) {
            pg_set_client_encoding($this->connection, $this->currentCharset);
        }
        if ($result && $this->currentTimezone !== null) {
            try {
                $this->applyTimezone($this->currentTimezone);
            } catch (DriverException) {
                // The session keeps the server default: the next setTimezone() with this timezone applies it again
                // and reports the error
                $this->currentTimezone = null;
            }
        }

        return $result;
    }

    /**
     * @throws DriverException when the server rejects the timezone, which is then not recorded
     */
    public function setTimezone(?string $timezone = null): void
    {
        if ($this->currentTimezone === $timezone) {
            return;
        }
        $this->validateConnection();
        $this->applyTimezone($timezone);
        $this->currentTimezone = $timezone;
    }

    /**
     * @throws DriverException
     */
    private function applyTimezone(?string $timezone): void
    {
        $value = $timezone === null ? 'DEFAULT' : '"' . $timezone . '"';
        if (@pg_query($this->connection, 'SET timezone = ' . $value . ';') === false) {
            throw new DriverException(
                'Can\'t set timezone ' . $timezone . ' (' . pg_last_error($this->connection) . ')'
            );
        }
    }

    public function reconnect(): bool
    {
        $this->connection = null;
        $this->forgetPreparedQueries();
        try {
            $this->setDatabase($this->database);
            if ($this->currentTimezone !== null) {
                $tz = $this->currentTimezone;
                $this->currentTimezone = null;
                try {
                    $this->setTimezone($tz);
                } catch (DriverException) {
                    // As in ping(): the session keeps the server default, the next setTimezone() reports the error
                }
            }
            if ($this->currentCharset !== null) {
                $charset = $this->currentCharset;
                $this->currentCharset = null;
                $this->setCharset($charset);
            }
        } catch (DriverException) {
            return false;
        }

        return true;
    }

    /**
     * @throws NeverConnectedException
     */
    private function validateConnection(): void
    {
        if ($this->connection === null) {
            throw new NeverConnectedException('Please connect to your database before trying to ping it.');
        }
    }
}
