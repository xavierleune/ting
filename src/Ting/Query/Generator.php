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

namespace CCMBenchmark\Ting\Query;

use CCMBenchmark\Ting\Connection;
use CCMBenchmark\Ting\Driver\DriverInterface;
use CCMBenchmark\Ting\Repository\CollectionFactoryInterface;

class Generator
{
    /**
     * @param list<string> $fields column names of the table
     *
     * @internal
     */
    public function __construct(
        protected Connection $connection,
        protected QueryFactoryInterface $queryFactory,
        protected string $schemaName,
        protected string $tableName,
        protected array $fields
    ) {
    }

    /**
     * @param DriverInterface $driver
     * @return string
     */
    protected function getTarget(DriverInterface $driver): string
    {
        $schema = '';
        if ($this->schemaName !== '') {
            $schema = $driver->escapeField($this->schemaName) . '.';
        }

        return $schema . $driver->escapeField($this->tableName);
    }

    /**
     * @param list<string>    $fields escaped column names
     * @param DriverInterface $driver
     * @return string
     */
    protected function getSelect(array $fields, DriverInterface $driver): string
    {
        return 'SELECT ' . implode(', ', $fields) . ' FROM ' .
            $this->getTarget($driver);
    }


    /**
     * @param bool $forcePrimary
     * @return DriverInterface
     */
    protected function getDriver(bool $forcePrimary): DriverInterface
    {
        $driver = $forcePrimary === true ? $this->connection->primary() : $this->connection->replica();

        return $driver;
    }

    /**
     * @template T
     * @param CollectionFactoryInterface<T> $collectionFactory
     * @return QueryInterface<T>
     *
     * @internal
     */
    public function getAll(
        CollectionFactoryInterface $collectionFactory,
        bool $forcePrimary = false
    ): QueryInterface {
        $driver = $this->getDriver($forcePrimary);

        $fields = $this->escapeFields($this->fields, $driver);

        $sql = $this->getSelect($fields, $driver);

        $query = $this->queryFactory->get($sql, $this->connection, $collectionFactory);

        if ($forcePrimary === true) {
            $query->selectPrimary(true);
        }

        return $query;
    }

