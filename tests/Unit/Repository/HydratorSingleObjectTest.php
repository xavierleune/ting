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
use CCMBenchmark\Ting\Repository\HydratorSingleObject;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Services;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use tests\fixtures\FakeDriver\MysqliResult;

// Partial mocks of fake results only replace fetch_fields: they carry no expectation
#[AllowMockObjectsWithoutExpectations]
class HydratorSingleObjectTest extends TestCase
{
    public function testHydrateShouldReturnBouhObject()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setTable('T_BOUH_BOO');

        $metadata->addField([
            'fieldName'  => 'name',
            'columnName' => 'boo_name',
            'type'       => 'string'
        ]);

        $metadata->addField([
            'fieldName'  => 'firstname',
            'columnName' => 'boo_firstname',
            'type'       => 'string'
        ]);

        $services->get('MetadataRepository')->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $mockMysqliResult = $this->createMysqliResult([['Sylvain', 'Robez-Masson']]);
        $mockMysqliResult->method('fetch_fields')->willReturnCallback(function () {
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

        $hydrator = new HydratorSingleObject();
        $hydrator->setMetadataRepository($services->get('MetadataRepository'));
        $hydrator->setUnitOfWork($services->get('UnitOfWork'));
        $iterator = $hydrator->setResult($result)->getIterator();
        $bouh = $iterator->current();
        $this->assertInstanceOf(\tests\fixtures\model\Bouh::class, $bouh);
        $this->assertSame('Robez-Masson', $bouh->getName());
        $this->assertSame('Sylvain', $bouh->getFirstname());
    }

    public function testCountShouldReturn2()
    {
        $result = $this->createStub(Result::class);
        $result->method('getNumRows')->willReturn(2);

        $hydrator = new HydratorSingleObject();
        $hydrator->setResult($result);
        $this->assertSame(2, count($hydrator));
    }

    /**
     * Partial mock, like the atoum one: only fetch_fields is mocked, the iteration code of the fake result is kept.
     */
    private function createMysqliResult(array $data): MysqliResult&MockObject
    {
        return $this->getMockBuilder(MysqliResult::class)
            ->setConstructorArgs([$data])
            ->onlyMethods(['fetch_fields'])
            ->getMock();
    }
}
