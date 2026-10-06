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
use tests\fixtures\model\BouhRepository;
use tests\fixtures\model\City;
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

    public function testHydrationShouldUnserializeEachFieldOnceAndSerializeEachMutableFieldOnce()
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
        // A serializer of its own: mutable, its database value is kept at hydration
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
        $this->assertSame(3, CountingJson::$serialized);
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
}
