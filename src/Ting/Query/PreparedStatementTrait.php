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
        return $this->prepareOn(
            $this->selectPrimary === true ? $this->connection->primary() : $this->connection->replica()
        );
    }

    /**
     * Prepare a writing query (UPDATE, INSERT, DELETE, ...), on the primary
     * @return $this
     * @throws Exception
     * @throws QueryException
     */
    public function prepareExecute(): static
    {
        return $this->prepareOn($this->connection->primary());
    }

    /**
     * Prepare the statement on $driver, unless it already is
     * (replica() returns the primary when no replica is configured: the statement is then prepared once)
     * @return $this
     * @throws Exception
     * @throws QueryException
     */
    private function prepareOn(DriverInterface $driver): static
    {
        if ($this->prepared === true && $this->preparedOn === $driver) {
            return $this;
        }

        $this->statement  = $driver->prepare($this->sql);
        $this->preparedOn = $driver;
        $this->prepared   = true;

        return $this;
    }
}
