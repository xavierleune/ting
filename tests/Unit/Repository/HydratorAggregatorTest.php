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
use CCMBenchmark\Ting\Exceptions\HydratorException;
use CCMBenchmark\Ting\Repository\HydratorAggregator;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Tests\Support\TingServices;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use tests\fixtures\FakeDriver\MysqliResult;

/**
 * HydratorAggregator
 */
class HydratorAggregatorTest extends TestCase
{
    public function testHydrate()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setTable('T_BOUH_BOO');

        $metadata->addField([
            'fieldName'  => 'id',
            'columnName' => 'boo_id',
            'type'       => 'int'
        ]);

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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\City');
        $metadata->setTable('T_CITY_CIT');

        $metadata->addField([
            'fieldName'  => 'name',
            'columnName' => 'cit_name',
            'type'       => 'string'
        ]);

        $services->metadataRepository()->addMetadata('tests\fixtures\model\CityRepository', $metadata);

        $mockMysqliResult = new MysqliResult([
            [4, 'Xavier', 'Leune', 'Boulogne-Billancourt'],
            [4, 'Xavier', 'Leune', 'Palaiseau'],
            [3, 'Sylvain', 'Robez-Masson', 'Palaiseau'],
            [3, 'Sylvain', 'Robez-Masson', 'Montbéliard'],
            [3, 'Sylvain', 'Robez-Masson', 'Luxiol']
        ]);
        $mockMysqliResult->setFieldsCallback(function () {
            $fields = [];

            $stdClass = new \stdClass();
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'boo_id';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

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

            $stdClass = new \stdClass();
            $stdClass->name     = 'name';
            $stdClass->orgname  = 'cit_name';
            $stdClass->table    = 'c';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;
            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        $hydrator = new HydratorAggregator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->callableDataIs(fn ($result) => $result['c']);
        $hydrator->callableIdIs(fn ($result) => $result['bouh']->getId());
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Leune', $data['bouh']->getName());
        $this->assertSame('Xavier', $data['bouh']->getFirstname());
        $this->assertSame('Boulogne-Billancourt', $data['aggregate'][0]->getName());
        $this->assertSame('Palaiseau', $data['aggregate'][1]->getName());
        $iterator->next();
        $data = $iterator->current();
        $this->assertSame('Robez-Masson', $data['bouh']->getName());
        $this->assertSame('Sylvain', $data['bouh']->getFirstname());
        $this->assertSame('Palaiseau', $data['aggregate'][0]->getName());
        $this->assertSame('Montbéliard', $data['aggregate'][1]->getName());
        $this->assertSame('Luxiol', $data['aggregate'][2]->getName());
    }

    public function testHydrateWithFinalize()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setTable('T_BOUH_BOO');

        $metadata->addField([
            'fieldName'  => 'id',
            'columnName' => 'boo_id',
            'type'       => 'int'
        ]);

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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\City');
        $metadata->setTable('T_CITY_CIT');

        $metadata->addField([
            'fieldName'  => 'name',
            'columnName' => 'cit_name',
            'type'       => 'string'
        ]);

        $services->metadataRepository()->addMetadata('tests\fixtures\model\CityRepository', $metadata);

        $mockMysqliResult = new MysqliResult([
            [4, 'Xavier', 'Leune', 'Boulogne-Billancourt'],
            [4, 'Xavier', 'Leune', 'Palaiseau'],
            [3, 'Sylvain', 'Robez-Masson', 'Palaiseau'],
            [3, 'Sylvain', 'Robez-Masson', 'Montbéliard'],
            [3, 'Sylvain', 'Robez-Masson', 'Luxiol']
        ]);
        $mockMysqliResult->setFieldsCallback(function () {
            $fields = [];

            $stdClass = new \stdClass();
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'boo_id';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

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

            $stdClass = new \stdClass();
            $stdClass->name     = 'name';
            $stdClass->orgname  = 'cit_name';
            $stdClass->table    = 'c';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;
            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        $hydrator = new HydratorAggregator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->callableDataIs(fn ($result) => $result['c']);
        $hydrator->callableIdIs(fn ($result) => $result['bouh']->getId());
        $hydrator->callableFinalizeAggregate(function ($result, $aggregate) {
            $result['bouh']->aggregate = $aggregate;
            return $result['bouh'];
        });
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Leune', $data->getName());
        $this->assertSame('Xavier', $data->getFirstname());
        $this->assertSame('Boulogne-Billancourt', $data->aggregate[0]->getName());
        $this->assertSame('Palaiseau', $data->aggregate[1]->getName());
        $iterator->next();
        $data = $iterator->current();
        $this->assertSame('Robez-Masson', $data->getName());
        $this->assertSame('Sylvain', $data->getFirstname());
        $this->assertSame('Palaiseau', $data->aggregate[0]->getName());
        $this->assertSame('Montbéliard', $data->aggregate[1]->getName());
        $this->assertSame('Luxiol', $data->aggregate[2]->getName());
    }

    public function testHydrateWithoutRightSortShouldContinue()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setTable('T_BOUH_BOO');

        $metadata->addField([
            'fieldName'  => 'id',
            'columnName' => 'boo_id',
            'type'       => 'int'
        ]);

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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\City');
        $metadata->setTable('T_CITY_CIT');

        $metadata->addField([
            'fieldName'  => 'name',
            'columnName' => 'cit_name',
            'type'       => 'string'
        ]);

        $services->metadataRepository()->addMetadata('tests\fixtures\model\CityRepository', $metadata);

        $mockMysqliResult = new MysqliResult([
            [4, 'Xavier', 'Leune', 'Boulogne-Billancourt'],
            [3, 'Sylvain', 'Robez-Masson', 'Palaiseau'],
            [4, 'Xavier', 'Leune', 'Palaiseau'],
            [3, 'Sylvain', 'Robez-Masson', 'Montbéliard'],
            [3, 'Sylvain', 'Robez-Masson', 'Luxiol']
        ]);
        $mockMysqliResult->setFieldsCallback(function () {
            $fields = [];

            $stdClass = new \stdClass();
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'boo_id';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

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

            $stdClass = new \stdClass();
            $stdClass->name     = 'name';
            $stdClass->orgname  = 'cit_name';
            $stdClass->table    = 'c';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;
            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        $hydrator = new HydratorAggregator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->callableDataIs(fn ($result) => $result['c']);
        $hydrator->callableIdIs(fn ($result) => $result['bouh']->getId());
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Leune', $data['bouh']->getName());
        $this->assertSame('Xavier', $data['bouh']->getFirstname());
        $this->assertSame('Boulogne-Billancourt', $data['aggregate'][0]->getName());
        $iterator->next();
        $data = $iterator->current();
        $this->assertSame('Robez-Masson', $data['bouh']->getName());
        $this->assertSame('Sylvain', $data['bouh']->getFirstname());
        $this->assertSame('Palaiseau', $data['aggregate'][0]->getName());
        $this->assertSame('Montbéliard', $data['aggregate'][1]->getName());
        $this->assertSame('Luxiol', $data['aggregate'][2]->getName());
    }

    public function testHydrateEndingWithAKnownGroupShouldYieldTheLastGroup()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setTable('T_BOUH_BOO');

        $metadata->addField([
            'fieldName'  => 'id',
            'columnName' => 'boo_id',
            'type'       => 'int'
        ]);

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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\City');
        $metadata->setTable('T_CITY_CIT');

        $metadata->addField([
            'fieldName'  => 'name',
            'columnName' => 'cit_name',
            'type'       => 'string'
        ]);

        $services->metadataRepository()->addMetadata('tests\fixtures\model\CityRepository', $metadata);

        $mockMysqliResult = new MysqliResult([
            [4, 'Xavier', 'Leune', 'Boulogne-Billancourt'],
            [3, 'Sylvain', 'Robez-Masson', 'Palaiseau'],
            [4, 'Xavier', 'Leune', 'Palaiseau'],
            [3, 'Sylvain', 'Robez-Masson', 'Montbéliard'],
            [3, 'Sylvain', 'Robez-Masson', 'Luxiol'],
            // Last row in a group already yielded: the pending group must still be yielded
            [4, 'Xavier', 'Leune', 'Paris']
        ]);
        $mockMysqliResult->setFieldsCallback(function () {
            $fields = [];

            $stdClass = new \stdClass();
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'boo_id';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

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

            $stdClass = new \stdClass();
            $stdClass->name     = 'name';
            $stdClass->orgname  = 'cit_name';
            $stdClass->table    = 'c';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;
            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        $hydrator = new HydratorAggregator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->callableDataIs(fn ($result) => $result['c']);
        $hydrator->callableIdIs(fn ($result) => $result['bouh']->getId());
        $groups = iterator_to_array($hydrator->setResult($result)->getIterator());

        // Each group is keyed and built from its first row
        $this->assertSame([0, 1], array_keys($groups));
        $this->assertSame('Leune', $groups[0]['bouh']->getName());
        $this->assertSame(['Boulogne-Billancourt'], array_map(fn ($city) => $city->getName(), $groups[0]['aggregate']));
        $this->assertSame('Robez-Masson', $groups[1]['bouh']->getName());
        $this->assertSame(
            ['Palaiseau', 'Montbéliard', 'Luxiol'],
            array_map(fn ($city) => $city->getName(), $groups[1]['aggregate'])
        );
    }

    public function testHydrateShouldThrowWhenTheIdentifierIsNull()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setTable('T_BOUH_BOO');
        $metadata->addField(['fieldName' => 'name', 'columnName' => 'boo_name', 'type' => 'string']);
        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $mockMysqliResult = new MysqliResult([['Leune'], ['Robez-Masson']]);
        $mockMysqliResult->setFieldsCallback(function () {
            $field = new \stdClass();
            $field->name     = 'name';
            $field->orgname  = 'boo_name';
            $field->table    = 'bouh';
            $field->orgtable = 'T_BOUH_BOO';
            $field->type     = MYSQLI_TYPE_VAR_STRING;
            return [$field];
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        $hydrator = new HydratorAggregator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->callableDataIs(fn ($result) => $result['bouh']->getName());
        $hydrator->callableIdIs(fn ($result) => null);
        $hydrator->setResult($result);

        $types = $this->collectErrorTypes(function () use ($hydrator): void {
            iterator_to_array($hydrator->getIterator());
        }, $thrown);

        $this->assertInstanceOf(HydratorException::class, $thrown);
        $this->assertStringContainsString('callableIdIs()', $thrown->getMessage());
        $this->assertSame([], $types);
    }
}
