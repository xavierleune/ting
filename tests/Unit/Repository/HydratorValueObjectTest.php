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
use CCMBenchmark\Ting\Repository\HydratorValueObject;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\FakeDriver\MysqliResult;
use tests\fixtures\ValueObject\Bouh;

class HydratorValueObjectTest extends TestCase
{
    public function testHydrateShouldReturnBouhObject()
    {
        $data = ['Sylvain', 'Robez-Masson'];
        $mockMysqliResult = $this->getMockBuilder(MysqliResult::class)
            ->setConstructorArgs([[$data]])
            ->onlyMethods(['fetch_object'])
            ->getMock();
        $mockMysqliResult->setFieldsCallback(function () {
            $fields = [];
            $stdClass = new \stdClass();
            $stdClass->name     = 'firstname';
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

        $mockMysqliResult->expects($this->once())
            ->method('fetch_object')
            ->willReturnCallback(function () use ($data) {
                return new Bouh(...$data);
            });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        $hydrator = new HydratorValueObject(Bouh::class);
        $iterator = $hydrator->setResult($result)->getIterator();
        $bouh = $iterator->current();
        $this->assertInstanceOf(Bouh::class, $bouh);
        $this->assertSame('Robez-Masson', $bouh->getName());
        $this->assertSame('Sylvain', $bouh->getFirstname());
    }

    public function testCountShouldReturn2()
    {
        $result = $this->createStub(Result::class);
        $result->method('getNumRows')->willReturn(2);

        $hydrator = new HydratorValueObject(Bouh::class);
        $hydrator->setResult($result);
        $this->assertSame(2, $hydrator->count());
    }
}
