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

use CCMBenchmark\Ting\Connection;
use CCMBenchmark\Ting\ConnectionPool;
use CCMBenchmark\Ting\Driver\Mysqli\Driver as MysqliDriver;
use CCMBenchmark\Ting\Driver\Pgsql\Serializer\Boolean as PgsqlBoolean;
use CCMBenchmark\Ting\Driver\StatementInterface;
use CCMBenchmark\Ting\Exception;
use CCMBenchmark\Ting\Exceptions\ConfigException;
use CCMBenchmark\Ting\Exceptions\ValueException;
use CCMBenchmark\Ting\Query\PreparedQuery;
use CCMBenchmark\Ting\Query\Query;
use CCMBenchmark\Ting\Query\QueryInterface;
use CCMBenchmark\Ting\Query\QueryFactory;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Serializer\BackedEnum;
use CCMBenchmark\Ting\Serializer\DateTime;
use CCMBenchmark\Ting\Serializer\Json;
use CCMBenchmark\Ting\Tests\Support\TingServices;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use CCMBenchmark\Ting\Util\PropertyAccessor;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use tests\fixtures\ColorsEnum;
use tests\fixtures\FakeDriver\Driver as FakeDriver;
use tests\fixtures\PriorityEnum;
use tests\fixtures\Serializer\CountingJson;
use tests\fixtures\model\Bouh;
use tests\fixtures\model\BouhCustomGetter;
use tests\fixtures\model\CustomGetterEntity;
use tests\fixtures\model\DatedEntity;
use tests\fixtures\model\HookedPropertiesEntity;
use tests\fixtures\model\PublicPropertiesEntity;

class MetadataTest extends TestCase
{
    public function testGetConnection()
    {
        $mockConnectionPool = $this->createStub(ConnectionPool::class);

        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setDatabase('myDatabase');
        $metadata->setConnectionName('myConnection');
        $this->assertInstanceOf(Connection::class, $metadata->getConnection($mockConnectionPool));
    }

