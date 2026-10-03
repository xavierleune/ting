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
use CCMBenchmark\Ting\Repository\Hydrator\AggregateFrom;
use CCMBenchmark\Ting\Repository\Hydrator\AggregateTo;
use CCMBenchmark\Ting\Repository\Hydrator\RelationMany;
use CCMBenchmark\Ting\Repository\Hydrator\RelationOne;
use CCMBenchmark\Ting\Repository\HydratorRelational;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Services;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use tests\fixtures\FakeDriver\MysqliResult;
use tests\fixtures\model\CityWithPublicPropertiesRepository;
use tests\fixtures\model\CountryWithPublicPropertiesRepository;

use const MYSQLI_TYPE_VAR_STRING;

/**
 * HydratorRelational
 */
// Partial mocks of fake results only replace fetch_fields: they carry no expectation
#[AllowMockObjectsWithoutExpectations]
class HydratorRelationalTest extends TestCase
{
    /**
     * @var Services
     */
    private $services;

    private function getResult()
    {
        $this->services = new Services();
        $metadata = new Metadata($this->services->get('SerializerFactory'));
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setTable('T_BOUH_BOO');

        $metadata->addField([
            'primary'    => true,
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

        $this->services->get('MetadataRepository')->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadata = new Metadata($this->services->get('SerializerFactory'));
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\City');
        $metadata->setTable('T_CITY_CIT');

        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'cit_id',
            'type'       => 'int'
        ]);

        $metadata->addField([
            'fieldName'  => 'name',
            'columnName' => 'cit_name',
            'type'       => 'string'
        ]);

        $this->services->get('MetadataRepository')->addMetadata('tests\fixtures\model\CityRepository', $metadata);

        $mockMysqliResult = $this->createMysqliResult([
            [4, 'Xavier', 'Leune', 1, 'Boulogne-Billancourt'],
            [4, 'Xavier', 'Leune', 2, 'Palaiseau'],
            [3, 'Sylvain', 'Robez-Masson', 2, 'Palaiseau'],
            [3, 'Sylvain', 'Robez-Masson', 3, 'Montbéliard'],
            [3, 'Sylvain', 'Robez-Masson', 4, 'Luxiol']
        ]);
        $mockMysqliResult->method('fetch_fields')->willReturnCallback(function () {
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
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'cit_id';
            $stdClass->table    = 'c';
            $stdClass->orgtable = 'T_CITY_CIT';
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

        return $result;
    }

    public function testHydrate()
    {
        $result = $this->getResult();
        $hydrator = new HydratorRelational();
        $hydrator->setMetadataRepository($this->services->get('MetadataRepository'));
        $hydrator->setUnitOfWork($this->services->get('UnitOfWork'));
        $hydrator->addRelation(new RelationMany(
            new AggregateFrom('c'),
            new AggregateTo('bouh'),
            'citiesAre'
        ));
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Leune', $data['bouh']->getName());
        $this->assertSame('Xavier', $data['bouh']->getFirstname());
        $cities = $data['bouh']->getCities();
        $this->assertIsArray($cities);
        $this->assertSame('Boulogne-Billancourt', reset($cities)->getName());
        $this->assertSame('Palaiseau', next($cities)->getName());
        $iterator->next();
        $data = $iterator->current();
        $this->assertSame('Robez-Masson', $data['bouh']->getName());
        $this->assertSame('Sylvain', $data['bouh']->getFirstname());
        $cities = $data['bouh']->getCities();
        $this->assertIsArray($cities);
        $this->assertSame('Palaiseau', reset($cities)->getName());
        $this->assertSame('Montbéliard', next($cities)->getName());
        $this->assertSame('Luxiol', next($cities)->getName());
    }

    public function testHydrateWithDefaultPk()
    {
        $result = $this->getResult();

        $this->services->get('MetadataRepository')->addMetadata(
            'tests\fixtures\model\CityRepository',
            \tests\fixtures\model\CityRepository::initMetadata($this->services->get('SerializerFactory'))
        );

        $this->services->get('MetadataRepository')->addMetadata(
            'tests\fixtures\model\BouhRepository',
            \tests\fixtures\model\BouhRepository::initMetadata($this->services->get('SerializerFactory'))
        );

        $hydrator = new HydratorRelational();
        $hydrator->setMetadataRepository($this->services->get('MetadataRepository'));
        $hydrator->setUnitOfWork($this->services->get('UnitOfWork'));
        $hydrator->addRelation(new RelationMany(
            new AggregateFrom('c'),
            new AggregateTo('bouh'),
            'citiesAre'
        ));
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Leune', $data['bouh']->getName());
        $this->assertSame('Xavier', $data['bouh']->getFirstname());
        $cities = $data['bouh']->getCities();
        $this->assertIsArray($cities);
        $this->assertSame('Boulogne-Billancourt', reset($cities)->getName());
        $this->assertSame('Palaiseau', next($cities)->getName());
        $iterator->next();
        $data = $iterator->current();
        $this->assertSame('Robez-Masson', $data['bouh']->getName());
        $this->assertSame('Sylvain', $data['bouh']->getFirstname());
        $cities = $data['bouh']->getCities();
        $this->assertIsArray($cities);
        $this->assertSame('Palaiseau', reset($cities)->getName());
        $this->assertSame('Montbéliard', next($cities)->getName());
        $this->assertSame('Luxiol', next($cities)->getName());
    }

    public function testHydrateRelationOne()
    {
        $result = $this->getResult();
        $hydrator = new HydratorRelational();
        $hydrator->setMetadataRepository($this->services->get('MetadataRepository'));
        $hydrator->setUnitOfWork($this->services->get('UnitOfWork'));
        $hydrator->addRelation(new RelationOne(
            new AggregateFrom('c'),
            new AggregateTo('bouh'),
            'setCity'
        ));
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Leune', $data['bouh']->getName());
        $this->assertSame('Xavier', $data['bouh']->getFirstname());
        $this->assertSame('Palaiseau', $data['bouh']->getCity()->getName());
        $iterator->next();
        $data = $iterator->current();
        $this->assertSame('Robez-Masson', $data['bouh']->getName());
        $this->assertSame('Sylvain', $data['bouh']->getFirstname());
        $this->assertSame('Luxiol', $data['bouh']->getCity()->getName());
        ;
    }

    public function testHydrateWithFinalize()
    {
        $result = $this->getResult();
        $hydrator = new HydratorRelational();
        $hydrator->setMetadataRepository($this->services->get('MetadataRepository'));
        $hydrator->setUnitOfWork($this->services->get('UnitOfWork'));
        $hydrator->addRelation(new RelationMany(
            new AggregateFrom('c'),
            new AggregateTo('bouh'),
            'citiesAre'
        ));
        $hydrator->callableFinalizeAggregate(fn ($result) => $result['bouh']);
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Leune', $data->getName());
        $this->assertSame('Xavier', $data->getFirstname());
        $cities = $data->getCities();
        $this->assertIsArray($cities);
        $this->assertSame('Boulogne-Billancourt', reset($cities)->getName());
        $this->assertSame('Palaiseau', next($cities)->getName());
        $iterator->next();
        $data = $iterator->current();
        $this->assertSame('Robez-Masson', $data->getName());
        $this->assertSame('Sylvain', $data->getFirstname());
        $cities = $data->getCities();
        $this->assertIsArray($cities);
        $this->assertSame('Palaiseau', reset($cities)->getName());
        $this->assertSame('Montbéliard', next($cities)->getName());
        $this->assertSame('Luxiol', next($cities)->getName());
    }

    public function testHydrateWithoutRightSort()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setTable('T_BOUH_BOO');

        $metadata->addField([
            'primary'    => true,
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

        $services->get('MetadataRepository')->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\City');
        $metadata->setTable('T_CITY_CIT');

        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'cit_id',
            'type'       => 'int'
        ]);

        $metadata->addField([
            'fieldName'  => 'name',
            'columnName' => 'cit_name',
            'type'       => 'string'
        ]);

        $services->get('MetadataRepository')->addMetadata('tests\fixtures\model\CityRepository', $metadata);

        $mockMysqliResult = $this->createMysqliResult([
            [4, 'Xavier', 'Leune', 1, 'Boulogne-Billancourt'],
            [3, 'Sylvain', 'Robez-Masson', 2, 'Palaiseau'],
            [4, 'Xavier', 'Leune', 2, 'Palaiseau'],
            [3, 'Sylvain', 'Robez-Masson', 3, 'Montbéliard'],
            [3, 'Sylvain', 'Robez-Masson', 4, 'Luxiol']
        ]);
        $mockMysqliResult->method('fetch_fields')->willReturnCallback(function () {
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
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'cit_id';
            $stdClass->table    = 'c';
            $stdClass->orgtable = 'T_CITY_CIT';
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

        $hydrator = new HydratorRelational();
        $hydrator->setMetadataRepository($services->get('MetadataRepository'));
        $hydrator->setUnitOfWork($services->get('UnitOfWork'));
        $hydrator->addRelation(new RelationMany(
            new AggregateFrom('c'),
            new AggregateTo('bouh'),
            'citiesAre'
        ));
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Leune', $data['bouh']->getName());
        $this->assertSame('Xavier', $data['bouh']->getFirstname());
        $cities = $data['bouh']->getCities();
        $this->assertIsArray($cities);
        $this->assertSame('Boulogne-Billancourt', reset($cities)->getName());
        $this->assertSame('Palaiseau', next($cities)->getName());
        $iterator->next();
        $data = $iterator->current();
        $this->assertSame('Robez-Masson', $data['bouh']->getName());
        $this->assertSame('Sylvain', $data['bouh']->getFirstname());
        $cities = $data['bouh']->getCities();
        $this->assertIsArray($cities);
        $this->assertSame('Palaiseau', reset($cities)->getName());
        $this->assertSame('Montbéliard', next($cities)->getName());
        $this->assertSame('Luxiol', next($cities)->getName());
    }

    public function testHydrateWithDepth2()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setTable('T_BOUH_BOO');

        $metadata->addField([
            'primary'    => true,
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

        $services->get('MetadataRepository')->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\City');
        $metadata->setTable('T_CITY_CIT');

        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'cit_id',
            'type'       => 'int'
        ]);

        $metadata->addField([
            'fieldName'  => 'name',
            'columnName' => 'cit_name',
            'type'       => 'string'
        ]);

        $services->get('MetadataRepository')->addMetadata('tests\fixtures\model\CityRepository', $metadata);

        $services->get('MetadataRepository')->addMetadata(
            'tests\fixtures\model\ParkRepository',
            \tests\fixtures\model\ParkRepository::initMetadata($services->get('SerializerFactory'))
        );

        $mockMysqliResult = $this->createMysqliResult([
            [4, 'Xavier', 'Leune', 1, 'Boulogne-Billancourt', 1, 'Parc de Billancourt'],
            [3, 'Sylvain', 'Robez-Masson', 2, 'Palaiseau', 2, 'Parc Pierre et Marie Curie'],
            [4, 'Xavier', 'Leune', 2, 'Palaiseau', null, null],
            [3, 'Sylvain', 'Robez-Masson', 3, 'Montbéliard', null, null],
            [4, 'Xavier', 'Leune', 1, 'Boulogne-Billancourt', 3, 'Parc de Boulogne-Edmond-de-Rothschild'],
            [3, 'Sylvain', 'Robez-Masson', 2, 'Palaiseau', 4, 'Square du Pileu'],
            [3, 'Sylvain', 'Robez-Masson', 2, 'Palaiseau', 5, 'Bois du Clos du Pileu'],
            [3, 'Sylvain', 'Robez-Masson', 4, 'Luxiol', null, null]
        ]);
        $mockMysqliResult->method('fetch_fields')->willReturnCallback(function () {
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
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'cit_id';
            $stdClass->table    = 'c';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'name';
            $stdClass->orgname  = 'cit_name';
            $stdClass->table    = 'c';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'pa_id';
            $stdClass->table    = 'park';
            $stdClass->orgtable = 'T_PARK_PA';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'name';
            $stdClass->orgname  = 'pa_name';
            $stdClass->table    = 'park';
            $stdClass->orgtable = 'T_PARK_PA';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;
            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        $hydrator = new HydratorRelational();
        $hydrator->setMetadataRepository($services->get('MetadataRepository'));
        $hydrator->setUnitOfWork($services->get('UnitOfWork'));
        $hydrator->addRelation(new RelationMany(
            new AggregateFrom('c'),
            new AggregateTo('bouh'),
            'citiesAre'
        ));
        $hydrator->addRelation(new RelationMany(
            new AggregateFrom('park'),
            new AggregateTo('c'),
            'parksAre'
        ));
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Leune', $data['bouh']->getName());
        $this->assertSame('Xavier', $data['bouh']->getFirstname());
        $cities = $data['bouh']->getCities();
        $this->assertIsArray($cities);
        $this->assertSame('Boulogne-Billancourt', reset($cities)->getName());
        $parks = reset($cities)->getParks();
        $this->assertIsArray($parks);
        $this->assertSame('Parc de Billancourt', reset($parks)->getName());
        $this->assertSame('Parc de Boulogne-Edmond-de-Rothschild', next($parks)->getName());
        $this->assertSame('Palaiseau', next($cities)->getName());
        $parks = current($cities)->getParks();
        $this->assertIsArray($parks);
        $this->assertSame('Parc Pierre et Marie Curie', reset($parks)->getName());
        $this->assertSame('Square du Pileu', next($parks)->getName());
        $this->assertSame('Bois du Clos du Pileu', next($parks)->getName());
        $iterator->next();
        $data = $iterator->current();
        $this->assertSame('Robez-Masson', $data['bouh']->getName());
        $this->assertSame('Sylvain', $data['bouh']->getFirstname());
        $cities = $data['bouh']->getCities();
        $this->assertIsArray($cities);
        $this->assertSame('Palaiseau', reset($cities)->getName());
        $parks = current($cities)->getParks();
        $this->assertIsArray($parks);
        $this->assertSame('Parc Pierre et Marie Curie', reset($parks)->getName());
        $this->assertSame('Square du Pileu', next($parks)->getName());
        $this->assertSame('Bois du Clos du Pileu', next($parks)->getName());
        $this->assertSame('Montbéliard', next($cities)->getName());
        $this->assertSame('Luxiol', next($cities)->getName());
    }

    public function testHydrateWithDepth3()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\Nursery');
        $metadata->setTable('T_NURSERY');

        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'nursery_id',
            'type'       => 'int'
        ]);

        $services->get('MetadataRepository')->addMetadata('tests\fixtures\model\NurseryRepository', $metadata);

        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\City');
        $metadata->setTable('T_CITY_CIT');

        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'cit_id',
            'type'       => 'int'
        ]);

