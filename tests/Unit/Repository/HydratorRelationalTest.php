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
use CCMBenchmark\Ting\Repository\Hydrator\AggregateFrom;
use CCMBenchmark\Ting\Repository\Hydrator\AggregateTo;
use CCMBenchmark\Ting\Repository\Hydrator\RelationMany;
use CCMBenchmark\Ting\Repository\Hydrator\RelationOne;
use CCMBenchmark\Ting\Repository\HydratorRelational;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Tests\Support\TingServices;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use tests\fixtures\FakeDriver\MysqliResult;
use tests\fixtures\model\CityWithPublicPropertiesRepository;
use tests\fixtures\model\CountryWithPublicPropertiesRepository;

use function array_keys;
use function array_map;
use function array_values;
use function in_array;

use const MYSQLI_TYPE_VAR_STRING;

/**
 * HydratorRelational
 */
class HydratorRelationalTest extends TestCase
{
    /**
     * @var TingServices
     */
    private $services;

    private function getResult()
    {
        $this->services = new TingServices();
        $metadata = new Metadata($this->services->serializerFactory());
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

        $this->services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadata = new Metadata($this->services->serializerFactory());
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

        $this->services->metadataRepository()->addMetadata('tests\fixtures\model\CityRepository', $metadata);

        $mockMysqliResult = new MysqliResult([
            [4, 'Xavier', 'Leune', 1, 'Boulogne-Billancourt'],
            [4, 'Xavier', 'Leune', 2, 'Palaiseau'],
            [3, 'Sylvain', 'Robez-Masson', 2, 'Palaiseau'],
            [3, 'Sylvain', 'Robez-Masson', 3, 'Montbéliard'],
            [3, 'Sylvain', 'Robez-Masson', 4, 'Luxiol']
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
        $hydrator->setMetadataRepository($this->services->metadataRepository());
        $hydrator->setUnitOfWork($this->services->unitOfWork());
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

        $this->services->metadataRepository()->addMetadata(
            'tests\fixtures\model\CityRepository',
            \tests\fixtures\model\CityRepository::initMetadata($this->services->serializerFactory())
        );

        $this->services->metadataRepository()->addMetadata(
            'tests\fixtures\model\BouhRepository',
            \tests\fixtures\model\BouhRepository::initMetadata($this->services->serializerFactory())
        );

        $hydrator = new HydratorRelational();
        $hydrator->setMetadataRepository($this->services->metadataRepository());
        $hydrator->setUnitOfWork($this->services->unitOfWork());
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
        $hydrator->setMetadataRepository($this->services->metadataRepository());
        $hydrator->setUnitOfWork($this->services->unitOfWork());
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
        $hydrator->setMetadataRepository($this->services->metadataRepository());
        $hydrator->setUnitOfWork($this->services->unitOfWork());
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
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadata = new Metadata($services->serializerFactory());
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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\CityRepository', $metadata);

        $mockMysqliResult = new MysqliResult([
            [4, 'Xavier', 'Leune', 1, 'Boulogne-Billancourt'],
            [3, 'Sylvain', 'Robez-Masson', 2, 'Palaiseau'],
            [4, 'Xavier', 'Leune', 2, 'Palaiseau'],
            [3, 'Sylvain', 'Robez-Masson', 3, 'Montbéliard'],
            [3, 'Sylvain', 'Robez-Masson', 4, 'Luxiol']
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
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
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
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadata = new Metadata($services->serializerFactory());
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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\CityRepository', $metadata);

        $services->metadataRepository()->addMetadata(
            'tests\fixtures\model\ParkRepository',
            \tests\fixtures\model\ParkRepository::initMetadata($services->serializerFactory())
        );

        $mockMysqliResult = new MysqliResult([
            [4, 'Xavier', 'Leune', 1, 'Boulogne-Billancourt', 1, 'Parc de Billancourt'],
            [3, 'Sylvain', 'Robez-Masson', 2, 'Palaiseau', 2, 'Parc Pierre et Marie Curie'],
            [4, 'Xavier', 'Leune', 2, 'Palaiseau', null, null],
            [3, 'Sylvain', 'Robez-Masson', 3, 'Montbéliard', null, null],
            [4, 'Xavier', 'Leune', 1, 'Boulogne-Billancourt', 3, 'Parc de Boulogne-Edmond-de-Rothschild'],
            [3, 'Sylvain', 'Robez-Masson', 2, 'Palaiseau', 4, 'Square du Pileu'],
            [3, 'Sylvain', 'Robez-Masson', 2, 'Palaiseau', 5, 'Bois du Clos du Pileu'],
            [3, 'Sylvain', 'Robez-Masson', 4, 'Luxiol', null, null]
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
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
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
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\NurseryRepository', $metadata);

        $metadata = new Metadata($services->serializerFactory());
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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\CityRepository', $metadata);

        $metadata = new Metadata($services->serializerFactory());
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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\DepartmentRepository', $metadata);

        $metadata = new Metadata($services->serializerFactory());
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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\AuthorityRepository', $metadata);

        $mockMysqliResult = new MysqliResult([
            [1, 11, 22, 33],
        ]);
        $mockMysqliResult->setFieldsCallback(function () {
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
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
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
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $mockMysqliResult = new MysqliResult([
            [1, 'Xavier', 'Leune'],
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
            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        $hydrator = new HydratorRelational();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Leune', $data['bouh']->getName());
        $this->assertSame('Xavier', $data['bouh']->getFirstname());
    }

    public function testHydrateNoRelationFinalizeAggregate()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $mockMysqliResult = new MysqliResult([
            [1, 'Xavier', 'Leune'],
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
            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        $hydrator = new HydratorRelational();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
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
        $services = new TingServices();
        $metadataCity = CityWithPublicPropertiesRepository::initMetadata($services->serializerFactory());
        $metadataCountry = CountryWithPublicPropertiesRepository::initMetadata($services->serializerFactory());

        $services->metadataRepository()->addMetadata($metadataCity->getEntity(), $metadataCity);
        $services->metadataRepository()->addMetadata($metadataCountry->getEntity(), $metadataCountry);

        $mockMysqliResult = new MysqliResult([
            [1, 'Paris', 2, 'France'],
            [2, 'London', 3, 'United Kingdom'],
            [3, 'Tokyo', null, null], // No relation
        ]);
        $mockMysqliResult->setFieldsCallback(function () {
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
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
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
     * Every permutation of the relations of a three-level tree (bouh <- c <- dep <- auth), with two relations sharing
     * the source c.
     */
    public static function relationOrders(): iterable
    {
        $relations = ['citiesAre', 'setCity', 'setDepartment', 'setAuthority'];
        foreach (self::permutations($relations) as $permutation) {
            yield implode(', ', $permutation) => [$permutation];
        }
    }

    private static function permutations(array $items): array
    {
        if (count($items) <= 1) {
            return [$items];
        }

        $permutations = [];
        foreach ($items as $index => $item) {
            $rest = $items;
            unset($rest[$index]);
            foreach (self::permutations(array_values($rest)) as $permutation) {
                $permutations[] = [$item, ...$permutation];
            }
        }

        return $permutations;
    }

    /**
     * @param list<string> $order
     */
    #[DataProvider('relationOrders')]
    public function testHydrateDoesNotDependOnTheOrderOfTheRelations(array $order): void
    {
        $services = new TingServices();
        $hydrator = $this->getTreeHydrator($services, [
            [1, 'Leune', 10, 'Paris', 100, 1000],
            [1, 'Leune', 11, 'Lyon', 101, 1001],
            [2, 'Robez-Masson', 12, 'Nancy', 100, 1000],
        ]);
        $relations = [
            'citiesAre'     => new RelationMany(new AggregateFrom('c'), new AggregateTo('bouh'), 'citiesAre'),
            'setCity'       => new RelationOne(new AggregateFrom('c'), new AggregateTo('bouh'), 'setCity'),
            'setDepartment' => new RelationOne(new AggregateFrom('dep'), new AggregateTo('c'), 'setDepartment'),
            'setAuthority'  => new RelationOne(new AggregateFrom('auth'), new AggregateTo('dep'), 'setAuthority'),
        ];
        foreach ($order as $name) {
            $hydrator->addRelation($relations[$name]);
        }

        $rows = iterator_to_array($hydrator->getIterator(), false);

        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertSame(['bouh'], array_keys($row));
        }

        $describe = static fn ($city) => $city->getName() . '/' . $city->getDepartment()?->getId()
            . '/' . $city->getDepartment()?->getAuthority()?->getId();

        $first = $rows[0]['bouh'];
        $this->assertSame('Leune', $first->getName());
        $this->assertSame(['Paris/100/1000', 'Lyon/101/1001'], array_map($describe, array_values($first->getCities())));
        $this->assertSame('Lyon/101/1001', $describe($first->getCity()));
        // Leaves first: the city given to setCity() (cloned by the setter) already has its department
        $this->assertSame('Lyon/101/1001', $describe($first->getOriginalCity()));

        $second = $rows[1]['bouh'];
        $this->assertSame('Robez-Masson', $second->getName());
        $this->assertSame(['Nancy/100/1000'], array_map($describe, array_values($second->getCities())));
        $this->assertSame('Nancy/100/1000', $describe($second->getOriginalCity()));
        $this->assertSame(
            array_values($first->getCities())[0]->getDepartment(),
            $second->getCity()->getDepartment()
        );
    }

    public function testHydrateAppliesEveryRelationOfTheSameSource(): void
    {
        $result = $this->getResult();
        $hydrator = new HydratorRelational();
        $hydrator->setMetadataRepository($this->services->metadataRepository());
        $hydrator->setUnitOfWork($this->services->unitOfWork());
        $hydrator->addRelation(new RelationMany(new AggregateFrom('c'), new AggregateTo('bouh'), 'citiesAre'));
        $hydrator->addRelation(new RelationOne(new AggregateFrom('c'), new AggregateTo('bouh'), 'setCity'));

        $rows = iterator_to_array($hydrator->setResult($result)->getIterator(), false);

        $this->assertCount(2, $rows);
        $names = static fn (array $cities) => array_map(static fn ($city) => $city->getName(), array_values($cities));
        $this->assertSame(['Boulogne-Billancourt', 'Palaiseau'], $names($rows[0]['bouh']->getCities()));
        $this->assertSame('Palaiseau', $rows[0]['bouh']->getCity()->getName());
        $this->assertSame(['Palaiseau', 'Montbéliard', 'Luxiol'], $names($rows[1]['bouh']->getCities()));
        $this->assertSame('Luxiol', $rows[1]['bouh']->getCity()->getName());
    }

    public function testHydrateShouldIdentifyRootsByTheirWholeCompositeKey(): void
    {
        $services = new TingServices();
        $tables = [
            ['tests\fixtures\model\Bouh', 'T_BOUH_BOO', 'b', ['name' => 'boo_name', 'firstname' => 'boo_firstname'], ['name', 'firstname']],
            ['tests\fixtures\model\City', 'T_CITY_CIT', 'c', ['id' => 'cit_id', 'name' => 'cit_name'], ['id']],
        ];
        $fields = [];
        foreach ($tables as [$entity, $table, $alias, $columns, $primaries]) {
            $metadata = new Metadata($services->serializerFactory());
            $metadata->setConnectionName('connectionName');
            $metadata->setDatabase('database');
            $metadata->setEntity($entity);
            $metadata->setTable($table);
            foreach ($columns as $fieldName => $columnName) {
                $metadata->addField([
                    'primary'    => in_array($fieldName, $primaries, true),
                    'fieldName'  => $fieldName,
                    'columnName' => $columnName,
                    'type'       => 'string',
                ]);

                $field = new \stdClass();
                $field->name     = $columnName;
                $field->orgname  = $columnName;
                $field->table    = $alias;
                $field->orgtable = $table;
                $field->type     = MYSQLI_TYPE_VAR_STRING;
                $fields[] = $field;
            }
            $services->metadataRepository()->addMetadata($entity . 'Repository', $metadata);
        }

        // With "-" between the values, both keys of b are "Jean-Pierre-Paul-"
        $mockMysqliResult = new MysqliResult([['Jean', 'Pierre-Paul', '1', 'Paris'], ['Jean-Pierre', 'Paul', '2', 'Lyon']]);
        $mockMysqliResult->setFields($fields);
        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        $hydrator = new HydratorRelational();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->addRelation(new RelationMany(new AggregateFrom('c'), new AggregateTo('b'), 'citiesAre'));

        $rows = iterator_to_array($hydrator->setResult($result)->getIterator(), false);

        $this->assertSame(
            [['Jean', 'Pierre-Paul', ['Paris']], ['Jean-Pierre', 'Paul', ['Lyon']]],
            array_map(static fn (array $row) => [
                $row['b']->getName(),
                $row['b']->getFirstname(),
                array_map(static fn ($city) => $city->getName(), array_values($row['b']->getCities())),
            ], $rows)
        );
    }

    public function testHydrateRejectsABidirectionalRelation(): void
    {
        $hydrator = $this->getTreeHydrator(new TingServices(), [[1, 'Leune', 10, 'Paris', 100, 1000]]);
        $hydrator->addRelation(new RelationMany(new AggregateFrom('c'), new AggregateTo('bouh'), 'citiesAre'));
        $hydrator->addRelation(new RelationOne(new AggregateFrom('bouh'), new AggregateTo('c'), 'setBouh'));

        $this->assertThrows(
            HydratorException::class,
            fn () => iterator_to_array($hydrator->getIterator()),
            'Cannot order the relations c -> bouh, bouh -> c: they contain a cycle. Every relation must lead to a '
            . 'root alias, which is the source of no relation: set the back reference in the setter instead.'
        );
    }

    public function testHydrateRejectsACycle(): void
    {
        $hydrator = $this->getTreeHydrator(new TingServices(), [[1, 'Leune', 10, 'Paris', 100, 1000]]);
        $hydrator->addRelation(new RelationMany(new AggregateFrom('c'), new AggregateTo('bouh'), 'citiesAre'));
        $hydrator->addRelation(new RelationOne(new AggregateFrom('auth'), new AggregateTo('dep'), 'setAuthority'));
        $hydrator->addRelation(new RelationOne(new AggregateFrom('dep'), new AggregateTo('c'), 'setDepartment'));
        $hydrator->addRelation(new RelationOne(new AggregateFrom('c'), new AggregateTo('auth'), 'setCity'));

        $this->assertThrows(
            HydratorException::class,
            fn () => iterator_to_array($hydrator->getIterator()),
            'Cannot order the relations c -> bouh, auth -> dep, dep -> c, c -> auth: they contain a cycle. Every '
            . 'relation must lead to a root alias, which is the source of no relation: set the back reference in '
            . 'the setter instead.'
        );
    }

    public function testHydrateRejectsARelationOfAnAliasToItself(): void
    {
        $hydrator = $this->getTreeHydrator(new TingServices(), [[1, 'Leune', 10, 'Paris', 100, 1000]]);
        $hydrator->addRelation(new RelationOne(new AggregateFrom('c'), new AggregateTo('c'), 'setCity'));

        $this->assertThrows(
            HydratorException::class,
            fn () => iterator_to_array($hydrator->getIterator()),
            'Cannot order the relations c -> c: they contain a cycle. Every relation must lead to a root alias, '
            . 'which is the source of no relation: set the back reference in the setter instead.'
        );
    }

    /**
     * A hydrator over rows (bouh.id, bouh.name, c.id, c.name, dep.id, auth.id).
     */
    private function getTreeHydrator(TingServices $services, array $rows): HydratorRelational
    {
        $tables = [
            ['tests\fixtures\model\Bouh', 'T_BOUH_BOO', 'bouh', ['id' => 'boo_id', 'name' => 'boo_name']],
            ['tests\fixtures\model\City', 'T_CITY_CIT', 'c', ['id' => 'cit_id', 'name' => 'cit_name']],
            ['tests\fixtures\model\Department', 'T_DEPARTMENT', 'dep', ['id' => 'department_id']],
            ['tests\fixtures\model\Authority', 'T_AUTHORITY', 'auth', ['id' => 'authority_id']],
        ];
        $fields = [];
        foreach ($tables as [$entity, $table, $alias, $columns]) {
            $metadata = new Metadata($services->serializerFactory());
            $metadata->setConnectionName('connectionName');
            $metadata->setDatabase('database');
            $metadata->setEntity($entity);
            $metadata->setTable($table);
            foreach ($columns as $fieldName => $columnName) {
                $metadata->addField([
                    'primary'    => $fieldName === 'id',
                    'fieldName'  => $fieldName,
                    'columnName' => $columnName,
                    'type'       => $fieldName === 'id' ? 'int' : 'string',
                ]);

                $field = new \stdClass();
                $field->name     = $fieldName;
                $field->orgname  = $columnName;
                $field->table    = $alias;
                $field->orgtable = $table;
                $field->type     = MYSQLI_TYPE_VAR_STRING;
                $fields[] = $field;
            }
            $services->metadataRepository()->addMetadata($entity . 'Repository', $metadata);
        }

        $mockMysqliResult = new MysqliResult($rows);
        $mockMysqliResult->setFieldsCallback(static fn () => $fields);

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        $hydrator = new HydratorRelational();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->setResult($result);

        return $hydrator;
    }
}
