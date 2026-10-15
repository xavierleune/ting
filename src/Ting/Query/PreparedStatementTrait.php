<?php

/***********************************************************************
 *
 * Ting - PHP Datamapper
 * ==========================================
 *
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

use CCMBenchmark\Ting\Driver\DriverInterface;
use CCMBenchmark\Ting\Driver\StatementInterface;
use CCMBenchmark\Ting\Exception;

/**
 * Prepares the statement of a prepared query on the driver it must run on: the primary for a writing query or when
 * selectPrimary(true) is set, a replica otherwise. The statement is prepared again when that driver changes (a query
 * read on a replica then executed as a write, or read again with selectPrimary(true)), and when it is stale (the
 * driver reconnected, or its connection was reset: the statement is gone with the session), and when its SQL changes
 * (a list parameter of another length, see ParameterConverter).
 *
 * @internal shared by PreparedQuery and Cached\PreparedQuery
 */
trait PreparedStatementTrait
{
    protected bool $prepared = false;

    protected ?StatementInterface $statement = null;

    /**
     * Driver the statement was prepared on
     */
    private ?DriverInterface $preparedOn = null;

    /**
     * SQL the statement was prepared with, its list parameters expanded
     */
    private ?string $preparedSql = null;

    /**
     * Prepare a reading query (SELECT, SHOW, ...), on the primary when selectPrimary(true) is set, on a replica
     * otherwise
     * @return $this
     * @throws Exception
     * @throws QueryException
     */
    public function prepareQuery(): static
    {
        $this->statementForQuery(ParameterConverter::convert($this->sql, $this->params)[0]);

        return $this;
    }

    /**
     * Prepare a writing query (UPDATE, INSERT, DELETE, ...), on the primary
     * @return $this
     * @throws Exception
     * @throws QueryException
     */
    public function prepareExecute(): static
    {
        $this->statementForExecute(ParameterConverter::convert($this->sql, $this->params)[0]);

        return $this;
    }

    /**
     * The statement of a reading query, prepared as prepareQuery() does
     * @param string $sql the SQL of the query, converted by ParameterConverter
     * @throws Exception
     * @throws QueryException
     */
    private function statementForQuery(string $sql): StatementInterface
    {
        return $this->statementOn($this->readingDriver(), $sql);
    }

    /**
     * The statement of a writing query, prepared as prepareExecute() does
     * @param string $sql the SQL of the query, converted by ParameterConverter
     * @throws Exception
     * @throws QueryException
     */
    private function statementForExecute(string $sql): StatementInterface
    {
        return $this->statementOn($this->connection->primary(), $sql);
    }

    /**
     * The statement of $sql prepared on $driver, prepared unless it already is and is not stale
     * (replica() returns the primary when no replica is configured: the statement is then prepared once)
     * @throws Exception
     * @throws QueryException
     */
    private function statementOn(DriverInterface $driver, string $sql): StatementInterface
    {
        if (
            $this->statement === null
            || $this->preparedOn !== $driver
            || $this->preparedSql !== $sql
            || $this->statement->isStale()
        ) {
            $this->statement   = $driver->prepare($sql);
            $this->preparedOn  = $driver;
            $this->preparedSql = $sql;
            $this->prepared    = true;
        }

        return $this->statement;
    }
}
