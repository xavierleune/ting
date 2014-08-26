<?php

namespace fastorm\Driver;

interface DriverInterface
{

    public function connect($hostname, $username, $password, $port);
    public function prepare(
        $sql,
        callable $callback,
        StatementInterface $statement = null
    );
    public function setDatabase($database);
    public function ifIsError(callable $callback);
    public function ifIsNotConnected(callable $callback);
    public function escapeFields($fields, callable $callback);
    public function startTransaction();
    public function rollback();
    public function commit();
    public static function forConnectionKey($connectionName, $database, callable $callback);
}
