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
use CCMBenchmark\Ting\Tests\Support\TingServices;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\FakeDriver\MysqliResult;

class CollectionFactoryTest extends TestCase
{
    public function testGetShouldReturnInstanceOfCollection()
    {
        $services = new TingServices();

        $collectionFactory = $services->collectionFactory();
        $this->assertInstanceOf(Collection::class, $collectionFactory->get());
    }

    public function testGetShouldReturnInstanceOfCollectionWithNewHydrator()
    {
        $services = new TingServices();

        $result = new Result();
        $result->setResult($this->createMysqliResult([['a-Bouh']]));
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $result2 = new Result();
        $result2->setResult($this->createMysqliResult([['b-Bouh']]));
        $result2->setConnectionName('main');
        $result2->setDatabase('bouh_world');

        $collectionFactory = $services->collectionFactory();
        $collection = $collectionFactory->get();
        $collection->set($result);
        $collection2 = $collectionFactory->get();
        $collection2->set($result2);

        $stdClass = $collection2->getIterator()->current()[0];
        $this->assertSame('b-Bouh', $stdClass->name);
        $stdClass = $collection->getIterator()->current()[0];
        $this->assertSame('a-Bouh', $stdClass->name);
    }

    /**
     * Fake mysqli result describing a single bouh.name column
     */
    private function createMysqliResult(array $data): MysqliResult
    {
        $mockMysqliResult = new MysqliResult($data);
        $mockMysqliResult->setFieldsCallback(function () {
            $fields = [];
            $stdClass = new \stdClass();
            $stdClass->name     = 'name';
            $stdClass->orgname  = 'boo_name';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;
            return $fields;
        });

        return $mockMysqliResult;
    }
}
