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

namespace CCMBenchmark\Ting\Driver\Mysqli;

use mysqli;
use mysqli_driver;
use Exception;
use mysqli_result;
use CCMBenchmark\Ting\Driver\StatementInterface;
use CCMBenchmark\Ting\Exceptions\ConnectionException;
use CCMBenchmark\Ting\Exceptions\DatabaseException;
use CCMBenchmark\Ting\Exceptions\DriverException;
use CCMBenchmark\Ting\Driver\DriverInterface;
use CCMBenchmark\Ting\Driver\LostTransactionTrait;
use CCMBenchmark\Ting\Driver\NeverConnectedException;
use CCMBenchmark\Ting\Driver\QueryException;
use CCMBenchmark\Ting\Exceptions\StatementException;
use CCMBenchmark\Ting\Exceptions\TransactionException;
use CCMBenchmark\Ting\Logger\DriverLoggerInterface;
use CCMBenchmark\Ting\Repository\CollectionInterface;
use mysqli_sql_exception;

class Driver implements DriverInterface
{
    private const RECONNECTION_PENDING = 'the connection was lost and could not be reopened, ping() retries it';

    protected string $name = '';

    protected mysqli_driver $driver;

    /**
     * Natively typed object: tests stand in for mysqli, whose properties cannot be read without a server
     * @var mysqli|null driver connection
     */
    protected ?object $connection = null;

    protected string $currentDatabase = '';

    protected ?string $currentCharset = null;

    protected ?string $currentTimezone = null;

    protected bool $connected = false;

    use LostTransactionTrait;

    protected bool $transactionOpened = false;

    /**
     * True when reconnect() dropped the connection but could not open a new one: the mysqli object is not usable
     * (any call throws an \Error), ping() retries the connection and queries throw until it succeeds.
     */
    protected bool $reconnectionPending = false;

    protected ?DriverLoggerInterface $logger = null;

    /**
     * hash of current object
     */
    protected string $objectHash = '';

    /**
     * Already prepared queries, by statement name then by database: one driver serves every database of a server
     * (see getConnectionKey()) and a statement reads and writes the database selected when it was prepared.
     *
     * @var array<string,array<string,StatementInterface>>
     */
    protected array $preparedQueries = [];

    /**
     * @var array<string,array<string,StatementInterface>> Old list of prepared queries, filled after a reconnect
     */
    protected array $oldPreparedQueries = [];

    /**
     * Match parameter in SQL
     *
     * Match : values (:name)
     * Don't match : values (\:name)
     * Don't match : HH:MI:SS
     * Don't match : ::string
     */
    private string $parameterMatching = '(?<!\b)(?<![:\\\]):(#?[a-zA-Z0-9_-]+)';

    /**
     * Data used to open a connection.
     */
    private array $connectionConfig = [];

    /**
     * @param mysqli|null $connection
     */
    public function __construct(?object $connection = null, ?mysqli_driver $driver = null)
    {
        if ($connection === null) {
            $this->createConnection();
        } else {
            $this->connection = $connection;
        }

        $this->driver = $driver ?? new mysqli_driver();
    }

    /**
     * One driver serves every database of a server: the database is not part of the key. Serialized, the values
     * cannot be mixed up (a separator could appear in any of them), and null stays distinct from ''
     */
    public static function getConnectionKey(array $connectionConfig, string $database): string
    {
        return serialize([
            $connectionConfig['host'],
            (string) $connectionConfig['port'],
            $connectionConfig['user'],
            $connectionConfig['password'],
        ]);
    }

    /**
     * @throws ConnectionException
     */
    public function connect(string $hostname, ?string $username, ?string $password, int $port = 3306): static
    {
        $this->driver->report_mode = MYSQLI_REPORT_STRICT;

        $this->connectionConfig = [
            'hostname' => $hostname,
            'username' => $username,
            'password' => $password,
            'port' => $port
        ];

        try {
            $this->connected = $this->connection->real_connect($hostname, $username, $password, null, $port);
        } catch (Exception $e) {
            throw new ConnectionException('Connect Error: ' . $e->getMessage(), $e->getCode());
        }

        return $this;
    }

