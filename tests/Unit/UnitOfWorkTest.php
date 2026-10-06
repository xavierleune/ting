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

namespace CCMBenchmark\Ting\Tests\Unit;

use CCMBenchmark\Ting\Connection;
use CCMBenchmark\Ting\ConnectionPool;
use CCMBenchmark\Ting\Driver\Mysqli\Driver;
use CCMBenchmark\Ting\Driver\Mysqli\Result;
use CCMBenchmark\Ting\Driver\QueryException;
use CCMBenchmark\Ting\Exception;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Query\PreparedQuery;
use CCMBenchmark\Ting\Query\QueryFactory;
use CCMBenchmark\Ting\Repository\Hydrator;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Tests\Support\TingServices;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use CCMBenchmark\Ting\UnitOfWork;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use tests\fixtures\FakeDriver\MysqliResult;
use tests\fixtures\model\Bouh;
use tests\fixtures\model\BouhRepository;
use tests\fixtures\model\Document;
use tests\fixtures\model\DocumentRepository;
use tests\fixtures\model\Event;
use tests\fixtures\model\EventRepository;
use tests\fixtures\model\PrimaryOnMultiField;
use tests\fixtures\model\Slot;
use tests\fixtures\model\SlotRepository;

class UnitOfWorkTest extends TestCase
{
    protected ?TingServices $services = null;

    protected function setUp(): void
    {
        $connectionPool = new ConnectionPool();
        $connectionPool->setConfig(
            [
                'main' => [
                    'namespace' => '\tests\fixtures\FakeDriver',
                    'primary'   => [
                        'host'      => 'localhost.test',
                        'user'      => 'test',
                        'password'  => 'test',
                        'port'      => 3306
                    ]
                ]
            ]
        );

        $this->services = new TingServices($connectionPool);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testManageShouldAddPropertyListener()
    {
        $mockEntity = $this->getMockBuilder(Bouh::class)
            ->onlyMethods(['addPropertyListener'])
            ->getMock();
        $mockEntity->method('addPropertyListener')->willReturnCallback(
            function ($unitOfWork) use (&$outerUnitOfWork): void {
                $outerUnitOfWork = $unitOfWork;
            }
        );

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $unitOfWork->manage($mockEntity);
        $this->assertSame($unitOfWork, $outerUnitOfWork);
        $this->assertTrue($unitOfWork->isManaged($mockEntity));
    }

    public function testIsManagedShouldReturnFalse()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $this->assertFalse($unitOfWork->isManaged($mockEntity));
    }

