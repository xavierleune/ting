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

use CCMBenchmark\Ting\Driver\CacheResult;
use CCMBenchmark\Ting\Driver\Mysqli\Result;
use CCMBenchmark\Ting\Driver\ResultInterface;
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Repository\Hydrator;
use CCMBenchmark\Ting\Repository\HydratorArray;
use CCMBenchmark\Ting\Repository\HydratorInterface;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;
use CCMBenchmark\Ting\Repository\HydratorValueObject;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use CCMBenchmark\Ting\Tests\Support\TingServices;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use tests\fixtures\FakeDriver\MysqliResult;

class CollectionTest extends TestCase
{
    public function testCollectionShouldDoNothingWithoutHydrator()
    {
        $mockMysqliResult = $this->createMysqliResult(
            [['Sylvain', 'Robez-Masson']],
            ['prenom' => 'firstname', 'nom' => 'lastname']
        );

        $collection = new Collection();
        $result = new Result();
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult($mockMysqliResult);
        $collection->set($result);

        $this->assertSame(['prenom' => 'Sylvain', 'nom' => 'Robez-Masson'], $collection->first());
    }

    public function testFirstShouldReturnNull()
    {
        $collection = new Collection();

        $this->assertNull($collection->first());
    }

    public function testFirstShouldReturnFirstItemOfCollection()
    {
        $mockMysqliResult = $this->createMysqliResult([['Sylvain']], ['prenom' => 'firstname']);

        $result = new Result();
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult($mockMysqliResult);

        $collection = new Collection();
        $collection->set($result);
        $data = $collection->first();

        $this->assertIsArray($data);
        $this->assertEquals(['prenom' => 'Sylvain'], $data);
    }

    public function testGetIterator()
    {
        $mockMysqliResult = $this->createMysqliResult([['Sylvain']], ['prenom' => 'firstname']);

        $result = new Result();
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult($mockMysqliResult);

        $collection = new Collection();
        $collection->set($result);

        $this->assertInstanceOf(\Iterator::class, $collection->getIterator());
    }

    public function testACollectionWithoutResultShouldBeEmptyWithEveryHydrator()
    {
        // new Collection() is how a repository returns an empty collection without querying
        foreach ([new Hydrator(), new HydratorArray(), new HydratorSingleObject(), new HydratorValueObject(\stdClass::class)] as $hydrator) {
            $collection = new Collection($hydrator);

            $this->assertSame([], $this->collectErrorTypes(function () use ($collection, &$items, &$json): void {
                $items = iterator_to_array($collection);
                $json = json_encode($collection);
            }), $hydrator::class);
            $this->assertSame([], $items, $hydrator::class);
            $this->assertSame('[]', $json, $hydrator::class);
        }
    }

    public function testIsFromCache()
    {
        $collection = new Collection();
        $collection->setFromCache(false);
        $this->assertFalse($collection->isFromCache());
        $collection->setFromCache(true);
        $this->assertTrue($collection->isFromCache());
    }

    public function testToCacheReturnArray()
    {
        $collection = new Collection();

        $this->assertSame(['connection' => null, 'database' => null, 'data' => []], $collection->toCache());
    }

