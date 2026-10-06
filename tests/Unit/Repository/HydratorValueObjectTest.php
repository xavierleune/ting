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
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Repository\HydratorValueObject;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\FakeDriver\MysqliResult;
use tests\fixtures\ValueObject\Counter;
use tests\fixtures\ValueObject\Person;

class HydratorValueObjectTest extends TestCase
{
    public function testHydrateShouldReturnTheValueObject()
    {
        $hydrator = new HydratorValueObject(Person::class);
        $person = $hydrator->setResult($this->createResult())->getIterator()->current();

        $this->assertInstanceOf(Person::class, $person);
        $this->assertSame('Sylvain', $person->getFirstname());
        // Properties are set before the constructor runs, which receives no argument
        $this->assertSame('Sylvain Robez-Masson', $person->getFullName());
    }

    public function testHydrateShouldGiveTheSameObjectsWithAndWithoutCache()
    {
        $withoutCache = new Collection(new HydratorValueObject(Person::class));
        $withoutCache->set($this->createResult());

        // What a cached query stores on a miss, and rebuilds on a hit
        $missed = new Collection(new HydratorValueObject(Person::class));
        $missed->set($this->createResult());
        $fromCache = new Collection(new HydratorValueObject(Person::class));
        $fromCache->fromCache(unserialize(serialize($missed->toCache())));

        $this->assertTrue($fromCache->isFromCache());
        $this->assertEquals(iterator_to_array($withoutCache), iterator_to_array($fromCache));
    }

    public function testHydrateShouldNotWriteAStaticProperty()
    {
        $field = new \stdClass();
        $field->name     = 'instances';
        $field->orgname  = 'instances';
        $field->table    = '';
        $field->orgtable = '';
        $field->type     = MYSQLI_TYPE_VAR_STRING;
        $result = new Result();
        $result->setResult((new MysqliResult([['42']]))->setFields([$field]));
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        $hydrator = new HydratorValueObject(Counter::class);
        $this->collectErrorTypes(function () use ($hydrator, $result, &$counter): void {
            $counter = $hydrator->setResult($result)->getIterator()->current();
        }, $thrown);

        $this->assertNull($thrown);
        // The column is handled as one without property: a dynamic property, as fetch_object() does
        $this->assertSame(0, Counter::$instances);
        $this->assertSame(['name' => null, 'instances' => '42'], get_object_vars($counter));
    }

    public function testCountShouldReturn2()
    {
        $result = $this->createStub(Result::class);
        $result->method('getNumRows')->willReturn(2);

        $hydrator = new HydratorValueObject(Person::class);
        $hydrator->setResult($result);
        $this->assertSame(2, $hydrator->count());
    }

    private function createResult(): Result
    {
        $field = function (string $name, string $column): \stdClass {
            $field = new \stdClass();
            $field->name     = $name;
            $field->orgname  = $column;
            $field->table    = 'p';
            $field->orgtable = 'T_PERSON';
            $field->type     = MYSQLI_TYPE_VAR_STRING;

            return $field;
        };

        $result = new Result();
        $result->setResult(
            (new MysqliResult([['Sylvain', 'Robez-Masson']]))
                ->setFields([$field('firstname', 'per_firstname'), $field('name', 'per_name')])
        );
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        return $result;
    }
}
