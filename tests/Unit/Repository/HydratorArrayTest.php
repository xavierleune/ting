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

namespace CCMBenchmark\Ting\Tests\Unit\Repository;

use CCMBenchmark\Ting\Driver\Mysqli\Result;
use CCMBenchmark\Ting\Repository\HydratorArray;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use tests\fixtures\FakeDriver\MysqliResult;

class HydratorArrayTest extends TestCase
{
    public function testHydrateShouldReturnArray()
    {
        $mockMysqliResult = new MysqliResult([['Sylvain', 'Robez-Masson']]);
        $mockMysqliResult->setFieldsCallback(function () {
            $fields = [];
            $stdClass = new \stdClass();
            $stdClass->name     = 'fname';
            $stdClass->orgname  = 'boo_firstname';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'name';
            $stdClass->orgname  = 'boo_name';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;
            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        $hydrator = new HydratorArray();
        $iterator = $hydrator->setResult($result)->getIterator();
        $this->assertSame(['fname' => 'Sylvain', 'name' => 'Robez-Masson'], $iterator->current());
    }

    public function testCountShouldReturn2()
    {
        $result = $this->createStub(Result::class);
        $result->method('getNumRows')->willReturn(2);

        $hydrator = new HydratorArray();
        $hydrator->setResult($result);
        $this->assertSame(2, count($hydrator));
    }

    public function testCountWithoutResultShoulddReturn0()
    {
        $hydrator = new HydratorArray();
        $this->assertSame(0, count($hydrator));
    }
}