    public function testACollectionWithoutResultShouldComeBackFromTheCacheAsAnEmptyCollection()
    {
        // e.g. a cached prepared query whose statement has no result set
        $cached = json_decode(json_encode((new Collection())->toCache()), true);

        foreach ([new Hydrator(), new HydratorArray(), new HydratorSingleObject(), new HydratorValueObject(\stdClass::class)] as $hydrator) {
            $collection = new Collection($hydrator);
            $collection->fromCache($cached);

            $this->assertTrue($collection->isFromCache(), $hydrator::class);
            $this->assertSame([], iterator_to_array($collection), $hydrator::class);
            $this->assertCount(0, $collection, $hydrator::class);
            $this->assertNull($collection->first(), $hydrator::class);
        }
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFromCacheShouldSetCacheResult()
    {
        $outerResult = null;
        // Partial mock: fromCache() must run its real code
        $mockCollection = $this->getMockBuilder(Collection::class)
            ->onlyMethods(['set'])
            ->getMock();
        $mockCollection
            ->method('set')
            ->willReturnCallback(function (ResultInterface $result) use (&$outerResult): void {
                $outerResult = $result;
            });

        $mockCollection->fromCache(
            ['connection' => 'connection_name', 'database' => 'database_name', 'data' => ['bouh']]
        );

        $this->assertInstanceOf(CacheResult::class, $outerResult);
    }

    public function testCountShouldCallHydratorCount()
    {
        // Spy: counts calls while keeping the real implementation
        $mockHydrator = new class () extends Hydrator {
            public int $countCalls = 0;

            public function count(): int
            {
                $this->countCalls++;
                return parent::count();
            }
        };

        $collection = new Collection($mockHydrator);
        $collection->count();

        $this->assertSame(1, $mockHydrator->countCalls);
    }

    public function testCollectionShouldBeCountable()
    {
        $result = $this->createStub(Result::class);
        $result->method('getNumRows')->willReturn(2);

        $collection = new Collection();
        $collection->set($result);

        $this->assertInstanceOf(\Countable::class, $collection);
        $this->assertSame(2, count($collection));
    }

    public function testCountWithoutResultShouldReturn0()
    {
        $this->assertSame(0, count(new Collection()));
    }

    public function testSetFromCache()
    {
        $mockMysqliResult = $this->createMysqliResult([['Sylvain']], ['prenom' => 'firstname']);

        $result = new Result();
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult($mockMysqliResult);

        $collection = new Collection();
        $collection->setFromCache(true);
        $collection->set($result);

        $this->assertTrue($collection->isFromCache());
    }

    public function testCollectionShouldBeJsonSerializable()
    {
        $mockMysqliResult = $this->createMysqliResult([['Bob']], ['prenom' => 'firstname']);

        $result = new Result();
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult($mockMysqliResult);

        $collection = new Collection();
        $collection->set($result);

        $this->assertEquals('[{"prenom":"Bob"}]', json_encode($collection));
    }

    public function testFirstInsideAForeachShouldNotDisturbTheIteration()
    {
        $services = new TingServices();
        $hydrators = [
            'array' => static fn () => new HydratorArray(),
            'hydrator' => static fn () => $services->hydrator(),
            'relational' => static fn () => $services->hydratorRelational(),
        ];

        foreach ($hydrators as $name => $hydrator) {
            foreach (['live' => false, 'cached' => true] as $source => $fromCache) {
                $collection = $this->createCollectionOf3Rows($hydrator(), $fromCache);
                $seen = [];
                $firsts = [];
                foreach ($collection as $row) {
                    $seen[] = $this->firstnameOf($row);
                    $firsts[] = $this->firstnameOf($collection->first());
                    if (count($seen) > 3) {
                        break;
                    }
                }

                $this->assertSame(['Xavier', 'Sylvain', 'Bob'], $seen, "$name, $source");
                $this->assertSame(['Xavier', 'Xavier', 'Xavier'], $firsts, "$name, $source");
            }
        }
    }

    public function testFirstShouldNotStartTheIterationFromItsSecondItem()
    {
        $collection = $this->createCollectionOf3Rows(new HydratorArray(), false);

        $this->assertSame('Xavier', $this->firstnameOf($collection->first()));
        $this->assertSame(['Xavier', 'Sylvain', 'Bob'], array_map($this->firstnameOf(...), iterator_to_array($collection)));
        $this->assertSame('Xavier', $this->firstnameOf($collection->first()));
    }

    public function testCountInsideAForeachOverACachedCollectionShouldNotEndTheIteration()
    {
        $collection = $this->createCollectionOf3Rows(new HydratorArray(), true);
        $seen = [];
        $counts = [];
        foreach ($collection as $row) {
            $seen[] = $this->firstnameOf($row);
            $counts[] = count($collection);
        }

        $this->assertSame(['Xavier', 'Sylvain', 'Bob'], $seen);
        $this->assertSame([3, 3, 3], $counts);
    }

    private function createCollectionOf3Rows(HydratorInterface $hydrator, bool $fromCache): Collection
    {
        $result = new Result();
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult($this->createMysqliResult([['Xavier'], ['Sylvain'], ['Bob']], ['prenom' => 'firstname']));

        $collection = new Collection($hydrator);
        if ($fromCache) {
            $live = new Collection();
            $live->set($result);
            $collection->fromCache($live->toCache());
        } else {
            $collection->set($result);
        }

        return $collection;
    }

    /**
     * @param array|null $row from HydratorArray, or from Hydrator(Relational): the unmapped column in the key 0
     */
    private function firstnameOf(?array $row): ?string
    {
        return $row === null ? null : ($row['prenom'] ?? $row[0]->prenom);
    }

    /**
     * Fake mysqli result describing a single bouh.name column
     *
     * @param array<string, string> $fields alias => original column name, all in table bouh (T_BOUH_BOO)
     */
    private function createMysqliResult(array $data, array $fields): MysqliResult
    {
        $mockMysqliResult = new MysqliResult($data);
        $mockMysqliResult->setFieldsCallback(function () use ($fields) {
            $result = [];
            foreach ($fields as $name => $orgname) {
                $stdClass = new \stdClass();
                $stdClass->name     = $name;
                $stdClass->orgname  = $orgname;
                $stdClass->table    = 'bouh';
                $stdClass->orgtable = 'T_BOUH_BOO';
                $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
                $result[] = $stdClass;
            }
            return $result;
        });

        return $mockMysqliResult;
    }
}
