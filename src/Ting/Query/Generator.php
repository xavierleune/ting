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
use CCMBenchmark\Ting\Driver\DefaultValuesInsertDriverInterface;
use CCMBenchmark\Ting\Driver\DriverInterface;
use CCMBenchmark\Ting\Driver\SphinxQL;
use CCMBenchmark\Ting\Repository\CollectionFactoryInterface;

class Generator
{
    /**
     * @var \WeakMap<DriverInterface, array<string, array{0: string, 1: list<string>}>>|null by driver, then table and
     *      columns: the SQL of an insert or an update and the names of its parameters, see insert() and update()
     */
    private static ?\WeakMap $writeStatements = null;

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
     * @param array<string, string> $order column name => direction
     * @return QueryInterface<T>
     *
     * @internal
     */
    public function getOneByCriteria(
        array $primariesValue,
        CollectionFactoryInterface $collectionFactory,
        bool $forcePrimary = false,
        array $order = []
    ): QueryInterface {
        $driver = $this->getDriver($forcePrimary);

        [$sql, $params] = $this->getSqlAndParamsByCriteria($primariesValue, $driver);
        $this->updateSQLWithOrderAndLimit($sql, $driver, $order, 1);

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
     * @param int                        $offset rows skipped, only with a limit
     * @return QueryInterface<T>
     */
    public function getByCriteria(
        array $criteria,
        CollectionFactoryInterface $collectionFactory,
        bool $forcePrimary = false,
        array $order = [],
        int $limit = 0,
        int $offset = 0
    ): QueryInterface {
        $driver = $this->getDriver($forcePrimary);

        [$sql, $params] = $this->getSqlAndParamsByCriteria($criteria, $driver);
        $this->updateSQLWithOrderAndLimit($sql, $driver, $order, $limit, $offset);

        $query = $this->queryFactory->get($sql, $this->connection, $collectionFactory);
        $query->setParams($params);

        if ($forcePrimary === true) {
            $query->selectPrimary(true);
        }

        return $query;
    }

    /**
     * Returns a Query counting the rows matching the criteria (every row without criteria)
     *
     * @param array<string, mixed> $criteria column name => value
     * @return QueryInterface<mixed>
     *
     * @internal
     */
    public function countByCriteria(array $criteria, bool $forcePrimary = false): QueryInterface
    {
        $driver = $this->getDriver($forcePrimary);
        [$where, $params] = $this->getWhereAndParams($criteria, $driver);

        return $this->readQuery('SELECT COUNT(*) FROM ' . $this->getTarget($driver) . $where, $params, $forcePrimary);
    }

    /**
     * Returns a Query reading 1 when a row matches the criteria, no row otherwise
     *
     * @param array<string, mixed> $criteria column name => value
     * @return QueryInterface<mixed>
     *
     * @internal
     */
    public function existsByCriteria(array $criteria, bool $forcePrimary = false): QueryInterface
    {
        $driver = $this->getDriver($forcePrimary);
        [$where, $params] = $this->getWhereAndParams($criteria, $driver);

        return $this->readQuery(
            'SELECT 1 FROM ' . $this->getTarget($driver) . $where . $this->generateLimit(1),
            $params,
            $forcePrimary
        );
    }

    /**
     * Returns a Query deleting the rows matching the criteria
     *
     * @param array<string, mixed> $criteria column name => value, not empty
     * @return QueryInterface<mixed> a writing query: no collection factory
     *
     * @internal
     */
    public function deleteByCriteria(array $criteria): QueryInterface
    {
        $driver = $this->getDriver(true);
        [$where, $params] = $this->getWhereAndParams($criteria, $driver);

        $query = $this->queryFactory->get('DELETE FROM ' . $this->getTarget($driver) . $where, $this->connection);
        $query->setParams($params);

        return $query;
    }

    /**
     * @param array<string, mixed> $criteria column name => value
     * @return array{0: string, 1: array<string, mixed>} the WHERE clause (empty without criteria) and its parameters
     */
    private function getWhereAndParams(array $criteria, DriverInterface $driver): array
    {
        if ($criteria === []) {
            return ['', []];
        }

        $criteriaFields = $this->escapeFields(array_keys($criteria), $driver);
        [$conditions, $params] = $this->generateConditionAndParams($criteriaFields, $criteria);

        return [' WHERE ' . implode(' AND ', $conditions), $params];
    }

    /**
     * @param array<string, mixed> $params
     * @return QueryInterface<mixed>
     */
    private function readQuery(string $sql, array $params, bool $forcePrimary): QueryInterface
    {
        $query = $this->queryFactory->get($sql, $this->connection);
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

        // The SQL and the parameter names only depend on the driver, the table and the columns: built once for each
        // (by this class only: a subclass may build them otherwise)
        if (static::class !== self::class) {
            [$sql, $names] = $this->buildInsert(array_keys($values), $driver);
        } else {
            $key = $this->schemaName . "\0" . $this->tableName . "\0" . implode("\0", array_keys($values));
            self::$writeStatements ??= new \WeakMap();
            $cached = self::$writeStatements[$driver][$key] ?? null;
            if ($cached === null) {
                $cached = $this->buildInsert(array_keys($values), $driver);
                $statements = self::$writeStatements[$driver] ?? [];
                $statements[$key] = $cached;
                self::$writeStatements[$driver] = $statements;
            }
            [$sql, $names] = $cached;
        }

        $query = $this->queryFactory->getPrepared($sql, $this->connection);

        $query->setParams($names === [] ? [] : array_combine($names, array_values($values)));

        return $query;
    }

    /**
     * @param list<string> $columns
     * @return array{0: string, 1: list<string>} the SQL of the insert, the names of its parameters in the order of the
     *                                           columns
     */
    private function buildInsert(array $columns, DriverInterface $driver): array
    {
        $fields = $this->escapeFields($columns, $driver);

        $names = [];
        $i = 0;
        foreach ($columns as $column) {
            $names[] = $this->parameterName('v', ++$i, $column);
        }

        if ($columns === []) {
            // Only default values: an entity with only an autoincrement field, or with no field initialized
            $clause = $driver instanceof DefaultValuesInsertDriverInterface
                ? $driver->getDefaultValuesInsertClause()
                : '() VALUES ()';
            $sql = 'INSERT INTO ' . $this->getTarget($driver) . ' ' . $clause;
        } else {
            $sql = 'INSERT INTO ' . $this->getTarget($driver) . ' ('
                . implode(', ', $fields) . ') VALUES ('
                . implode(', ', array_map(fn (string $name): string => ':' . $name, $names)) . ')';
        }

        return [$sql, $names];
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

        // A primary key of scalar values (the rule): the SQL and the parameter names only depend on the driver, the
        // table and the columns, built once for each (by this class only). A null or a list changes the condition:
        // built each time
        $scalarPrimaries = true;
        foreach ($primariesValue as $value) {
            if ($value === null || \is_array($value)) {
                $scalarPrimaries = false;
                break;
            }
        }
        if ($scalarPrimaries && $values !== [] && static::class === self::class) {
            $key = "UPDATE\0" . $this->schemaName . "\0" . $this->tableName . "\0" . implode("\0", array_keys($values))
                . "\0\0" . implode("\0", array_keys($primariesValue));
            self::$writeStatements ??= new \WeakMap();
            $cached = self::$writeStatements[$driver][$key] ?? null;
            if ($cached === null) {
                [$sql, $params] = $this->buildUpdate($values, $primariesValue, $driver);
                $cached = [$sql, array_keys($params)];
                $statements = self::$writeStatements[$driver] ?? [];
                $statements[$key] = $cached;
                self::$writeStatements[$driver] = $statements;
            }
            [$sql, $names] = $cached;
            $params = array_combine($names, [...array_values($values), ...array_values($primariesValue)]);
        } else {
            [$sql, $params] = $this->buildUpdate($values, $primariesValue, $driver);
        }

        $query = $this->queryFactory->getPrepared($sql, $this->connection);
        $query->setParams($params);

        return $query;
    }

    /**
     * @param array<string, mixed> $values         columnName => value
     * @param array<string, mixed> $primariesValue columnName => value
     * @return array{0: string, 1: array<string, mixed>} the SQL of the update and its parameters: the values, then the
     *                                                   primary values
     */
    private function buildUpdate(array $values, array $primariesValue, DriverInterface $driver): array
    {
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

        return [$sql, $params];
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
     * @param int             $offset rows skipped, only with a limit
     * @return void
     */
    protected function updateSQLWithOrderAndLimit(
        string &$sql,
        DriverInterface $driver,
        array $order = [],
        int $limit = 0,
        int $offset = 0
    ): void {
        if (count($order) > 0) {
            $sql .= $this->generateOrder($order, $driver);
        }

        if ($limit > 0 && $offset > 0 && $driver instanceof SphinxQL\Driver) {
            // SphinxQL has no OFFSET keyword
            $sql .= ' LIMIT ' . $offset . ', ' . $limit;
        } elseif ($limit > 0) {
            $sql .= $this->generateLimit($limit);
            if ($offset > 0) {
                $sql .= ' OFFSET ' . $offset;
            }
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