    public function testSave()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $unitOfWork->pushSave($mockEntity);
        $this->assertTrue($unitOfWork->shouldBePersisted($mockEntity));
        $this->assertTrue($unitOfWork->isNew($mockEntity));
    }

    public function testIsPersistedShouldReturnFalse()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $this->assertFalse($unitOfWork->shouldBePersisted($mockEntity));
    }

    public function testPersistManagedEntityShouldNotMarkedNew()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $unitOfWork->manage($mockEntity);
        $unitOfWork->pushSave($mockEntity);
        $this->assertTrue($unitOfWork->shouldBePersisted($mockEntity));
        $this->assertFalse($unitOfWork->isNew($mockEntity));
    }

    public function testIsPropertyChangedWithUUIDShouldReturnFalse()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $unitOfWork->propertyChanged($mockEntity, 'firstname', 'Sylvain', 'Sylvain');
        $this->assertFalse($unitOfWork->isPropertyChanged($mockEntity, 'firstname'));
    }

    public function testPropertyChangedShouldDoNothing()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $unitOfWork->propertyChanged($mockEntity, 'firstname', 'Sylvain', 'Sylvain');
        $this->assertFalse($unitOfWork->isPropertyChanged($mockEntity, 'firstname'));
    }

    public function testPropertyChangedShouldMarkedChanged()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $unitOfWork->manage($mockEntity);
        $unitOfWork->propertyChanged($mockEntity, 'firstname', 'Sylvain', 'Sylvain 2');
        $this->assertTrue($unitOfWork->isPropertyChanged($mockEntity, 'firstname'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testManageShouldAddItsListenerOnce()
    {
        $entity = new Bouh();
        $entity->setName('Xavier');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);

        $unitOfWork->manage($entity);
        $unitOfWork->manage($entity);
        for ($i = 0; $i < 3; $i++) {
            $unitOfWork->pushDelete($entity)->process();
            $unitOfWork->pushSave($entity)->process();
        }
        $unitOfWork->detachAll();
        $unitOfWork->manage($entity);

        $this->assertCount(1, (fn (): array => $this->listeners)->call($entity));
    }

    public function testChangesOfAnEntityShouldNotBeRecordedOnceDetached()
    {
        $entity = new Bouh();
        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $unitOfWork->manage($entity);
        $unitOfWork->detach($entity);

        $entity->setName('changed while detached');

        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'name'));

        // Managed again: only the changes made from now on are written
        $unitOfWork->manage($entity);
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'name'));
        $entity->setFirstname('changed while managed');
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'firstname'));
    }

    public function testDetach()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $unitOfWork->pushSave($mockEntity);
        $this->assertTrue($unitOfWork->shouldBePersisted($mockEntity));
        $unitOfWork->detach($mockEntity);
        $this->assertFalse($unitOfWork->shouldBePersisted($mockEntity));
    }

    public function testDetachAManagedEntityNotQueuedShouldStopManagingIt()
    {
        $entity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $unitOfWork->manage($entity);
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));

        $unitOfWork->detach($entity);

        $this->assertFalse($unitOfWork->isManaged($entity));
    }

    public function testDetachAll()
    {
        $entity1 = new Bouh();
        $entity2 = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $unitOfWork->pushSave($entity1);
        $unitOfWork->pushSave($entity2);
        $unitOfWork->detachAll();
        $this->assertFalse($unitOfWork->shouldBePersisted($entity1));
        $this->assertFalse($unitOfWork->shouldBePersisted($entity2));
    }

    public function testShouldBeRemovedWithUUIDShouldReturnFalse()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $this->assertFalse($unitOfWork->shouldBeRemoved($mockEntity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDeletingANeverPersistedEntityShouldRunNoQuery()
    {
        $entity = new Bouh();
        $entity->setName('never saved');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);

        $unitOfWork->pushDelete($entity);
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
        $unitOfWork->process();
        $this->assertSame([], $queries);
        $this->assertFalse($unitOfWork->isManaged($entity));

        // Still a new entity: saving it inserts it
        $unitOfWork->pushSave($entity)->process();
        $this->assertSame(['INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDeletingANewEntityQueuedForInsertShouldUnqueueItWithoutQuery()
    {
        $entity = new Bouh();
        $entity->setName('never saved');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);

        $unitOfWork->pushSave($entity);
        $unitOfWork->pushDelete($entity);
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
        $unitOfWork->process();

        $this->assertSame([], $queries);
        $this->assertFalse($unitOfWork->isManaged($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDeletingAnEntityNotManagedWithItsPrimaryKeyShouldDeleteItsRow()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);

        $unitOfWork->pushDelete($entity)->process();

        $this->assertSame(['DELETE'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(3, $params[0]['w1_boo_id']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAFailedDeleteOfAnEntityNotManagedShouldLeaveItNotManaged()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);

        // A foreign key, say
        $failOn = 'DELETE';
        $this->assertThrows(QueryException::class, function () use ($unitOfWork, $entity): void {
            $unitOfWork->pushDelete($entity)->process();
        });
        $this->assertFalse($unitOfWork->isManaged($entity));

        $failOn = null;
        $entity->setName('name');
        // Its row still exists: it is updated
        $unitOfWork->pushSave($entity)->process();
        $this->assertSame(['UPDATE'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPushSaveShouldReplaceThePushDeleteOfAnEntityNotManaged()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);

        $unitOfWork->pushDelete($entity)->pushSave($entity);
        $this->assertFalse($unitOfWork->isManaged($entity));
        $this->assertFalse($unitOfWork->isNew($entity));
        $unitOfWork->process();

        $this->assertSame(['UPDATE'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertTrue($unitOfWork->isManaged($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAnEntityAfterItsDeleteShouldInsertItAgain()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $entity->setName('name');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $unitOfWork->manage($entity);

        $unitOfWork->pushDelete($entity)->process();
        // Its row is gone, its id is still set: it is not an existing row
        $unitOfWork->pushSave($entity);
        $this->assertTrue($unitOfWork->isNew($entity));
        $unitOfWork->process();

        $this->assertSame(['DELETE', 'INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(1, $entity->getId());
        $this->assertTrue($unitOfWork->isManaged($entity));

        // Inserted again: an existing row from now on
        $unitOfWork->detach($entity);
        $unitOfWork->pushSave($entity)->process();
        $this->assertSame('UPDATE', strtok($queries[2], ' '));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAFailedDeleteOfAManagedEntityShouldKeepItManaged()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $unitOfWork->manage($entity);

        $failOn = 'DELETE';
        $this->assertThrows(QueryException::class, function () use ($unitOfWork, $entity): void {
            $unitOfWork->pushDelete($entity)->process();
        });
        $this->assertTrue($unitOfWork->isManaged($entity));

        $failOn = null;
        $entity->setName('name');
        $unitOfWork->pushSave($entity)->process();
        $this->assertSame(['UPDATE'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
    }

    public function testRemove()
    {
        $mockEntity = new Bouh();
        $mockEntity->setId(3);

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $unitOfWork->pushDelete($mockEntity);
        $this->assertTrue($unitOfWork->shouldBeRemoved($mockEntity));
    }

    public function testRemoveShouldReturnFalse()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $this->assertFalse($unitOfWork->shouldBeRemoved($mockEntity));
    }

    public function testIsNewWithoutUUIDShouldReturnFalse()
    {
        $mockEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $this->assertFalse($unitOfWork->isNew($mockEntity));
    }

    public function testResetShouldForgetEveryEntity()
    {
        $managedEntity = new Bouh();
        $newEntity     = new Bouh();
        $deletedEntity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $unitOfWork->manage($managedEntity);
        $unitOfWork->propertyChanged($managedEntity, 'name', 'Sylvain', 'Xavier');
        $unitOfWork->pushSave($managedEntity);
        $unitOfWork->pushSave($newEntity);
        $unitOfWork->pushDelete($deletedEntity);

        $unitOfWork->reset();

        $this->assertFalse($unitOfWork->isManaged($managedEntity));
        $this->assertFalse($unitOfWork->isPropertyChanged($managedEntity, 'name'));
        $this->assertFalse($unitOfWork->shouldBePersisted($managedEntity));
        $this->assertFalse($unitOfWork->shouldBePersisted($newEntity));
        $this->assertFalse($unitOfWork->isNew($newEntity));
        $this->assertFalse($unitOfWork->shouldBeRemoved($deletedEntity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testIsNewAfterProcessShouldReturnFalse()
    {
        $entity = new Bouh();
        $mockMetadataRepository = new MetadataRepository($this->services->serializerFactory());

        $mockMetadataRepository->addMetadata(
            'tests\fixtures\model\BouhRepository',
            BouhRepository::initMetadata($this->services->serializerFactory())
        );

        [$mockConnectionPool, $mockQueryFactory] = $this->createProcessMocks(true);

        $unitOfWork = new UnitOfWork(
            $mockConnectionPool,
            $mockMetadataRepository,
            $mockQueryFactory
        );
        $unitOfWork->pushSave($entity);
        $this->assertTrue($unitOfWork->shouldBePersisted($entity));
        $this->assertTrue($unitOfWork->isNew($entity));
        $this->assertNull($unitOfWork->process());
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
        $this->assertFalse($unitOfWork->isNew($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testShouldBePersistedAfterProcessShouldReturnFalse()
    {
        $entity = new Bouh();
        $mockMetadataRepository = new MetadataRepository($this->services->serializerFactory());

        $mockMetadataRepository->addMetadata(
            'tests\fixtures\model\BouhRepository',
            BouhRepository::initMetadata($this->services->serializerFactory())
        );

        [$mockConnectionPool, $mockQueryFactory] = $this->createProcessMocks(true);

        $unitOfWork = new UnitOfWork(
            $mockConnectionPool,
            $mockMetadataRepository,
            $mockQueryFactory
        );
        $unitOfWork->manage($entity);
        $entity->setName('newName');
        $unitOfWork->pushSave($entity);
        $this->assertTrue($unitOfWork->shouldBePersisted($entity));
        $this->assertNull($unitOfWork->process());
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
        $unitOfWork->pushDelete($entity);
        $this->assertTrue($unitOfWork->shouldBePersisted($entity));
        $entity->setId(1);
        $this->assertNull($unitOfWork->process());
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
    }

    public function testProcessAnUnchangedManagedEntityShouldUnqueueIt()
    {
        $entity = new Bouh();

        $unitOfWork = new UnitOfWork(
            $this->services->connectionPool(),
            $this->services->metadataRepository(),
            $this->services->queryFactory()
        );
        $unitOfWork->manage($entity);
        $unitOfWork->pushSave($entity);
        $this->assertTrue($unitOfWork->shouldBePersisted($entity));
        $unitOfWork->process();
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testProcessAManagedEntityWithRevertedChangesShouldUnqueueIt()
    {
        $entity = new Bouh();
        $entity->setName('name');

        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $unitOfWork->manage($entity);
        $entity->setName('newName');
        $entity->setName('name');
        $unitOfWork->pushSave($entity);
        $unitOfWork->process();
        $this->assertSame([], $queries);
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'name'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testTryingToProcessAnEntityWithoutRepositoryShouldRaiseAnException()
    {
        $entity = new Bouh();
        $mockMetadataRepository = new MetadataRepository($this->services->serializerFactory());

        [$mockConnectionPool, $mockQueryFactory] = $this->createProcessMocks(false);

        $unitOfWork = new UnitOfWork(
            $mockConnectionPool,
            $mockMetadataRepository,
            $mockQueryFactory
        );
        $unitOfWork->pushSave($entity);
        $this->assertThrows(Exception::class, function () use ($unitOfWork): void {
            $unitOfWork->process();
        });
        $unitOfWork->pushDelete($entity);
        $this->assertThrows(Exception::class, function () use ($unitOfWork): void {
            $unitOfWork->process();
        });
        $unitOfWork->manage($entity);
        $entity->setName('newName');
        $unitOfWork->pushSave($entity);
        $this->assertThrows(Exception::class, function () use ($unitOfWork): void {
            $unitOfWork->process();
        });
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testProcessShouldUpdateAndDeleteByPrimaryColumns()
    {
        $entity = new Bouh();
        $metadataRepository = new MetadataRepository($this->services->serializerFactory());
        $metadataRepository->addMetadata(
            'tests\fixtures\model\BouhRepository',
            BouhRepository::initMetadata($this->services->serializerFactory())
        );

        [$mockConnectionPool] = $this->createProcessMocks(true);

        // Records the SQL and the parameters of each prepared query, without executing it
        $queries = [];
        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['getPrepared'])->getMock();
        $mockQueryFactory->method('getPrepared')->willReturnCallback(
            function (string $sql, Connection $connection) use (&$queries): PreparedQuery {
                $query = $this->getMockBuilder(PreparedQuery::class)
                    ->setConstructorArgs([$sql, $connection])
                    ->onlyMethods(['prepareExecute', 'execute'])
                    ->getMock();
                $query->method('prepareExecute')->willReturnSelf();
                $query->method('execute')->willReturn(true);
                $queries[] = $query;

                return $query;
            }
        );

        $unitOfWork = new UnitOfWork($mockConnectionPool, $metadataRepository, $mockQueryFactory);
        $entity->setId(3);
        $unitOfWork->manage($entity);
        $entity->setName('newName');
        $unitOfWork->pushSave($entity)->process();
        $unitOfWork->pushDelete($entity)->process();

        $read = fn (PreparedQuery $query): array => (fn () => [$this->sql, $this->params])->call($query);
        $this->assertSame(
            [
                ['UPDATE `T_BOUH_BOO` SET `boo_name` = :v1_boo_name WHERE `boo_id` = :w1_boo_id', ['v1_boo_name' => 'newName', 'w1_boo_id' => 3]],
                ['DELETE FROM `T_BOUH_BOO` WHERE `boo_id` = :w1_boo_id', ['w1_boo_id' => 3]],
            ],
            array_map($read, $queries)
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPushSaveShouldNotManageANewEntityBeforeItIsInserted()
    {
        $entity = new Bouh();
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);

        $unitOfWork->pushSave($entity);
        $this->assertFalse($unitOfWork->isManaged($entity));
        $this->assertTrue($unitOfWork->isNew($entity));

        $unitOfWork->pushSave($entity);
        $this->assertFalse($unitOfWork->isManaged($entity));
        $this->assertTrue($unitOfWork->isNew($entity));

        $unitOfWork->process();
        $this->assertSame(['INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(1, $entity->getId());
        $this->assertTrue($unitOfWork->isManaged($entity));
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAgainANewEntityWhoseInsertFailedShouldInsertIt()
    {
        $entity = new Bouh();
        $entity->setName('name');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);

        $failOn = 'INSERT';
        $this->assertThrows(QueryException::class, function () use ($unitOfWork, $entity): void {
            $unitOfWork->pushSave($entity)->process();
        });
        $this->assertFalse($unitOfWork->isManaged($entity));
        $this->assertNull($entity->getId());

        $failOn = null;
        $unitOfWork->pushSave($entity)->process();
        $this->assertSame(['INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(1, $entity->getId());
        $this->assertTrue($unitOfWork->isManaged($entity));
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnEntityInsertedWhoseIdCannotBeWrittenBackShouldBeManaged()
    {
        $entity = new Bouh();
        $entity->setName('name');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);

        $failOn = 'getInsertedId';
        $this->assertThrows(QueryException::class, function () use ($unitOfWork, $entity): void {
            $unitOfWork->pushSave($entity)->process();
        }, 'Forced failure of getInsertedId()');
        // The row exists: saving the entity again must not insert a second one
        $this->assertTrue($unitOfWork->isManaged($entity));
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));

        $failOn = null;
        $entity->setName('other');
        $unitOfWork->pushSave($entity)->process();
        $this->assertSame(['INSERT', 'UPDATE'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        // Without its id: the UPDATE targets no row, but no duplicate is inserted
        $this->assertSame('UPDATE `T_BOUH_BOO` SET `boo_name` = :v1_boo_name WHERE `boo_id` IS NULL', $queries[1]);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testWritingBackTheIdOfAnInsertedEntityShouldNotBeAChange()
    {
        $entity = new Bouh();
        $entity->setName('name');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);

        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(1, $entity->getId());
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'id'));
        $unitOfWork->pushSave($entity)->process();
        $this->assertSame(['INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingACloneOfAManagedEntityShouldUpdateItsRowWithEveryField()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $entity->setFirstname('first');
        $entity->setName('name');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);
        $unitOfWork->manage($entity);

        $clone = clone $entity;
        $this->assertFalse($unitOfWork->isManaged($clone));
        $clone->setName('copy');
        $unitOfWork->pushSave($clone);
        $this->assertFalse($unitOfWork->isNew($clone));
        $unitOfWork->process();

        $this->assertSame(
            ['UPDATE `T_BOUH_BOO` SET `boo_firstname` = :v1_boo_firstname, `boo_name` = :v2_boo_name, '
                . '`boo_roles` = :v3_boo_roles WHERE `boo_id` = :w1_boo_id'],
            $queries
        );
        $this->assertSame(
            [['v1_boo_firstname' => 'first', 'v2_boo_name' => 'copy', 'v3_boo_roles' => '["USER"]', 'w1_boo_id' => 3]],
            $params
        );
        $this->assertSame(3, $clone->getId());
        $this->assertTrue($unitOfWork->isManaged($clone));
        $this->assertFalse($unitOfWork->shouldBePersisted($clone));

        // Managed from now on: only its changes are written
        $clone->setFirstname('other');
        $unitOfWork->pushSave($clone)->process();
        $this->assertSame(
            'UPDATE `T_BOUH_BOO` SET `boo_firstname` = :v1_boo_firstname WHERE `boo_id` = :w1_boo_id',
            $queries[1]
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingANewEntityWithItsIdShouldUpdateItsRow()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $entity->setName('name');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);

        $unitOfWork->pushSave($entity);
        $this->assertFalse($unitOfWork->isNew($entity));
        $this->assertTrue($unitOfWork->shouldBePersisted($entity));
        $unitOfWork->process();

        $this->assertSame(['UPDATE'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(
            [['v1_boo_firstname' => null, 'v2_boo_name' => 'name', 'v3_boo_roles' => '["USER"]', 'w1_boo_id' => 3]],
            $params
        );
        $this->assertTrue($unitOfWork->isManaged($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingANewEntityWithoutIdShouldInsertIt()
    {
        $entity = new Bouh();
        $entity->setName('name');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);

        $unitOfWork->pushSave($entity);
        $this->assertTrue($unitOfWork->isNew($entity));
        $unitOfWork->process();

        $this->assertSame(['INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(1, $entity->getId());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingACloneWhoseIdIsSetBackToNullShouldInsertIt()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $entity->setName('name');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $unitOfWork->manage($entity);

        $clone = clone $entity;
        $clone->setId(null);
        $unitOfWork->pushSave($clone)->process();

        $this->assertSame(['INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(1, $clone->getId());
        $this->assertSame(3, $entity->getId());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnIdSetToNullAfterThePushSaveShouldInsertTheEntity()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);

        $unitOfWork->pushSave($entity);
        $entity->setId(null);
        $unitOfWork->process();

        $this->assertSame(['INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(1, $entity->getId());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAnEntityNotManagedWithoutAutoincrementShouldInsertIt()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $metadata = new Metadata($this->services->serializerFactory());
        $metadata->setEntity(PrimaryOnMultiField::class);
        $metadata->setConnectionName('main');
        $metadata->setDatabase('bouh_world');
        $metadata->setTable('T_MULTI_MUL');
        $metadata->addField(['primary' => true, 'fieldName' => 'cityId', 'columnName' => 'cit_id', 'type' => 'int']);
        $metadata->addField(['primary' => true, 'fieldName' => 'otherItemId', 'columnName' => 'oth_id', 'type' => 'int']);
        $metadata->addField(['fieldName' => 'value', 'columnName' => 'mul_value', 'type' => 'string']);
        $metadataRepository->addMetadata('tests\fixtures\model\PrimaryOnMultiFieldRepository', $metadata);

        // Composite key, natural key
        $entity = new PrimaryOnMultiField();
        $entity->setCityId(1);
        $entity->setOtherItemId(2);
        $entity->setValue('a');
        $slot = $this->createSlot('2026-01-01 00:00:00');

        $unitOfWork->pushSave($entity);
        $unitOfWork->pushSave($slot);
        $this->assertTrue($unitOfWork->isNew($entity));
        $this->assertTrue($unitOfWork->isNew($slot));
        $unitOfWork->process();

        $this->assertSame(['INSERT', 'INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingADetachedEntityShouldUpdateItsRow()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $entity->setName('name');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $unitOfWork->manage($entity);
        $unitOfWork->detach($entity);

        $entity->setName('other');
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(
            ['UPDATE `T_BOUH_BOO` SET `boo_firstname` = :v1_boo_firstname, `boo_name` = :v2_boo_name, '
                . '`boo_roles` = :v3_boo_roles WHERE `boo_id` = :w1_boo_id'],
            $queries
        );
        $this->assertTrue($unitOfWork->isManaged($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAFailedUpdateOfAnEntityNotManagedShouldLeaveItNotManaged()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $entity->setName('name');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);

        $failOn = 'UPDATE';
        $this->assertThrows(QueryException::class, function () use ($unitOfWork, $entity): void {
            $unitOfWork->pushSave($entity)->process();
        });
        $this->assertFalse($unitOfWork->isManaged($entity));
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));

        $failOn = null;
        $unitOfWork->pushSave($entity)->process();
        $this->assertSame(['UPDATE'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertTrue($unitOfWork->isManaged($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAnEntityNotManagedShouldWriteItsMutableFieldsThenManageIt()
    {
        $entity = new Document();
        $entity->setId(1);
        $entity->setTitle('title');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);

        $unitOfWork->pushSave($entity)->process();
        $entity->setTitle('new');
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(
            [
                'UPDATE `T_DOCUMENT_DOC` SET `doc_title` = :v1_doc_title, `doc_payload` = :v2_doc_payload, '
                    . '`doc_published_at` = :v3_doc_published_at WHERE `doc_id` = :w1_doc_id',
                // Managed: the notified change, then the mutable fields
                'UPDATE `T_DOCUMENT_DOC` SET `doc_title` = :v1_doc_title, `doc_payload` = :v2_doc_payload, '
                    . '`doc_published_at` = :v3_doc_published_at WHERE `doc_id` = :w1_doc_id',
            ],
            $queries
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingACloneOfAPartiallyReadEntityShouldWriteItsFieldsNotRead()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $entity = $this->hydrateDocument($unitOfWork, $metadataRepository, ['doc_id' => '1', 'doc_title' => 'title']);

        // Documented limit: the columns not read are recorded for the entity read only
        $clone = clone $entity;
        $unitOfWork->pushSave($clone)->process();

        $this->assertSame(
            [['v1_doc_title' => 'title', 'v2_doc_payload' => null, 'v3_doc_published_at' => null, 'w1_doc_id' => 1]],
            $params
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAFailedQueryShouldNotBeReplayedByTheNextProcess()
    {
        $failing = new Bouh();
        $failing->setName('duplicate');
        $other = new Bouh();
        $other->setId(7);
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $unitOfWork->manage($other);

        $failOn = 'INSERT';
        $this->assertThrows(QueryException::class, function () use ($unitOfWork, $failing): void {
            $unitOfWork->pushSave($failing)->process();
        }, 'Forced failure');
        $this->assertFalse($unitOfWork->shouldBePersisted($failing));
        $this->assertCount(1, $closed, 'the statement of the failed query is closed');

        $failOn = null;
        $other->setName('other');
        $unitOfWork->pushSave($other)->process();
        $this->assertSame(['UPDATE'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));

        // The failed entity can still be saved
        $unitOfWork->pushSave($failing)->process();
        $this->assertSame(['UPDATE', 'INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(1, $failing->getId());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAFailedQueryShouldLeaveTheEntitiesNotProcessedYetQueued()
    {
        $done = new Bouh();
        $done->setId(1);
        $failing = new Bouh();
        $failing->setId(2);
        $pending = new Bouh();
        $pending->setId(3);
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        foreach ([$done, $failing, $pending] as $entity) {
            $unitOfWork->manage($entity);
        }
        $done->setName('done');
        $failing->setFirstname('failing');
        $pending->setName('pending');
        $unitOfWork->pushSave($done)->pushSave($failing)->pushSave($pending);

        $failOn = 'boo_firstname';
        $this->assertThrows(QueryException::class, function () use ($unitOfWork): void {
            $unitOfWork->process();
        }, 'Forced failure');
        $this->assertFalse($unitOfWork->shouldBePersisted($done));
        $this->assertFalse($unitOfWork->shouldBePersisted($failing));
        $this->assertTrue($unitOfWork->isPropertyChanged($failing, 'firstname'), 'the changes of the failed entity are kept');
        $this->assertTrue($unitOfWork->shouldBePersisted($pending));
        $this->assertCount(1, $queries);

        $failOn = null;
        $unitOfWork->process();
        $this->assertCount(2, $queries, 'only the pending entity is updated');
        $this->assertStringContainsString('boo_name', $queries[1]);
        $this->assertFalse($unitOfWork->shouldBePersisted($pending));

        // Saving the failed entity again sends its changes
        $unitOfWork->pushSave($failing)->process();
        $this->assertCount(3, $queries);
        $this->assertStringContainsString('boo_firstname', $queries[2]);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAnEntityWhoseOnlyChangeIsNotMappedShouldRunNoQuery()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $unitOfWork->manage($entity);

        // "enabled" notifies its changes but is not a field of BouhRepository
        $entity->setEnabled(true);
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame([], $queries);
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAnEntityShouldIgnoreItsChangesOnPropertiesNotMapped()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $unitOfWork->manage($entity);

        $entity->setEnabled(true);
        $entity->setName('name');
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(['UPDATE `T_BOUH_BOO` SET `boo_name` = :v1_boo_name WHERE `boo_id` = :w1_boo_id'], $queries);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAnImmutableValueReplacedThroughItsSetterShouldUpdateIt()
    {
        $entity = $this->createEvent(1, '2026-01-01 10:00:00');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);
        $unitOfWork->manage($entity);

        $entity->setStartAt($entity->getStartAt()->modify('+1 day'));
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'startAt'));
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(['UPDATE `T_EVENT_EVT` SET `evt_start_at` = :v1_evt_start_at WHERE `evt_id` = :w1_evt_id'], $queries);
        $this->assertSame([['v1_evt_start_at' => '2026-01-02 10:00:00', 'w1_evt_id' => 1]], $params);
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'startAt'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAnUnchangedEntityWithoutMutableFieldShouldRunNoQuery()
    {
        $entity = $this->createEvent(1, '2026-01-01 10:00:00');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $unitOfWork->manage($entity);

        $unitOfWork->pushSave($entity)->process();

        $this->assertSame([], $queries);
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnImmutableValueReplacedByAnEqualOneShouldBeUpdated()
    {
        $entity = $this->createEvent(1, '2026-01-01 10:00:00');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);
        $unitOfWork->manage($entity);

        // Changes are notified, not compared: another instance is a change, even if equal
        $entity->setStartAt(new \DateTimeImmutable('2026-01-01 10:00:00'));
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame([['v1_evt_start_at' => '2026-01-01 10:00:00', 'w1_evt_id' => 1]], $params);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testPropertyChangedShouldIgnoreTheSameObjectGivenAsOldAndNewValue()
    {
        $entity = $this->createEvent(1, '2026-01-01 10:00:00');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $unitOfWork->manage($entity);

        $startAt = $entity->getStartAt();
        $unitOfWork->propertyChanged($entity, 'startAt', $startAt, $startAt);
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'startAt'));
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame([], $queries);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAJsonObjectModifiedInPlaceShouldUpdateIt()
    {
        $entity = $this->createDocument();
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);
        $unitOfWork->manage($entity);

        // No setter called: nothing is notified
        $entity->getPayload()->tags[] = 'php';
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(
            ['UPDATE `T_DOCUMENT_DOC` SET `doc_payload` = :v1_doc_payload, `doc_published_at` = :v2_doc_published_at WHERE `doc_id` = :w1_doc_id'],
            $queries
        );
        $this->assertSame(
            [['v1_doc_payload' => '{"tags":["php"]}', 'v2_doc_published_at' => '2026-01-01 10:00:00', 'w1_doc_id' => 1]],
            $params
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAnEntityWithAMutableFieldShouldUpdateItEvenWithoutChange()
    {
        $entity = $this->createDocument();
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);
        $unitOfWork->manage($entity);

        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(
            [['v1_doc_payload' => '{"tags":[]}', 'v2_doc_published_at' => '2026-01-01 10:00:00', 'w1_doc_id' => 1]],
            $params
        );
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAnEntityShouldWriteItsMutableFieldsWithItsNotifiedChanges()
    {
        $entity = $this->createDocument();
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);
        $unitOfWork->manage($entity);

        $entity->setTitle('new');
        $entity->getPublishedAt()->modify('+1 hour');
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(
            [['v1_doc_title' => 'new', 'v2_doc_payload' => '{"tags":[]}', 'v3_doc_published_at' => '2026-01-01 11:00:00', 'w1_doc_id' => 1]],
            $params
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testIsPropertyChangedShouldBeTrueForTheMutableFieldsOfAManagedEntity()
    {
        $entity = $this->createDocument();
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'payload'));

        $unitOfWork->manage($entity);
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'payload'));
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'publishedAt'));
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'title'));

        // Written by each save: still "changed" afterwards
        $unitOfWork->pushSave($entity)->process();
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'payload'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testInsertingAnEntityWithMutableFieldsShouldRunOnlyTheInsert()
    {
        $entity = $this->createDocument();
        $entity->setId(null);
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);

        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(['INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(1, $entity->getId());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testChangingThePrimaryKeyShouldUpdateTheRowStoredWithTheOldOne()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);
        $unitOfWork->manage($entity);

        $entity->setId(4);
        $entity->setName('name');
        $unitOfWork->pushSave($entity)->process();
        $entity->setName('other');
        $unitOfWork->pushSave($entity)->process();
        $entity->setId(5);
        $unitOfWork->pushDelete($entity)->process();

        $this->assertSame(
            [
                ['v1_boo_id' => 4, 'v2_boo_name' => 'name', 'w1_boo_id' => 3],
                ['v1_boo_name' => 'other', 'w1_boo_id' => 4],
                ['w1_boo_id' => 4],
            ],
            $params
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAMutablePrimaryKeyModifiedInPlaceShouldTargetTheRowByItsSourceValue()
    {
        $entity = $this->createSlot('2026-01-01 00:00:00');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);
        $unitOfWork->manage($entity);

        $entity->getDay()->modify('+1 day');
        $unitOfWork->pushSave($entity)->process();
        // The UPDATE wrote the new key: it targets the row from now on
        $entity->setLabel('b');
        $unitOfWork->pushSave($entity)->process();
        $entity->getDay()->modify('+1 day');
        $unitOfWork->pushDelete($entity)->process();

        $this->assertSame(
            [
                'UPDATE `T_SLOT_SLO` SET `slo_day` = :v1_slo_day WHERE `slo_day` = :w1_slo_day',
                'UPDATE `T_SLOT_SLO` SET `slo_label` = :v1_slo_label, `slo_day` = :v2_slo_day WHERE `slo_day` = :w1_slo_day',
                'DELETE FROM `T_SLOT_SLO` WHERE `slo_day` = :w1_slo_day',
            ],
            $queries
        );
        $this->assertSame(
            [
                ['v1_slo_day' => '2026-01-02 00:00:00', 'w1_slo_day' => '2026-01-01 00:00:00'],
                ['v1_slo_label' => 'b', 'v2_slo_day' => '2026-01-02 00:00:00', 'w1_slo_day' => '2026-01-02 00:00:00'],
                ['w1_slo_day' => '2026-01-02 00:00:00'],
            ],
            $params
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnInsertShouldKeepTheSourceValueOfAMutablePrimaryKey()
    {
        $entity = $this->createSlot('2026-01-01 00:00:00');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);
        $unitOfWork->pushSave($entity)->process();

        $entity->getDay()->modify('+1 day');
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(['INSERT', 'UPDATE'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(['v1_slo_day' => '2026-01-02 00:00:00', 'w1_slo_day' => '2026-01-01 00:00:00'], $params[1]);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDetachShouldForgetTheSourceValueOfAMutablePrimaryKey()
    {
        $entity = $this->createSlot('2026-01-01 00:00:00');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);
        $unitOfWork->manage($entity);
        $unitOfWork->detach($entity);

        // Read again from the database, as it is now
        $entity->getDay()->modify('+1 day');
        $unitOfWork->manage($entity);
        $unitOfWork->pushDelete($entity)->process();

        $this->assertSame([['w1_slo_day' => '2026-01-02 00:00:00']], $params);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAPartiallyReadEntityShouldNotWriteTheMutableFieldsNotRead()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        // SELECT doc_id, doc_title: the mutable payload and publishedAt keep their PHP default (null)
        $entity = $this->hydrateDocument($unitOfWork, $metadataRepository, ['doc_id' => '1', 'doc_title' => 'title']);

        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'payload'));
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'publishedAt'));
        $entity->setTitle('new');
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(['UPDATE `T_DOCUMENT_DOC` SET `doc_title` = :v1_doc_title WHERE `doc_id` = :w1_doc_id'], $queries);
        $this->assertSame([['v1_doc_title' => 'new', 'w1_doc_id' => 1]], $params);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAPartiallyJoinedEntityShouldWriteOnlyTheMutableFieldsRead()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        // A join selecting some columns of the joined entity: doc_published_at is not read
        $entity = $this->hydrateDocument(
            $unitOfWork,
            $metadataRepository,
            ['boo_name' => 'Sylvain', 'doc_id' => '1', 'doc_payload' => '{"tags":[]}']
        );

        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'payload'));
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'publishedAt'));
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame([['v1_doc_payload' => '{"tags":[]}', 'w1_doc_id' => 1]], $params);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAMutableFieldNotReadShouldBeWrittenOnceSetThroughItsSetter()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $entity = $this->hydrateDocument($unitOfWork, $metadataRepository, ['doc_id' => '1', 'doc_title' => 'title']);

        $entity->setPublishedAt(new \DateTime('2026-02-01 10:00:00'));
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'publishedAt'));
        $unitOfWork->pushSave($entity)->process();
        // Known from now on: written by every save, as a mutable field read
        $entity->getPublishedAt()->modify('+1 hour');
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(
            [
                ['v1_doc_published_at' => '2026-02-01 10:00:00', 'w1_doc_id' => 1],
                ['v1_doc_published_at' => '2026-02-01 11:00:00', 'w1_doc_id' => 1],
            ],
            $params
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAnEntityReadWithEveryColumnShouldWriteItsMutableFields()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $entity = $this->hydrateDocument(
            $unitOfWork,
            $metadataRepository,
            ['doc_id' => '1', 'doc_title' => 'title', 'doc_payload' => null, 'doc_published_at' => '2026-01-01 10:00:00']
        );

        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(
            [['v1_doc_payload' => null, 'v2_doc_published_at' => '2026-01-01 10:00:00', 'w1_doc_id' => 1]],
            $params
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDetachShouldForgetTheMutableFieldsNotRead()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $entity = $this->hydrateDocument($unitOfWork, $metadataRepository, ['doc_id' => '1', 'doc_title' => 'title']);
        $unitOfWork->detach($entity);

        // Managed again by hand: its current values are taken as the stored ones
        $entity->setPublishedAt(new \DateTime('2026-02-01 10:00:00'));
        $unitOfWork->manage($entity);
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(
            [['v1_doc_payload' => null, 'v2_doc_published_at' => '2026-02-01 10:00:00', 'w1_doc_id' => 1]],
            $params
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAFailedPrepareShouldRethrowItsException()
    {
        $metadataRepository = new MetadataRepository($this->services->serializerFactory());
        $metadataRepository->addMetadata(
            'tests\fixtures\model\BouhRepository',
            BouhRepository::initMetadata($this->services->serializerFactory())
        );
        $connectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['primary'])->getMock();
        // The real closeStatement() throws on a statement that was never prepared
        $driver = $this->getMockBuilder(Driver::class)->onlyMethods(['prepare'])->getMock();
        $driver->method('prepare')->willThrowException(new QueryException('Prepare failed'));
        $connectionPool->method('primary')->willReturn($driver);
        $unitOfWork = new UnitOfWork($connectionPool, $metadataRepository, $this->services->queryFactory());

        $entity = new Bouh();
        $this->assertThrows(QueryException::class, function () use ($unitOfWork, $entity): void {
            $unitOfWork->pushSave($entity)->process();
        }, 'Prepare failed');
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
    }

    private function createEvent(?int $id, string $startAt): Event
    {
        $entity = new Event();
        $entity->setId($id);
        $entity->setStartAt(new \DateTimeImmutable($startAt));

        return $entity;
    }

    private function createDocument(): Document
    {
        $entity = new Document();
        $entity->setId(1);
        $entity->setTitle('title');
        $entity->setPayload((object) ['tags' => []]);
        $entity->setPublishedAt(new \DateTime('2026-01-01 10:00:00'));

        return $entity;
    }

    private function createSlot(string $day): Slot
    {
        $entity = new Slot();
        $entity->setDay(new \DateTime($day));
        $entity->setLabel('a');

        return $entity;
    }

    /**
     * A Document hydrated from a row of the given columns (alias "doc"; the columns of Bouh, alias "bouh"): a partial
     * read when some columns of T_DOCUMENT_DOC are missing
     *
     * @param array<string, string|null> $columns column name => raw value
     */
    private function hydrateDocument(UnitOfWork $unitOfWork, MetadataRepository $metadataRepository, array $columns): Document
    {
        $fields = [];
        foreach (array_keys($columns) as $column) {
            $field = new \stdClass();
            $field->name     = $column;
            $field->orgname  = $column;
            [$field->table, $field->orgtable] = str_starts_with($column, 'doc_')
                ? ['doc', 'T_DOCUMENT_DOC']
                : ['bouh', 'T_BOUH_BOO'];
            $field->type     = $column === 'doc_id' ? MYSQLI_TYPE_LONG : MYSQLI_TYPE_VAR_STRING;
            $fields[] = $field;
        }
        $result = new Result();
        $result->setResult((new MysqliResult([array_values($columns)]))->setFields($fields));
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $hydrator = new Hydrator();
        $hydrator->setMetadataRepository($metadataRepository);
        $hydrator->setUnitOfWork($unitOfWork);
        $entity = $hydrator->setResult($result)->getIterator()->current()['doc'];
        $this->assertInstanceOf(Document::class, $entity);

        return $entity;
    }

    /**
     * Two repositories on the same entity (another table, or a lighter projection): the metadata given to pushSave()
     * and pushDelete() (by Repository::save() and delete()) write the entity, not those of the repository
     * registered last
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testTheMetadataGivenToPushSaveAndPushDeleteShouldWriteTheEntity()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $bouhMetadata = BouhRepository::initMetadata($this->services->serializerFactory());
        $archiveMetadata = BouhRepository::initMetadata($this->services->serializerFactory());
        $archiveMetadata->setTable('T_BOUH_ARCHIVE');
        $metadataRepository->addMetadata('BouhArchiveRepository', $archiveMetadata);
        $tables = function () use (&$queries): array {
            return array_map(
                fn (string $sql): string => strtok($sql, ' ') . ' ' . (str_contains($sql, 'T_BOUH_ARCHIVE') ? 'archive' : 'main'),
                $queries
            );
        };

        $entity = new Bouh();
        $entity->setName('name');
        $unitOfWork->pushSave($entity, $bouhMetadata)->process();
        $entity->setName('other name');
        $unitOfWork->pushSave($entity, $bouhMetadata)->process();
        $unitOfWork->pushDelete($entity, $bouhMetadata)->process();
        $this->assertSame(['INSERT main', 'UPDATE main', 'DELETE main'], $tables());

        // Not managed, with its id: an existing row of the table of the metadata given
        $queries = [];
        $entity = new Bouh();
        $entity->setId(3);
        $unitOfWork->pushSave($entity, $bouhMetadata)->process();
        $unitOfWork->detach($entity);
        $unitOfWork->pushDelete($entity, $bouhMetadata)->process();
        $this->assertSame(['UPDATE main', 'DELETE main'], $tables());

        // Without metadata, those of the entity: the repository registered last
        $queries = [];
        $entity = new Bouh();
        $unitOfWork->pushSave($entity)->process();
        $unitOfWork->pushDelete($entity)->process();
        $this->assertSame(['INSERT archive', 'DELETE archive'], $tables());
    }

    /**
     * Builds a UnitOfWork on Bouh, Event, Document and Slot whose queries are recorded instead of executed.
     *
     * @param list<string>|null $queries SQL of each successfully executed query
     * @param string|null       $failOn  executing a query whose SQL contains it throws a QueryException, and
     *                                   getInsertedId() too with 'getInsertedId'
     * @param list<string>|null $closed  names of the closed statements
     * @param list<array<string, mixed>>|null $params parameters of each successfully executed query
     * @param MetadataRepository|null $metadataRepository the metadata repository of the unit of work
     */
    private function createRecordingUnitOfWork(
        ?array &$queries,
        ?string &$failOn,
        ?array &$closed,
        ?array &$params = null,
        ?MetadataRepository &$metadataRepository = null
    ): UnitOfWork {
        $queries = [];
        $closed = [];
        $params = [];
        $metadataRepository = new MetadataRepository($this->services->serializerFactory());
        $metadataRepository->addMetadata(
            'tests\fixtures\model\BouhRepository',
            BouhRepository::initMetadata($this->services->serializerFactory())
        );
        foreach ([EventRepository::class, DocumentRepository::class, SlotRepository::class] as $repository) {
            $metadataRepository->addMetadata($repository, $repository::initMetadata($this->services->serializerFactory()));
        }

        $connectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['primary'])->getMock();
        $driver = $this->getMockBuilder(Driver::class)->onlyMethods(['getInsertedId', 'closeStatement'])->getMock();
        $driver->method('getInsertedId')->willReturnCallback(function () use (&$failOn): int {
            if ($failOn === 'getInsertedId') {
                throw new QueryException('Forced failure of getInsertedId()');
            }

            return 1;
        });
        $driver->method('closeStatement')->willReturnCallback(function (string $statement) use (&$closed): void {
            $closed[] = $statement;
        });
        $connectionPool->method('primary')->willReturn($driver);

        $queryFactory = $this->getMockBuilder(QueryFactory::class)->onlyMethods(['getPrepared'])->getMock();
        $queryFactory->method('getPrepared')->willReturnCallback(
            function (string $sql, Connection $connection) use (&$queries, &$failOn, &$params): PreparedQuery {
                $query = $this->getMockBuilder(PreparedQuery::class)
                    ->setConstructorArgs([$sql, $connection])
                    ->onlyMethods(['prepareExecute', 'execute'])
                    ->getMock();
                $query->method('prepareExecute')->willReturnSelf();
                $query->method('execute')->willReturnCallback(function () use ($query, $sql, &$queries, &$failOn, &$params): bool {
                    if ($failOn !== null && str_contains($sql, $failOn)) {
                        throw new QueryException('Forced failure');
                    }
                    $queries[] = $sql;
                    $params[] = (fn (): array => $this->params)->call($query);

                    return true;
                });

                return $query;
            }
        );

        return new UnitOfWork($connectionPool, $metadataRepository, $queryFactory);
    }

    /**
     * Builds the connection pool and query factory partial mocks shared by the process() tests.
     *
     * @return array{0: ConnectionPool, 1: QueryFactory}
     */
    private function createProcessMocks(bool $mockCloseStatement): array
    {
        $mockConnectionPool = $this->getMockBuilder(ConnectionPool::class)
            ->onlyMethods(['primary'])
            ->getMock();
        $mockConnection = new Connection($mockConnectionPool, 'main', 'db');

        $mockQueryFactory = $this->getMockBuilder(QueryFactory::class)
            ->onlyMethods(['getPrepared'])
            ->getMock();

        $mockPreparedQuery = $this->getMockBuilder(PreparedQuery::class)
            ->setConstructorArgs(['', $mockConnection])
            ->onlyMethods(['prepareExecute', 'execute'])
            ->getMock();
        $mockPreparedQuery->method('prepareExecute')->willReturn($mockPreparedQuery);
        $mockPreparedQuery->method('execute')->willReturn(true);

        $driverMethods = $mockCloseStatement ? ['getInsertedId', 'closeStatement'] : ['getInsertedId'];
        $mockDriver = $this->getMockBuilder(Driver::class)
            ->onlyMethods($driverMethods)
            ->getMock();
        // closeStatement() is declared void: atoum's "closeStatement = true" could not be returned anyway
        $mockDriver->method('getInsertedId')->willReturn(1);

        $mockQueryFactory->method('getPrepared')->willReturn($mockPreparedQuery);
        $mockConnectionPool->method('primary')->willReturn($mockDriver);

        return [$mockConnectionPool, $mockQueryFactory];
    }
}
