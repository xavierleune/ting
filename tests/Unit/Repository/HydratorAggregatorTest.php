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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Uid\Uuid;
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

    /**
     * @return array<string, array{callable, list<string>}>
     */
    public static function nonScalarIdentifierProvider(): array
    {
        // Loaded here, outside the errors collected by the test: the lowest symfony/uid versions declare implicitly
        // nullable parameters, deprecated since PHP 8.4
        $uuids = [
            4 => Uuid::fromString('f47ac10b-58cc-4372-a567-0e02b2c3d479'),
            3 => Uuid::fromString('9b2c1a3e-0d4f-4e6a-8b7c-1d2e3f4a5b6c'),
        ];

        return [
            // A new instance for each row: the groups are compared by value, not by identity
            'uuid object' => [
                fn ($result) => Uuid::fromString((string) $uuids[$result['bouh']->getId()]),
                ['Leune', 'Robez-Masson'],
            ],
            'composite array' => [
                fn ($result) => [$result['bouh']->getName(), $result['bouh']->getFirstname()],
                ['Leune', 'Robez-Masson'],
            ],
            'float' => [fn ($result) => $result['bouh']->getId() + 0.5, ['Leune', 'Robez-Masson']],
        ];
    }

    #[DataProvider('nonScalarIdentifierProvider')]
    public function testHydrateShouldGroupByNonScalarIdentifiers(callable $callableForId, array $expectedNames)
    {
        $hydrator = $this->buildBouhCityHydrator([
            [4, 'Xavier', 'Leune', 'Boulogne-Billancourt'],
            [3, 'Sylvain', 'Robez-Masson', 'Palaiseau'],
            [4, 'Xavier', 'Leune', 'Palaiseau'],
            [3, 'Sylvain', 'Robez-Masson', 'Montbéliard'],
        ]);
        $hydrator->callableIdIs($callableForId);

        $types = $this->collectErrorTypes(function () use ($hydrator, &$groups): void {
            $groups = iterator_to_array($hydrator->getIterator());
        }, $thrown);

        $this->assertNull($thrown);
        $this->assertSame([], $types);
        $this->assertSame($expectedNames, array_map(fn ($group) => $group['bouh']->getName(), array_values($groups)));
        $this->assertSame(['Boulogne-Billancourt'], array_map(fn ($city) => $city->getName(), $groups[0]['aggregate']));
        $this->assertSame(
            ['Palaiseau', 'Montbéliard'],
            array_map(fn ($city) => $city->getName(), $groups[1]['aggregate'])
        );
    }

    public function testHydrateShouldThrowWhenTheIdentifierCannotBeSerialized()
    {
        $hydrator = $this->buildBouhCityHydrator([[4, 'Xavier', 'Leune', 'Boulogne-Billancourt']]);
        $hydrator->callableIdIs(fn ($result) => fn () => $result);

        $exception = $this->assertThrows(
            HydratorException::class,
            fn () => iterator_to_array($hydrator->getIterator())
        );
        $this->assertStringContainsString('Closure', $exception->getMessage());
    }

    /**
     * @param list<array{int, string, string, string}> $rows id, firstname, name, city name
     */
    private function buildBouhCityHydrator(array $rows): HydratorAggregator
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setTable('T_BOUH_BOO');
        $metadata->addField(['fieldName' => 'id', 'columnName' => 'boo_id', 'type' => 'int']);
        $metadata->addField(['fieldName' => 'name', 'columnName' => 'boo_name', 'type' => 'string']);
        $metadata->addField(['fieldName' => 'firstname', 'columnName' => 'boo_firstname', 'type' => 'string']);
        $services->metadataRepository()->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setEntity('tests\fixtures\model\City');
        $metadata->setTable('T_CITY_CIT');
        $metadata->addField(['fieldName' => 'name', 'columnName' => 'cit_name', 'type' => 'string']);
        $services->metadataRepository()->addMetadata('tests\fixtures\model\CityRepository', $metadata);

        $mockMysqliResult = new MysqliResult($rows);
        $mockMysqliResult->setFieldsCallback(function () {
            $fields = [];
            foreach ([['id', 'boo_id', 'bouh', 'T_BOUH_BOO'], ['fname', 'boo_firstname', 'bouh', 'T_BOUH_BOO'],
                ['name', 'boo_name', 'bouh', 'T_BOUH_BOO'], ['name', 'cit_name', 'c', 'T_CITY_CIT']] as $column) {
                $field = new \stdClass();
                [$field->name, $field->orgname, $field->table, $field->orgtable] = $column;
                $field->type = MYSQLI_TYPE_VAR_STRING;
                $fields[] = $field;
            }
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
        $hydrator->setResult($result);

        return $hydrator;
    }
}
