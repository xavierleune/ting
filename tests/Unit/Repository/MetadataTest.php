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
use CCMBenchmark\Ting\Driver\StatementInterface;
use CCMBenchmark\Ting\Exception;
use CCMBenchmark\Ting\Exceptions\ValueException;
use CCMBenchmark\Ting\Query\PreparedQuery;
use CCMBenchmark\Ting\Query\Query;
use CCMBenchmark\Ting\Query\QueryFactory;
use CCMBenchmark\Ting\Repository\CollectionFactoryInterface;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Serializer\BackedEnum;
use CCMBenchmark\Ting\Serializer\Json;
use CCMBenchmark\Ting\Services;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\RequiresPhp;
use tests\fixtures\ColorsEnum;
use tests\fixtures\FakeDriver\Driver as FakeDriver;
use tests\fixtures\model\Bouh;
use tests\fixtures\model\BouhCustomGetter;
use tests\fixtures\model\HookedPropertiesEntity;
use tests\fixtures\model\PublicPropertiesEntity;

class MetadataTest extends TestCase
{
    public function testGetConnection()
    {
        $mockConnectionPool = $this->createStub(ConnectionPool::class);

        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setDatabase('myDatabase');
        $metadata->setConnectionName('myConnection');
        $this->assertInstanceOf(Connection::class, $metadata->getConnection($mockConnectionPool));
    }

