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

namespace CCMBenchmark\Ting\Tests\Unit\Driver\Mysqli;

use CCMBenchmark\Ting\Driver\Mysqli\Result;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\FakeDriver\MysqliResult;

class ResultTest extends TestCase
{
    public function testIterator()
    {
        // Partial mock: the real fetch_array/data_seek of the fake are used by Result
        $mockMysqliResult = new MysqliResult([['value'], ['value2']]);

        $mockMysqliResult->setFieldsCallback(function () {
            $fields = [];
            $stdClass = new \stdClass();
            $stdClass->name     = 'prenom';
            $stdClass->orgname  = 'firstname';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            return $fields;
        });

        // Spy: records calls while keeping the real implementation
        $result = new class () extends Result {
            /** @var array<string, int> */
            public array $calls = ['next' => 0, 'key' => 0, 'valid' => 0, 'current' => 0];

            public function next(): void
            {
                $this->calls['next']++;
                parent::next();
            }

            public function key(): mixed
            {
                $this->calls['key']++;
                return parent::key();
            }

            public function valid(): bool
            {
                $this->calls['valid']++;
                return parent::valid();
            }

            public function current(): mixed
            {
                $this->calls['current']++;
                return parent::current();
            }
        };
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult($mockMysqliResult);

        $result->rewind();
        $this->assertSame(1, $result->calls['next']);
        $result->key();
        $this->assertSame(1, $result->calls['key']);
        $result->next();
        $this->assertSame(2, $result->calls['next']);
        $result->valid();
        $this->assertSame(1, $result->calls['valid']);
        $result->current();
        $this->assertSame(1, $result->calls['current']);
    }

    public function testGetNumRows()
    {
        // atoum mocked ResultInterface, accepting the undeclared fetch_fields() call (mocked to return [] as upstream 4.0)
        // and the dynamic num_rows property: an anonymous class gives the same shape
        $mockMysqliResult = new class () {
            public $num_rows = null;

            // @codingStandardsIgnoreStart
            public function fetch_fields()
            {
                return [];
            }
            // @codingStandardsIgnoreEnd
        };
        $mockMysqliResult->num_rows = 10;

        $result = new Result();
        $result->setResult($mockMysqliResult);

        $this->assertEquals(10, $result->getNumRows());
    }

    public function testGetNumRowsWithoutResultShouldReturn0()
    {
        $this->assertSame(0, (new Result())->getNumRows());
    }
}
