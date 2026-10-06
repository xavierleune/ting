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
use CCMBenchmark\Ting\Driver\LostTransactionTrait;
use CCMBenchmark\Ting\Driver\Exception;
use CCMBenchmark\Ting\Driver\NeverConnectedException;
use CCMBenchmark\Ting\Driver\QueryException;
use CCMBenchmark\Ting\Driver\SequenceAwareDriverInterface;
use CCMBenchmark\Ting\Driver\StatementInterface;
use CCMBenchmark\Ting\Exceptions\DriverException;
use CCMBenchmark\Ting\Exceptions\StatementException;
use CCMBenchmark\Ting\Exceptions\TransactionException;
use CCMBenchmark\Ting\Logger\DriverLoggerInterface;
use CCMBenchmark\Ting\Repository\CollectionInterface;

/**
 * @phpstan-import-type ConnectionParameters from DriverInterface
 */
class Driver implements DriverInterface, SequenceAwareDriverInterface
{
    /**
     * @var string
     */
    protected string $name = '';

    protected string $database  = '';

    protected ?string $currentCharset = null;

    protected ?string $currentTimezone = null;

    /**
     * Natively typed object: PgSql\Connection is final and only built by a server, tests stand in for it
     * @var Connection|null
     */
    protected ?object $connection = null;

    use LostTransactionTrait;

    protected bool $transactionOpened = false;

    protected ?DriverLoggerInterface $logger = null;

    /**
     * spl_object_hash of current object
     */
    protected string $objectHash = '';

    /**
     * Natively typed object, as $connection
     * @var \PgSql\Result|null
     */
    protected ?object $result = null;

    /**
     * @var array<string, StatementInterface>
     */
    protected array $preparedQueries = [];

    /**
     * Names of the statements forgotten when the connection was reset, that closeStatement() still accepts
     * @var array<string, true>
     */
    protected array $forgottenPreparedQueries = [];

    protected string $dsn = '';

    /**
     * A connection is bound to its database. Serialized, the values cannot be mixed up (a separator could appear in
     * any of them), and null stays distinct from ''
     *
     * @param ConnectionParameters $connectionConfig
     */
    public static function getConnectionKey(array $connectionConfig, string $database): string
    {
        return serialize([
            $connectionConfig['host'],
            (string) $connectionConfig['port'],
            $connectionConfig['user'],
            $connectionConfig['password'],
            $database,
        ]);
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
            $this->loseTransaction();
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
     *
     * @template T
     * @param array<string, mixed> $params parameter name => value
     * @param CollectionInterface<T>|null $collection
     * @return ($collection is null ? array<int|string, string|null>|false|int : CollectionInterface<T>) without
     *         collection: the first row, false without row, the status of the result without result set
     * @throws QueryException
     */
    public function execute(string $sql, array $params = [], ?CollectionInterface $collection = null): string|int|bool|array|CollectionInterface|null
    {
        [$convertedSql, $paramsOrder] = $this->convertParameters($sql);

        $connection = $this->validConnection();

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
                $result = @pg_query($connection, $convertedSql);
            } else {
                $result = @pg_query_params($connection, $convertedSql, $values);
            }
        } finally {
            if ($this->logger !== null) {
                $this->logger->stopQuery();
            }
        }

        if ($result === false) {
            throw new QueryException(pg_last_error($connection) . ' (Query: ' . $convertedSql . ')');
        }
        $this->result = $result;


        if (!$collection instanceof CollectionInterface) {
            $resultStatus = pg_result_status($result);
            if ($resultStatus === \PGSQL_TUPLES_OK) {
                return pg_fetch_assoc($result);
            }
            return $resultStatus;
        }