    /**
     * Close the connection to the database
     */
    public function close(): static
    {
        if ($this->connected === true) {
            // After a failed reconnect() there is no open connection to close
            if ($this->reconnectionPending === false) {
                $this->connection->close();
            }
            $this->connected = false;
            $this->reconnectionPending = false;
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

        if ($this->reconnectionPending === true) {
            // Applied by the next reconnect()
            $this->currentCharset = $charset;
            return;
        }

        if ($this->connection->set_charset($charset) === false) {
            throw new DriverException('Can\'t set charset ' . $charset . ' (' . $this->connection->error . ')');
        }
        $this->currentCharset = $charset;
    }

    /**
     * @param DriverLoggerInterface $logger
     */
    public function setLogger(?DriverLoggerInterface $logger = null): static
    {
        $this->logger = $logger;
        $this->objectHash = spl_object_hash($this);

        return $this;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @throws DatabaseException
     */
    public function setDatabase(string $database): static
    {
        if ($this->currentDatabase === $database) {
            return $this;
        }

        if ($this->reconnectionPending === true) {
            // Selected by the next reconnect()
            $this->currentDatabase = $database;
            return $this;
        }

        $this->connection->select_db($database);

        $this->ifIsError(function (): void {
            throw new DatabaseException('Select database error: ' . $this->connection->error, $this->connection->errno);
        });

        $this->currentDatabase = $database;

        return $this;
    }

    /**
     * @param callable $callback
     * @return $this
     */
    public function ifIsError(callable $callback): static
    {
        if ($this->connection->error !== '') {
            $callback($this->connection->error);
        }

        return $this;
    }

    /**
     * @param string $sql
     * @param array $params
     * @param CollectionInterface $collection
     * @throws QueryException
     */
    public function execute(string $sql, array $params = [], ?CollectionInterface $collection = null): bool|CollectionInterface|array|null
    {
        $this->assertNoReconnectionPending();

        // One pass: unescaping \: after the substitution would alter the values
        $sql = preg_replace_callback(
            '/\\\\:|' . $this->parameterMatching . '/',
            function (array $match) use ($params) {
                if ($match[0] === '\\:') {
                    return ':';
                }
                if (!\array_key_exists($match[1], $params)) {
                    throw QueryException::missingParameter($match[1]);
                }

                return (string) $this->quoteValue($params[$match[1]]);
            },
            $sql
        );

        if ($this->logger !== null) {
            $this->logger->startQuery($sql, $params, $this->objectHash, $this->currentDatabase);
        }

        try {
            $result = $this->connection->query($sql);
        } finally {
            // Also when mysqli throws (report mode MYSQLI_REPORT_ERROR)
            if ($this->logger !== null) {
                $this->logger->stopQuery();
            }
        }

        if ($result === false) {
            throw new QueryException($this->connection->error . ' (Query: ' . $sql . ')', $this->connection->errno);
        }

        if (!$collection instanceof CollectionInterface) {
            if ($result === true) {
                return true;
            }

            return $result->fetch_assoc();
        }
        if ($result === true) {
            return true;
        }
        return $this->setCollectionWithResult($result, $collection);
    }

    /**
     * Quote value according to the type of variable
     *
     * Strings are quoted with single quotes: real_escape_string() only doubles single quotes under the sql_mode
     * NO_BACKSLASH_ESCAPES, and a double-quoted string is an identifier under ANSI_QUOTES.
     */
    protected function quoteValue(mixed $value): string | int | float
    {
        return match (\gettype($value)) {
            "boolean" => (int) $value,
            "integer", "double" => $value,
            "NULL" => 'null',
            default => "'" . $this->connection->real_escape_string($value) . "'",
        };
    }

    /**
     * @param mysqli_result $resultData
     * @param CollectionInterface $collection
     * @return CollectionInterface
     */
    protected function setCollectionWithResult(object $resultData,CollectionInterface $collection): CollectionInterface
    {
        $result = new Result();
        $result->setConnectionName($this->name);
        $result->setDatabase($this->currentDatabase);
        $result->setResult($resultData);
        $collection->set($result);

        return $collection;
    }

    /**
     * @param string $sql
     * @return StatementInterface
     * @throws QueryException
     */
    public function prepare(string $sql): StatementInterface
    {
        $this->assertNoReconnectionPending();

        $statementName = sha1($sql);
        $database = $this->currentDatabase;
        if (isset($this->preparedQueries[$statementName][$database])) {
            return $this->preparedQueries[$statementName][$database];
        }
        $paramsOrder = [];
        $sql = preg_replace_callback(
            '/' . $this->parameterMatching . '/',
            function (array $match) use (&$paramsOrder): string {
                $paramsOrder[] = $match[1];
                return '?';
            },
            $sql
        );

        $sql = str_replace('\:', ':', $sql);

        if ($this->logger !== null) {
            $this->logger->startPrepare($sql, $this->objectHash, $this->currentDatabase);
        }
        $driverStatement = false;
        try {
            $driverStatement = $this->connection->prepare($sql);
        } finally {
            // Also on failure (false, or a mysqli_sql_exception under MYSQLI_REPORT_ERROR): named after the SQL
            if ($this->logger !== null) {
                $this->logger->stopPrepare(
                    $driverStatement !== false ? spl_object_hash($driverStatement) : $statementName
                );
            }
        }

        if ($driverStatement === false) {
            throw new QueryException($this->connection->error . ' (Query: ' . $sql . ')', $this->connection->errno);
        }

        $statement = new Statement($driverStatement, $paramsOrder, $this->name, $database);
        $statement->setLogger($this->logger);

        $this->preparedQueries[$statementName][$database] = $statement;

        return $statement;
    }

    /**
     * @param callable $callback
     * @return $this
     */
    public function ifIsNotConnected(callable $callback): static
    {
        if ($this->connected === false) {
            $callback();
        }

        return $this;
    }

    public function escapeField(mixed $field = null): string
    {
        return '`' . $field . '`';
    }

    /**
     * @throws TransactionException
     */
    public function startTransaction(): void
    {
        if ($this->transactionOpened === true) {
            throw new TransactionException('Cannot start another transaction');
        }
        $this->forgetLostTransaction();
        $this->runTransactionCommand('start', fn () => $this->connection->begin_transaction());
        $this->transactionOpened = true;
    }

    /**
     * @throws TransactionException
     */
    public function commit(): void
    {
        $this->assertTransactionNotLost();
        if ($this->transactionOpened === false) {
            throw new TransactionException('Cannot commit no transaction');
        }
        // Even when the COMMIT fails, the transaction is over: rolled back by the server or lost with the connection
        $this->transactionOpened = false;
        $this->runTransactionCommand('commit', fn () => $this->connection->commit());
    }

    /**
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
        $this->transactionOpened = false;
        $this->runTransactionCommand('rollback', fn () => $this->connection->rollback());
    }

    /**
     * @param callable(): bool $command
     * @throws TransactionException when the command fails, whatever the mysqli report mode
     */
    private function runTransactionCommand(string $action, callable $command): void
    {
        if ($this->reconnectionPending === true) {
            throw new TransactionException('Cannot ' . $action . ' transaction: ' . self::RECONNECTION_PENDING);
        }

        try {
            $succeeded = $command() !== false;
        } catch (mysqli_sql_exception $exception) {
            throw new TransactionException(
                'Cannot ' . $action . ' transaction: ' . $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        }

        if ($succeeded === false) {
            throw new TransactionException(
                'Cannot ' . $action . ' transaction: ' . $this->connection->error,
                (int) $this->connection->errno
            );
        }
    }

    /**
     * @throws QueryException
     */
    public function getInsertedId(): int
    {
        $this->assertNoReconnectionPending();

        return (int) $this->connection->insert_id;
    }

    /**
     * @throws QueryException
     */
    public function getAffectedRows(): int|string
    {
        $this->assertNoReconnectionPending();

        if ($this->connection->affected_rows < 0) {
            return 0;
        }

        return $this->connection->affected_rows;
    }

    /**
     * Closes the statement prepared for the given name on every database
     *
     * @param string $statement name of the statement (sha1 of its SQL, see PreparedQuery::getStatementName())
     * @throws StatementException
     */
    public function closeStatement(string $statement): void
    {
        if (!isset($this->preparedQueries[$statement]) && !isset($this->oldPreparedQueries[$statement])) {
            throw new StatementException('Cannot close non prepared statement');
        }
        unset($this->preparedQueries[$statement], $this->oldPreparedQueries[$statement]);
    }

    /**
     * Ping server and reconnect if connection has been lost.
     *
     * @return bool true on success, false on failure
     *
     * @throws NeverConnectedException when you have not been connected to your database before trying to ping it.
     */
    public function ping(): bool
    {
        if ($this->connected === false) {
            throw new NeverConnectedException('Please connect to your database before trying to ping it.');
        }

        // mysqli.reconnect has been removed in PHP 8.2 and mysqli_ping has been deprecated in PHP 8.4 as it has no effect, so we cannot rely on ping
        // We need to reimplement the logic here.

        // First try a simple query, if it works we don't need to do anything
        if ($this->reconnectionPending === false) {
            try {
                $result = $this->connection->query('SELECT 1');
                if ($result !== false) {
                    return true;
                }
            } catch (mysqli_sql_exception) { }
        }

        return $this->reconnect();
    }

    /**
     * @throws DriverException when the server rejects the timezone, which is then not recorded
     */
    public function setTimezone(?string $timezone = null): void
    {
        if ($this->currentTimezone === $timezone) {
            return;
        }

        if ($this->reconnectionPending === true) {
            // Applied by the next reconnect()
            $this->currentTimezone = $timezone;
            return;
        }

        $this->applyTimezone($timezone);
        $this->currentTimezone = $timezone;
    }

    /**
     * The timezone is sent as a string literal: a double-quoted string is an identifier under the sql_mode ANSI_QUOTES
     *
     * @throws DriverException
     */
    private function applyTimezone(?string $timezone): void
    {
        $value = $timezone === null ? 'DEFAULT' : $this->quoteValue($timezone);

        try {
            $succeeded = $this->connection->query('SET time_zone = ' . $value . ';') !== false;
        } catch (mysqli_sql_exception $exception) {
            throw new DriverException(
                'Can\'t set timezone ' . $timezone . ' (' . $exception->getMessage() . ')',
                $exception->getCode(),
                $exception
            );
        }

        if ($succeeded === false) {
            throw new DriverException(
                'Can\'t set timezone ' . $timezone . ' (' . $this->connection->error . ')',
                (int) $this->connection->errno
            );
        }
    }

    private function createConnection(): void
    {
        $connection = mysqli_init();
        if ($connection !== false) {
            $this->connection = $connection;
            $this->connection->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        }
    }

    /**
     * Replaces the connection with a new one.
     *
     * On failure the driver stays connected, without usable connection: ping() retries, queries throw.
     *
     * @throws NeverConnectedException when connect() has not been called: there is nothing to reconnect to
     */
    public function reconnect(): bool
    {
        $config = $this->connectionConfig;
        if ($config === []) {
            throw new NeverConnectedException('Please connect to your database before trying to reconnect.');
        }

        // The previous connection is dropped whatever happens: its statements cannot be reused, its transaction is lost
        $this->loseTransaction();
        $this->oldPreparedQueries = array_replace_recursive($this->oldPreparedQueries, $this->preparedQueries);
        $this->preparedQueries = [];
        $this->reconnectionPending = true;

        try {
            $this->createConnection();
            $connected = $this->connection->real_connect($config['hostname'], $config['username'], $config['password'], $this->currentDatabase, $config['port']);
            if ($connected === false) {
                return false;
            }

            if ($this->currentCharset !== null) {
                $this->connection->set_charset($this->currentCharset);
            }

            if ($this->currentTimezone !== null) {
                try {
                    $this->applyTimezone($this->currentTimezone);
                } catch (DriverException) {
                    // e.g. set while the reconnection was pending: the session keeps the server default, and the
                    // next setTimezone() with this timezone applies it again and reports the error
                    $this->currentTimezone = null;
                }
            }
        } catch (\Exception) {
            return false;
        }

        $this->connected = true;
        $this->reconnectionPending = false;

        return true;
    }

    /**
     * @throws QueryException
     */
    private function assertNoReconnectionPending(): void
    {
        if ($this->reconnectionPending === true) {
            throw new QueryException(self::RECONNECTION_PENDING);
        }
    }
}
