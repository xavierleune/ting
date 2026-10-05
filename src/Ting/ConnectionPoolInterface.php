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

namespace CCMBenchmark\Ting;

use CCMBenchmark\Ting\Driver\DriverInterface;
use CCMBenchmark\Ting\Logger\DriverLoggerInterface;

interface ConnectionPoolInterface
{
    public function setConfig(array $config): void;

    /**
     * Return the primary connection
     *
     * @throws Exception
     */
    public function primary(string $name, string $database): DriverInterface;

    /**
     * Return a replica connection, or the primary connection when no replica is configured
     *
     * @throws Exception
     */
    public function replica(string $name, string $database): DriverInterface;

    public function closeAll(): void;

    public function setDatabaseOptions(array $options): void;
}
