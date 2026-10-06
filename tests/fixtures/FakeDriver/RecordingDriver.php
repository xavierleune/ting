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

namespace tests\fixtures\FakeDriver;

use CCMBenchmark\Ting\Driver\StatementInterface;
use CCMBenchmark\Ting\Logger\DriverLoggerInterface;
use CCMBenchmark\Ting\Repository\CollectionInterface;

/**
 * Driver recording, in a log shared between drivers, the statements it prepares and their executions
 * ("prepare on <label>", "execute on <label>")
 */
class RecordingDriver extends Driver
{
    /**
     * @param \ArrayObject<int, string> $log
     */
    public function __construct(public readonly string $label, public readonly \ArrayObject $log)
    {
    }

    public function prepare($sql): StatementInterface
    {
        $this->log[] = 'prepare on ' . $this->label;

        return new class ($this->label, $this->log) implements StatementInterface {
            /**
             * @param \ArrayObject<int, string> $log
             */
            public function __construct(private readonly string $label, private readonly \ArrayObject $log)
            {
            }

            public function execute(array $params, ?CollectionInterface $collection = null): bool|CollectionInterface
            {
                $this->log[] = 'execute on ' . $this->label;

                return $collection ?? true;
            }

            public function setLogger(?DriverLoggerInterface $logger = null): void
            {
            }
        };
    }
}
