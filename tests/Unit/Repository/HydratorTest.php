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
use CCMBenchmark\Ting\Driver\Pgsql\Result as PgsqlResult;
use CCMBenchmark\Ting\Exceptions\HydratorException;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Repository\Hydrator;
use CCMBenchmark\Ting\Repository\HydratorAggregator;
use CCMBenchmark\Ting\Repository\HydratorArray;
use CCMBenchmark\Ting\Repository\HydratorInterface;
use CCMBenchmark\Ting\Repository\HydratorRelational;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;
use CCMBenchmark\Ting\Repository\HydratorValueObject;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Serializer\DateTime;
use CCMBenchmark\Ting\Serializer\Json;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;
use CCMBenchmark\Ting\Tests\Support\TingServices;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use CCMBenchmark\Ting\UnitOfWork;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use tests\fixtures\FakeDriver\MysqliResult;
use tests\fixtures\Serializer\CountingJson;
use tests\fixtures\model\Bouh;
use tests\fixtures\model\BouhRepository;
use tests\fixtures\model\City;
use tests\fixtures\model\CityLookingUp;
use tests\fixtures\model\CityRepository;
use tests\fixtures\model\PrimaryOnMultiField;

class HydratorTest extends TestCase
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

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Robez-Masson', $data['bouh']->getName());
        $this->assertSame('Sylvain', $data['bouh']->getFirstname());
    }

    public function testHydrateForEntityWithouNotifyPropertyInterfaceShouldWork()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\BouhReadOnly');
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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhReadOnlyRepository', $metadata);

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

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Robez-Masson', $data['bouh']->getName());
        $this->assertSame('Sylvain', $data['bouh']->getFirstname());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testHydrateWithSchema()
    {
        $services = new TingServices();

        $services->metadataRepository()->addMetadata(
            'tests\fixtures\model\BouhRepository',
            \tests\fixtures\model\BouhRepository::initMetadata($services->serializerFactory())
        );

        $services->metadataRepository()->addMetadata(
            'tests\fixtures\model\BouhMySchemaRepository',
            \tests\fixtures\model\BouhMySchemaRepository::initMetadata($services->serializerFactory())
        );

        // Partial mock, like the atoum one: only the overridden iterator methods are replaced
        $result = $this->getMockBuilder(PgsqlResult::class)
            ->onlyMethods(['rewind', 'valid', 'current'])
            ->getMock();
        $result->method('rewind');
        $result->method('valid')->willReturn(true);
        $result->method('current')->willReturn([
            [
                'name' => 'fname',
                'orgName' => 'boo_firstname',
                'schema' => 'mySchema',
                'table' => 'bouh',
                'orgTable' => 'T_BOUH_BOO',
                'value' => 'Sylvain'
            ],
            [
                'name' => 'name',
                'orgName' => 'boo_name',
                'schema' => 'mySchema',
                'table' => 'bouh',
                'orgTable' => 'T_BOUH_BOO',
                'value' => 'Robez-Masson'
            ]
        ]);

        $result->setResult(new PgsqlResult());
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('MySchemaRobez-Masson', $data['bouh']->getName());
        $this->assertSame('MySchemaSylvain', $data['bouh']->getFirstname());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testHydrateWithObjectSchemaIsAndNoSchemaInQuery()
    {
        $services = new TingServices();

        $services->metadataRepository()->addMetadata(
            'tests\fixtures\model\BouhRepository',
            \tests\fixtures\model\BouhRepository::initMetadata($services->serializerFactory())
        );

        $services->metadataRepository()->addMetadata(
            'tests\fixtures\model\BouhMySchemaRepository',
            \tests\fixtures\model\BouhMySchemaRepository::initMetadata($services->serializerFactory())
        );

        // Pgsql\Result sets the schema to '' when the query doesn't name it
        $result = $this->getMockBuilder(PgsqlResult::class)
            ->onlyMethods(['rewind', 'valid', 'current'])
            ->getMock();
        $result->method('rewind');
        $result->method('valid')->willReturn(true);
        $result->method('current')->willReturn([
            [
                'name' => 'fname',
                'orgName' => 'boo_firstname',
                'schema' => '',
                'table' => 'bouh',
                'orgTable' => 'T_BOUH_BOO',
                'value' => 'Sylvain'
            ],
            [
                'name' => 'name',
                'orgName' => 'boo_name',
                'schema' => '',
                'table' => 'bouh',
                'orgTable' => 'T_BOUH_BOO',
                'value' => 'Robez-Masson'
            ]
        ]);

        $result->setResult(new PgsqlResult());
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        $hydrator->objectSchemaIs('bouh', 'mySchema');
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('MySchemaRobez-Masson', $data['bouh']->getName());
        $this->assertSame('MySchemaSylvain', $data['bouh']->getFirstname());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testHydrateWithObjectSchemaIsShouldOverrideTheSchemaOfTheQuery()
    {
        $services = new TingServices();

        $services->metadataRepository()->addMetadata(
            'tests\fixtures\model\BouhRepository',
            \tests\fixtures\model\BouhRepository::initMetadata($services->serializerFactory())
        );

        $services->metadataRepository()->addMetadata(
            'tests\fixtures\model\BouhMySchemaRepository',
            \tests\fixtures\model\BouhMySchemaRepository::initMetadata($services->serializerFactory())
        );

        // As in 3.x, objectSchemaIs() wins over the schema read in the query
        $result = $this->getMockBuilder(PgsqlResult::class)
            ->onlyMethods(['rewind', 'valid', 'current'])
            ->getMock();
        $result->method('rewind');
        $result->method('valid')->willReturn(true);
        $result->method('current')->willReturn([
            [
                'name' => 'fname',
                'orgName' => 'boo_firstname',
                'schema' => 'otherSchema',
                'table' => 'bouh',
                'orgTable' => 'T_BOUH_BOO',
                'value' => 'Sylvain'
            ],
            [
                'name' => 'name',
                'orgName' => 'boo_name',
                'schema' => 'otherSchema',
                'table' => 'bouh',
                'orgTable' => 'T_BOUH_BOO',
                'value' => 'Robez-Masson'
            ]
        ]);

        $result->setResult(new PgsqlResult());
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        $hydrator->objectSchemaIs('bouh', 'mySchema');
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('MySchemaRobez-Masson', $data['bouh']->getName());
        $this->assertSame('MySchemaSylvain', $data['bouh']->getFirstname());
    }

    public function testHydrateWithAllNullValueShouldReturnNull()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $mockMysqliResult = new MysqliResult([[null, null]]);
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

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertNull($data['bouh']);
    }

    public function testHydrateWithSomeNullValueShouldNotReturnNull()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $mockMysqliResult = new MysqliResult([[null, 'Robez-Masson']]);
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

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Robez-Masson', $data['bouh']->getName());
    }

    public function testHydrateShouldHydrateUnknownColumnIntoKey0()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
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

        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $mockMysqliResult = new MysqliResult([['Sylvain', 'Robez-Masson', 'Happy Face']]);
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

            $stdClass = new \stdClass();
            $stdClass->name     = 'otherColumn';
            $stdClass->orgname  = 'boo_other_column';
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

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Robez-Masson', $data['bouh']->getName());
        $this->assertSame('Sylvain', $data['bouh']->getFirstname());
        $this->assertSame('Happy Face', $data[0]->otherColumn);
    }

    public function testHydrateShouldHydrateUnknownColumnOfFromReferenceTable()
    {
        $services = new TingServices();
        $services->metadataRepository()
            ->batchLoadMetadata('tests\fixtures\model', __DIR__ . '/../../fixtures/model/*Repository.php');

        $mockMysqliResult = new MysqliResult([
            [23, 'LeBron', 'James', 'Cleveland'],
            [23, 'LeBron', 'James', 'Los Angeles']
        ]);

        $mockMysqliResult->setFieldsCallback(function () {
            $fields = [];

            $stdClass = new \stdClass();
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'boo_id';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_LONG;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'firstname';
            $stdClass->orgname  = 'boo_firstname';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'bouh';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'name';
            $stdClass->orgname  = 'boo_name';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            // This column is associated with a mapped table
            // while parsing but not mapped into metadatas
            $stdClass = new \stdClass();
            $stdClass->name     = 'notMappedBouhColumn';
            $stdClass->orgname  = 'bouh_notMappedBouhColumn';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        // BouhReadOnlyRepository maps T_BOUH_BOO of bouh_world too
        $hydrator->preferRepository(BouhRepository::class);
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $iterator = $hydrator->setResult($result)->getIterator();
        $currentObject = $iterator->current();
        $iterator->next();
        $nextObject = $iterator->current();
        $this->assertSame(
            true,
            is_array($currentObject) && array_key_exists(0, $currentObject),
            'Unmapped column of known table was not hydrated'
        );
        $this->assertSame('Cleveland', $currentObject[0]->notMappedBouhColumn);
        $this->assertSame(
            true,
            is_array($nextObject) && array_key_exists(0, $nextObject),
            'Unmapped column of known table was not hydrated'
        );
        $this->assertSame('Los Angeles', $nextObject[0]->notMappedBouhColumn);
    }

    public function testHydrateShouldHydrateIntoKey0()
    {
        $services = new TingServices();

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

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame('Robez-Masson', $data[0]->name);
        $this->assertSame('Sylvain', $data[0]->fname);
    }

    public function testCountShouldReturn3()
    {
        $result = $this->createStub(Result::class);
        $result->method('getNumRows')->willReturn(3);

        $hydrator = new Hydrator();
        $hydrator->setResult($result);
        $this->assertSame(3, count($hydrator));
    }

    public function testCountWithoutResultShoulddReturn0()
    {
        $hydrator = new Hydrator();
        $this->assertSame(0, count($hydrator));
    }

    public static function hydratorsProvider(): array
    {
        return [
            'Hydrator' => [new Hydrator()],
            'HydratorSingleObject' => [new HydratorSingleObject()],
            'HydratorAggregator' => [(new HydratorAggregator())->callableIdIs(fn (array $row) => 1)],
            'HydratorRelational' => [new HydratorRelational()],
            'HydratorArray' => [new HydratorArray()],
            'HydratorValueObject' => [new HydratorValueObject(\stdClass::class)],
        ];
    }

    #[DataProvider('hydratorsProvider')]
    public function testIterationWithoutResultShouldBeEmpty(HydratorInterface $hydrator)
    {
        $items = null;
        $errors = $this->collectErrorTypes(function () use ($hydrator, &$items): void {
            $items = iterator_to_array($hydrator->getIterator());
        }, $thrown);

        $this->assertNull($thrown);
        $this->assertSame([], $errors);
        $this->assertSame([], $items);
    }

    public function testHydrateARowOfAResultWithoutConnectionShouldRaiseHydratorException()
    {
        $services = new TingServices();
        $result = new CacheResult();
        $result->setResult(new \ArrayIterator([
            [['name' => 'name', 'orgName' => 'boo_name', 'table' => 'bouh', 'orgTable' => 'T_BOUH_BOO', 'value' => 'Sylvain']],
        ]));
        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->setResult($result);

        $this->assertThrows(
            HydratorException::class,
            fn () => iterator_to_array($hydrator->getIterator()),
            'Cannot hydrate a row of a result without connection name or database: its metadata cannot be found'
        );
    }

    public function testHydrateBeforeSetMetadataRepositoryShouldRaiseHydratorException()
    {
        $hydrator = new Hydrator();
        $hydrator->setUnitOfWork((new TingServices())->unitOfWork());
        $hydrator->setResult($this->bouhResult());

        $this->assertThrows(
            HydratorException::class,
            fn () => iterator_to_array($hydrator->getIterator()),
            'Hydrator used before setMetadataRepository(): the metadata of the rows cannot be found'
        );
    }

    public function testHydrateAnEntityBeforeSetUnitOfWorkShouldRaiseHydratorException()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setTable('T_BOUH_BOO');
        $metadata->addField(['fieldName' => 'name', 'columnName' => 'boo_name', 'type' => 'string']);
        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setResult($this->bouhResult());

        $this->assertThrows(
            HydratorException::class,
            fn () => iterator_to_array($hydrator->getIterator()),
            'Hydrator used before setUnitOfWork(): the entities cannot be managed'
        );
    }

    private function bouhResult(): Result
    {
        $result = new Result();
        $result->setResult(
            (new MysqliResult([['Sylvain']]))->setFields([$this->field('name', 'boo_name', 'bouh', 'T_BOUH_BOO')])
        );
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');

        return $result;
    }

    public function testHydrateWithMapAliasShouldHydrateToMethodOfObject()
    {
        $services = new TingServices();
        $services->metadataRepository()
            ->batchLoadMetadata('tests\fixtures\model', __DIR__ . '/../../fixtures/model/*Repository.php');

        $time = time();
        $mockMysqliResult = new MysqliResult([['Sylvain', 'Robez-Masson', $time]]);
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

            $stdClass = new \stdClass();
            $stdClass->name     = 'current_time';
            $stdClass->orgname  = '';
            $stdClass->table    = '';
            $stdClass->orgtable = '';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;
            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        // BouhReadOnlyRepository maps T_BOUH_BOO of bouh_world too
        $hydrator->preferRepository(BouhRepository::class);
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->mapAliasTo('current_time', 'bouh', 'setRetrievedTime');
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertSame($time, $data['bouh']->getRetrievedTime());
        $this->assertArrayNotHasKey(0, $data);
    }

    public function testHydrateWithMapObjectShouldHydrateToMethodOfObject()
    {
        $services = new TingServices();
        $services->metadataRepository()
            ->batchLoadMetadata('tests\fixtures\model', __DIR__ . '/../../fixtures/model/*Repository.php');

        $mockMysqliResult = new MysqliResult([
                ['Sylvain', 'Robez-Masson', 3, 'Palaiseau']
            ]);
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

            $stdClass = new \stdClass();
            $stdClass->name     = 'cityId';
            $stdClass->orgname  = 'cit_id';
            $stdClass->table    = 'cit';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type     = MYSQLI_TYPE_LONG;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'citname';
            $stdClass->orgname  = 'cit_name';
            $stdClass->table    = 'cit';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;
            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        // BouhReadOnlyRepository maps T_BOUH_BOO of bouh_world too
        $hydrator->preferRepository(BouhRepository::class);
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->mapObjectTo('cit', 'bouh', 'setCity');
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $city = $data['bouh']->getCity();
        $this->assertIsObject($city);
        $this->assertSame(3, $city->getId());
        $this->assertSame('Palaiseau', $city->getName());
    }

    public function testHydrateWithMapObjectShouldHydrateToMethodOfObjectWithAManagedEntity()
    {
        $services = new TingServices();
        $services->metadataRepository()
            ->batchLoadMetadata('tests\fixtures\model', __DIR__ . '/../../fixtures/model/*Repository.php');

        $mockMysqliResult = new MysqliResult([
            ['Sylvain', 'Robez-Masson', 3, 'Palaiseau']
        ]);
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

            $stdClass = new \stdClass();
            $stdClass->name     = 'cityId';
            $stdClass->orgname  = 'cit_id';
            $stdClass->table    = 'cit';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type     = MYSQLI_TYPE_LONG;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'citname';
            $stdClass->orgname  = 'cit_name';
            $stdClass->table    = 'cit';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;
            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        // BouhReadOnlyRepository maps T_BOUH_BOO of bouh_world too
        $hydrator->preferRepository(BouhRepository::class);
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->mapObjectTo('cit', 'bouh', 'setCity');
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $city = $data['bouh']->getOriginalCity();
        $this->assertIsObject($city);
    }

    public static function joinedColumnsFirstProvider(): array
    {
        return ['joined entity before' => [true], 'joined entity after' => [false]];
    }

    #[DataProvider('joinedColumnsFirstProvider')]
    public function testHydrateWithMapObjectShouldNotCallTheMethodWhenTheJoinedEntityIsNull(bool $joinedFirst)
    {
        $services = new TingServices();
        $services->metadataRepository()
            ->batchLoadMetadata('tests\fixtures\model', __DIR__ . '/../../fixtures/model/*Repository.php');

        $field = static function (string $name, string $orgName, string $table, string $orgTable, int $type) {
            $field = new \stdClass();
            $field->name     = $name;
            $field->orgname  = $orgName;
            $field->table    = $table;
            $field->orgtable = $orgTable;
            $field->type     = $type;
            return $field;
        };
        $bouhFields = [
            $field('fname', 'boo_firstname', 'bouh', 'T_BOUH_BOO', MYSQLI_TYPE_VAR_STRING),
            $field('name', 'boo_name', 'bouh', 'T_BOUH_BOO', MYSQLI_TYPE_VAR_STRING),
        ];
        $cityFields = [
            $field('cityId', 'cit_id', 'cit', 'T_CITY_CIT', MYSQLI_TYPE_LONG),
            $field('citname', 'cit_name', 'cit', 'T_CITY_CIT', MYSQLI_TYPE_VAR_STRING),
        ];
        // LEFT JOIN without match: every column of the city is null
        $mockMysqliResult = new MysqliResult([
            $joinedFirst ? [null, null, 'Sylvain', 'Robez-Masson'] : ['Sylvain', 'Robez-Masson', null, null],
        ]);
        $mockMysqliResult->setFieldsCallback(
            fn () => $joinedFirst ? [...$cityFields, ...$bouhFields] : [...$bouhFields, ...$cityFields]
        );

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        // BouhReadOnlyRepository maps T_BOUH_BOO of bouh_world too
        $hydrator->preferRepository(BouhRepository::class);
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->mapObjectTo('cit', 'bouh', 'setCity');
        $data = $hydrator->setResult($result)->getIterator()->current();

        $this->assertSame(['bouh'], array_keys($data));
        $this->assertSame('Sylvain', $data['bouh']->getFirstname());
        $this->assertNull($data['bouh']->getCity());
    }

    public function testHydrateWithUnserializeAlias()
    {
        $services = new TingServices();
        $services->metadataRepository()
            ->batchLoadMetadata('tests\fixtures\model', __DIR__ . '/../../fixtures/model/*Repository.php');

        $mockMysqliResult = new MysqliResult([
            ['{"name": "Sylvain"}', 'Palaiseau']
        ]);
        $mockMysqliResult->setFieldsCallback(function () {
            $fields = [];
            $stdClass = new \stdClass();
            $stdClass->name     = 'data';
            $stdClass->orgname  = '';
            $stdClass->table    = '';
            $stdClass->orgtable = '';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'citname';
            $stdClass->orgname  = 'cit_name';
            $stdClass->table    = 'cit';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;
            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->unserializeAliasWith('data', new Json(), ['assoc' => true]);
        $iterator = $hydrator->setResult($result)->getIterator();
        $result = $iterator->current();
        $data = $result[0]->data;
        $this->assertIsArray($data);
        $this->assertSame('Sylvain', $data['name']);
    }

    public function testHydrateWithUnserializeAliasAndMapAlias()
    {
        $services = new TingServices();
        $services->metadataRepository()
            ->batchLoadMetadata('tests\fixtures\model', __DIR__ . '/../../fixtures/model/*Repository.php');

        $time = time();
        $mockMysqliResult = new MysqliResult([['Sylvain', 'Robez-Masson', $time]]);
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

            $stdClass = new \stdClass();
            $stdClass->name     = 'current_time';
            $stdClass->orgname  = '';
            $stdClass->table    = '';
            $stdClass->orgtable = '';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;
            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        // BouhReadOnlyRepository maps T_BOUH_BOO of bouh_world too
        $hydrator->preferRepository(BouhRepository::class);
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->mapAliasTo('current_time', 'bouh', 'setRetrievedTime');
        $hydrator->unserializeAliasWith('current_time', new DateTime(), ['format' => 'U']);
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $datetime = $data['bouh']->getRetrievedTime();
        $this->assertIsObject($datetime);
        $this->assertSame($time, $datetime->getTimestamp());
    }

    public function testHydrateWithObjectDatabaseIsShouldHydrateToCity2()
    {
        $services = new TingServices();
        $services->metadataRepository()
            ->batchLoadMetadata('tests\fixtures\model', __DIR__ . '/../../fixtures/model/*Repository.php');

        $mockMysqliResult = new MysqliResult([
            ['Sylvain', 'Robez-Masson', 3, 'Palaiseau']
        ]);
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

            $stdClass = new \stdClass();
            $stdClass->name     = 'cityId';
            $stdClass->orgname  = 'cit_id';
            $stdClass->table    = 'cit';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type     = MYSQLI_TYPE_LONG;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'citname';
            $stdClass->orgname  = 'cit_name';
            $stdClass->table    = 'cit';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;
            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        // BouhReadOnlyRepository maps T_BOUH_BOO of bouh_world too
        $hydrator->preferRepository(BouhRepository::class);
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->objectDatabaseIs('cit', 'bouh_world_2');
        $iterator = $hydrator->setResult($result)->getIterator();
        $data = $iterator->current();
        $this->assertInstanceOf(\tests\fixtures\model\CitySecond::class, $data['cit']);
    }

    public function testObjectDatabaseIsShouldOnlyApplyToItsAlias()
    {
        $services = new TingServices();
        $services->metadataRepository()
            ->batchLoadMetadata('tests\fixtures\model', __DIR__ . '/../../fixtures/model/*Repository.php');

        // T_CITY_CIT is City in bouh_world and CitySecond in bouh_world_2: only cit2 lives in bouh_world_2
        $cit2 = [
            $this->field('cit_id', 'cit_id', 'cit2', 'T_CITY_CIT', MYSQLI_TYPE_LONG),
            $this->field('cit_name', 'cit_name', 'cit2', 'T_CITY_CIT'),
        ];
        $cit = [
            $this->field('cit_id', 'cit_id', 'cit', 'T_CITY_CIT', MYSQLI_TYPE_LONG),
            $this->field('cit_name', 'cit_name', 'cit', 'T_CITY_CIT'),
        ];

        foreach ([[...$cit2, ...$cit], [...$cit, ...$cit2]] as $fields) {
            $mockMysqliResult = new MysqliResult([[1, 'Paris', 2, 'Lyon']]);
            $mockMysqliResult->setFields($fields);

            $result = new Result();
            $result->setResult($mockMysqliResult);
            $result->setConnectionName('main');
            $result->setDatabase('bouh_world');

            $hydrator = new Hydrator();
            $hydrator->setMetadataRepository($services->metadataRepository());
            $hydrator->setUnitOfWork($services->unitOfWork());
            $hydrator->objectDatabaseIs('cit2', 'bouh_world_2');
            $data = $hydrator->setResult($result)->getIterator()->current();

            $this->assertInstanceOf(\tests\fixtures\model\CitySecond::class, $data['cit2']);
            $this->assertInstanceOf(City::class, $data['cit']);
        }
    }

    public function testHydrateATableOfAnotherDatabaseShouldNotDependOnTheRegistrationOrder(): void
    {
        // T_CITY_CIT is City in bouh_world and CitySecond in bouh_world_2, the query runs from bouh_world_3
        $repositories = [
            CityRepository::class => CityRepository::class,
            \tests\fixtures\model\CitySecondRepository::class => \tests\fixtures\model\CitySecondMetadataRepository::class,
        ];

        foreach ([$repositories, array_reverse($repositories, true)] as $registered) {
            $services = new TingServices();
            $services->metadataRepository()->batchLoadMetadataFromCache($registered);

            $hydrate = function (?string $objectDatabase) use ($services): array {
                $mockMysqliResult = new MysqliResult([[1, 'Paris']]);
                $mockMysqliResult->setFields([
                    $this->field('cit_id', 'cit_id', 'cit', 'T_CITY_CIT', MYSQLI_TYPE_LONG),
                    $this->field('cit_name', 'cit_name', 'cit', 'T_CITY_CIT'),
                ]);

                $result = new Result();
                $result->setResult($mockMysqliResult);
                $result->setConnectionName('main');
                $result->setDatabase('bouh_world_3');

                $hydrator = new Hydrator();
                $hydrator->setMetadataRepository($services->metadataRepository());
                $hydrator->setUnitOfWork($services->unitOfWork());
                if ($objectDatabase !== null) {
                    $hydrator->objectDatabaseIs('cit', $objectDatabase);
                }

                return $hydrator->setResult($result)->getIterator()->current();
            };

            $this->assertThrows(HydratorException::class, fn () => $hydrate(null));
            $this->assertInstanceOf(\tests\fixtures\model\CitySecond::class, $hydrate('bouh_world_2')['cit']);
            $this->assertInstanceOf(City::class, $hydrate('bouh_world')['cit']);
        }
    }

    public function testHydrateReturnSameResultWhenChangingPrimaryKeyOrder()
    {
        $services = new TingServices();

        $metaDataRepo = $services->metadataRepository();

        $cityMetadata =  new Metadata(
            $this->createStub(SerializerFactoryInterface::class)
        );

        $cityMetadata->setEntity(City::class);
        $cityMetadata->setConnectionName('main');
        $cityMetadata->setDatabase('bouh_world');
        $cityMetadata->setTable('T_CITY_CIT');

        $cityMetadata->addField([
            'primary'       => true,
            'autoincrement' => true,
            'fieldName'     => 'id',
            'columnName'    => 'cit_id',
            'type'          => 'int'
        ]);

        $cityMetadata->addField([
            'fieldName'  => 'name',
            'columnName' => 'cit_name',
            'type'      => 'string'
        ]);

        $cityMetadata->addField([
            'fieldName'  => 'zipcode',
            'columnName' => 'cit_zipcode',
            'type'       => 'string'
        ]);

        $primaryMultiFieldMetadata = new Metadata($this->createStub(SerializerFactoryInterface::class));

        $primaryMultiFieldMetadata->setEntity(PrimaryOnMultiField::class);
        $primaryMultiFieldMetadata->setConnectionName('main');
        $primaryMultiFieldMetadata->setDatabase('bouh_world');
        $primaryMultiFieldMetadata->setTable('T_PRIMARY_MULTI_FIELD');

        $primaryMultiFieldMetadata->addField([
            'primary'       => true,
            'fieldName'     => 'cityId',
            'columnName'    => 'city_id',
            'type'          => 'int'
        ]);

        $primaryMultiFieldMetadata->addField([
            'fieldName'  => 'otherItemId',
            'columnName' => 'other_item_id',
            'type'      => 'string',
            'primary'   => true
        ]);

        $primaryMultiFieldMetadata->addField([
            'fieldName'  => 'value',
            'columnName' => 'value',
            'type'       => 'string'
        ]);

        //*
        $primaryMultiFieldMetadataInverted = new Metadata($this->createStub(SerializerFactoryInterface::class));
        $primaryMultiFieldMetadataInverted->setEntity(PrimaryOnMultiField::class);
        $primaryMultiFieldMetadataInverted->setConnectionName('main');
        $primaryMultiFieldMetadataInverted->setDatabase('bouh_world');
        $primaryMultiFieldMetadataInverted->setTable('T_PRIMARY_MULTI_FIELD');

        $primaryMultiFieldMetadataInverted->addField([
             'fieldName'  => 'otherItemId',
             'columnName' => 'other_item_id',
             'type'      => 'string',
             'primary'   => true
         ]);
        $primaryMultiFieldMetadataInverted->addField([
             'primary'       => true,
             'fieldName'     => 'cityId',
             'columnName'    => 'city_id',
             'type'          => 'int'
         ]);
        $primaryMultiFieldMetadataInverted->addField([
             'fieldName'  => 'value',
             'columnName' => 'value',
             'type'       => 'string'
         ]);
        //*/

        /** @var MetadataRepository $metadataRepo1 */
        $metadataRepo1 = clone $metaDataRepo;
        $metadataRepo2 = clone $metaDataRepo;

        $metadataRepo1->addMetadata(CityRepository::class, $cityMetadata);
        $metadataRepo1->addMetadata(PrimaryOnMultiField::class, $primaryMultiFieldMetadata);

        /** @var MetadataRepository $metadataRepo2 */
        $metadataRepo2->addMetadata(CityRepository::class, $cityMetadata);
        $metadataRepo2->addMetadata(PrimaryOnMultiField::class, $primaryMultiFieldMetadataInverted);

        $mysqliResult =  [
            [1, 'other_item1', 10, 'City1', 1],
            [1, 'other_item2', 20, 'City1', 1],
            [2, 'other_item1', 5, 'City2', 2],
            [2, 'other_item2', 8, 'City2', 2]
        ];

        $mockMysqliResult = new MysqliResult($mysqliResult);
        $mockMysqliResult2 = new MysqliResult($mysqliResult);

        $fetchFields = function () {
            $fields = [];
            $stdClass = new \stdClass();
            $stdClass->name     = 'cityId';
            $stdClass->orgname  = 'city_id';
            $stdClass->table    = 'primaryMultiField';
            $stdClass->orgtable = 'T_PRIMARY_MULTI_FIELD';
            $stdClass->type     = MYSQLI_TYPE_LONG;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'otherItemId';
            $stdClass->orgname  = 'other_item_id';
            $stdClass->table    = 'primaryMultiField';
            $stdClass->orgtable = 'T_PRIMARY_MULTI_FIELD';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'value';
            $stdClass->orgname  = 'value';
            $stdClass->table    = 'primaryMultiField';
            $stdClass->orgtable = 'T_PRIMARY_MULTI_FIELD';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'citname';
            $stdClass->orgname  = 'cit_name';
            $stdClass->table    = 'cit';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'cityId';
            $stdClass->orgname  = 'cit_id';
            $stdClass->table    = 'cit';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type     = MYSQLI_TYPE_LONG;
            $fields[] = $stdClass;

            return $fields;
        };

        $mockMysqliResult->setFields($fetchFields());
        $mockMysqliResult2->setFields($fetchFields());

        //*
        $sqlResult = new Result();
        $sqlResult->setResult($mockMysqliResult);
        $sqlResult->setConnectionName('main');
        $sqlResult->setDatabase('bouh_world');

        $sqlResult2 = new Result();
        $sqlResult2->setResult($mockMysqliResult2);
        $sqlResult2->setConnectionName('main');
        $sqlResult2->setDatabase('bouh_world');

        /** @var UnitOfWork $uow */
        $uow = $services->unitOfWork();

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($metadataRepo1);
        $hydrator->setUnitOfWork($uow);
        $hydrator->setResult($sqlResult);
        $iterator = $hydrator->getIterator();
        $uow->detachAll();
        $result1 = iterator_to_array($iterator);
        $hydrator2 = new Hydrator();
        $hydrator2->setMetadataRepository($metadataRepo2);
        $hydrator2->setUnitOfWork($uow);
        $iterator2 = $hydrator2->setResult($sqlResult2)->getIterator();
        $result2 = iterator_to_array($iterator2);

        $this->assertIsInt(count($result1));
        $this->assertEquals(count($result2), count($result1));

        foreach ($result1 as $i => $row) {
            $this->assertIsInt($row['cit']->getId());
            $this->assertEquals($result2[$i]['cit']->getId(), $row['cit']->getId());
            $this->assertIsString($row['cit']->getName());
            $this->assertEquals($result2[$i]['cit']->getName(), $row['cit']->getName());
            $this->assertIsInt($row['primaryMultiField']->getCityId());
            $this->assertEquals($result2[$i]['primaryMultiField']->getCityId(), $row['primaryMultiField']->getCityId());
            $this->assertIsInt($row['primaryMultiField']->getValue());
            $this->assertEquals($result2[$i]['primaryMultiField']->getValue(), $row['primaryMultiField']->getValue());
            $this->assertIsString($row['primaryMultiField']->getOtherItemId());
            $this->assertEquals(
                $result2[$i]['primaryMultiField']->getOtherItemId(),
                $row['primaryMultiField']->getOtherItemId()
            );
        }
    }

    public function testHydrateWithNullSQLReturnShouldReturnNull()
    {
        $services = new TingServices();
        $services->metadataRepository()
                 ->batchLoadMetadata('tests\fixtures\model', __DIR__ . '/../../fixtures/model/*Repository.php');

        $mockMysqliResult = new MysqliResult([
            ['Sylvain', 'Robez-Masson', null, null]
        ]);
        $mockMysqliResult->setFieldsCallback(function () {
            $fields = [];
            $stdClass = new \stdClass();
            $stdClass->name = 'fname';
            $stdClass->orgname = 'boo_firstname';
            $stdClass->table = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name = 'name';
            $stdClass->orgname = 'boo_name';
            $stdClass->table = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name = 'cityId';
            $stdClass->orgname = 'cit_id';
            $stdClass->table = 'cit';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type = MYSQLI_TYPE_LONG;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name = 'citname';
            $stdClass->orgname = 'cit_name';
            $stdClass->table = 'cit';
            $stdClass->orgtable = 'T_CITY_CIT';
            $stdClass->type = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        // BouhReadOnlyRepository maps T_BOUH_BOO of bouh_world too
        $hydrator->preferRepository(BouhRepository::class);
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->objectDatabaseIs('cit', 'bouh_world_2');
        $data = $hydrator->setResult($result)->getIterator()->current();
        $this->assertNull($data['cit']);
    }

    public function testHydrateWithIdentityMapFalseShouldReturnNewEntity()
    {
        $services = new TingServices();
        $services->metadataRepository()
            ->batchLoadMetadata('tests\fixtures\model', __DIR__ . '/../../fixtures/model/*Repository.php');

        $mockMysqliResult = new MysqliResult([
            [23, 'Michael', 'Jordan'],
            [23, 'Michael', 'Jordan']
        ]);

        $mockMysqliResult->setFieldsCallback(function () {
            $fields = [];

            $stdClass = new \stdClass();
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'boo_id';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_LONG;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'name';
            $stdClass->orgname  = 'boo_name';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'firstname';
            $stdClass->orgname  = 'boo_firstname';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        // BouhReadOnlyRepository maps T_BOUH_BOO of bouh_world too
        $hydrator->preferRepository(BouhRepository::class);
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $iterator = $hydrator->setResult($result)->getIterator();
        $currentObject = $iterator->current()['bouh'];
        $iterator->next();
        $nextObject = $iterator->current()['bouh'];
        $this->assertIsString(spl_object_hash($currentObject));
        $this->assertNotEquals(spl_object_hash($nextObject), spl_object_hash($currentObject));
    }

    public function testHydrateWithIdentityMapTrueShouldReturnSameEntity()
    {
        $services = new TingServices();
        $services->metadataRepository()
            ->batchLoadMetadata('tests\fixtures\model', __DIR__ . '/../../fixtures/model/*Repository.php');

        $mockMysqliResult = new MysqliResult([
            [23, 'LeBron', 'James', 'Cleveland'],
            [30, 'Stephen', 'Curry', 'San Francisco'],
            [23, 'LeBron', 'James', 'Los Angeles']
        ]);

        $mockMysqliResult->setFieldsCallback(function () {
            $fields = [];

            $stdClass = new \stdClass();
            $stdClass->name     = 'id';
            $stdClass->orgname  = 'boo_id';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_LONG;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'name';
            $stdClass->orgname  = 'boo_name';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'firstname';
            $stdClass->orgname  = 'boo_firstname';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            $stdClass = new \stdClass();
            $stdClass->name     = 'cityName';
            $stdClass->orgname  = '';
            $stdClass->table    = '';
            $stdClass->orgtable = '';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;

            return $fields;
        });

        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        // BouhReadOnlyRepository maps T_BOUH_BOO of bouh_world too
        $hydrator->preferRepository(BouhRepository::class);
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->identityMap(true);
        $iterator = $hydrator->setResult($result)->getIterator();
        $currentObject = $iterator->current()['bouh'];
        $iterator->next();
        $iterator->next();
        $nextObject = $iterator->current()['bouh'];
        $this->assertIsString(spl_object_hash($currentObject));
        $this->assertEquals(spl_object_hash($nextObject), spl_object_hash($currentObject));
    }

    public function testIdentityMapShouldIdentifyEntitiesByTheirWholeCompositeKey()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('main');
        $metadata->setDatabase('bouh_world');
        $metadata->setEntity(\tests\fixtures\model\Bouh::class);
        $metadata->setTable('T_BOUH_BOO');
        $metadata->addField(['primary' => true, 'fieldName' => 'name', 'columnName' => 'boo_name', 'type' => 'string']);
        $metadata->addField(['primary' => true, 'fieldName' => 'firstname', 'columnName' => 'boo_firstname', 'type' => 'string']);
        $metadata->addField(['fieldName' => 'price', 'columnName' => 'boo_price', 'type' => 'string']);
        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $mockMysqliResult = new MysqliResult([
            ['Jean', 'Pierre-Paul', '10'],
            ['Jean', 'Pierre', '20'],
            ['Jean-Pierre', 'Paul', '30'], // with "-" between the values, its key is the same as the first one
            ['Jean', 'Pierre-Paul', '10'], // the first one again
        ]);
        $mockMysqliResult->setFields([
            $this->field('boo_name', 'boo_name', 'b', 'T_BOUH_BOO'),
            $this->field('boo_firstname', 'boo_firstname', 'b', 'T_BOUH_BOO'),
            $this->field('boo_price', 'boo_price', 'b', 'T_BOUH_BOO'),
        ]);
        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->identityMap(true);
        $bouhs = array_column(iterator_to_array($hydrator->setResult($result)->getIterator()), 'b');

        $this->assertSame(
            [['Jean', 'Pierre-Paul', '10'], ['Jean', 'Pierre', '20'], ['Jean-Pierre', 'Paul', '30'], ['Jean', 'Pierre-Paul', '10']],
            array_map(fn ($bouh) => [$bouh->getName(), $bouh->getFirstname(), $bouh->getPrice()], $bouhs)
        );
        $this->assertSame($bouhs[0], $bouhs[3]);
        $this->assertCount(3, array_unique(array_map('spl_object_id', $bouhs)));
    }

    public function testHydrationShouldUnserializeEachFieldOnceAndSerializeNothing()
    {
        CountingJson::resetCounters();
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('main');
        $metadata->setDatabase('bouh_world');
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setTable('T_BOUH_BOO');
        $metadata->addField(['primary' => true, 'fieldName' => 'id', 'columnName' => 'boo_id', 'type' => 'int']);
        $metadata->addField(['fieldName' => 'name', 'columnName' => 'boo_name', 'type' => 'string']);
        // A serializer of its own: mutable, its raw database value is kept at hydration
        $metadata->addField([
            'fieldName'          => 'roles',
            'columnName'         => 'boo_roles',
            'type'               => 'json',
            'serializer'         => CountingJson::class,
            'serializer_options' => ['unserialize' => ['assoc' => true]],
        ]);
        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $mockMysqliResult = new MysqliResult([[1, 'Sylvain', '["A"]'], [2, 'Xavier', '["B"]'], [3, 'Bouh', '[]']]);
        $mockMysqliResult->setFieldsCallback(fn (): array => [
            $this->field('id', 'boo_id', 'bouh', 'T_BOUH_BOO', MYSQLI_TYPE_LONG),
            $this->field('name', 'boo_name', 'bouh', 'T_BOUH_BOO'),
            $this->field('roles', 'boo_roles', 'bouh', 'T_BOUH_BOO'),
        ]);
        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $unitOfWork = $services->unitOfWork();
        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($unitOfWork);
        $bouhs = array_column(iterator_to_array($hydrator->setResult($result)->getIterator()), 'bouh');

        $this->assertSame([['A'], ['B'], []], array_map(fn ($bouh) => $bouh->getRoles(), $bouhs));
        $this->assertTrue($unitOfWork->isManaged($bouhs[0]));
        $this->assertSame(3, CountingJson::$unserialized);
        $this->assertSame(0, CountingJson::$serialized);
    }

    public function testTheEntitiesShouldBeManagedWithTheMetadataHydratingThem()
    {
        $services = new TingServices();
        $metadata = BouhRepository::initMetadata($services->serializerFactory());
        $services->metadataRepository()->addMetadata(BouhRepository::class, $metadata);
        // Registered last for Bouh, but not the metadata of the table read
        $other = BouhRepository::initMetadata($services->serializerFactory());
        $other->setTable('T_BOUH_ARCHIVE');
        $services->metadataRepository()->addMetadata('BouhArchiveRepository', $other);

        $mockMysqliResult = new MysqliResult([[1, 'Sylvain']]);
        $mockMysqliResult->setFieldsCallback(fn (): array => [
            $this->field('id', 'boo_id', 'bouh', 'T_BOUH_BOO', MYSQLI_TYPE_LONG),
            $this->field('name', 'boo_name', 'bouh', 'T_BOUH_BOO'),
        ]);
        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->expects($this->once())->method('manage')->with($this->isInstanceOf(Bouh::class), $this->identicalTo($metadata));
        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($unitOfWork);
        iterator_to_array($hydrator->setResult($result)->getIterator());
    }

    /**
     * @param list<array{0: string, 1: string}> $columns [column name, table] of each column, aliased $alias
     * @param list<list<string>> $rows
     */
    private function resultOf(array $columns, array $rows, string $alias = 'x'): Result
    {
        $mockMysqliResult = new MysqliResult($rows);
        $mockMysqliResult->setFields(array_map(
            fn (array $column): \stdClass => $this->field($column[0], $column[0], $alias, $column[1]),
            $columns
        ));
        $result = new Result();
        $result->setResult($mockMysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        return $result;
    }

    public function testAHydratorGivenANewResultShouldStartAnEmptyIdentityMap()
    {
        $services = new TingServices();
        $services->metadataRepository()->addMetadata(CityRepository::class, CityRepository::initMetadata($services->serializerFactory()));
        $services->metadataRepository()->addMetadata(BouhRepository::class, BouhRepository::initMetadata($services->serializerFactory()));
        $unitOfWork = $services->unitOfWork();
        // Kept as a service, say
        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($unitOfWork);
        $hydrator->identityMap(true);

        $city = iterator_to_array($hydrator->setResult(
            $this->resultOf([['cit_id', 'T_CITY_CIT'], ['cit_name', 'T_CITY_CIT']], [['1', 'Paris']])
        )->getIterator())[0]['x'];
        // The same alias and the same primary key, another table
        $bouh = iterator_to_array($hydrator->setResult(
            $this->resultOf([['boo_id', 'T_BOUH_BOO'], ['boo_name', 'T_BOUH_BOO']], [['1', 'Sylvain']])
        )->getIterator())[0]['x'];
        // The same row read again, once the unit of work is reset (a worker between two requests)
        $unitOfWork->reset();
        $again = iterator_to_array($hydrator->setResult(
            $this->resultOf([['cit_id', 'T_CITY_CIT'], ['cit_name', 'T_CITY_CIT']], [['1', 'Paris 1er']])
        )->getIterator())[0]['x'];

        $this->assertInstanceOf(City::class, $city);
        $this->assertInstanceOf(Bouh::class, $bouh);
        $this->assertSame('Sylvain', $bouh->getName());
        $this->assertInstanceOf(City::class, $again);
        $this->assertNotSame($city, $again);
        $this->assertSame('Paris 1er', $again->getName());
        $this->assertTrue($unitOfWork->isManaged($again));
    }

    public function testAResultReadDuringTheIterationOfAnotherShouldNotChangeItsIdentityMap()
    {
        $services = new TingServices();
        $services->metadataRepository()->addMetadata(CityRepository::class, CityRepository::initMetadata($services->serializerFactory()));
        $services->metadataRepository()->addMetadata(BouhRepository::class, BouhRepository::initMetadata($services->serializerFactory()));
        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->identityMap(true);

        $cities = [];
        $outer = $this->resultOf([['cit_id', 'T_CITY_CIT'], ['cit_name', 'T_CITY_CIT']], [['1', 'Paris'], ['1', 'Paris'], ['2', 'Lyon']]);
        foreach ($hydrator->setResult($outer) as $i => $row) {
            $cities[] = $row['x'];
            if ($i === 0) {
                // A nested read with the same hydrator (kept as a service), the same alias on another table
                $inner = $this->resultOf([['boo_id', 'T_BOUH_BOO'], ['boo_name', 'T_BOUH_BOO']], [['1', 'Sylvain']]);
                foreach ($hydrator->setResult($inner) as $innerRow) {
                    $this->assertInstanceOf(Bouh::class, $innerRow['x']);
                }
            }
        }

        $this->assertCount(3, $cities);
        $this->assertContainsOnlyInstancesOf(City::class, $cities);
        $this->assertSame($cities[0], $cities[1]);
        $this->assertNotSame($cities[0], $cities[2]);
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public static function identityMapProvider(): array
    {
        return ['with identity map' => [true], 'without identity map' => [false]];
    }

    /**
     * A hydrator kept as a service, the metadata of City hydrating CityLookingUp, and of Bouh
     */
    private function hydratorLookingUp(bool $identityMap): Hydrator
    {
        $services = new TingServices();
        $cityMetadata = CityRepository::initMetadata($services->serializerFactory());
        $cityMetadata->setEntity(CityLookingUp::class);
        $services->metadataRepository()->addMetadata(CityRepository::class, $cityMetadata);
        $services->metadataRepository()->addMetadata(BouhRepository::class, BouhRepository::initMetadata($services->serializerFactory()));
        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $hydrator->identityMap($identityMap);

        return $hydrator;
    }

    #[DataProvider('identityMapProvider')]
    public function testAReadNestedInASetterShouldNotChangeTheRowBeingHydrated(bool $identityMap)
    {
        $hydrator = $this->hydratorLookingUp($identityMap);
        $innerRows = [];
        // A lookup in a setter with the same hydrator, while the hydrator sets the fields of the row
        CityLookingUp::$lookup = function () use ($hydrator, &$innerRows): void {
            $inner = $this->resultOf([['boo_id', 'T_BOUH_BOO'], ['boo_name', 'T_BOUH_BOO']], [['9', 'Sylvain']], 'b');
            foreach ($hydrator->setResult($inner) as $row) {
                $innerRows[] = $row;
            }
        };

        try {
            $outer = $this->resultOf(
                [['cit_id', 'T_CITY_CIT'], ['cit_name', 'T_CITY_CIT'], ['cit_zipcode', 'T_CITY_CIT']],
                [['1', 'Paris', '75000'], ['2', 'Lyon', '69000']]
            );
            $rows = iterator_to_array($hydrator->setResult($outer)->getIterator());
        } finally {
            CityLookingUp::$lookup = null;
        }

        $this->assertCount(2, $rows);
        $this->assertSame(['x'], array_keys($rows[0]));
        $this->assertSame([1, 'Paris', '75000'], [$rows[0]['x']->getId(), $rows[0]['x']->getName(), $rows[0]['x']->getZipcode()]);
        $this->assertSame([2, 'Lyon', '69000'], [$rows[1]['x']->getId(), $rows[1]['x']->getName(), $rows[1]['x']->getZipcode()]);
        $this->assertCount(1, $innerRows);
        $this->assertSame(['b'], array_keys($innerRows[0]));
        $this->assertInstanceOf(Bouh::class, $innerRows[0]['b']);
        $this->assertSame('Sylvain', $innerRows[0]['b']->getName());
    }

    public function testAReadNestedInASetterAndStoppedShouldNotChangeTheRowBeingHydrated()
    {
        $hydrator = $this->hydratorLookingUp(true);
        $first = null;
        // Only the first row of the lookup is read: its iteration is stopped
        CityLookingUp::$lookup = function () use ($hydrator, &$first): void {
            $inner = $this->resultOf([['boo_id', 'T_BOUH_BOO'], ['boo_name', 'T_BOUH_BOO']], [['9', 'Sylvain'], ['10', 'Xavier']], 'b');
            $first = $hydrator->setResult($inner)->getIterator()->current();
        };

        try {
            $outer = $this->resultOf(
                [['cit_id', 'T_CITY_CIT'], ['cit_name', 'T_CITY_CIT'], ['cit_zipcode', 'T_CITY_CIT']],
                [['1', 'Paris', '75000'], ['1', 'Paris', '75000']]
            );
            $rows = iterator_to_array($hydrator->setResult($outer)->getIterator());
        } finally {
            CityLookingUp::$lookup = null;
        }

        $this->assertSame($rows[0]['x'], $rows[1]['x']);
        $this->assertSame(['Paris', '75000'], [$rows[0]['x']->getName(), $rows[0]['x']->getZipcode()]);
        $this->assertSame('Sylvain', $first['b']->getName());
    }

    public function testAReadNestedInASetterShouldKeepItsOwnIdentityMap()
    {
        $hydrator = $this->hydratorLookingUp(true);
        $innerRows = [];
        // The same alias, the same table and the same primary key as the row being hydrated
        CityLookingUp::$lookup = function () use ($hydrator, &$innerRows): void {
            $inner = $this->resultOf([['cit_id', 'T_CITY_CIT'], ['cit_name', 'T_CITY_CIT']], [['1', 'Inner']]);
            foreach ($hydrator->setResult($inner) as $row) {
                $innerRows[] = $row;
            }
        };

        try {
            $outer = $this->resultOf(
                [['cit_id', 'T_CITY_CIT'], ['cit_name', 'T_CITY_CIT'], ['cit_zipcode', 'T_CITY_CIT']],
                [['1', 'Paris', '75000'], ['1', 'Paris', '75000']]
            );
            $rows = iterator_to_array($hydrator->setResult($outer)->getIterator());
        } finally {
            CityLookingUp::$lookup = null;
        }

        $this->assertSame($rows[0]['x'], $rows[1]['x']);
        $this->assertSame(['Paris', '75000'], [$rows[0]['x']->getName(), $rows[0]['x']->getZipcode()]);
        $this->assertCount(1, $innerRows);
        $this->assertNotSame($rows[0]['x'], $innerRows[0]['x']);
        $this->assertSame('Inner', $innerRows[0]['x']->getName());
    }

    public function testAnEntityOfTheIdentityMapShouldBeManagedAgainOnceDetached()
    {
        $services = new TingServices();
        $services->metadataRepository()->addMetadata(CityRepository::class, CityRepository::initMetadata($services->serializerFactory()));
        $unitOfWork = $services->unitOfWork();
        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($unitOfWork);
        $hydrator->identityMap(true);

        $rows = $hydrator->setResult(
            $this->resultOf([['cit_id', 'T_CITY_CIT'], ['cit_name', 'T_CITY_CIT']], [['1', 'Paris'], ['1', 'Paris']])
        )->getIterator();
        $first = $rows->current()['x'];
        $unitOfWork->detach($first);
        $rows->next();
        $second = $rows->current()['x'];

        $this->assertSame($first, $second);
        $this->assertTrue($unitOfWork->isManaged($second));
    }

    public function testAnEntityReadPartiallyShouldBeManagedAgainWithoutItsFieldsNotRead()
    {
        $services = new TingServices();
        $services->metadataRepository()->addMetadata(CityRepository::class, CityRepository::initMetadata($services->serializerFactory()));
        $unitOfWork = $services->unitOfWork();
        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($unitOfWork);
        $hydrator->identityMap(true);
        $notRead = fn (object $entity): array => (fn (): array => $this->propertiesNotRead[$entity] ?? [])
            ->call($unitOfWork);

        $rows = $hydrator->setResult(
            $this->resultOf([['cit_id', 'T_CITY_CIT'], ['cit_name', 'T_CITY_CIT']], [['1', 'Paris'], ['1', 'Paris']])
        )->getIterator();
        $first = $rows->current()['x'];
        $fieldsNotRead = $notRead($first);
        $unitOfWork->detach($first);
        $rows->next();
        $second = $rows->current()['x'];

        $this->assertSame($first, $second);
        $this->assertArrayHasKey('zipcode', $fieldsNotRead);
        $this->assertSame($fieldsNotRead, $notRead($second));
    }

    private function field(string $name, string $orgName, string $table, string $orgTable, int $type = MYSQLI_TYPE_VAR_STRING): \stdClass
    {
        $field = new \stdClass();
        $field->name     = $name;
        $field->orgname  = $orgName;
        $field->table    = $table;
        $field->orgtable = $orgTable;
        $field->type     = $type;

        return $field;
    }

    /**
     * A Bouh metadata on $table with the given columns (column name => field name), and the row of $orgTable
     *
     * @param array<string, string> $fields column name => field name
     * @param array<string, string> $row column name read => value
     * @return array<int|string, mixed> the hydrated row
     */
    private function hydrateRowOf(string $table, array $fields, string $orgTable, array $row): array
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('main');
        $metadata->setDatabase('bouh_world');
        $metadata->setEntity(Bouh::class);
        $metadata->setTable($table);
        foreach ($fields as $columnName => $fieldName) {
            $metadata->addField(['fieldName' => $fieldName, 'columnName' => $columnName, 'type' => 'string']);
        }
        $services->metadataRepository()->addMetadata(BouhRepository::class, $metadata);

        $columns = [];
        foreach ($row as $orgName => $value) {
            $columns[] = [
                'name' => $orgName,
                'orgName' => $orgName,
                'table' => 'b',
                'orgTable' => $orgTable,
                'schema' => '',
                'value' => $value,
            ];
        }
        $result = new CacheResult();
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');
        $result->setResult(new \ArrayIterator([$columns]));

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());

        return $hydrator->setResult($result)->getIterator()->current();
    }

    public function testHydrateShouldFindTheTableOfAnotherCase()
    {
        // A table created quoted ("Users") with metadata declared in lowercase, as 3.x matched it
        $data = $this->hydrateRowOf('users', ['boo_name' => 'name'], 'Users', ['boo_name' => 'Robez']);

        $this->assertInstanceOf(Bouh::class, $data['b']);
        $this->assertSame('Robez', $data['b']->getName());
    }

    public function testHydrateShouldFindTheColumnOfAnotherCase()
    {
        // Metadata column ID, read by an unquoted SELECT u.ID that PostgreSQL folds to id
        $data = $this->hydrateRowOf(
            'users',
            ['BOO_NAME' => 'name', 'boo_firstname' => 'firstname'],
            'users',
            ['boo_name' => 'Robez', 'boo_firstname' => 'Sylvain']
        );

        $this->assertSame('Robez', $data['b']->getName());
        $this->assertSame('Sylvain', $data['b']->getFirstname());
        $this->assertArrayNotHasKey(0, $data);
    }

    public function testHydrateShouldPreferTheColumnOfTheSameCase()
    {
        $data = $this->hydrateRowOf(
            'users',
            ['boo_name' => 'name', 'BOO_NAME' => 'firstname'],
            'users',
            ['BOO_NAME' => 'Sylvain']
        );

        $this->assertSame('Sylvain', $data['b']->getFirstname());
        $this->assertNull($data['b']->getName());
    }

    public function testHydrateShouldNotTakeAColumnOfAnotherCaseForAColumnReadUnderItsExactName()
    {
        // PostgreSQL table t(id, "Name", name): only "Name" is mapped, name is another column
        foreach ([['Name' => 'Display', 'name' => 'legacy'], ['name' => 'legacy', 'Name' => 'Display']] as $row) {
            $data = $this->hydrateRowOf('users', ['Name' => 'name'], 'users', $row);

            $this->assertSame('Display', $data['b']->getName());
            $this->assertSame('legacy', $data[0]->name);
        }
    }

    public function testHydrateShouldNotMatchAColumnOfAnotherCaseWhenEachCandidateIsReadUnderItsExactName()
    {
        // PostgreSQL table t("Name", name, "NAME"): "Name" and name mapped, "NAME" is another column
        foreach ([['Name' => 'A', 'name' => 'B', 'NAME' => 'C'], ['NAME' => 'C', 'name' => 'B', 'Name' => 'A']] as $row) {
            $data = $this->hydrateRowOf('users', ['Name' => 'name', 'name' => 'firstname'], 'users', $row);

            $this->assertSame('A', $data['b']->getName());
            $this->assertSame('B', $data['b']->getFirstname());
            $this->assertSame('C', $data[0]->NAME);
        }
    }

    public function testHydrateShouldMatchTheOnlyCandidateNotReadUnderItsExactName()
    {
        // "Name" and name mapped, the row holds "Name" and "NAME": NAME can only be name
        $data = $this->hydrateRowOf(
            'users',
            ['Name' => 'name', 'name' => 'firstname'],
            'users',
            ['NAME' => 'C', 'Name' => 'A']
        );

        $this->assertSame('A', $data['b']->getName());
        $this->assertSame('C', $data['b']->getFirstname());
        $this->assertArrayNotHasKey(0, $data);
    }

    public function testHydrateShouldMatchTheColumnsOfEachResultOnItsOwnColumns()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('main');
        $metadata->setDatabase('bouh_world');
        $metadata->setEntity(Bouh::class);
        $metadata->setTable('users');
        $metadata->addField(['fieldName' => 'name', 'columnName' => 'Name', 'type' => 'string']);
        $services->metadataRepository()->addMetadata(BouhRepository::class, $metadata);
        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($services->metadataRepository());
        $hydrator->setUnitOfWork($services->unitOfWork());
        $resultOf = function (array $row): CacheResult {
            $columns = [];
            foreach ($row as $orgName => $value) {
                $columns[] = [
                    'name' => $orgName,
                    'orgName' => $orgName,
                    'table' => 'b',
                    'orgTable' => 'users',
                    'schema' => '',
                    'value' => $value,
                ];
            }
            $result = new CacheResult();
            $result->setConnectionName('main');
            $result->setDatabase('bouh_world');
            $result->setResult(new \ArrayIterator([$columns, $columns]));

            return $result;
        };

        // The same hydrator: name is another column in the first result, the column "Name" in the second one
        $first = $hydrator->setResult($resultOf(['Name' => 'Display', 'name' => 'legacy']))->getIterator();
        $this->assertSame('Display', $first->current()['b']->getName());
        foreach ($hydrator->setResult($resultOf(['name' => 'folded'])) as $row) {
            $this->assertSame('folded', $row['b']->getName());
        }
        $first->next();
        $this->assertSame('Display', $first->current()['b']->getName());
    }

    public function testHydrateColumnsShouldMatchTheColumnsOfEachRowOnItsOwnColumns()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('main');
        $metadata->setDatabase('bouh_world');
        $metadata->setEntity(Bouh::class);
        $metadata->setTable('users');
        $metadata->addField(['fieldName' => 'name', 'columnName' => 'name', 'type' => 'string']);
        $services->metadataRepository()->addMetadata(BouhRepository::class, $metadata);
        $columnsOf = function (array $row): array {
            $columns = [];
            foreach ($row as $orgName => $value) {
                $columns[] = [
                    'name' => $orgName,
                    'orgName' => $orgName,
                    'table' => 'b',
                    'orgTable' => 'users',
                    'schema' => '',
                    'value' => $value,
                ];
            }

            return $columns;
        };
        $withBoth = $columnsOf(['name' => 'exact', 'Name' => 'other']);
        $withOther = $columnsOf(['Name' => 'folded']);

        // A subclass hydrating rows of its own: "Name" is another column only in the rows holding name
        foreach ([[$withBoth, $withOther], [$withOther, $withBoth]] as $rows) {
            $hydrator = new class () extends Hydrator {
                /**
                 * @param list<array<string, mixed>> $columns
                 * @return array<int|string, object|null>
                 */
                public function row(array $columns): array
                {
                    return $this->hydrateColumns('main', 'bouh_world', $columns);
                }
            };
            $hydrator->setMetadataRepository($services->metadataRepository());
            $hydrator->setUnitOfWork($services->unitOfWork());

            foreach ($rows as $columns) {
                $this->assertSame(
                    $columns === $withBoth ? 'exact' : 'folded',
                    $hydrator->row($columns)['b']->getName()
                );
            }
        }
    }

    public function testHydrateShouldThrowWhenSeveralColumnsDifferOnlyByCase()
    {
        $this->assertThrows(
            HydratorException::class,
            fn () => $this->hydrateRowOf(
                'users',
                ['boo_name' => 'name', 'BOO_NAME' => 'firstname'],
                'users',
                ['Boo_Name' => 'Sylvain']
            ),
            'The column Boo_Name of users matches several columns of its metadata, whose case differs: boo_name, BOO_NAME'
        );
    }
}