    public function testGetConnectionName()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setDatabase('myDatabase');
        $metadata->setConnectionName('myConnection');
        $this->assertSame('myConnection', $metadata->getConnectionName());
    }

    public function testGetSchema()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setDatabase('myDatabase');
        $metadata->setSchema('schemaName');
        $this->assertSame('schemaName', $metadata->getSchema());
    }

    public function testSetRepositoryShouldRaiseExceptionWhenStartWithSlash()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setRepository('myRepository');
        $this->assertSame('myRepository', $metadata->getRepository());
    }

    public function testSetDatabaseShouldReturnThis()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $this->assertSame($metadata, $metadata->setDatabase('myDatabase'));
    }

    public function testSetConnectionShouldReturnThis()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $this->assertSame($metadata, $metadata->setConnectionName('main'));
    }

    public function testSetEntityShouldRaiseExceptionWhenStartWithSlash()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $this->assertSame(
            $metadata,
            $metadata->addField(['columnName' => 'BO_BOUH', 'fieldName' => 'bouh', 'type' => 'string'])
        );
    }

    public function testGetFields()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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

    public function testIfTableKnownShouldCallCallbackAndReturnTrue()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setTable('Bouh');
        $metadata->addField(['fieldName' => 'Bouh', 'columnName' => 'boo_bouh', 'type' => 'string']);
        $this->assertTrue($metadata->hasColumn('boo_bouh'));
    }

    public function testHasColumnShouldReturnFalse()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setTable('Bouh');
        $metadata->addField(['fieldName' => 'Bouh', 'columnName' => 'BOO_bouh', 'type' => 'string']);
        $this->assertFalse($metadata->hasColumn('boo_no'));
    }

    public function testCreateEntityShouldReturnObject()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setEntity(Bouh::class);
        $bouh = $metadata->createEntity();
        $this->assertInstanceOf(Bouh::class, $bouh);
    }

    public function testSetEntityPropertyWithDefaultSetter()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));

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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));

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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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

        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $this->assertThrows(
            Exception::class,
            function () use ($metadata, $mockConnection, $services): void {
                $metadata->getByPrimaries(
                    $mockConnection,
                    $services->get('QueryFactory'),
                    $services->get('CollectionFactory'),
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

        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setEntity(Bouh::class);
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'boo_id',
            'type'       => 'int'
        ]);
        $this->assertInstanceOf(
            Query::class,
            $metadata->getByPrimaries(
                $mockConnection,
                $services->get('QueryFactory'),
                $services->get('CollectionFactory'),
                ['id' => 1]
            )
        );
        $this->assertInstanceOf(
            Query::class,
            $metadata->getByPrimaries(
                $mockConnection,
                $services->get('QueryFactory'),
                $services->get('CollectionFactory'),
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

        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $this->assertInstanceOf(
            Query::class,
            $metadata->getOneByCriteria(
                $mockConnection,
                $services->get('QueryFactory'),
                $services->get('CollectionFactory'),
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

        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $this->assertThrows(
            Exception::class,
            function () use ($metadata, $mockConnection, $services): void {
                $metadata->getOneByCriteria(
                    $mockConnection,
                    $services->get('QueryFactory'),
                    $services->get('CollectionFactory'),
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

        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setEntity(Bouh::class);
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'id',
            'columnName' => 'boo_id',
            'type'       => 'int'
        ]);
        $this->assertInstanceOf(
            Query::class,
            $metadata->getAll(
                $mockConnection,
                $services->get('QueryFactory'),
                $services->get('CollectionFactory')
            )
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGetByCriteriaShouldReturnAQuery()
    {
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['replica'])->getMock();
        $mockConnectionPool->method('replica')->willReturn(new FakeDriver());
        $mockConnection = new Connection($mockConnectionPool, 'main', 'db');

        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $this->assertInstanceOf(
            Query::class,
            $metadata->getByCriteria(
                ['name' => 'Xavier'],
                $mockConnection,
                $services->get('QueryFactory'),
                $services->get('CollectionFactory')
            )
        );
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
            ->with('INSERT INTO bouh (boo_id, boo_name) VALUES (:boo_id, :boo_name)')
            ->willReturn($mockStatement);
        // atoum first returned a FakeDriver, then replaced it with $mockDriver before any call
        $mockConnectionPool->method('primary')->willReturn($mockDriver);
        $mockStatement
            ->expects($this->once())
            ->method('execute')
            ->with($this->identicalTo(['boo_id' => null, 'boo_name' => 'Xavier']));

        $entity = new Bouh();
        $entity->setName('Xavier');

        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $query = $metadata->generateQueryForInsert($mockConnection, $services->get('QueryFactory'), $entity);
        $this->assertInstanceOf(PreparedQuery::class, $query);
        $query->execute();
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGenerateQueryForInsertShouldSerializeArray()
    {
        $services = new Services();

        [$mockConnection, $mockQueryFactory] = $this->createInsertMocks($services, $outerParams);

        $entity = new Bouh();
        $entity->setRoles(['USER', 'ADMIN']);

        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setEntity(Bouh::class);
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'roles',
            'columnName' => 'boo_roles',
            'type'       => 'string',
            'serializer' => Json::class
        ]);
        $query = $metadata->generateQueryForInsert($mockConnection, $mockQueryFactory, $entity);
        $this->assertSame(json_encode(['USER', 'ADMIN']), $outerParams['boo_roles']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGenerateQueryForInsertShouldSkipUnitializedFields()
    {
        $services = new Services();

        [$mockConnection, $mockQueryFactory] = $this->createInsertMocks($services, $outerParams);

        $entity = new PublicPropertiesEntity();

        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $query = $metadata->generateQueryForInsert($mockConnection, $mockQueryFactory, $entity);
        $this->assertSame(
            ['property_with_default_value' => 'default', 'property_with_getter' => 'with getter'],
            $outerParams
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGenerateQueryForInsertShouldSerializeWithOptions()
    {
        $services = new Services();

        [$mockConnection, $mockQueryFactory] = $this->createInsertMocks($services, $outerParams);

        $entity = new Bouh();
        $entity->setRoles(['USER', '"BOUH"']);

        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $query = $metadata->generateQueryForInsert($mockConnection, $mockQueryFactory, $entity);
        $this->assertSame(json_encode(['USER', '"BOUH"'], JSON_HEX_QUOT), $outerParams['boo_roles']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGenerateQueryForInsertShouldNotUseAutoIncrementColumn()
    {
        $services = new Services();

        [$mockConnection, $mockQueryFactory] = $this->createInsertMocks($services, $outerParams, $outerSql);

        $entity = new Bouh();
        $entity->setRoles(['USER', 'ADMIN']);

        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $query = $metadata->generateQueryForInsert($mockConnection, $mockQueryFactory, $entity);
        $this->assertSame('INSERT INTO  (boo_roles) VALUES (:boo_roles)', $outerSql);
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
            ->with('UPDATE bouh SET firstname = :firstname WHERE boo_id = :#boo_id AND firstname = :#firstname')
            ->willReturn($mockStatement);
        $mockConnectionPool->method('primary')->willReturn($mockDriver);
        $mockConnection = new Connection($mockConnectionPool, 'main', 'db');
        $mockStatement
            ->expects($this->once())
            ->method('execute')
            ->with(['#boo_id' => 20, '#firstname' => 'Sylvain', 'firstname' => 'Xavier']);

        $entity = new Bouh();
        $entity->setId(20);
        $entity->setName('Xavier');

        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
            $services->get('QueryFactory'),
            $entity,
            ['name' => 'Sylvain']
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
            ->with('DELETE FROM bouh WHERE boo_id = :#boo_id')
            ->willReturn($mockStatement);
        $mockConnectionPool->method('primary')->willReturn($mockDriver);
        $mockConnection = new Connection($mockConnectionPool, 'main', 'db');
        $mockStatement
            ->expects($this->once())
            ->method('execute')
            ->with($this->identicalTo(['#boo_id' => 1]));

        $entity = new Bouh();
        $entity->setName('Xavier');

        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
            $services->get('QueryFactory'),
            ['id' => 1],
            $entity
        );
        $this->assertInstanceOf(PreparedQuery::class, $query);
        $query->execute();
    }

    public function testSetEntityPropertyWithDefinedSetter()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $services = new Services();

        [$mockConnection, $mockQueryFactory] = $this->createInsertMocks($services, $outerParams);

        $entity = new BouhCustomGetter();
        $entity->setName('Nicolas');

        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setEntity(BouhCustomGetter::class);
        $metadata->addField([
            'primary'    => true,
            'fieldName'  => 'name',
            'columnName' => 'boo_name',
            'type'       => 'string',
            'getter'     => 'nameIs'
        ]);
        $query = $metadata->generateQueryForInsert($mockConnection, $mockQueryFactory, $entity);
        $this->assertSame('Nicolas', $outerParams['boo_name']);
    }

    public function testGetGetterAndGetSetterWithDefaultValue()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setEntity(Bouh::class);
        $metadata->addField([
            'fieldName'  => 'name',
            'columnName' => 'boo_name',
            'type'       => 'string',
        ]);

        $this->assertSame('getname', $metadata->getGetter('name'));
        $this->assertSame('setname', $metadata->getSetter('name'));
    }

    public function testGetGetterAndGetSetterWithCustomValues()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setEntity(Bouh::class);
        $getter = uniqid('getter');
        $setter = uniqid('setter');
        $metadata->addField([
            'fieldName'  => 'name',
            'columnName' => 'boo_name',
            'type'       => 'string',
            'getter'     => $getter,
            'setter'     => $setter
        ]);

        $this->assertSame($getter, $metadata->getGetter('name'));
        $this->assertSame($setter, $metadata->getSetter('name'));
    }

    #[RequiresPhp('>= 8.4.0')]
    public function testSetEntityPropertyShouldByPassPropertyHook()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
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
    private function createInsertMocks(Services $services, &$outerParams, &$outerSql = null): array
    {
        $mockDriver = $this->getMockBuilder(MysqliDriver::class)->onlyMethods(['escapeField'])->getMock();
        $mockDriver->method('escapeField')->willReturnCallback(fn ($field) => $field);

        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['primary'])->getMock();
        $mockConnectionPool->method('primary')->willReturn($mockDriver);

        $mockConnection = new Connection($mockConnectionPool, 'main', 'db');

        $mockPreparedQuery = $this->getMockBuilder(PreparedQuery::class)
            ->setConstructorArgs(['', $mockConnection, $services->get('CollectionFactory')])
            ->onlyMethods(['setParams'])
            ->getMock();
        $mockPreparedQuery->method('setParams')->willReturnCallback(function ($params) use (&$outerParams): void {
            $outerParams = $params;
        });

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

    public function testGetByPrimariesWithPropertyKeysShouldUseTheColumns()
    {
        $deprecations = $this->collectDeprecations(function () use (&$sql, &$params): void {
            [$sql, $params] = $this->captureReadQuery(
                $this->createReadMetadata(),
                fn (Metadata $metadata, ...$services) => $metadata->getByPrimaries(...$services, ...[['id' => 1, 'secondId' => 2]])
            );
        });

        $this->assertSame([], $deprecations);
        $this->assertSame(' WHERE boo_id = :#boo_id AND boo_second_id = :#boo_second_id LIMIT 1', strstr($sql, ' WHERE '));
        $this->assertSame(['#boo_id' => 1, '#boo_second_id' => 2], $params);
    }

    public function testGetByPrimariesWithColumnKeysShouldStillWorkAndTriggerADeprecation()
    {
        $deprecations = $this->collectDeprecations(function () use (&$sql, &$params): void {
            [$sql, $params] = $this->captureReadQuery(
                $this->createReadMetadata(),
                fn (Metadata $metadata, ...$services) => $metadata->getByPrimaries(...$services, ...[['boo_id' => 1, 'boo_second_id' => 2]])
            );
        });

        $this->assertSame(
            [
                'Using the column name "boo_id" in Repository::get() is deprecated since Ting 3.15, use the property name "id" instead.',
                'Using the column name "boo_second_id" in Repository::get() is deprecated since Ting 3.15, use the property name "secondId" instead.',
            ],
            $deprecations
        );
        $this->assertSame(' WHERE boo_id = :#boo_id AND boo_second_id = :#boo_second_id LIMIT 1', strstr($sql, ' WHERE '));
        $this->assertSame(['#boo_id' => 1, '#boo_second_id' => 2], $params);
    }

    public function testGetByPrimariesWithAScalarShouldTriggerNoDeprecation()
    {
        $metadata = new Metadata((new Services())->get('SerializerFactory'));
        $metadata->setEntity(Bouh::class);
        $metadata->setTable('bouh');
        $metadata->addField(['primary' => true, 'fieldName' => 'id', 'columnName' => 'boo_id', 'type' => 'int']);

        $deprecations = $this->collectDeprecations(function () use ($metadata, &$sql, &$params): void {
            [$sql, $params] = $this->captureReadQuery(
                $metadata,
                fn (Metadata $metadata, ...$services) => $metadata->getByPrimaries(...$services, ...[3])
            );
        });

        $this->assertSame([], $deprecations);
        $this->assertSame(' WHERE boo_id = :#boo_id LIMIT 1', strstr($sql, ' WHERE '));
        $this->assertSame(['#boo_id' => 3], $params);
    }

    public function testGetByPrimariesWithAnEmptyArrayShouldThrowAValueException()
    {
        $this->assertThrows(
            ValueException::class,
            fn () => $this->captureReadQuery(
                $this->createReadMetadata(),
                fn (Metadata $metadata, ...$services) => $metadata->getByPrimaries(...$services, ...[['id' => [], 'secondId' => 2]])
            ),
            'Empty array for property "id" in Repository::get(): nothing can match'
        );
    }

    public function testGetByCriteriaWithOrderShouldUseTheColumnOfTheProperty()
    {
        $deprecations = $this->collectDeprecations(function () use (&$sql): void {
            [$sql] = $this->captureReadQuery(
                $this->createReadMetadata(),
                fn (Metadata $metadata, ...$services) => $metadata->getByCriteriaWithOrderAndLimit(
                    ['name' => 'Xavier'],
                    ['date' => 'DESC', 'id' => 'asc'],
                    0,
                    ...$services
                )
            );
        });

        $this->assertSame([], $deprecations);
        $this->assertSame(' WHERE boo_name = :#boo_name ORDER BY boo_date DESC,boo_id ASC', strstr($sql, ' WHERE '));
    }

    public function testGetByCriteriaWithColumnsInOrderShouldStillWorkAndTriggerADeprecation()
    {
        $deprecations = $this->collectDeprecations(function () use (&$sql): void {
            [$sql] = $this->captureReadQuery(
                $this->createReadMetadata(),
                fn (Metadata $metadata, ...$services) => $metadata->getByCriteriaWithOrderAndLimit(
                    ['name' => 'Xavier'],
                    ['boo_date' => 'DESC'],
                    0,
                    ...$services
                )
            );
        });

        $this->assertSame(
            ['Using the column name "boo_date" in the order of Repository::getBy() is deprecated since Ting 3.15, use the property name "date" instead.'],
            $deprecations
        );
        $this->assertSame(' WHERE boo_name = :#boo_name ORDER BY boo_date DESC', strstr($sql, ' WHERE '));
    }

    public function testGetByCriteriaWithAnInvalidOrderDirectionShouldIgnoreItAndTriggerADeprecation()
    {
        $deprecations = $this->collectDeprecations(function () use (&$sql): void {
            [$sql] = $this->captureReadQuery(
                $this->createReadMetadata(),
                fn (Metadata $metadata, ...$services) => $metadata->getByCriteriaWithOrderAndLimit(
                    ['name' => 'Xavier'],
                    ['date' => 'DESC', 'id' => 'sideways'],
                    0,
                    ...$services
                )
            );
        });

        $this->assertSame(
            ['Using the direction "sideways" for "id" in the order of Repository::getBy() is deprecated since Ting 3.15 and it is ignored: it will throw a ValueException in 4.0, use "ASC" or "DESC".'],
            $deprecations
        );
        $this->assertSame(' WHERE boo_name = :#boo_name ORDER BY boo_date DESC', strstr($sql, ' WHERE '));
    }

    public function testGetByCriteriaWithColumnKeysShouldStillWorkAndTriggerADeprecation()
    {
        $deprecations = $this->collectDeprecations(function () use (&$sql, &$params): void {
            [$sql, $params] = $this->captureReadQuery(
                $this->createReadMetadata(),
                fn (Metadata $metadata, ...$services) => $metadata->getOneByCriteria(...$services, ...[['boo_name' => 'Xavier']])
            );
        });

        $this->assertSame(
            ['Using the column name "boo_name" in the criteria of Repository::getOneBy() is deprecated since Ting 3.15, use the property name "name" instead.'],
            $deprecations
        );
        $this->assertSame(' WHERE boo_name = :#boo_name LIMIT 1', strstr($sql, ' WHERE '));
        $this->assertSame(['#boo_name' => 'Xavier'], $params);
    }

    public function testAKeyBeingAPropertyAndTheColumnOfAnotherFieldShouldBeThePropertyForCriteriaAndOrder()
    {
        $metadata = new Metadata((new Services())->get('SerializerFactory'));
        $metadata->setEntity(Bouh::class);
        $metadata->setTable('bouh');
        $metadata->addField(['primary' => true, 'fieldName' => 'id', 'columnName' => 'code', 'type' => 'int']);
        $metadata->addField(['fieldName' => 'code', 'columnName' => 'boo_code', 'type' => 'string']);

        $deprecations = $this->collectDeprecations(function () use ($metadata, &$sql, &$params): void {
            [$sql, $params] = $this->captureReadQuery(
                $metadata,
                fn (Metadata $metadata, ...$services) => $metadata->getByCriteriaWithOrderAndLimit(
                    ['code' => 'A'],
                    ['code' => 'ASC'],
                    0,
                    ...$services
                )
            );
        });

        $this->assertSame([], $deprecations);
        $this->assertSame(' WHERE boo_code = :#boo_code ORDER BY boo_code ASC', strstr($sql, ' WHERE '));
        $this->assertSame(['#boo_code' => 'A'], $params);
    }

    public function testGetByCriteriaWithAnUnknownKeyShouldStillThrowAValueException()
    {
        $this->assertThrows(
            ValueException::class,
            fn () => $this->captureReadQuery(
                $this->createReadMetadata(),
                fn (Metadata $metadata, ...$services) => $metadata->getByCriteria(['unknown' => 1], ...$services)
            ),
            'Undefined property unknown in your criteria'
        );
    }

    public function testCriteriaObjectsShouldBeSerializedByTheirField()
    {
        $deprecations = $this->collectDeprecations(function () use (&$sql, &$params): void {
            [$sql, $params] = $this->captureReadQuery(
                $this->createReadMetadata(),
                fn (Metadata $metadata, ...$services) => $metadata->getByCriteria(
                    ['color' => ColorsEnum::RED, 'date' => new \DateTime('2026-10-05 12:34:56')],
                    ...$services
                )
            );
        });

        $this->assertSame([], $deprecations);
        $this->assertSame(' WHERE boo_color = :#boo_color AND boo_date = :#boo_date', strstr($sql, ' WHERE '));
        $this->assertSame(['#boo_color' => 'red', '#boo_date' => '2026-10-05 12:34:56'], $params);
    }

    public function testCriteriaArrayOfObjectsShouldBeAnInListOfSerializedValues()
    {
        [$sql, $params] = $this->captureReadQuery(
            $this->createReadMetadata(),
            fn (Metadata $metadata, ...$services) => $metadata->getByCriteria(
                ['color' => [ColorsEnum::RED, ColorsEnum::BLUE]],
                ...$services
            )
        );

        $this->assertSame(' WHERE boo_color IN (:boo_color__1,:boo_color__2)', strstr($sql, ' WHERE '));
        $this->assertSame(['boo_color__1' => 'red', 'boo_color__2' => 'blue'], $params);
    }

    public function testCriteriaArrayForASerializerOfArraysShouldBeSerializedAsAWhole()
    {
        [$sql, $params] = $this->captureReadQuery(
            $this->createReadMetadata(),
            fn (Metadata $metadata, ...$services) => $metadata->getByCriteria(['tags' => ['a', 'b']], ...$services)
        );

        $this->assertSame(' WHERE boo_tags = :#boo_tags', strstr($sql, ' WHERE '));
        $this->assertSame(['#boo_tags' => '["a","b"]'], $params);
    }

    public function testCriteriaSerializerOptionsShouldBeUsed()
    {
        $metadata = $this->createReadMetadata();
        $metadata->addField([
            'fieldName' => 'url',
            'columnName' => 'boo_url',
            'type' => 'json',
            'serializer_options' => ['serialize' => ['options' => JSON_UNESCAPED_SLASHES]],
        ]);

        [, $params] = $this->captureReadQuery(
            $metadata,
            fn (Metadata $metadata, ...$services) => $metadata->getByCriteria(['url' => ['a/b']], ...$services)
        );

        $this->assertSame(['#boo_url' => '["a/b"]'], $params);
    }

    public function testCriteriaWithAnEmptyArrayShouldThrowAValueException()
    {
        $this->assertThrows(
            ValueException::class,
            fn () => $this->captureReadQuery(
                $this->createReadMetadata(),
                fn (Metadata $metadata, ...$services) => $metadata->getByCriteriaWithOrderAndLimit(['id' => []], [], 0, ...$services)
            ),
            'Empty array for property "id" in the criteria of Repository::getBy(): nothing can match'
        );
    }

    public function testCriteriaWithAnObjectWithoutSerializerShouldThrowAValueException()
    {
        $this->assertThrows(
            ValueException::class,
            fn () => $this->captureReadQuery(
                $this->createReadMetadata(),
                fn (Metadata $metadata, ...$services) => $metadata->getOneByCriteria(...$services, ...[['name' => [new \stdClass()]]])
            ),
            'Cannot use an object of class "stdClass" for property "name" in the criteria of Repository::getOneBy(): its field has no serializer'
        );
    }

    public function testCriteriaWithAStringableObjectWithoutSerializerShouldBeSentAsIs()
    {
        $stringable = new class () {
            public function __toString(): string
            {
                return 'Xavier';
            }
        };

        [, $params] = $this->captureReadQuery(
            $this->createReadMetadata(),
            fn (Metadata $metadata, ...$services) => $metadata->getByCriteria(['name' => $stringable], ...$services)
        );

        $this->assertSame(['#boo_name' => $stringable], $params);
    }

    public function testCriteriaScalarsAndNullShouldBeUnchanged()
    {
        [$sql, $params] = $this->captureReadQuery(
            $this->createReadMetadata(),
            fn (Metadata $metadata, ...$services) => $metadata->getByCriteria(
                ['id' => [1, 2], 'name' => 'Xavier', 'color' => 'red', 'date' => '2026-10-05', 'tags' => '[]', 'secondId' => null],
                ...$services
            )
        );

        $this->assertSame(
            ' WHERE boo_id IN (:boo_id__1,:boo_id__2) AND boo_name = :#boo_name AND boo_color = :#boo_color'
            . ' AND boo_date = :#boo_date AND boo_tags = :#boo_tags AND boo_second_id IS NULL',
            strstr($sql, ' WHERE ')
        );
        $this->assertSame(
            [
                'boo_id__1' => 1,
                'boo_id__2' => 2,
                '#boo_name' => 'Xavier',
                '#boo_color' => 'red',
                '#boo_date' => '2026-10-05',
                '#boo_tags' => '[]',
            ],
            $params
        );
    }

    /**
     * A composite primary key and fields with and without serializers.
     */
    private function createReadMetadata(): Metadata
    {
        $metadata = new Metadata((new Services())->get('SerializerFactory'));
        $metadata->setEntity(Bouh::class);
        $metadata->setTable('bouh');
        $metadata->addField(['primary' => true, 'fieldName' => 'id', 'columnName' => 'boo_id', 'type' => 'int']);
        $metadata->addField(['primary' => true, 'fieldName' => 'secondId', 'columnName' => 'boo_second_id', 'type' => 'int']);
        $metadata->addField(['fieldName' => 'name', 'columnName' => 'boo_name', 'type' => 'string']);
        $metadata->addField([
            'fieldName' => 'color',
            'columnName' => 'boo_color',
            'type' => 'string',
            'serializer' => BackedEnum::class,
            'serializer_options' => ['unserialize' => ['enum' => ColorsEnum::class]],
        ]);
        $metadata->addField(['fieldName' => 'date', 'columnName' => 'boo_date', 'type' => 'datetime']);
        $metadata->addField(['fieldName' => 'tags', 'columnName' => 'boo_tags', 'type' => 'json']);

        return $metadata;
    }

    /**
     * Calls $read($metadata, $connection, $queryFactory, $collectionFactory) and returns the SQL and the params
     * of the query it builds. The driver keeps the field names as is.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function captureReadQuery(Metadata $metadata, callable $read): array
    {
        $connectionPool = $this->createStub(ConnectionPool::class);
        $connectionPool->method('replica')->willReturn(new FakeDriver());
        $connection = new Connection($connectionPool, 'main', 'db');

        $queryFactory = new class () extends QueryFactory {
            public ?string $sql = null;

            public function get($sql, Connection $connection, ?CollectionFactoryInterface $collectionFactory = null)
            {
                $this->sql = $sql;
                return parent::get($sql, $connection, $collectionFactory);
            }
        };

        $query = $read($metadata, $connection, $queryFactory, (new Services())->get('CollectionFactory'));
        $params = (fn () => $this->params)->call($query);

        return [$queryFactory->sql, $params];
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
}
