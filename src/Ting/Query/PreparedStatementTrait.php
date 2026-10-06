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
 * read on a replica then executed as a write, or read again with selectPrimary(true)).
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
     * Prepare a reading query (SELECT, SHOW, ...), on the primary when selectPrimary(true) is set, on a replica
     * otherwise
     * @return $this
     * @throws Exception
     * @throws QueryException
     */
    public function prepareQuery(): static
    {
        $this->statementForQuery();

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
        $this->statementForExecute();

        return $this;
    }

    /**
     * The statement of a reading query, prepared as prepareQuery() does
     * @throws Exception
     * @throws QueryException
     */
    private function statementForQuery(): StatementInterface
    {
        return $this->statementOn(
            $this->selectPrimary === true ? $this->connection->primary() : $this->connection->replica()
        );
    }

    /**
     * The statement of a writing query, prepared as prepareExecute() does
     * @throws Exception
     * @throws QueryException
     */
    private function statementForExecute(): StatementInterface
    {
        return $this->statementOn($this->connection->primary());
    }

    /**
     * The statement prepared on $driver, prepared unless it already is
     * (replica() returns the primary when no replica is configured: the statement is then prepared once)
     * @throws Exception
     * @throws QueryException
     */
    private function statementOn(DriverInterface $driver): StatementInterface
    {
        if ($this->statement === null || $this->preparedOn !== $driver) {
            $this->statement  = $driver->prepare($this->sql);
            $this->preparedOn = $driver;
            $this->prepared   = true;
        }

        return $this->statement;
    }
}