    public function testGetConnectionName()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setDatabase('myDatabase');
        $metadata->setConnectionName('myConnection');
        $this->assertSame('myConnection', $metadata->getConnectionName());
    }

    public function testGetSchema()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setDatabase('myDatabase');
        $metadata->setSchema('schemaName');
        $this->assertSame('schemaName', $metadata->getSchema());
    }

    public function testSetRepositoryShouldRaiseExceptionWhenStartWithSlash()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $this->assertThrows(
            \Throwable::class,
            function () use ($metadata): void {
                $metadata->setRepository('\my\namespace\Bouh');
            },
            'Class must not start with a \\'
        );
    }

    public function testGetRepository()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setRepository('myRepository');
        $this->assertSame('myRepository', $metadata->getRepository());
    }

    public function testSetDatabaseShouldReturnThis()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $this->assertSame($metadata, $metadata->setDatabase('myDatabase'));
    }

    public function testSetConnectionShouldReturnThis()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $this->assertSame($metadata, $metadata->setConnectionName('main'));
    }

    public function testSetEntityShouldRaiseExceptionWhenStartWithSlash()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $this->assertThrows(
            \Throwable::class,
            function () use ($metadata): void {
                $metadata->setEntity('\my\namespace\Bouh');
            },
            'Class must not start with a \\'
        );
    }

    public function testAddFieldShouldReturnThis()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $this->assertSame(
            $metadata,
            $metadata->addField(['columnName' => 'BO_BOUH', 'fieldName' => 'bouh', 'type' => 'string'])
        );
    }

    public function testGetFields()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->addField(
            ['columnName' => 'user_firstname', 'fieldName' => 'firstname', 'type' => 'string']
        );
        $metadata->addField(
            ['columnName' => 'user_lastname', 'fieldName' => 'lastname', 'type' => 'string']
        );
        $this->assertSame(
            [
                [
                    'columnName' => 'user_firstname',
                    'fieldName'  => 'firstname',
                    'type'       => 'string'
                ],
                [
                    'columnName' => 'user_lastname',
                    'fieldName'  => 'lastname',
                    'type'       => 'string'
                ]
            ],
            $metadata->getFields()
        );
    }

    public function testAddFieldWithInvalidParametersShouldThrowException()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $exception = $this->assertThrows(
            \Throwable::class,
            function () use ($metadata): void {
                $metadata->addField(['fieldName' => 'bouh']);
            },
            'Field configuration must have "columnName" property'
        );
        $this->assertSame(0, $exception->getCode());
        $exception = $this->assertThrows(
            \Throwable::class,
            function () use ($metadata): void {
                $metadata->addField(['columnName' => 'BOO_BOUH']);
            },
            'Field configuration must have "fieldName" property'
        );
        $this->assertSame(0, $exception->getCode());
        $exception = $this->assertThrows(
            \Throwable::class,
            function () use ($metadata): void {
                $metadata->addField(['fieldName' => 'bouh', 'columnName' => 'BOO_BOUH']);
            },
            'Field configuration must have "type" property'
        );
        $this->assertSame(0, $exception->getCode());
    }

    /**
     * @return array<string, array{0: string, 1: class-string, 2: bool}>
     */
    public static function provideDateTimeProperties(): array
    {
        return [
            'typed \DateTimeImmutable'       => ['immutable', \DateTimeImmutable::class, false],
            'typed \DateTimeInterface'       => ['interface', \DateTimeImmutable::class, false],
            'inherited \DateTimeImmutable'   => ['inherited', \DateTimeImmutable::class, false],
            'typed \DateTime'                => ['mutable', \DateTime::class, true],
            'not typed'                      => ['untyped', \DateTime::class, true],
            'union type'                     => ['union', \DateTime::class, true],
            'no property, through a setter'  => ['virtual', \DateTime::class, true],
        ];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideDateTimeProperties')]
    public function testDatetimeShouldHydrateTheClassOfItsProperty(string $property, string $class, bool $mutable)
    {
        $services = new TingServices();
        foreach ([true, false] as $entityFirst) {
            $metadata = new Metadata($services->serializerFactory());
            if ($entityFirst) {
                $metadata->setEntity(DatedEntity::class);
            }
            $metadata->addField(['fieldName' => $property, 'columnName' => 'col_at', 'type' => 'datetime']);
            if ($entityFirst === false) {
                $metadata->setEntity(DatedEntity::class);
            }
            $entity = new DatedEntity();

            // Same format whatever the class: a MySQL DATETIME
            $metadata->setEntityProperty($entity, 'col_at', '2026-01-02 03:04:05');

            $value = (new PropertyAccessor())->getValue($entity, $property);
            $this->assertSame($class, $value::class);
            $this->assertSame('2026-01-02 03:04:05', $value->format('Y-m-d H:i:s'));
            $this->assertSame('2026-01-02 03:04:05', $metadata->getEntityPropertyByFieldName($entity, $property));
            $this->assertSame($mutable, $metadata->isMutable($property));
        }
    }

    public function testDatetimeWithoutEntityShouldHydrateADateTime()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->addField(['fieldName' => 'at', 'columnName' => 'col_at', 'type' => 'datetime']);
        $entity = new class () {
            public mixed $at = null;
        };

        $metadata->setEntityProperty($entity, 'col_at', '2026-01-02 03:04:05');

        $this->assertInstanceOf(\DateTime::class, $entity->at);
        $this->assertTrue($metadata->isMutable('at'));
    }

    public function testAnExplicitSerializerShouldWinOverThePropertyType()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(DatedEntity::class);
        $metadata->addField([
            'fieldName'  => 'interface',
            'columnName' => 'col_at',
            'type'       => 'datetime',
            'serializer' => DateTime::class,
        ]);
        $entity = new DatedEntity();

        $metadata->setEntityProperty($entity, 'col_at', '2026-01-02 03:04:05');

        $this->assertInstanceOf(\DateTime::class, $entity->interface);
        $this->assertTrue($metadata->isMutable('interface'));
    }

    public function testDatetimeShouldKeepTheFormatGivenInItsOptions()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(DatedEntity::class);
        $metadata->addField([
            'fieldName'          => 'immutable',
            'columnName'         => 'col_at',
            'type'               => 'datetime',
            'serializer_options' => ['serialize' => ['format' => 'U'], 'unserialize' => ['format' => 'U']],
        ]);
        $entity = new DatedEntity();

        $metadata->setEntityProperty($entity, 'col_at', '86400');

        $this->assertSame('1970-01-02', $entity->immutable->format('Y-m-d'));
        $this->assertSame('86400', $metadata->getEntityPropertyByFieldName($entity, 'immutable'));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function provideFieldsAndTheirDefaultMutability(): array
    {
        return [
            'string'                       => [['type' => 'string'], false],
            'int'                          => [['type' => 'int'], false],
            'double'                       => [['type' => 'double'], false],
            'bool'                         => [['type' => 'bool'], false],
            'bool with a driver serializer' => [['type' => 'bool', 'serializer' => PgsqlBoolean::class], false],
            'datetime, no entity'          => [['type' => 'datetime'], true],
            'datetime_immutable'           => [['type' => 'datetime_immutable'], false],
            'datetimezone'                 => [['type' => 'datetimezone'], false],
            'uuid'                         => [['type' => 'uuid'], false],
            'ip'                           => [['type' => 'ip'], false],
            'geometry'                     => [['type' => 'geometry'], false],
            'enum'                         => [['type' => 'string', 'serializer' => BackedEnum::class], false],
            'json decoded to arrays'       => [
                ['type' => 'json', 'serializer_options' => ['unserialize' => ['assoc' => true]]],
                false,
            ],
            'json decoded to objects'      => [['type' => 'json'], true],
            'json with explicit serializer' => [['type' => 'string', 'serializer' => Json::class], true],
            'datetime with Serializer\DateTime' => [['type' => 'datetime', 'serializer' => DateTime::class], true],
            'serializer of its own'        => [['type' => 'json', 'serializer' => CountingJson::class], true],
        ];
    }

    /**
     * @param array<string, mixed> $field
     */
    #[DataProvider('provideFieldsAndTheirDefaultMutability')]
    public function testIsMutableShouldDependOnTheFieldByDefault(array $field, bool $mutable)
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->addField(['fieldName' => 'value', 'columnName' => 'col_value'] + $field);

        $this->assertSame($mutable, $metadata->isMutable('value'));
    }

    public function testTheMutableOptionShouldOverrideTheDefault()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->addField(['fieldName' => 'payload', 'columnName' => 'col_payload', 'type' => 'json', 'mutable' => false]);
        $metadata->addField(['fieldName' => 'name', 'columnName' => 'col_name', 'type' => 'string', 'mutable' => true]);

        $this->assertFalse($metadata->isMutable('payload'));
        $this->assertTrue($metadata->isMutable('name'));
        $this->assertFalse($metadata->isMutable('unknown'));
    }

    public function testANonBooleanMutableOptionShouldThrowAnException()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());

        $this->assertThrows(ConfigException::class, function () use ($metadata): void {
            $metadata->addField(['fieldName' => 'name', 'columnName' => 'col_name', 'type' => 'string', 'mutable' => 'yes']);
        }, 'The "mutable" option of field "name" must be a boolean');
    }

    public function testIfTableKnownShouldCallCallbackAndReturnTrue()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setTable('Bouh');
        $this->assertTrue($metadata->ifTableKnown(
            'connectionName',
            'database',
            'Bouh',
            function ($metadata) use (&$outerMetadata): void {
                $outerMetadata = $metadata;
            }
        ));
        $this->assertSame($metadata, $outerMetadata);
    }

    public function testIfTableKnownShouldReturnFalse()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setTable('Bouh');
        $this->assertFalse($metadata->ifTableKnown(
            'connectionName',
            'database',
            'Bim',
            function (): void {
            }
        ));
    }

    public function testHasColumnShouldReturnTrue()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setTable('Bouh');
        $metadata->addField(['fieldName' => 'Bouh', 'columnName' => 'boo_bouh', 'type' => 'string']);
        $this->assertTrue($metadata->hasColumn('boo_bouh'));
    }

    public function testHasColumnShouldReturnFalse()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setTable('Bouh');
        $metadata->addField(['fieldName' => 'Bouh', 'columnName' => 'BOO_bouh', 'type' => 'string']);
        $this->assertFalse($metadata->hasColumn('boo_no'));
    }

    public function testCreateEntityShouldReturnObject()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $bouh = $metadata->createEntity();
        $this->assertInstanceOf(Bouh::class, $bouh);
    }

    public function testSetEntityPropertyWithDefaultSetter()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());

        $metadata->setEntity($this->bouhSpyClass());
        $metadata->addField([
            'fieldName'  => 'name',
            'columnName' => 'boo_name',
            'type'       => 'string'
        ]);

        $bouh = $metadata->createEntity();

        $metadata->setEntityProperty($bouh, 'boo_name', 'Sylvain');
        $this->assertCalledOnceWith($bouh, 'setName', ['Sylvain']);
    }

    public function testSetEntityPropertyShouldKeepNull()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());

        $metadata->setEntity($this->bouhSpyClass());
        $metadata->addField([
            'fieldName'  => 'price',
            'columnName' => 'boo_price',
            'type'       => 'int'
        ]);

        $bouh = $metadata->createEntity();

        $metadata->setEntityProperty($bouh, 'boo_price', null);
        $this->assertCalledOnceWith($bouh, 'setPrice', [null]);
    }

    public function testSetEntityPropertyShouldCastToInt()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity($this->bouhSpyClass());
        $metadata->addField([
            'fieldName'  => 'price',
            'columnName' => 'boo_price',
            'type'       => 'int'
        ]);

        $bouh = $metadata->createEntity();

        $metadata->setEntityProperty($bouh, 'boo_price', '32');
        $this->assertCalledOnceWith($bouh, 'setPrice', [32]);
    }

    public function testSetEntityPropertyShouldCastToDouble()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity($this->bouhSpyClass());
        $metadata->addField([
            'fieldName'  => 'price',
            'columnName' => 'boo_price',
            'type'       => 'double'
        ]);

        $bouh = $metadata->createEntity();

        $metadata->setEntityProperty($bouh, 'boo_price', '32.3');
        $this->assertCalledOnceWith($bouh, 'setPrice', [32.3]);
    }

    public function testSetEntityPropertyShouldCastToBool()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity($this->bouhSpyClass());
        $metadata->addField([
            'fieldName'  => 'enabled',
            'columnName' => 'boo_enabled',
            'type'       => 'bool'
        ]);

        $bouh = $metadata->createEntity();

        $metadata->setEntityProperty($bouh, 'boo_enabled', '1');
        $this->assertCalledOnceWith($bouh, 'setEnabled', [true]);
    }

    public function testSetEntityPropertyShouldUnserializeData()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity($this->bouhSpyClass());
        $metadata->addField([
            'fieldName'  => 'roles',
            'columnName' => 'boo_roles',
            'type'       => 'string',
            'serializer' => Json::class
        ]);

        $bouh = $metadata->createEntity();

        $metadata->setEntityProperty($bouh, 'boo_roles', json_encode(['Bouh', 'Sylvain']));
        $this->assertCalledOnceWith($bouh, 'setRoles', [['Bouh', 'Sylvain']]);
    }

    public function testSetEntityPropertyShouldUnserializeDataWithOptions()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity($this->bouhSpyClass());
        $metadata->addField([
            'fieldName'  => 'roles',
            'columnName' => 'boo_roles',
            'type'       => 'string',
            'serializer' => Json::class,
            'serializer_options' => [
                'unserialize' => ['assoc' => true]
            ]
        ]);

        $bouh = $metadata->createEntity();

        $metadata->setEntityProperty($bouh, 'boo_roles', json_encode(['Bouh', 'Sylvain']));
        $this->assertCalledOnceWith($bouh, 'setRoles', [['Bouh', 'Sylvain']]);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSetEntityPropertyForAutoIncrement()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity($this->bouhSpyClass());
        $metadata->addField([
            'primary'       => true,
            'autoincrement' => true,
            'fieldName'     => 'id',
            'columnName'    => 'boo_id',
            'type'          => 'int'
        ]);

        $driver = $this->getMockBuilder(MysqliDriver::class)->onlyMethods(['getInsertedId'])->getMock();
        $driver->method('getInsertedId')->willReturn(321);

        $bouh = $metadata->createEntity();

        $metadata->setEntityPropertyForAutoIncrement($bouh, $driver);
        $this->assertCalledOnceWith($bouh, 'setId', [321]);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSetEntityPropertyForAutoIncrementWithoutAutoIncrementColumnShouldReturnFalse()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity($this->bouhSpyClass());
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'boo_id',
            'type'       => 'int'
        ]);

        $driver = $this->getMockBuilder(MysqliDriver::class)->onlyMethods(['getInsertedId'])->getMock();
        $driver->method('getInsertedId')->willReturn(321);

        $bouh = $metadata->createEntity();

        $this->assertFalse($metadata->setEntityPropertyForAutoIncrement($bouh, $driver));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetByPrimariesShouldRaiseExceptionIfIncorrectPrimaries()
    {
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['replica'])->getMock();
        $mockConnectionPool->method('replica')->willReturn(new FakeDriver());
        $mockConnection = new Connection($mockConnectionPool, 'main', 'db');

        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'boo_id',
            'type'       => 'int'
        ]);
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'secondId',
            'columnName' => 'wonderful_id',
            'type'       => 'int'
        ]);
        $metadata->setTable('bouh');
        $this->assertThrows(
            Exception::class,
            function () use ($metadata, $mockConnection, $services): void {
                $metadata->getByPrimaries(
                    $mockConnection,
                    $services->queryFactory(),
                    $services->collectionFactory(),
                    1
                );
            },
            'Incorrect format for primaries'
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetByPrimariesShouldReturnAQuery()
    {
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['replica'])->getMock();
        $mockConnectionPool->method('replica')->willReturn(new FakeDriver());
        $mockConnection = new Connection($mockConnectionPool, 'main', 'db');

        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'boo_id',
            'type'       => 'int'
        ]);
        $metadata->setTable('bouh');
        $this->assertInstanceOf(
            Query::class,
            $metadata->getByPrimaries(
                $mockConnection,
                $services->queryFactory(),
                $services->collectionFactory(),
                ['id' => 1]
            )
        );
        $this->assertInstanceOf(
            Query::class,
            $metadata->getByPrimaries(
                $mockConnection,
                $services->queryFactory(),
                $services->collectionFactory(),
                1
            )
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetOneByCriteriaShouldReturnAQuery()
    {
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['replica'])->getMock();
        $mockConnectionPool->method('replica')->willReturn(new FakeDriver());
        $mockConnection = new Connection($mockConnectionPool, 'main', 'db');

        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
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
        $metadata->setTable('bouh');
        $this->assertInstanceOf(
            Query::class,
            $metadata->getOneByCriteria(
                $mockConnection,
                $services->queryFactory(),
                $services->collectionFactory(),
                ['name' => 'Xavier']
            )
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetOneByCriteriaShouldRaiseExceptionOnUnknownField()
    {
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['replica'])->getMock();
        $mockConnectionPool->method('replica')->willReturn(new FakeDriver());
        $mockConnection = new Connection($mockConnectionPool, 'main', 'db');

        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
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
        $metadata->setTable('bouh');
        $this->assertThrows(
            Exception::class,
            function () use ($metadata, $mockConnection, $services): void {
                $metadata->getOneByCriteria(
                    $mockConnection,
                    $services->queryFactory(),
                    $services->collectionFactory(),
                    ['weirdColumnName' => 'Xavier']
                );
            }
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetAllShouldReturnAQuery()
    {
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['replica'])->getMock();
        $mockConnectionPool->method('replica')->willReturn(new FakeDriver());
        $mockConnection = new Connection($mockConnectionPool, 'main', 'db');

        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'boo_id',
            'type'       => 'int'
        ]);
        $metadata->setTable('bouh');
        $this->assertInstanceOf(
            Query::class,
            $metadata->getAll(
                $mockConnection,
                $services->queryFactory(),
                $services->collectionFactory()
            )
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetByCriteriaShouldReturnAQuery()
    {
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['replica'])->getMock();
        $mockConnectionPool->method('replica')->willReturn(new FakeDriver());
        $mockConnection = new Connection($mockConnectionPool, 'main', 'db');

        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
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
        $metadata->setTable('bouh');
        $this->assertInstanceOf(
            Query::class,
            $metadata->getByCriteria(
                ['name' => 'Xavier'],
                $mockConnection,
                $services->queryFactory(),
                $services->collectionFactory()
            )
        );
    }

    public function testGetByPrimariesShouldConvertCompositeKeyPropertiesToColumns()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $query = $metadata->getByPrimaries(
            $connection,
            $services->queryFactory(),
            $services->collectionFactory(),
            ['id' => 3, 'secondId' => 4]
        );

        $this->assertSame(
            [
                'SELECT boo_id, boo_second_id, boo_name, boo_color, boo_priority, boo_created_at, boo_roles, boo_raw,'
                . ' boo_ip, boo_active FROM bouh WHERE boo_id = :w1_boo_id AND boo_second_id = :w2_boo_second_id LIMIT 1',
                ['w1_boo_id' => 3, 'w2_boo_second_id' => 4]
            ],
            $this->readQuery($query)
        );
    }

    public function testGetByPrimariesShouldSerializeCompositeKeyValues()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $query = $metadata->getByPrimaries(
            $connection,
            $services->queryFactory(),
            $services->collectionFactory(),
            ['id' => 3, 'color' => ColorsEnum::RED]
        );

        $this->assertSame(['w1_boo_id' => 3, 'w2_boo_color' => 'red'], $this->readQuery($query)[1]);
    }

    public function testGetByPrimariesShouldRejectAColumnNameAndNameTheProperty()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getByPrimaries(
                $connection,
                $services->queryFactory(),
                $services->collectionFactory(),
                ['boo_id' => 3, 'secondId' => 4]
            ),
            '"boo_id" is a column name: use the property name "id" in Repository::get()'
        );
    }

    public function testGetByPrimariesWithAScalarShouldUseThePrimaryColumn()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata(singlePrimary: true);

        $query = $metadata->getByPrimaries(
            $connection,
            $services->queryFactory(),
            $services->collectionFactory(),
            '3'
        );

        $this->assertSame(['w1_boo_id' => '3'], $this->readQuery($query)[1]);
    }

    public function testGetByCriteriaWithOrderShouldConvertOrderPropertiesToColumns()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $query = $metadata->getByCriteriaWithOrderAndLimit(
            ['name' => 'Xavier'],
            ['name' => 'asc', 'id' => 'DESC'],
            10,
            $connection,
            $services->queryFactory(),
            $services->collectionFactory()
        );

        $this->assertStringEndsWith(
            ' WHERE boo_name = :w1_boo_name ORDER BY boo_name ASC,boo_id DESC LIMIT 10',
            $this->readQuery($query)[0]
        );
    }

    public function testGetByCriteriaWithOrderShouldRejectAColumnNameAndNameTheProperty()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getByCriteriaWithOrderAndLimit(
                ['name' => 'Xavier'],
                ['boo_name' => 'ASC'],
                0,
                $connection,
                $services->queryFactory(),
                $services->collectionFactory()
            ),
            '"boo_name" is a column name: use the property name "name" in the order of Repository::getBy()'
        );
    }

    public function testGetByCriteriaWithOrderShouldRejectAnUnknownProperty()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getByCriteriaWithOrderAndLimit(
                ['name' => 'Xavier'],
                ['unknown' => 'ASC'],
                0,
                $connection,
                $services->queryFactory(),
                $services->collectionFactory()
            ),
            'Undefined property "unknown" in the order of Repository::getBy()'
        );
    }

    public function testGetByCriteriaWithOrderShouldRejectAnInvalidDirection()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getByCriteriaWithOrderAndLimit(
                ['name' => 'Xavier'],
                ['name' => 'ASC; DROP TABLE bouh'],
                0,
                $connection,
                $services->queryFactory(),
                $services->collectionFactory()
            ),
            'Invalid direction "ASC; DROP TABLE bouh" for property "name" in the order of Repository::getBy():'
            . ' use "ASC" or "DESC"'
        );
    }

    public function testGetByCriteriaShouldRejectAColumnNameAndNameTheProperty()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getByCriteria(
                ['boo_name' => 'Xavier'],
                $connection,
                $services->queryFactory(),
                $services->collectionFactory()
            ),
            '"boo_name" is a column name: use the property name "name" in the criteria of Repository::getBy()'
        );
        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getOneByCriteria(
                $connection,
                $services->queryFactory(),
                $services->collectionFactory(),
                ['boo_name' => 'Xavier']
            ),
            '"boo_name" is a column name: use the property name "name" in the criteria of Repository::getOneBy()'
        );
    }

    public function testGetByCriteriaShouldSerializeObjectValues()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $query = $metadata->getByCriteria(
            [
                'color'     => ColorsEnum::GREEN,
                'priority'  => [PriorityEnum::LOW, PriorityEnum::HIGH],
                'createdAt' => new \DateTime('2026-10-05 12:34:56'),
            ],
            $connection,
            $services->queryFactory(),
            $services->collectionFactory()
        );

        [$sql, $params] = $this->readQuery($query);
        $this->assertStringEndsWith(
            ' WHERE boo_color = :w1_boo_color AND boo_priority IN (:w2_boo_priority__1,:w2_boo_priority__2)'
            . ' AND boo_created_at = :w3_boo_created_at',
            $sql
        );
        $this->assertSame(
            [
                'w1_boo_color' => 'green',
                'w2_boo_priority__1' => '1',
                'w2_boo_priority__2' => '3',
                'w3_boo_created_at' => '2026-10-05 12:34:56',
            ],
            $params
        );
    }

    public function testGetByCriteriaShouldSerializeAnArrayAsAWholeForAnArrayValueSerializer()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $query = $metadata->getOneByCriteria(
            $connection,
            $services->queryFactory(),
            $services->collectionFactory(),
            ['roles' => ['ROLE_ADMIN', 'ROLE/USER']]
        );

        [$sql, $params] = $this->readQuery($query);
        $this->assertStringEndsWith(' WHERE boo_roles = :w1_boo_roles LIMIT 1', $sql);
        // The serialize options of the field are used (JSON_UNESCAPED_SLASHES)
        $this->assertSame(['w1_boo_roles' => '["ROLE_ADMIN","ROLE/USER"]'], $params);
    }

    public function testGetByCriteriaShouldSendScalarsAndNullAsIs()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $query = $metadata->getByCriteria(
            ['id' => [1, '2'], 'name' => 'Xavier', 'color' => 'red', 'createdAt' => null, 'raw' => true],
            $connection,
            $services->queryFactory(),
            $services->collectionFactory()
        );

        [$sql, $params] = $this->readQuery($query);
        $this->assertStringEndsWith(
            ' WHERE boo_id IN (:w1_boo_id__1,:w1_boo_id__2) AND boo_name = :w2_boo_name AND boo_color = :w3_boo_color'
            . ' AND boo_created_at IS NULL AND boo_raw = :w5_boo_raw',
            $sql
        );
        $this->assertSame(
            ['w1_boo_id__1' => 1, 'w1_boo_id__2' => '2', 'w2_boo_name' => 'Xavier', 'w3_boo_color' => 'red', 'w5_boo_raw' => true],
            $params
        );
    }

    public function testGetByCriteriaShouldSendAStringableObjectWithoutSerializerAsIs()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();
        $stringable = new class () implements \Stringable {
            public function __toString(): string
            {
                return 'd4c5a1f0-0000-4000-8000-000000000000';
            }
        };

        $query = $metadata->getByCriteria(
            ['raw' => $stringable, 'name' => [$stringable]],
            $connection,
            $services->queryFactory(),
            $services->collectionFactory()
        );

        $this->assertSame(['w1_boo_raw' => $stringable, 'w2_boo_name__1' => $stringable], $this->readQuery($query)[1]);
    }

    public function testGetByPrimariesWithASingleObjectShouldSerializeIt()
    {
        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('replica')->willReturn(new FakeDriver());
        $connection = new Connection($connectionPool, 'main', 'db');
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $metadata->setTable('bouh');
        $metadata->addField([
            'primary'            => true,
            'fieldName'          => 'color',
            'columnName'         => 'boo_color',
            'type'               => 'string',
            'serializer'         => BackedEnum::class,
            'serializer_options' => ['unserialize' => ['enum' => ColorsEnum::class]]
        ]);

        $query = $metadata->getByPrimaries(
            $connection,
            $services->queryFactory(),
            $services->collectionFactory(),
            ColorsEnum::BLUE
        );

        $this->assertSame(['w1_boo_color' => 'blue'], $this->readQuery($query)[1]);
    }

    public function testGetByCriteriaShouldRejectAnEmptyArray()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getByCriteria(
                ['id' => []],
                $connection,
                $services->queryFactory(),
                $services->collectionFactory()
            ),
            'Empty array for property "id" in the criteria of Repository::getBy(): nothing can match'
        );
    }

    public function testGetByCriteriaShouldRejectAnObjectWithoutSerializer()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getByCriteria(
                ['raw' => new \stdClass()],
                $connection,
                $services->queryFactory(),
                $services->collectionFactory()
            ),
            'Cannot use an object of class "stdClass" for property "raw" in the criteria of Repository::getBy():'
            . ' its field has no serializer'
        );
        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getByCriteria(
                ['raw' => ['a', new \stdClass()]],
                $connection,
                $services->queryFactory(),
                $services->collectionFactory()
            ),
            'Cannot use an object of class "stdClass" for property "raw" in the criteria of Repository::getBy():'
            . ' its field has no serializer'
        );
    }

    public function testGetByCriteriaShouldRejectNullOrNestedArrayInAnInList()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getByCriteria(
                ['name' => ['Xavier', null]],
                $connection,
                $services->queryFactory(),
                $services->collectionFactory()
            ),
            'Null in the array for property "name" in the criteria of Repository::getBy(): an IN list never matches NULL'
        );
        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getByCriteria(
                ['name' => [['Xavier']]],
                $connection,
                $services->queryFactory(),
                $services->collectionFactory()
            ),
            'Nested array for property "name" in the criteria of Repository::getBy()'
        );
    }

    public function testGetByCriteriaShouldSerializeScalarsForAScalarValueSerializer()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $query = $metadata->getByCriteria(
            ['ip' => '10.0.0.1', 'active' => false],
            $connection,
            $services->queryFactory(),
            $services->collectionFactory()
        );

        [$sql, $params] = $this->readQuery($query);
        $this->assertStringEndsWith(' WHERE boo_ip = :w1_boo_ip AND boo_active = :w2_boo_active', $sql);
        $this->assertSame(['w1_boo_ip' => 167772161, 'w2_boo_active' => 'f'], $params);
    }

    public function testGetByCriteriaShouldSerializeEachScalarOfAnInListForAScalarValueSerializer()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $query = $metadata->getByCriteriaWithOrderAndLimit(
            ['ip' => ['10.0.0.1', '10.0.0.2'], 'active' => null],
            ['ip' => 'ASC'],
            10,
            $connection,
            $services->queryFactory(),
            $services->collectionFactory()
        );

        [$sql, $params] = $this->readQuery($query);
        $this->assertStringContainsString(
            ' WHERE boo_ip IN (:w1_boo_ip__1,:w1_boo_ip__2) AND boo_active IS NULL',
            $sql
        );
        $this->assertSame(['w1_boo_ip__1' => 167772161, 'w1_boo_ip__2' => 167772162], $params);
    }

    public function testGetByCriteriaShouldRejectAScalarThatAScalarValueSerializerConvertsToNull()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getOneByCriteria(
                $connection,
                $services->queryFactory(),
                $services->collectionFactory(),
                ['active' => 't']
            ),
            'Invalid value \'t\' for property "active" in the criteria of Repository::getOneBy():'
            . ' the serializer of the field converts it to NULL'
        );
        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getByCriteria(
                ['active' => [true, 1]],
                $connection,
                $services->queryFactory(),
                $services->collectionFactory()
            ),
            'Invalid value 1 for property "active" in the criteria of Repository::getBy():'
            . ' the serializer of the field converts it to NULL'
        );
    }

    public function testGetByPrimariesShouldSerializeAScalarForAScalarValueSerializer()
    {
        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('replica')->willReturn(new FakeDriver());
        $connection = new Connection($connectionPool, 'main', 'db');
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $metadata->setTable('bouh');
        $metadata->addField(['primary' => true, 'fieldName' => 'ip', 'columnName' => 'boo_ip', 'type' => 'ip']);

        $scalarQuery = $metadata->getByPrimaries(
            $connection,
            $services->queryFactory(),
            $services->collectionFactory(),
            '10.0.0.1'
        );
        $arrayQuery = $metadata->getByPrimaries(
            $connection,
            $services->queryFactory(),
            $services->collectionFactory(),
            ['ip' => '10.0.0.1']
        );

        $this->assertSame(['w1_boo_ip' => 167772161], $this->readQuery($scalarQuery)[1]);
        $this->assertSame(['w1_boo_ip' => 167772161], $this->readQuery($arrayQuery)[1]);
    }

    public function testReadsShouldRejectEmptyCriteria()
    {
        [$metadata, $connection, $services] = $this->createReadMetadata();

        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getByCriteria(
                [],
                $connection,
                $services->queryFactory(),
                $services->collectionFactory()
            ),
            'No criteria in Repository::getBy(): use Repository::getAll() to read every row'
        );
        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getByCriteriaWithOrderAndLimit(
                [],
                ['id' => 'ASC'],
                10,
                $connection,
                $services->queryFactory(),
                $services->collectionFactory()
            ),
            'No criteria in Repository::getBy(): use Repository::getAll() to read every row'
        );
        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getOneByCriteria(
                $connection,
                $services->queryFactory(),
                $services->collectionFactory(),
                []
            ),
            'No criteria in Repository::getOneBy(): use Repository::getAll() to read every row'
        );
        $this->assertThrows(
            ValueException::class,
            fn () => $metadata->getByPrimaries(
                $connection,
                $services->queryFactory(),
                $services->collectionFactory(),
                []
            ),
            'No primary key value in Repository::get()'
        );
    }

    /**
     * Metadata with a field for each kind of criterion value, read through a FakeDriver replica
     *
     * @return array{0: Metadata, 1: Connection, 2: TingServices}
     */
    private function createReadMetadata(bool $singlePrimary = false): array
    {
        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('replica')->willReturn(new FakeDriver());
        $connection = new Connection($connectionPool, 'main', 'db');

        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $metadata->setTable('bouh');
        $metadata->addField(['primary' => true, 'fieldName' => 'id', 'columnName' => 'boo_id', 'type' => 'int']);
        $metadata->addField([
            'primary'    => $singlePrimary === false,
            'fieldName'  => 'secondId',
            'columnName' => 'boo_second_id',
            'type'       => 'int'
        ]);
        $metadata->addField(['fieldName' => 'name', 'columnName' => 'boo_name', 'type' => 'string']);
        $metadata->addField([
            'fieldName'          => 'color',
            'columnName'         => 'boo_color',
            'type'               => 'string',
            'serializer'         => BackedEnum::class,
            'serializer_options' => ['unserialize' => ['enum' => ColorsEnum::class]]
        ]);
        $metadata->addField([
            'fieldName'          => 'priority',
            'columnName'         => 'boo_priority',
            'type'               => 'int',
            'serializer'         => BackedEnum::class,
            'serializer_options' => ['unserialize' => ['enum' => PriorityEnum::class]]
        ]);
        $metadata->addField(['fieldName' => 'createdAt', 'columnName' => 'boo_created_at', 'type' => 'datetime']);
        $metadata->addField([
            'fieldName'          => 'roles',
            'columnName'         => 'boo_roles',
            'type'               => 'json',
            'serializer_options' => ['serialize' => ['options' => JSON_UNESCAPED_SLASHES]]
        ]);
        $metadata->addField(['fieldName' => 'raw', 'columnName' => 'boo_raw', 'type' => 'string']);
        $metadata->addField(['fieldName' => 'ip', 'columnName' => 'boo_ip', 'type' => 'ip']);
        $metadata->addField([
            'fieldName'  => 'active',
            'columnName' => 'boo_active',
            'type'       => 'bool',
            'serializer' => PgsqlBoolean::class
        ]);

        return [$metadata, $connection, $services];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>} the SQL and the parameters of the query
     */
    private function readQuery(QueryInterface $query): array
    {
        return (fn () => [$this->sql, $this->params])->call($query);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGenerateQueryForInsertShouldReturnAPreparedQuery()
    {
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['primary'])->getMock();
        $mockConnection = new Connection($mockConnectionPool, 'main', 'db');

        $mockDriver = $this->getMockBuilder(FakeDriver::class)->onlyMethods(['prepare'])->getMock();
        $mockStatement = $this->createMock(StatementInterface::class);
        $mockDriver
            ->expects($this->once())
            ->method('prepare')
            ->with('INSERT INTO bouh (boo_id, boo_name) VALUES (:v1_boo_id, :v2_boo_name)')
            ->willReturn($mockStatement);
        // atoum first returned a FakeDriver, then replaced it with $mockDriver before any call
        $mockConnectionPool->method('primary')->willReturn($mockDriver);
        $mockStatement
            ->expects($this->once())
            ->method('execute')
            ->with($this->identicalTo(['v1_boo_id' => null, 'v2_boo_name' => 'Xavier']));

        $entity = new Bouh();
        $entity->setName('Xavier');

        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $metadata->setTable('bouh');
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'boo_id',
            'type'       => 'int'
        ]);
        $metadata->addField([
            'fieldName'  => 'name',
            'columnName' => 'boo_name',
            'type'       => 'int'
        ]);
        $query = $metadata->generateQueryForInsert($mockConnection, $services->queryFactory(), $entity);
        $this->assertInstanceOf(PreparedQuery::class, $query);
        $query->execute();
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGenerateQueryForInsertShouldSerializeArray()
    {
        $services = new TingServices();

        [$mockConnection, $mockQueryFactory] = $this->createInsertMocks($services, $outerParams);

        $entity = new Bouh();
        $entity->setRoles(['USER', 'ADMIN']);

        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'roles',
            'columnName' => 'boo_roles',
            'type'       => 'string',
            'serializer' => Json::class
        ]);
        $metadata->setTable('bouh');
        $query = $metadata->generateQueryForInsert($mockConnection, $mockQueryFactory, $entity);
        $this->assertSame(json_encode(['USER', 'ADMIN']), $outerParams['v1_boo_roles']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGenerateQueryForInsertShouldSkipUnitializedFields()
    {
        $services = new TingServices();

        [$mockConnection, $mockQueryFactory] = $this->createInsertMocks($services, $outerParams);

        $entity = new PublicPropertiesEntity();

        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(PublicPropertiesEntity::class);
        $metadata
            ->addField([
                'primary'    => false,
                'fieldName'  => 'propertyWithSetter',
                'columnName' => 'property_with_setter',
                'type'       => 'string',
            ])
            ->addField([
                'primary'    => false,
                'fieldName'  => 'propertyWithoutSetter',
                'columnName' => 'property_without_setter',
                'type'       => 'string',
            ])
            ->addField([
                'primary'    => false,
                'fieldName'  => 'propertyWithDefaultValue',
                'columnName' => 'property_with_default_value',
                'type'       => 'string',
            ])
            ->addField([
                'primary'    => false,
                'fieldName'  => 'propertyWithGetter',
                'columnName' => 'property_with_getter',
                'type'       => 'string',
            ]);
        $metadata->setTable('public_properties_entity');
        $query = $metadata->generateQueryForInsert($mockConnection, $mockQueryFactory, $entity);
        $this->assertSame(
            ['v1_property_with_default_value' => 'default', 'v2_property_with_getter' => 'with getter'],
            $outerParams
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGenerateQueryForInsertShouldSkipUnitializedFieldsWithACustomGetter()
    {
        $services = new TingServices();

        [$mockConnection, $mockQueryFactory] = $this->createInsertMocks($services, $outerParams);

        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(CustomGetterEntity::class);
        $metadata->addField([
            'fieldName'  => 'label',
            'columnName' => 'label',
            'type'       => 'string',
            'getter'     => 'label',
        ]);
        $metadata->setTable('custom_getter_entity');

        $metadata->generateQueryForInsert($mockConnection, $mockQueryFactory, new CustomGetterEntity());

        $this->assertSame([], $outerParams);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGenerateQueryForInsertShouldSerializeWithOptions()
    {
        $services = new TingServices();

        [$mockConnection, $mockQueryFactory] = $this->createInsertMocks($services, $outerParams);

        $entity = new Bouh();
        $entity->setRoles(['USER', '"BOUH"']);

        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'roles',
            'columnName' => 'boo_roles',
            'type'       => 'string',
            'serializer' => Json::class,
            'serializer_options' => [
                'serialize'   => ['options' => JSON_HEX_QUOT]
            ]
        ]);
        $metadata->setTable('bouh');
        $query = $metadata->generateQueryForInsert($mockConnection, $mockQueryFactory, $entity);
        $this->assertSame(json_encode(['USER', '"BOUH"'], JSON_HEX_QUOT), $outerParams['v1_boo_roles']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGenerateQueryForInsertShouldNotUseAutoIncrementColumn()
    {
        $services = new TingServices();

        [$mockConnection, $mockQueryFactory] = $this->createInsertMocks($services, $outerParams, $outerSql);

        $entity = new Bouh();
        $entity->setRoles(['USER', 'ADMIN']);

        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $metadata->addField([
            'primary'       => true,
            'autoincrement' => true,
            'fieldName'     => 'id',
            'columnName'    => 'boo_id',
            'type'          => 'int',
            'serializer'    => Json::class
        ]);
        $metadata->addField([
            'fieldName'  => 'roles',
            'columnName' => 'boo_roles',
            'type'       => 'string',
            'serializer' => Json::class
        ]);
        $metadata->setTable('bouh');
        $query = $metadata->generateQueryForInsert($mockConnection, $mockQueryFactory, $entity);
        $this->assertSame('INSERT INTO bouh (boo_roles) VALUES (:v1_boo_roles)', $outerSql);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGenerateQueryForUpdateShouldReturnAPreparedQuery()
    {
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['primary'])->getMock();
        $mockDriver = $this->getMockBuilder(FakeDriver::class)->onlyMethods(['prepare'])->getMock();
        $mockStatement = $this->createMock(StatementInterface::class);
        $mockDriver
            ->expects($this->once())
            ->method('prepare')
            ->with('UPDATE bouh SET firstname = :v1_firstname WHERE boo_id = :w1_boo_id AND firstname = :w2_firstname')
            ->willReturn($mockStatement);
        $mockConnectionPool->method('primary')->willReturn($mockDriver);
        $mockConnection = new Connection($mockConnectionPool, 'main', 'db');
        $mockStatement
            ->expects($this->once())
            ->method('execute')
            ->with(['v1_firstname' => 'Xavier', 'w1_boo_id' => 20, 'w2_firstname' => 'Sylvain']);

        $entity = new Bouh();
        $entity->setId(20);
        $entity->setName('Xavier');

        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $metadata->setTable('bouh');
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'boo_id',
            'type'       => 'int'
        ]);
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'name',
            'columnName' => 'firstname',
            'type'       => 'int'
        ]);
        $query = $metadata->generateQueryForUpdate(
            $mockConnection,
            $services->queryFactory(),
            $entity,
            // As UnitOfWork passes them: property => [database value before the change, new database value]
            ['name' => ['Sylvain', 'Xavier']]
        );
        $this->assertInstanceOf(PreparedQuery::class, $query);
        $query->execute();
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGenerateQueryForDeleteShouldReturnAPreparedQuery()
    {
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['primary'])->getMock();
        $mockDriver = $this->getMockBuilder(FakeDriver::class)->onlyMethods(['prepare'])->getMock();
        $mockStatement = $this->createMock(StatementInterface::class);
        $mockDriver
            ->expects($this->once())
            ->method('prepare')
            ->with('DELETE FROM bouh WHERE boo_id = :w1_boo_id')
            ->willReturn($mockStatement);
        $mockConnectionPool->method('primary')->willReturn($mockDriver);
        $mockConnection = new Connection($mockConnectionPool, 'main', 'db');
        $mockStatement
            ->expects($this->once())
            ->method('execute')
            ->with($this->identicalTo(['w1_boo_id' => 1]));

        $entity = new Bouh();
        $entity->setName('Xavier');

        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'boo_id',
            'type'       => 'int'
        ]);
        $metadata->setTable('bouh');
        $query = $metadata->generateQueryForDelete(
            $mockConnection,
            $services->queryFactory(),
            // The primary key was changed from 1 to 2 before the delete: the row still has the old one
            ['id' => [1, 2]],
            $entity
        );
        $this->assertInstanceOf(PreparedQuery::class, $query);
        $query->execute();
    }

    public function testGenerateQueryForUpdateShouldReadAnUnchangedPrimaryKeyThroughItsGetter()
    {
        $mockConnectionPool = $this->createStub(ConnectionPool::class);
        $mockDriver = $this->createStub(FakeDriver::class);
        $mockStatement = $this->createMock(StatementInterface::class);
        $mockDriver->method('prepare')->willReturn($mockStatement);
        $mockConnectionPool->method('primary')->willReturn($mockDriver);
        $mockStatement
            ->expects($this->once())
            ->method('execute')
            ->with(['v1_firstname' => 'Xavier', 'w1_boo_id' => 'id-20']);

        $entity = new class () extends Bouh {
            public function idForStorage(): string
            {
                return 'id-' . $this->getId();
            }
        };
        $entity->setId(20);
        $entity->setName('Xavier');

        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity($entity::class);
        $metadata->setTable('bouh');
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'boo_id',
            'type'       => 'string',
            'getter'     => 'idForStorage',
        ]);
        $metadata->addField(['fieldName' => 'name', 'columnName' => 'firstname', 'type' => 'string']);

        $metadata->generateQueryForUpdate(
            new Connection($mockConnectionPool, 'main', 'db'),
            $services->queryFactory(),
            $entity,
            ['name' => ['Sylvain', 'Xavier']]
        )->execute();
    }

    public function testGenerateQueryForUpdateShouldSerializeThePrimaryKey()
    {
        [$metadata, $connection, $services, $entity] = $this->createEnumPrimaryKeyMetadata();

        $query = $metadata->generateQueryForUpdate($connection, $services->queryFactory(), $entity, ['name' => ['Sylvain', 'Xavier']]);

        $this->assertSame(['v1_name' => 'Xavier', 'w1_color' => 'blue'], $this->readQuery($query)[1]);
    }

    public function testGenerateQueryForUpdateShouldTargetTheOldDatabaseValueOfAChangedPrimaryKey()
    {
        [$metadata, $connection, $services, $entity] = $this->createEnumPrimaryKeyMetadata();

        $query = $metadata->generateQueryForUpdate(
            $connection,
            $services->queryFactory(),
            $entity,
            // As UnitOfWork passes them: property => [database value before the change, new database value]
            ['color' => ['red', 'blue']]
        );

        $this->assertSame(['v1_color' => 'blue', 'w1_color' => 'red'], $this->readQuery($query)[1]);
    }

    public function testGenerateQueryForDeleteShouldSerializeThePrimaryKey()
    {
        [$metadata, $connection, $services, $entity] = $this->createEnumPrimaryKeyMetadata();

        $query = $metadata->generateQueryForDelete($connection, $services->queryFactory(), [], $entity);

        $this->assertSame(['w1_color' => 'blue'], $this->readQuery($query)[1]);
    }

    public function testAPrimaryKeyShouldUseTheDefaultSerializerOfItsType()
    {
        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('primary')->willReturn(new FakeDriver());
        $connectionPool->method('replica')->willReturn(new FakeDriver());
        $connection = new Connection($connectionPool, 'main', 'db');
        $services = new TingServices();
        $entity = new class () {
            public \DateTimeImmutable $day;
            public string $label = 'a';
        };
        $entity->day = new \DateTimeImmutable('2026-01-02 03:04:05');
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity($entity::class);
        $metadata->setTable('event');
        // No explicit serializer: "datetime" of a property typed \DateTimeImmutable brings Serializer\DateTimeImmutable
        $metadata->addField(['primary' => true, 'fieldName' => 'day', 'columnName' => 'ev_day', 'type' => 'datetime']);
        $metadata->addField(['fieldName' => 'label', 'columnName' => 'ev_label', 'type' => 'string']);

        $get = $metadata->getByPrimaries($connection, $services->queryFactory(), $services->collectionFactory(), $entity->day);
        $update = $metadata->generateQueryForUpdate($connection, $services->queryFactory(), $entity, ['label' => ['b', 'a']]);
        $delete = $metadata->generateQueryForDelete($connection, $services->queryFactory(), [], $entity);

        $this->assertSame(['w1_ev_day' => '2026-01-02 03:04:05'], $this->readQuery($get)[1]);
        $this->assertSame(['v1_ev_label' => 'a', 'w1_ev_day' => '2026-01-02 03:04:05'], $this->readQuery($update)[1]);
        $this->assertSame(['w1_ev_day' => '2026-01-02 03:04:05'], $this->readQuery($delete)[1]);
    }

    /**
     * A metadata whose primary key is an enum, and an entity whose key is ColorsEnum::BLUE.
     *
     * @return array{0: Metadata, 1: Connection, 2: TingServices, 3: object}
     */
    private function createEnumPrimaryKeyMetadata(): array
    {
        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('primary')->willReturn(new FakeDriver());
        $services = new TingServices();
        $entity = new class () {
            public ColorsEnum $color = ColorsEnum::BLUE;
            public string $name = 'Xavier';
        };
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity($entity::class);
        $metadata->setTable('bouh');
        $metadata->addField([
            'primary'            => true,
            'fieldName'          => 'color',
            'columnName'         => 'color',
            'type'               => 'string',
            'serializer'         => BackedEnum::class,
            'serializer_options' => ['unserialize' => ['enum' => ColorsEnum::class]],
        ]);
        $metadata->addField(['fieldName' => 'name', 'columnName' => 'name', 'type' => 'string']);

        return [$metadata, new Connection($connectionPool, 'main', 'db'), $services, $entity];
    }

    public function testSetEntityPropertyWithDefinedSetter()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        // Stand-in for the "mock\repository\Bouh" class atoum generated, with its nameIs() override
        $entityClass = new class () {
            public $name = null;
            public int $nameIsCalls = 0;

            public function nameIs($name): void
            {
                $this->nameIsCalls++;
                $this->name = $name;
            }
        };
        $metadata->setEntity($entityClass::class);
        $metadata->addField([
            'fieldName'  => 'name',
            'columnName' => 'boo_name',
            'type'       => 'string',
            'setter'     => 'nameIs'
        ]);

        $bouh = $metadata->createEntity();

        $metadata->setEntityProperty($bouh, 'boo_name', 'Sylvain');
        $this->assertSame('Sylvain', $bouh->name);
        $this->assertSame(1, $bouh->nameIsCalls);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testCustomGetterReturnGoodValue()
    {
        $services = new TingServices();

        [$mockConnection, $mockQueryFactory] = $this->createInsertMocks($services, $outerParams);

        $entity = new BouhCustomGetter();
        $entity->setName('Nicolas');

        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(BouhCustomGetter::class);
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'name',
            'columnName' => 'boo_name',
            'type'       => 'string',
            'getter'     => 'nameIs'
        ]);
        $metadata->setTable('bouh');
        $query = $metadata->generateQueryForInsert($mockConnection, $mockQueryFactory, $entity);
        $this->assertSame('Nicolas', $outerParams['v1_boo_name']);
    }

    #[RequiresPhp('>= 8.4.0')]
    public function testSetEntityPropertyShouldByPassPropertyHook()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(HookedPropertiesEntity::class);
        $metadata
            ->addField([
                'fieldName'  => 'hookSetOnly',
                'columnName' => 'hook_set_only',
                'type'       => 'string',
            ])
        ;
        $entity = new HookedPropertiesEntity();
        $metadata->setEntityProperty($entity, 'hook_set_only', 'value');
        $this->assertSame('value', $entity->hookSetOnly);
    }

    #[RequiresPhp('>= 8.4.0')]
    public function testGetEntityPropertyShouldUsePropertyHook()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(HookedPropertiesEntity::class);
        $metadata
            ->addField([
                'fieldName'  => 'hookBoth',
                'columnName' => 'hook_both',
                'type'       => 'string',
            ])
        ;
        $entity = new HookedPropertiesEntity();
        $metadata->setEntityProperty($entity, 'hook_both', 'value');
        $this->assertSame('value (hooked on get)', $metadata->getEntityPropertyByFieldName($entity, 'hookBoth'));
    }

    /**
     * Builds the mocks shared by the generateQueryForInsert() tests: a Mysqli driver whose escapeField() returns the
     * field as is, a connection pool returning it as primary, a prepared query capturing its params and a query factory
     * returning this prepared query (and capturing the SQL).
     *
     * @return array{0: Connection, 1: QueryFactory}
     */
    private function createInsertMocks(TingServices $services, &$outerParams, &$outerSql = null): array
    {
        $mockDriver = $this->getMockBuilder(MysqliDriver::class)->onlyMethods(['escapeField'])->getMock();
        $mockDriver->method('escapeField')->willReturnCallback(fn ($field) => (string) $field);

        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['primary'])->getMock();
        $mockConnectionPool->method('primary')->willReturn($mockDriver);

        $mockConnection = new Connection($mockConnectionPool, 'main', 'db');

        $mockPreparedQuery = $this->getMockBuilder(PreparedQuery::class)
            ->setConstructorArgs(['', $mockConnection, $services->collectionFactory()])
            ->onlyMethods(['setParams'])
            ->getMock();
        $mockPreparedQuery->method('setParams')->willReturnCallback(
            function ($params) use (&$outerParams, $mockPreparedQuery) {
                $outerParams = $params;
                return $mockPreparedQuery;
            }
        );

        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['getPrepared'])->getMock();
        $mockQueryFactory->method('getPrepared')->willReturnCallback(
            function ($sql) use (&$outerSql, $mockPreparedQuery) {
                $outerSql = $sql;
                return $mockPreparedQuery;
            }
        );

        return [$mockConnection, $mockQueryFactory];
    }

    /**
     * Spy standing for the "mock\tests\fixtures\model\Bouh" class atoum generated: it records the calls to the setters
     * while keeping their real implementation. Metadata::createEntity() instantiates it by its class name.
     *
     * @return class-string<Bouh>
     */
    private function bouhSpyClass(): string
    {
        $spy = new class () extends Bouh {
            /** @var array<string, list<array<mixed>>> */
            public array $calls = [];

            public function setId($id)
            {
                $this->calls[__FUNCTION__][] = func_get_args();
                parent::setId($id);
            }

            public function setName($name)
            {
                $this->calls[__FUNCTION__][] = func_get_args();
                parent::setName($name);
            }

            public function setRoles(array $roles)
            {
                $this->calls[__FUNCTION__][] = func_get_args();
                parent::setRoles($roles);
            }

            public function setEnabled($enabled)
            {
                $this->calls[__FUNCTION__][] = func_get_args();
                parent::setEnabled($enabled);
            }

            public function setPrice($price)
            {
                $this->calls[__FUNCTION__][] = func_get_args();
                parent::setPrice($price);
            }
        };

        return $spy::class;
    }

    /**
     * atoum's ->call($method)->withArguments(...$arguments)->once(): exactly one call with equal (==) arguments.
     */
    private function assertCalledOnceWith(object $spy, string $method, array $arguments): void
    {
        $this->assertCount(
            1,
            array_filter($spy->calls[$method] ?? [], fn (array $actual) => $actual == $arguments),
            sprintf('Failed asserting that %s() was called once with the expected arguments.', $method)
        );
    }

    public function testGetConnectionWithoutConnectionNameShouldRaiseConfigException()
    {
        $metadata = new Metadata((new TingServices())->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $metadata->setDatabase('myDatabase');

        $this->assertThrows(
            ConfigException::class,
            fn () => $metadata->getConnection($this->createStub(ConnectionPool::class)),
            'Metadata of tests\\fixtures\\model\\Bouh used before setConnectionName() and setDatabase(): '
            . 'no connection to query'
        );
    }

    public function testGetConnectionWithoutDatabaseShouldRaiseConfigException()
    {
        $metadata = new Metadata((new TingServices())->serializerFactory());
        $metadata->setConnectionName('myConnection');

        $this->assertThrows(
            ConfigException::class,
            fn () => $metadata->getConnection($this->createStub(ConnectionPool::class)),
            'Metadata of an unknown entity used before setConnectionName() and setDatabase(): no connection to query'
        );
    }

    public function testGenerateAQueryWithoutTableShouldRaiseConfigException()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $metadata->addField(['primary' => true, 'fieldName' => 'id', 'columnName' => 'boo_id', 'type' => 'int']);

        $this->assertThrows(
            ConfigException::class,
            fn () => $metadata->getAll(
                new Connection($this->createStub(ConnectionPool::class), 'main', 'db'),
                $services->queryFactory(),
                $services->collectionFactory()
            ),
            'Metadata of tests\\fixtures\\model\\Bouh used before setTable(): no table to query'
        );
    }
}
