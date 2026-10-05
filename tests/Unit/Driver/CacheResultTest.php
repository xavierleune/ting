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

namespace CCMBenchmark\Ting\Tests\Unit\Driver;

use ArrayIterator;
use CCMBenchmark\Ting\Driver\CacheResult;
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Repository\HydratorValueObject;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\ValueObject\Person;

class CacheResultTest extends TestCase
{
    private const ROW = [
        ['name' => 'firstname', 'orgName' => 'firstname', 'table' => 'p', 'orgTable' => 'person', 'value' => 'Xavier'],
        ['name' => 'name', 'orgName' => 'name', 'table' => 'p', 'orgTable' => 'person', 'value' => 'Leune'],
    ];

    public function testCurrentShouldReturnTheCachedRow()
    {
        $result = (new CacheResult())->setResult(new ArrayIterator([self::ROW]));
        $result->rewind();

        $this->assertSame(self::ROW, $result->current());
    }

    public function testHydratorValueObjectShouldReturnObjectsFromACachedResult()
    {
        $collection = new Collection(new HydratorValueObject(Person::class));
        $collection->fromCache(['connection' => 'main', 'database' => 'db', 'data' => [self::ROW, self::ROW]]);

        $people = iterator_to_array($collection);

        $this->assertCount(2, $people);
        $this->assertContainsOnlyInstancesOf(Person::class, $people);
        $this->assertSame('Xavier Leune', $people[0]->getFullName());
    }
}