        return $this->setCollectionWithResult($result, $convertedSql, $collection);
    }

    /**
     * @template T
     * @param \PgSql\Result $resultResource
     * @param string $sql
     * @param CollectionInterface<T> $collection
     * @return CollectionInterface<T>
     * @throws QueryException
     *
     * @internal
     */
    protected function setCollectionWithResult(object $resultResource, string $sql, CollectionInterface $collection): CollectionInterface
    {
        $result = new Result();
        $result->setConnectionName($this->name);
        $result->setDatabase($this->database);
        $result->setResult($resultResource);
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

        $connection = $this->validConnection();

        $statement = new Statement($statementName, $paramsOrder, $this->name, $this->database);

        if ($this->logger !== null) {
            $this->logger->startPrepare($originalSQL, $this->objectHash, $this->database);
            $statement->setLogger($this->logger);
        }
        try {
            $result = @pg_prepare($connection, $statementName, $sql);
        } finally {
            if ($this->logger !== null) {
                $this->logger->stopPrepare($statementName);
            }
        }

        if ($result === false) {
            throw new QueryException(pg_last_error($connection) . ' (Query: ' . $sql . ')');
        }

        // getAffectedRows() reports the last query, prepared or not. Weak: the driver holds the statement
        $driver = \WeakReference::create($this);
        $statement
            ->setConnection($connection)
            ->setQuery($sql)
            ->setResultHandler(static function (object $result) use ($driver): void {
                $driver = $driver->get();
                if ($driver !== null) {
                    $driver->result = $result;
                }
            });

        $this->preparedQueries[$statementName] = $statement;

        return $statement;
    }

    /**
     * @return array{0: string, 1: array<int|string, int>} the SQL with numbered placeholders, and the position of each
     *                                                     parameter by name (an integer for a numeric name)
     * @throws QueryException when PCRE fails (e.g. backtrack limit)
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
            $sql
        );

        if ($sql === null) {
            throw new QueryException('Cannot parse the parameters of the query: ' . preg_last_error_msg());
        }

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
        // An embedded double quote is doubled, as PostgreSQL expects in a quoted identifier
        return '"' . str_replace('"', '""', (string) $field) . '"';
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
        $this->forgetLostTransaction();
        $connection = $this->validConnection();
        if (@pg_query($connection, 'BEGIN') === false) {
            throw new TransactionException('Cannot start transaction: ' . pg_last_error($connection));
        }
        $this->transactionOpened = true;
    }

    /**
     * Commit the transaction against the current connection
     * @throws TransactionException
     */
    public function commit(): void
    {
        $this->assertTransactionNotLost();
        if ($this->transactionOpened === false) {
            throw new TransactionException('Cannot commit no transaction');
        }
        $connection = $this->validConnection();
        // Even when the COMMIT fails, the transaction is over: rolled back by the server or lost with the connection
        $this->transactionOpened = false;
        $result = @pg_query($connection, 'COMMIT');
        if ($result === false) {
            throw new TransactionException('Cannot commit transaction: ' . pg_last_error($connection));
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
        if ($this->forgetLostTransaction() === true) {
            return;
        }
        if ($this->transactionOpened === false) {
            throw new TransactionException('Cannot rollback no transaction');
        }
        $connection = $this->validConnection();
        $this->transactionOpened = false;
        if (@pg_query($connection, 'ROLLBACK') === false) {
            throw new TransactionException('Cannot rollback transaction: ' . pg_last_error($connection));
        }
    }

    /**
     * Return the last inserted id
     * @return int
     */
    public function getInsertedId(): int
    {
        $resultResource = @pg_query($this->validConnection(), 'SELECT lastval()');
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
        $connection = $this->validConnection();
        $sql = "SELECT currval($1)";
        $resultResource = @pg_query_params($connection, $sql, [$sequenceName]);

        if ($resultResource === false) {
            throw new QueryException(pg_last_error($connection) . ' (Query: ' . $sql . ')');
        }

        $row = pg_fetch_row($resultResource);
        if ($row === false) {
            throw new QueryException('Could not fetch last inserted id. Details: '. pg_last_error($connection));
        }
        return (int) $row[0];
    }

    /**
     * Give the number of affected rows
     *
     * @return int<0, max>
     */
    public function getAffectedRows(): int
    {
        if ($this->result === null) {
            return 0;
        }

        // Never negative (libpq gives the count as a string of digits, possibly empty), but typed as a plain int
        return max(0, pg_affected_rows($this->result));
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
        $connection = $this->validConnection();

        // pg_ping() re-establishes a lost connection: the new backend has none of the prepared statements, and the
        // server rolled back the transaction of the lost one
        $backendPid = pg_get_pid($connection);
        $result = pg_ping($connection);
        if ($result === false || pg_get_pid($connection) !== $backendPid) {
            $this->forgetPreparedQueries();
            $this->loseTransaction();
        }

        if ($result && $this->currentCharset !== null) {
            if (pg_set_client_encoding($connection, $this->currentCharset) === -1) {
                // As for the timezone: the session keeps the server default, the next setCharset() with this
                // charset applies it again and reports the error
                $this->currentCharset = null;
            }
        }
        if ($result && $this->currentTimezone !== null) {
            try {
                $this->applyTimezone($connection, $this->currentTimezone);
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
        $this->applyTimezone($this->validConnection(), $timezone);
        $this->currentTimezone = $timezone;
    }

    /**
     * @param Connection $connection
     * @throws DriverException
     */
    private function applyTimezone(object $connection, ?string $timezone): void
    {
        $value = $timezone === null ? 'DEFAULT' : '"' . $timezone . '"';
        if (@pg_query($connection, 'SET timezone = ' . $value . ';') === false) {
            throw new DriverException(
                'Can\'t set timezone ' . $timezone . ' (' . pg_last_error($connection) . ')'
            );
        }
    }

    public function reconnect(): bool
    {
        if ($this->dsn === '') {
            // Without connect(), libpq would connect with its default settings
            throw new NeverConnectedException('Please connect to your database before trying to reconnect.');
        }

        $this->connection = null;
        $this->forgetPreparedQueries();
        $this->loseTransaction();
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
                try {
                    $this->setCharset($charset);
                } catch (DriverException) {
                    // As in ping(): the session keeps the server default, the next setCharset() reports the error
                }
            }
        } catch (DriverException) {
            return false;
        }

        return true;
    }

    /**
     * @return Connection
     * @throws NeverConnectedException
     */
    private function validConnection(): object
    {
        if ($this->connection === null) {
            throw new NeverConnectedException('Please connect to your database before trying to ping it.');
        }

        return $this->connection;
    }
}