    /**
     * Returns a Query, allowing to fetch an object by an associative array (column => value).
     *
     * @template T
     * @param array<string, mixed> $primariesValue column name => value
     * @param CollectionFactoryInterface<T> $collectionFactory
     * @return QueryInterface<T>
     *
     * @internal
     */
    public function getOneByCriteria(
        array $primariesValue,
        CollectionFactoryInterface $collectionFactory,
        bool $forcePrimary = false
    ): QueryInterface {
        $driver = $this->getDriver($forcePrimary);

        [$sql, $params] = $this->getSqlAndParamsByCriteria($primariesValue, $driver);
        $sql .=  ' LIMIT 1';

        $query = $this->queryFactory->get($sql, $this->connection, $collectionFactory);
        $query->setParams($params);

        if ($forcePrimary === true) {
            $query->selectPrimary(true);
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $criteria column name => value
     * @param DriverInterface $driver
     * @return array{0: string, 1: array<string, mixed>} the SQL and its parameters
     */
    protected function getSqlAndParamsByCriteria(array $criteria, DriverInterface $driver): array
    {
        $fields = $this->escapeFields($this->fields, $driver);

        $sql = $this->getSelect($fields, $driver);

        $criteriaFields = $this->escapeFields(array_keys($criteria), $driver);
        [$conditions, $params] = $this->generateConditionAndParams($criteriaFields, $criteria);
        $sql .= ' WHERE ' . implode(' AND ', $conditions);

        return [$sql, $params];
    }

    /**
     * @template T
     * @param array<string, mixed>       $criteria column name => value
     * @param CollectionFactoryInterface<T> $collectionFactory
     * @param array<string, string>      $order column name => direction
     * @return QueryInterface<T>
     */
    public function getByCriteria(
        array $criteria,
        CollectionFactoryInterface $collectionFactory,
        bool $forcePrimary = false,
        array $order = [],
        int $limit = 0
    ): QueryInterface {
        $driver = $this->getDriver($forcePrimary);

        [$sql, $params] = $this->getSqlAndParamsByCriteria($criteria, $driver);
        $this->updateSQLWithOrderAndLimit($sql, $driver, $order, $limit);

        $query = $this->queryFactory->get($sql, $this->connection, $collectionFactory);
        $query->setParams($params);

        if ($forcePrimary === true) {
            $query->selectPrimary(true);
        }

        return $query;
    }

    /**
     * Returns a PreparedQuery to insert an object in database.
     *
     * @param array<string, mixed> $values associative array : columnName => value
     * @return PreparedQuery<mixed> a writing query: no collection factory
     *
     * @internal
     */
    public function insert(array $values): PreparedQuery
    {
        $driver = $this->getDriver(true);
        $fields = $this->escapeFields(array_keys($values), $driver);

        $params = [];
        $i = 0;
        foreach ($values as $column => $value) {
            $params[$this->parameterName('v', ++$i, $column)] = $value;
        }

        $sql = 'INSERT INTO ' . $this->getTarget($driver) . ' ('
            . implode(', ', $fields) . ') VALUES ('
            . implode(', ', array_map(fn (string $name): string => ':' . $name, array_keys($params))) . ')';

        $query = $this->queryFactory->getPrepared($sql, $this->connection);

        $query->setParams($params);

        return $query;
    }

    /**
     * Returns a prepared query to update values in database.
     *
     * @param array<string, mixed> $values         associative array : columnName => value
     * @param array<string, mixed> $primariesValue columnName => value
     * @return PreparedQuery<mixed> a writing query: no collection factory
     *
     * @internal
     */
    public function update(array $values, array $primariesValue): PreparedQuery
    {
        $driver = $this->getDriver(true);

        $sql = 'UPDATE ' . $this->getTarget($driver) . ' SET ';
        $set = [];
        $params = [];
        $i = 0;
        foreach ($values as $column => $value) {
            $name = $this->parameterName('v', ++$i, $column);
            $set[] = $driver->escapeField($column) . ' = :' . $name;
            $params[$name] = $value;
        }
        $sql .= implode(', ', $set);

        $primaryFields = $this->escapeFields(array_keys($primariesValue), $driver);

        [$conditions, $conditionParams] = $this->generateConditionAndParams($primaryFields, $primariesValue);

        // Not array_merge(): it would renumber the integer keys. The names never overlap ("v" and "w" prefixes)
        $params += $conditionParams;

        $sql .= ' WHERE ' . implode(' AND ', $conditions);

        $query = $this->queryFactory->getPrepared($sql, $this->connection);
        $query->setParams($params);

        return $query;
    }

    /**
     * @param array<string, mixed> $primariesKeyValue columnName => value
     * @return PreparedQuery<mixed> a writing query: no collection factory
     *
     * @internal
     */
    public function delete(array $primariesKeyValue): PreparedQuery
    {
        $driver = $this->getDriver(true);

        $sql = 'DELETE FROM ' . $this->getTarget($driver);

        $primaryFields = $this->escapeFields(array_keys($primariesKeyValue), $driver);

        [$conditions, $params] = $this->generateConditionAndParams($primaryFields, $primariesKeyValue);

        $sql .= ' WHERE ' . implode(' AND ', $conditions);

        $query = $this->queryFactory->getPrepared($sql, $this->connection);
        $query->setParams($params);

        return $query;
    }

    /**
     * Protect every fields provided, using the driver provided.
     *
     * @param list<string>    $fields
     * @param DriverInterface $driver
     *
     * @return list<string>
     */
    protected function escapeFields(array $fields, DriverInterface $driver): array
    {
        return array_map(
            fn (string $field) => $driver->escapeField($field),
            $fields
        );
    }

    /**
     * @param list<string> $fields escaped fields names, in the order of $values
     * @param array<string, mixed> $values column name => value, each values can be a value or an array
     *
     * @return array{0: list<string>, 1: array<string, mixed>} the conditions and their parameters
     */
    protected function generateConditionAndParams(array $fields, array $values): array
    {
        $conditions = [];
        $params = [];
        $i = 0;

        foreach ($values as $column => $value) {
            $name = $this->parameterName('w', $i + 1, $column);
            if ($value === null) {
                $conditions[] = $fields[$i] . ' IS NULL';
            } elseif (is_array($value)) {
                $placeholders = [];
                $j = 0;
                foreach ($value as $v) {
                    $j++;
                    $placeholders[] = ':' . $name . '__' . $j;
                    $params[$name . '__' . $j] = $v;
                }

                $conditions[] = $fields[$i] . ' IN (' . implode(',', $placeholders) . ')';
            } else {
                $conditions[] = $fields[$i] . ' = :' . $name;
                $params[$name] = $value;
            }
            $i++;
        }

        return [$conditions, $params];
    }

    /**
     * Name of the parameter of a column, independent of the raw column name.
     *
     * A column name may hold characters the drivers do not accept in a parameter name (space, accent, dot…),
     * be numeric (an integer key in a PHP array), or look like a generated name (a column "a__1" next to an IN
     * list on "a"). The name is therefore "<role><index>_<column>": the role ("v" for a value written by INSERT
     * or UPDATE, "w" for a WHERE condition) and the 1-based position of the column in that role make it unique
     * in the query, since the index ends at the first "_"; the column name, every character but [a-zA-Z0-9_]
     * replaced by "_", keeps it readable in logs. An IN list suffixes it with "__<n>".
     */
    protected function parameterName(string $role, int $index, int|string $column): string
    {
        return $role . $index . '_' . preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $column);
    }

    /**
     * @param string          $sql
     * @param DriverInterface $driver
     * @param array<string, string> $order column name => direction
     * @param int             $limit
     * @return void
     */
    protected function updateSQLWithOrderAndLimit(string &$sql, DriverInterface $driver, array $order = [], int $limit = 0): void
    {
        if (count($order) > 0) {
            $sql .= $this->generateOrder($order, $driver);
        }

        if ($limit > 0) {
            $sql .= $this->generateLimit($limit);
        }
    }

    /**
     * Generate Order params to add to query
     *
     * @param array<string, string> $orderList column name => direction
     * @param DriverInterface   $driver
     * @return string
     */
    protected function generateOrder(array $orderList, DriverInterface $driver): string
    {
        $fields = $this->escapeFields(array_keys($orderList), $driver);

        $orderClause = '';
        $orderCriteria = [];

        $i = 0;
        foreach ($orderList as $value) {
            $value = strtoupper((string) $value);
            if (\in_array($value, ['ASC', 'DESC'])) {
                $orderCriteria[] = $fields[$i] . ' ' . $value;
            }
            $i++;
        }

        // Every direction may have been ignored: no clause rather than an empty " ORDER BY "
        if ($orderCriteria !== []) {
            return ' ORDER BY ' . implode(',', $orderCriteria);
        }

        return '';
    }

    /**
     * Generate Limit params to add to query
     */
    protected function generateLimit(int $limit): string
    {
        return ' LIMIT ' . $limit;
    }
}