        $services->get('MetadataRepository')->addMetadata('tests\fixtures\model\CityRepository', $metadata);

        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\Department');
        $metadata->setTable('T_DEPARTMENT');

        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'department_id',
            'type'       => 'int'
        ]);

        $services->get('MetadataRepository')->addMetadata('tests\fixtures\model\DepartmentRepository', $metadata);

        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\Authority');
        $metadata->setTable('T_AUTHORITY');

        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'authority_id',
            'type'       => 'int'
        ]);

        $services->get('MetadataRepository')->addMetadata('tests\fixtures\model\AuthorityRepository', $metadata);

        $mockMysqliResult = $this->createMysqliResult([
            [1, 11, 22, 33],
        ]);
        $mockMysqliResult->method('fetch_fields')->willReturnCallback(function () {
            $fields = [];

            $stdClass = new \stdClass();
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'nursery_id';
            $stdClass->table    = 'o';
            $stdClass->orgtable = 'T_NURSERY';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'cit_id';
            $stdClass->table    = 'a1';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'department_id';
            $stdClass->table    = 'a2';
            $stdClass->orgtable = 'T_DEPARTMENT';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'authority_id';
            $stdClass->table    = 'a3';
            $stdClass->orgtable = 'T_AUTHORITY';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        $hydrator = new HydratorRelational();
        $hydrator->setMetadataRepository($services->get('MetadataRepository'));
        $hydrator->setUnitOfWork($services->get('UnitOfWork'));
        $hydrator->addRelation(new RelationOne(
            new AggregateFrom('a1'),
            new AggregateTo('o'),
            'setCity'
        ));
        $hydrator->addRelation(new RelationOne(
            new AggregateFrom('a2'),
            new AggregateTo('a1'),
            'setDepartment'
        ));
        $hydrator->addRelation(new RelationOne(
            new AggregateFrom('a3'),
            new AggregateTo('a2'),
            'setAuthority'
        ));
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame(1, $data['o']->getId());
        $city = $data['o']->getCity();
        $this->assertIsObject($city);
        $this->assertSame(11, $city->getId());
        $department = $city->getDepartment();
        $this->assertIsObject($department);
        $this->assertSame(22, $department->getId());
        $authority = $department->getAuthority();
        $this->assertIsObject($authority);
        $this->assertSame(33, $authority->getId());
    }

    public function testHydrateWithNoRelation()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setTable('T_BOUH_BOO');

        $metadata->addField([
            'primary'    => true,
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

        $services->get('MetadataRepository')->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $mockMysqliResult = $this->createMysqliResult([
            [1, 'Xavier', 'Leune'],
        ]);
        $mockMysqliResult->method('fetch_fields')->willReturnCallback(function () {
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
            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        $hydrator = new HydratorRelational();
        $hydrator->setMetadataRepository($services->get('MetadataRepository'));
        $hydrator->setUnitOfWork($services->get('UnitOfWork'));
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Leune', $data['bouh']->getName());
        $this->assertSame('Xavier', $data['bouh']->getFirstname());
    }

    public function testHydrateNoRelationFinalizeAggregate()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setTable('T_BOUH_BOO');

        $metadata->addField([
            'primary'    => true,
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

        $services->get('MetadataRepository')->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $mockMysqliResult = $this->createMysqliResult([
            [1, 'Xavier', 'Leune'],
        ]);
        $mockMysqliResult->method('fetch_fields')->willReturnCallback(function () {
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
            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        $hydrator = new HydratorRelational();
        $hydrator->setMetadataRepository($services->get('MetadataRepository'));
        $hydrator->setUnitOfWork($services->get('UnitOfWork'));
        $hydrator->callableFinalizeAggregate(static fn (array $row) => $row['bouh']);
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Leune', $data->getName());
        $this->assertSame('Xavier', $data->getFirstname());
    }

    /**
     * This test simulates a query with a relation, where the join row is null.
     * The id are public properties without default values.
     */
    public function testHydrateNullJoinWithPublicProperties(): void
    {
        $services = new Services();
        $metadataCity = CityWithPublicPropertiesRepository::initMetadata($services->get('SerializerFactory'));
        $metadataCountry = CountryWithPublicPropertiesRepository::initMetadata($services->get('SerializerFactory'));

        $services->get('MetadataRepository')->addMetadata($metadataCity->getEntity(), $metadataCity);
        $services->get('MetadataRepository')->addMetadata($metadataCountry->getEntity(), $metadataCountry);

        $mockMysqliResult = $this->createMysqliResult([
            [1, 'Paris', 2, 'France'],
            [2, 'London', 3, 'United Kingdom'],
            [3, 'Tokyo', null, null], // No relation
        ]);
        $mockMysqliResult->method('fetch_fields')->willReturnCallback(function () {
            $fields = [];

            $stdClass = new \stdClass();
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'id';
            $stdClass->table    = 'city';
            $stdClass->orgtable = 'T_CITY_PUB';
            $stdClass->type     = MYSQLI_TYPE_INT24;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'name';
            $stdClass->orgname  = 'name';
            $stdClass->table    = 'city';
            $stdClass->orgtable = 'T_CITY_PUB';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'id';
            $stdClass->table    = 'country';
            $stdClass->orgtable = 'T_COUNTRY_PUB';
            $stdClass->type     = MYSQLI_TYPE_INT24;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'name';
            $stdClass->orgname  = 'name';
            $stdClass->table    = 'country';
            $stdClass->orgtable = 'T_COUNTRY_PUB';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('database');

        $hydrator = new HydratorRelational();
        $hydrator->setMetadataRepository($services->get('MetadataRepository'));
        $hydrator->setUnitOfWork($services->get('UnitOfWork'));
        $hydrator->addRelation(new RelationOne(
            new AggregateFrom('country'),
            new AggregateTo('city'),
            'setCountry'
        ));
        $hydrator->callableFinalizeAggregate(static fn (array $row) => $row['city']);
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Paris', $data->name);
        $country = $data->getCountry();
        $this->assertSame('France', $country->name);
        $iterator->next();
        $data = $iterator->current();
        $this->assertSame('London', $data->name);
        $country = $data->getCountry();
        $this->assertSame('United Kingdom', $country->name);
        $iterator->next();
        $data = $iterator->current();
        $this->assertSame('Tokyo', $data->name);
        $country = $data->getCountry();
        $this->assertNull($country);
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
