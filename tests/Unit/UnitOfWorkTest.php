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
use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;
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
use PHPUnit\Framework\Attributes\DataProvider;
use tests\fixtures\Fake\Mysqli as FakeMysqli;
use tests\fixtures\FakeDriver\MysqliResult;
use tests\fixtures\model\Account;
use tests\fixtures\model\AccountRepository;
use tests\fixtures\model\Bouh;
use tests\fixtures\model\BouhRepository;
use tests\fixtures\model\Document;
use tests\fixtures\model\DocumentRepository;
use tests\fixtures\model\Event;
use tests\fixtures\model\EventRepository;
use tests\fixtures\model\Order;
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
        // Not managed, without id: queued for its INSERT
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
        // Not managed, with its id: saving it updates its row
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
    public function testSavingAnEntityAfterItsDeleteShouldInsertItOnceItsIdIsSetToNull()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $entity->setName('name');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $unitOfWork->manage($entity);

        $unitOfWork->pushDelete($entity)->process();
        $this->assertFalse($unitOfWork->isManaged($entity));
        // Detached by its deletion, it keeps its id: its UPDATE matches no row
        $unitOfWork->pushSave($entity)->process();
        $unitOfWork->detach($entity);
        $entity->setId(null);
        $unitOfWork->pushSave($entity);
        $this->assertTrue($unitOfWork->isNew($entity));
        $unitOfWork->process();

        $this->assertSame(['DELETE', 'UPDATE', 'INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(1, $entity->getId());
        $this->assertTrue($unitOfWork->isManaged($entity));
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

    /**
     * The setter of the autoincrement property changes other fields once the id is known: those changes were not
     * written by the INSERT, they are written by the next save
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testAChangeMadeBySettingTheGeneratedIdShouldBeWrittenByTheNextSave()
    {
        $entity = $this->createDocumentNamedByItsId();
        $metadata = DocumentRepository::initMetadata($this->services->serializerFactory());
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);

        $unitOfWork->pushSave($entity, $metadata)->process();

        $this->assertSame('item-1', $entity->getTitle());
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'id'));
        // Notified through its setter, and a mutable field modified in place
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'title'));
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'publishedAt'));
        // Written by the INSERT, unchanged since
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'payload'));

        $unitOfWork->pushSave($entity, $metadata)->process();
        $unitOfWork->pushSave($entity, $metadata)->process();

        $this->assertSame(['INSERT', 'UPDATE'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(
            [
                'v1_doc_title' => null,
                'v2_doc_payload' => '{"tags":[]}',
                'v3_doc_published_at' => '2026-01-01 10:00:00',
            ],
            $params[0]
        );
        $this->assertSame(
            ['v1_doc_title' => 'item-1', 'v2_doc_published_at' => '2026-01-02 10:00:00', 'w1_doc_id' => 1],
            $params[1]
        );
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'title'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAChangeMadeByASetterOfTheGeneratedIdThatThrowsShouldBeKept()
    {
        $entity = $this->createDocumentNamedByItsId(true);
        $metadata = DocumentRepository::initMetadata($this->services->serializerFactory());
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);

        $this->assertThrows(\LogicException::class, function () use ($unitOfWork, $entity, $metadata): void {
            $unitOfWork->pushSave($entity, $metadata)->process();
        }, 'Forced failure of setId()');

        $this->assertTrue($unitOfWork->isManaged($entity));
        $this->assertNull($entity->getId());
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'id'));
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'title'));
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'publishedAt'));
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'payload'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAChangeMadeBySettingTheGeneratedIdShouldBeInsertedAfterARollback()
    {
        $entity = $this->createDocumentNamedByItsId();
        $metadata = DocumentRepository::initMetadata($this->services->serializerFactory());
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository, $driver);

        $driver->startTransaction();
        $unitOfWork->pushSave($entity, $metadata)->process();
        $driver->rollback();
        $unitOfWork->pushSave($entity, $metadata)->process();
        $unitOfWork->pushSave($entity, $metadata)->process();

        // Inserted again with every value: nothing left to write
        $this->assertSame(['INSERT', 'INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(
            [
                'v1_doc_title' => 'item-1',
                'v2_doc_payload' => '{"tags":[]}',
                'v3_doc_published_at' => '2026-01-02 10:00:00',
            ],
            $params[1]
        );
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'title'));
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'publishedAt'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAnEntityNotManagedWithItsAutoincrementIdShouldUpdateItsRow()
    {
        // Built with its id, detached, read from a cache...
        $entity = new Bouh();
        $entity->setId(3);
        $entity->setFirstname('first');
        $entity->setName('name');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);

        $unitOfWork->pushSave($entity);
        $this->assertFalse($unitOfWork->isNew($entity));
        $this->assertFalse($unitOfWork->isManaged($entity));
        $this->assertTrue($unitOfWork->shouldBePersisted($entity));
        $unitOfWork->process();

        // No change is tracked for it: every readable mapped field is written, by its primary key
        $this->assertSame(
            ['UPDATE `T_BOUH_BOO` SET `boo_firstname` = :v1_boo_firstname, `boo_name` = :v2_boo_name, '
                . '`boo_roles` = :v3_boo_roles WHERE `boo_id` = :w1_boo_id'],
            $queries
        );
        $this->assertSame(
            [['v1_boo_firstname' => 'first', 'v2_boo_name' => 'name', 'v3_boo_roles' => '["USER"]', 'w1_boo_id' => 3]],
            $params
        );
        $this->assertTrue($unitOfWork->isManaged($entity));
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));

        // Managed from now on: only its changes are written
        $entity->setFirstname('other');
        $unitOfWork->pushSave($entity)->process();
        $this->assertSame(['v1_boo_firstname' => 'other', 'w1_boo_id' => 3], $params[1]);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnAutoincrementIdDefaultingToZeroShouldBeTakenAsTheIdOfARow()
    {
        $entity = new Bouh();
        // "private int $id = 0;": make the id nullable instead
        $entity->setId(0);
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);

        $unitOfWork->pushSave($entity);
        $this->assertFalse($unitOfWork->isNew($entity));
        $unitOfWork->process();

        $this->assertSame(['UPDATE'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(0, $params[0]['w1_boo_id']);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingACloneOfAManagedEntityShouldUpdateItsRowWithEveryField()
    {
        $entity = new Bouh();
        $entity->setId(3);
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
            [['v1_boo_firstname' => null, 'v2_boo_name' => 'copy', 'v3_boo_roles' => '["USER"]', 'w1_boo_id' => 3]],
            $params
        );
        $this->assertTrue($unitOfWork->isManaged($clone));
        $this->assertTrue($unitOfWork->isManaged($entity));
        // The clone keeps the listeners of its original: managed, it is not listened to twice
        $this->assertCount(1, (fn (): array => $this->listeners)->call($clone));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingACloneWhoseIdIsSetBackToNullShouldInsertIt()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $entity->setName('name');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $unitOfWork->manage($entity);

        // The duplication recipe
        $clone = clone $entity;
        $clone->setId(null);
        $unitOfWork->pushSave($clone);
        $this->assertTrue($unitOfWork->isNew($clone));
        $unitOfWork->process();

        $this->assertSame(['INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(1, $clone->getId());
        $this->assertSame(3, $entity->getId());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testTheDuplicationRecipesOfTheDocumentationShouldInsertACopy()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        // The shape of the entities of the documentation: private ?int $id = null; setId(?int $id)
        $entity = $this->createDocument();
        $unitOfWork->manage($entity);
        $copy = clone $entity;
        $copy->setId(null);
        $unitOfWork->pushSave($copy)->process();

        // __clone() resetting the id
        $withClone = new class () extends Document {
            public function __clone()
            {
                $this->id = null;
            }
        };
        $metadata = DocumentRepository::initMetadata($this->services->serializerFactory());
        $metadata->setEntity($withClone::class);
        $withClone->setId(2);
        $unitOfWork->manage($withClone, $metadata);
        $unitOfWork->pushSave(clone $withClone, $metadata)->process();

        $this->assertSame(['INSERT', 'INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame([1, 1], [$entity->getId(), $copy->getId()]);
        $this->assertSame(2, $withClone->getId());
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
    public function testSavingANewEntityShouldNotReadItsIdThroughItsGetter()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $metadata = new Metadata($this->services->serializerFactory());
        $metadata->setEntity(Order::class);
        $metadata->setConnectionName('main');
        $metadata->setDatabase('bouh_world');
        $metadata->setTable('T_ORDER_ORD');
        $metadata->addField([
            'primary'       => true,
            'autoincrement' => true,
            'fieldName'     => 'id',
            'columnName'    => 'ord_id',
            'type'          => 'int',
            'getter'        => 'getId',
        ]);
        $metadata->addField(['fieldName' => 'reference', 'columnName' => 'ord_reference', 'type' => 'string']);
        $metadataRepository->addMetadata('tests\fixtures\model\OrderRepository', $metadata);

        // Its getter throws until the id is generated
        $entity = new Order();
        $entity->setReference('R1');
        $unitOfWork->pushSave($entity);
        $this->assertTrue($unitOfWork->isNew($entity));
        $unitOfWork->process();

        $this->assertSame(['INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(1, $entity->getId());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAnEntityWhoseAutoincrementPropertyIsNotInitializedShouldInsertIt()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $entity = new class () implements NotifyPropertyInterface {
            use NotifyProperty;

            public int $id;
            public ?string $name = null;
        };
        $metadata = new Metadata($this->services->serializerFactory());
        $metadata->setEntity($entity::class);
        $metadata->setConnectionName('main');
        $metadata->setDatabase('bouh_world');
        $metadata->setTable('user');
        $metadata->addField(['primary' => true, 'autoincrement' => true, 'fieldName' => 'id', 'columnName' => 'id', 'type' => 'int']);
        $metadata->addField(['fieldName' => 'name', 'columnName' => 'name', 'type' => 'string']);

        // public int $id; not initialized
        $entity->name = 'name';
        $unitOfWork->pushSave($entity, $metadata);
        $this->assertTrue($unitOfWork->isNew($entity));
        $unitOfWork->process();

        $this->assertSame(['INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(1, $entity->id);
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
    public function testSavingADetachedOrUnserializedEntityShouldUpdateItsRow()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $entity->setName('name');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $unitOfWork->manage($entity);
        // Read from a session or a cache
        $copy = unserialize(serialize($entity));
        $this->assertInstanceOf(Bouh::class, $copy);
        $unitOfWork->detach($entity);

        $entity->setName('other');
        $unitOfWork->pushSave($entity)->pushSave($copy);
        $this->assertFalse($unitOfWork->isNew($entity));
        $this->assertFalse($unitOfWork->isNew($copy));
        $unitOfWork->process();

        $this->assertSame(['UPDATE', 'UPDATE'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertTrue($unitOfWork->isManaged($entity));
        $this->assertTrue($unitOfWork->isManaged($copy));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAPartiallyReadEntityOnceDetachedShouldWriteItsFieldsNotRead()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        // SELECT doc_id, doc_payload: the title and publishedAt hold their PHP default (null)
        $entity = $this->hydrateDocument($unitOfWork, $metadataRepository, ['doc_id' => '1', 'doc_payload' => '{"tags":[]}']);
        $clone = clone $entity;
        $unitOfWork->detach($entity);

        // Not managed: nothing tells which fields were read
        $unitOfWork->pushSave($entity)->pushSave($clone)->process();

        $this->assertSame(
            [
                ['v1_doc_title' => null, 'v2_doc_payload' => '{"tags":[]}', 'v3_doc_published_at' => null, 'w1_doc_id' => 1],
                ['v1_doc_title' => null, 'v2_doc_payload' => '{"tags":[]}', 'v3_doc_published_at' => null, 'w1_doc_id' => 1],
            ],
            $params
        );
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
    public function testPushDeleteShouldReplaceThePushSaveOfAnEntityNotManagedWithItsId()
    {
        $entity = new Bouh();
        $entity->setId(3);
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);

        $unitOfWork->pushSave($entity)->pushDelete($entity)->process();

        $this->assertSame(['DELETE'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(3, $params[0]['w1_boo_id']);
        $this->assertFalse($unitOfWork->isManaged($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAnEntityNotManagedShouldWriteItsMutableFieldsThenManageIt()
    {
        $entity = $this->createDocument();
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);

        $unitOfWork->pushSave($entity)->process();
        // Managed: its mutable fields are compared with the values written
        $unitOfWork->pushSave($entity)->process();
        $entity->getPublishedAt()?->modify('+1 hour');
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(
            [
                [
                    'v1_doc_title' => 'title',
                    'v2_doc_payload' => '{"tags":[]}',
                    'v3_doc_published_at' => '2026-01-01 10:00:00',
                    'w1_doc_id' => 1,
                ],
                ['v1_doc_published_at' => '2026-01-01 11:00:00', 'w1_doc_id' => 1],
            ],
            $params
        );
    }
    #[AllowMockObjectsWithoutExpectations]
    public function testAnEntityInsertedInATransactionRolledBackShouldBeInsertedAgain()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository, $driver);
        $entity = new Bouh();
        $entity->setName('name');
        $detached = new Bouh();
        $detached->setName('detached');

        $driver->startTransaction();
        $unitOfWork->pushSave($entity)->pushSave($detached)->process();
        $this->assertSame(1, $entity->getId());
        $unitOfWork->detachAll();
        $driver->rollback();

        // Left as it is, its id included, but no longer managed
        $this->assertSame(1, $entity->getId());
        $this->assertFalse($unitOfWork->isManaged($entity));
        // The retry: inserted again, with or without detachAll()
        $driver->startTransaction();
        $unitOfWork->pushSave($entity)->pushSave($detached);
        $this->assertTrue($unitOfWork->isNew($entity));
        $this->assertTrue($unitOfWork->isNew($detached));
        $unitOfWork->process();
        $driver->commit();

        $this->assertSame(['INSERT', 'INSERT', 'INSERT', 'INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertTrue($unitOfWork->isManaged($entity));
        // Inserted again: the row exists, its next save updates it
        $entity->setName('other');
        $unitOfWork->pushSave($entity)->process();
        $this->assertSame('UPDATE', strtok($queries[4], ' '));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnEntityInsertedInATransactionCommittedOrWithoutTransactionShouldBeUpdatedOnceDetached()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository, $driver);
        $committed = new Bouh();
        $withoutTransaction = new Bouh();

        $driver->startTransaction();
        $unitOfWork->pushSave($committed)->process();
        $driver->commit();
        $unitOfWork->pushSave($withoutTransaction)->process();
        // A rollback of a later transaction does not concern them
        $driver->startTransaction();
        $driver->rollback();
        $unitOfWork->detachAll();
        $unitOfWork->pushSave($committed)->pushSave($withoutTransaction)->process();

        $this->assertSame(['INSERT', 'INSERT', 'UPDATE', 'UPDATE'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnEntityInsertedThenQueuedBeforeARollbackShouldBeInsertedAgain()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository, $driver);
        $saved = new Bouh();
        $deleted = new Bouh();

        $driver->startTransaction();
        $unitOfWork->pushSave($saved)->pushSave($deleted)->process();
        $saved->setName('name');
        $unitOfWork->pushSave($saved)->pushDelete($deleted);
        $driver->rollback();
        $this->assertTrue($unitOfWork->isNew($saved));
        // Its row was rolled back: nothing to delete
        $this->assertFalse($unitOfWork->shouldBePersisted($deleted));
        $unitOfWork->pushDelete($deleted);
        $this->assertFalse($unitOfWork->shouldBePersisted($deleted));
        $unitOfWork->process();

        $this->assertSame(['INSERT', 'INSERT', 'INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnEntityQueuedBeforeARollbackShouldKeepItsPlaceInTheQueue()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository, $driver);
        $parent = new Bouh();
        $parent->setName('parent');
        $child = new Bouh();
        $child->setName('child');

        $driver->startTransaction();
        $unitOfWork->pushSave($parent)->process();
        $parent->setName('parent again');
        // The parent before the child (a foreign key)
        $unitOfWork->pushSave($parent)->pushSave($child);
        $driver->rollback();
        $unitOfWork->process();

        $this->assertSame(
            ['parent', 'parent again', 'child'],
            array_map(fn (array $queryParams): mixed => $queryParams['v2_boo_name'] ?? null, $params)
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testResetShouldKeepTheEntitiesWhoseInsertWasRolledBack()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository, $driver);
        $entity = new Bouh();

        $driver->startTransaction();
        $unitOfWork->pushSave($entity)->process();
        // ConnectionPool::reset() before UnitOfWork::reset(): the connection closed rolls the transaction back
        $driver->rollback();
        $unitOfWork->reset();
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(['INSERT', 'INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnEntityUpdatedInATransactionRolledBackShouldStayManaged()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository, $driver);
        $entity = new Bouh();
        $entity->setId(3);
        $unitOfWork->manage($entity);

        $driver->startTransaction();
        $entity->setName('name');
        $unitOfWork->pushSave($entity)->process();
        $driver->rollback();

        // Read it again before retrying: its change is no longer tracked
        $this->assertTrue($unitOfWork->isManaged($entity));
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'name'));
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

    /**
     * The retry recipe of the documentation: an entity still queued keeps its place when pushed again, so the queue is
     * emptied by detachAll() before the entities are pushed again in their order
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testTheRetryOfAFailedBatchShouldWriteItsEntitiesInTheOrderTheyArePushedAgain()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository, $driver);
        $parent = new Bouh();
        $parent->setName('parent');
        $failing = new Bouh();
        $failing->setId(7);
        $unitOfWork->manage($failing);
        $failing->setFirstname('failing');
        $child = new Bouh();
        $child->setName('child');

        $attempt = function () use ($unitOfWork, $driver, $parent, $failing, $child): void {
            $driver->startTransaction();
            try {
                $unitOfWork->pushSave($parent)->pushSave($failing)->pushSave($child)->process();
            } catch (QueryException $exception) {
                $driver->rollback();
                throw $exception;
            }
            $driver->commit();
        };

        $failOn = 'UPDATE';
        $this->assertThrows(QueryException::class, $attempt, 'Forced failure');
        $this->assertTrue($unitOfWork->shouldBePersisted($child), 'the entity after the failed query stays queued');

        $failOn = null;
        $unitOfWork->detachAll();
        $attempt();

        $this->assertSame(['INSERT', 'INSERT', 'UPDATE', 'INSERT'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(
            ['parent', 'parent', 'failing', 'child'],
            array_map(
                fn (array $queryParams): string => implode(',', array_intersect($queryParams, ['parent', 'failing', 'child'])),
                $params
            )
        );
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
        // No metadata can write it: forgotten by the save, not kept forever
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'enabled'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAChangeMappedByAnotherRepositoryOfTheClassShouldBeKeptUntilWritten()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $full = DocumentRepository::initMetadata($this->services->serializerFactory());
        $light = $this->lightDocumentMetadata();
        // Registered last for Document: the full metadata are those of another repository
        $metadataRepository->addMetadata('LightDocumentRepository', $light);

        $entity = new Document();
        $entity->setId(1);
        $unitOfWork->manage($entity, $light);
        $entity->setTitle('title');
        $entity->setPayload((object) ['a' => 1]);
        $unitOfWork->pushSave($entity, $light)->process();
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'payload'));
        $unitOfWork->pushSave($entity, $full)->process();

        $this->assertSame(
            [['v1_doc_title' => 'title', 'w1_doc_id' => 1], ['v1_doc_payload' => '{"a":1}', 'w1_doc_id' => 1]],
            $params
        );
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
            ['UPDATE `T_DOCUMENT_DOC` SET `doc_payload` = :v1_doc_payload WHERE `doc_id` = :w1_doc_id'],
            $queries
        );
        $this->assertSame([['v1_doc_payload' => '{"tags":["php"]}', 'w1_doc_id' => 1]], $params);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAnEntityWithAMutableFieldShouldNotUpdateItWithoutChange()
    {
        $entity = $this->createDocument();
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);
        $unitOfWork->manage($entity);

        $unitOfWork->pushSave($entity)->process();

        $this->assertSame([], $queries);
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingAnEntityShouldWriteItsMutableFieldsChangedWithItsNotifiedChanges()
    {
        $entity = $this->createDocument();
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);
        $unitOfWork->manage($entity);

        $entity->setTitle('new');
        $entity->getPublishedAt()->modify('+1 hour');
        $unitOfWork->pushSave($entity)->process();
        // Written: the value stored from now on
        $unitOfWork->pushSave($entity)->process();
        $entity->getPublishedAt()->modify('-1 hour');
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(
            [
                ['v1_doc_title' => 'new', 'v2_doc_published_at' => '2026-01-01 11:00:00', 'w1_doc_id' => 1],
                ['v1_doc_published_at' => '2026-01-01 10:00:00', 'w1_doc_id' => 1],
            ],
            $params
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAMutableValueReplacedThroughItsSetterShouldBeWritten()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $entity = $this->hydrateDocument(
            $unitOfWork,
            $metadataRepository,
            ['doc_id' => '1', 'doc_title' => 'title', 'doc_payload' => '{"a":1}', 'doc_published_at' => '2026-01-01 10:00:00']
        );

        $entity->setPublishedAt(new \DateTime('2026-03-01 10:00:00'));
        // Notified: written even with the value read
        $entity->setPayload((object) ['a' => 1]);
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(
            [['v1_doc_payload' => '{"a":1}', 'v2_doc_published_at' => '2026-03-01 10:00:00', 'w1_doc_id' => 1]],
            $params
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}> column => raw value, timezone of PHP
     */
    public static function provideValuesNotSurvivingARoundTrip(): array
    {
        return [
            // DATETIME(6), PostgreSQL timestamp: the microseconds are not written back
            'microseconds'         => ['doc_published_at', '2024-05-06 12:34:56.123456', 'UTC'],
            // Stored by another writer in UTC: read as 03:30 in the DST gap of Europe/Paris
            'time in a DST gap'    => ['doc_published_at', '2024-03-31 02:30:00', 'Europe/Paris'],
            // MySQL zero date: read as -0001-11-30
            'zero date'            => ['doc_published_at', '0000-00-00 00:00:00', 'UTC'],
            // Decoded to a float
            'JSON big integer'     => ['doc_payload', '{"n":12345678901234567890}', 'UTC'],
            'JSON spaces, escapes' => ['doc_payload', '{"a": 1, "s": "é"}', 'UTC'],
        ];
    }

    #[AllowMockObjectsWithoutExpectations]
    #[DataProvider('provideValuesNotSurvivingARoundTrip')]
    public function testAnUnrelatedSaveShouldNotRewriteAMutableValueRead(string $column, string $value, string $timezone)
    {
        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set($timezone);
        try {
            $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
            $entity = $this->hydrateDocument(
                $unitOfWork,
                $metadataRepository,
                ['doc_id' => '1', 'doc_title' => 'title', 'doc_payload' => '{}', 'doc_published_at' => null, $column => $value]
            );

            $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'payload'));
            $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'publishedAt'));
            $entity->setTitle('only the title changes');
            $unitOfWork->pushSave($entity)->process();
        } finally {
            date_default_timezone_set($previousTimezone);
        }

        $this->assertSame([['v1_doc_title' => 'only the title changes', 'w1_doc_id' => 1]], $params);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAReadShouldNotFailOnAMutableValueThatCannotBeWrittenBack()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);

        // Decoded to INF, which json_encode() refuses
        $entity = $this->hydrateDocument($unitOfWork, $metadataRepository, ['doc_id' => '1', 'doc_payload' => '{"v":1e400}']);

        $this->assertSame(INF, $entity->getPayload()?->v);
        $this->assertTrue($unitOfWork->isManaged($entity));

        // Its database value cannot be computed: taken as unchanged, as 3.13 did not write it either
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'payload'));
        $entity->setTitle('new');
        $unitOfWork->pushSave($entity)->process();
        $this->assertSame([['v1_doc_title' => 'new', 'w1_doc_id' => 1]], $params);

        // Set through its setter: written, so the error of its serializer is thrown
        $entity->setPayload((object) ['v' => INF]);
        $this->assertThrows(\Throwable::class, function () use ($unitOfWork, $entity): void {
            $unitOfWork->pushSave($entity)->process();
        });
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAMutableFieldReadShouldBeComparedWithItsRawValueNormalized()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $entity = $this->hydrateDocument(
            $unitOfWork,
            $metadataRepository,
            ['doc_id' => '1', 'doc_title' => 'title', 'doc_payload' => '{"tags": ["a"]}', 'doc_published_at' => '2026-01-01 10:00:00']
        );

        // The raw JSON holds spaces: written back without them, it is still the same value
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'payload'));
        $unitOfWork->pushSave($entity)->process();
        $entity->getPayload()->tags[] = 'b';
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'payload'));
        $unitOfWork->pushSave($entity)->process();
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame([['v1_doc_payload' => '{"tags":["a","b"]}', 'w1_doc_id' => 1]], $params);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAMutablePrimaryKeyReadShouldTargetItsRowByTheValueRead()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        // DATETIME(6): the microseconds are not written back by the serializer
        $entity = $this->hydrateRow(
            $unitOfWork,
            $metadataRepository,
            ['slo_day' => '2026-01-01 00:00:00.123456', 'slo_label' => 'a']
        )['slo'];
        $this->assertInstanceOf(Slot::class, $entity);

        $entity->setLabel('b');
        $unitOfWork->pushSave($entity)->process();
        $entity->getDay()->modify('+1 day');
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(
            [
                // Unchanged or changed in place: targeted by the value read, not by its current database value
                ['v1_slo_label' => 'b', 'w1_slo_day' => '2026-01-01 00:00:00.123456'],
                ['v1_slo_day' => '2026-01-02 00:00:00', 'w1_slo_day' => '2026-01-01 00:00:00.123456'],
            ],
            $params
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAMutableFieldWhoseDatabaseValueIsNotAScalarShouldBeCompared()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $metadata = new Metadata($this->services->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $metadata->setConnectionName('main');
        $metadata->setDatabase('bouh_world');
        $metadata->setTable('T_BOUH_BOO');
        $metadata->addField(['primary' => true, 'autoincrement' => true, 'fieldName' => 'id', 'columnName' => 'boo_id', 'type' => 'int']);
        // No serializer: its database value is the PHP array
        $metadata->addField(['fieldName' => 'roles', 'columnName' => 'boo_roles', 'type' => 'string', 'mutable' => true]);
        $metadataRepository->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $entity = new Bouh();
        $entity->setId(3);
        $unitOfWork->manage($entity);
        $unitOfWork->pushSave($entity)->process();
        $entity->setRoles(['ADMIN']);
        $unitOfWork->pushSave($entity)->process();
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame([['v1_boo_roles' => ['ADMIN'], 'w1_boo_id' => 3]], $params);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testIsPropertyChangedShouldTellWhetherAMutableFieldChanged()
    {
        $entity = $this->createDocument();
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'payload'));

        $unitOfWork->manage($entity);
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'payload'));
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'publishedAt'));

        $entity->getPayload()->tags[] = 'php';
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'payload'));
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'publishedAt'));
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'title'));

        // Written: unchanged afterwards
        $unitOfWork->pushSave($entity)->process();
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'payload'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testInsertingAnEntityWithMutableFieldsShouldRunOnlyTheInsert()
    {
        $entity = $this->createDocument();
        $entity->setId(null);
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);

        $unitOfWork->pushSave($entity)->process();
        // The values inserted are the ones stored
        $unitOfWork->pushSave($entity)->process();
        $entity->getPayload()->tags[] = 'php';
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(['INSERT', 'UPDATE'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(['v1_doc_payload' => '{"tags":["php"]}', 'w1_doc_id' => 1], $params[1]);
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

    /**
     * Only the old value of a primary key is written (it targets the row): the old value of another field is never
     * serialized, a serializer refusing it (a PHP default not read, '' for Ip) does not fail the save or the delete
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testTheOldValueOfAFieldThatIsNotAPrimaryKeyShouldNotBeSerialized()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $metadata = BouhRepository::initMetadata($this->services->serializerFactory());
        $metadata->addField([
            'fieldName'  => 'firstname',
            'columnName' => 'boo_ip',
            'type'       => 'int',
            'serializer' => \CCMBenchmark\Ting\Serializer\Ip::class,
        ]);
        $metadataRepository->addMetadata('BouhIpRepository', $metadata);

        $entity = new Bouh();
        $entity->setId(3);
        $entity->setFirstname('');
        $unitOfWork->manage($entity);
        $entity->setFirstname('10.0.0.1');
        $unitOfWork->pushSave($entity)->process();
        $entity->setFirstname('10.0.0.2');
        $unitOfWork->pushDelete($entity)->process();

        $this->assertSame(
            [
                'UPDATE `T_BOUH_BOO` SET `boo_ip` = :v1_boo_ip WHERE `boo_id` = :w1_boo_id',
                'DELETE FROM `T_BOUH_BOO` WHERE `boo_id` = :w1_boo_id',
            ],
            $queries
        );
        $this->assertSame([['v1_boo_ip' => 167772161, 'w1_boo_id' => 3], ['w1_boo_id' => 3]], $params);
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
                'UPDATE `T_SLOT_SLO` SET `slo_label` = :v1_slo_label WHERE `slo_day` = :w1_slo_day',
                'DELETE FROM `T_SLOT_SLO` WHERE `slo_day` = :w1_slo_day',
            ],
            $queries
        );
        $this->assertSame(
            [
                ['v1_slo_day' => '2026-01-02 00:00:00', 'w1_slo_day' => '2026-01-01 00:00:00'],
                ['v1_slo_label' => 'b', 'w1_slo_day' => '2026-01-02 00:00:00'],
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
    public function testAnInsertShouldKeepTheGeneratedValueOfAMutableAutoincrementKey()
    {
        $entity = new Account();
        $entity->setName('a');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);
        $unitOfWork->pushSave($entity)->process();

        $entity->setName('b');
        $unitOfWork->pushSave($entity)->process();
        $unitOfWork->pushDelete($entity)->process();

        $this->assertSame(1, $entity->getId()?->value);
        $this->assertSame(
            [
                'UPDATE `T_ACCOUNT_ACC` SET `acc_name` = :v1_acc_name WHERE `acc_id` = :w1_acc_id',
                'DELETE FROM `T_ACCOUNT_ACC` WHERE `acc_id` = :w1_acc_id',
            ],
            \array_slice($queries, 1)
        );
        $this->assertSame(
            [['v1_acc_name' => 'b', 'w1_acc_id' => 1], ['w1_acc_id' => 1]],
            \array_slice($params, 1)
        );
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

        $entity->getPayload()->tags[] = 'php';
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'payload'));
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'publishedAt'));
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame([['v1_doc_payload' => '{"tags":["php"]}', 'w1_doc_id' => 1]], $params);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAMutableFieldNotReadShouldBeWrittenOnceSetThroughItsSetter()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $entity = $this->hydrateDocument($unitOfWork, $metadataRepository, ['doc_id' => '1', 'doc_title' => 'title']);

        $entity->setPublishedAt(new \DateTime('2026-02-01 10:00:00'));
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'publishedAt'));
        $unitOfWork->pushSave($entity)->process();
        // Known from now on: written when changed, as a mutable field read
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
    public function testSavingAnEntityReadWithEveryColumnShouldWriteItsMutableFieldsChanged()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $entity = $this->hydrateDocument(
            $unitOfWork,
            $metadataRepository,
            ['doc_id' => '1', 'doc_title' => 'title', 'doc_payload' => null, 'doc_published_at' => '2026-01-01 10:00:00']
        );

        $unitOfWork->pushSave($entity)->process();
        $entity->getPublishedAt()?->modify('+1 day');
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame([['v1_doc_published_at' => '2026-01-02 10:00:00', 'w1_doc_id' => 1]], $params);
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
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'publishedAt'));
        $entity->getPublishedAt()?->modify('+1 hour');
        $entity->setPayload((object) ['tags' => []]);
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(
            [['v1_doc_payload' => '{"tags":[]}', 'v2_doc_published_at' => '2026-02-01 11:00:00', 'w1_doc_id' => 1]],
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

    /**
     * A new Document whose setId() names it after its id when it has no title (notified), and moves its publication
     * one day later (modified in place, not notified); throwing, it throws once those changes are made, the id not set
     */
    private function createDocumentNamedByItsId(bool $throwing = false): Document
    {
        $entity = new class ($throwing) extends Document {
            public function __construct(private readonly bool $throwing)
            {
            }

            public function setId(?int $id): void
            {
                if ($id !== null && $this->getTitle() === null) {
                    $this->setTitle('item-' . $id);
                    $this->getPublishedAt()?->modify('+1 day');
                }
                if ($this->throwing) {
                    throw new \LogicException('Forced failure of setId()');
                }

                parent::setId($id);
            }
        };
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
        $entity = $this->hydrateRow($unitOfWork, $metadataRepository, $columns)['doc'];
        $this->assertInstanceOf(Document::class, $entity);

        return $entity;
    }

    /**
     * Hydrates one row: the columns prefixed with doc_ are those of T_DOCUMENT_DOC (alias "doc"), slo_ those of
     * T_SLOT_SLO (alias "slo"), the others those of T_BOUH_BOO (alias "bouh")
     *
     * @param array<string, string|null> $columns column name => raw value
     * @return array<int|string, object|null>
     */
    private function hydrateRow(UnitOfWork $unitOfWork, MetadataRepository $metadataRepository, array $columns): array
    {
        $fields = [];
        foreach (array_keys($columns) as $column) {
            $field = new \stdClass();
            $field->name     = $column;
            $field->orgname  = $column;
            [$field->table, $field->orgtable] = match (substr($column, 0, 4)) {
                'doc_' => ['doc', 'T_DOCUMENT_DOC'],
                'slo_' => ['slo', 'T_SLOT_SLO'],
                default => ['bouh', 'T_BOUH_BOO'],
            };
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

        return $hydrator->setResult($result)->getIterator()->current();
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

        // Not managed, with its id: its row is updated in the table of the metadata given, and so is the row of a
        // clone of a managed entity
        $queries = [];
        $entity = new Bouh();
        $entity->setId(3);
        $unitOfWork->pushSave($entity, $bouhMetadata)->process();
        $unitOfWork->pushSave(clone $entity, $bouhMetadata)->process();
        $unitOfWork->detach($entity);
        $unitOfWork->pushDelete($entity, $bouhMetadata)->process();
        $this->assertSame(['UPDATE main', 'UPDATE main', 'DELETE main'], $tables());

        // Without metadata, those of the entity: the repository registered last
        $queries = [];
        $entity = new Bouh();
        $unitOfWork->pushSave($entity)->process();
        $unitOfWork->pushDelete($entity)->process();
        $this->assertSame(['INSERT archive', 'DELETE archive'], $tables());
    }

    /**
     * The metadata of a lighter projection of Document: its id and title
     */
    private function lightDocumentMetadata(): Metadata
    {
        $metadata = new Metadata($this->services->serializerFactory());
        $metadata->setEntity(Document::class);
        $metadata->setConnectionName('main');
        $metadata->setDatabase('bouh_world');
        $metadata->setTable('T_DOCUMENT_DOC');
        $metadata->addField(['primary' => true, 'autoincrement' => true, 'fieldName' => 'id', 'columnName' => 'doc_id', 'type' => 'int']);
        $metadata->addField(['fieldName' => 'title', 'columnName' => 'doc_title', 'type' => 'string']);

        return $metadata;
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnEntityReadThroughAProjectionShouldNotWriteTheFieldsTheProjectionDoesNotHave()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $full = DocumentRepository::initMetadata($this->services->serializerFactory());
        $light = $this->lightDocumentMetadata();
        // Registered last for Document
        $metadataRepository->addMetadata('LightDocumentRepository', $light);

        // Read through the projection: publishedAt holds a default set by the constructor, say
        $entity = new Document();
        $entity->setId(1);
        $entity->setTitle('title');
        $entity->setPublishedAt(new \DateTime('2026-01-01 10:00:00'));
        $unitOfWork->manage($entity, $light);
        $entity->setTitle('new');
        $entity->getPublishedAt()?->modify('+1 hour');
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'publishedAt'));
        $unitOfWork->pushSave($entity, $full)->process();

        $this->assertSame([['v1_doc_title' => 'new', 'w1_doc_id' => 1]], $params);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnEntityReadWithEveryFieldShouldBeComparedWithTheMetadataThatReadIt()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $full = DocumentRepository::initMetadata($this->services->serializerFactory());
        // Registered last for Document: it has no mutable field
        $metadataRepository->addMetadata('LightDocumentRepository', $this->lightDocumentMetadata());

        $entity = $this->createDocument();
        $unitOfWork->manage($entity, $full);
        $unitOfWork->pushSave($entity, $full)->process();
        $entity->getPublishedAt()?->modify('+1 hour');
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'publishedAt'));
        $unitOfWork->pushSave($entity, $full)->process();

        $this->assertSame([['v1_doc_published_at' => '2026-01-01 11:00:00', 'w1_doc_id' => 1]], $params);
    }

    /**
     * @return array<string, array{0: bool}>
     */
    public static function provideProjectionRegisteredLast(): array
    {
        return ['full metadata registered last' => [false], 'projection registered last' => [true]];
    }

    #[AllowMockObjectsWithoutExpectations]
    #[DataProvider('provideProjectionRegisteredLast')]
    public function testAMutableFieldWrittenThroughOtherMetadataShouldBeComparedFromThenOn(bool $projectionRegisteredLast)
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $full = DocumentRepository::initMetadata($this->services->serializerFactory());
        $light = $this->lightDocumentMetadata();
        if ($projectionRegisteredLast) {
            $metadataRepository->addMetadata('LightDocumentRepository', $light);
        }

        // Read through the projection, publishedAt set then written through the full metadata: known from now on
        $entity = new Document();
        $entity->setId(1);
        $unitOfWork->manage($entity, $light);
        $entity->setPublishedAt(new \DateTime('2026-01-01 10:00:00'));
        $unitOfWork->pushSave($entity, $full)->process();
        $entity->getPublishedAt()?->modify('+1 day');
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'publishedAt'));
        $unitOfWork->pushSave($entity, $full)->process();

        $this->assertSame(
            [
                ['v1_doc_published_at' => '2026-01-01 10:00:00', 'w1_doc_id' => 1],
                ['v1_doc_published_at' => '2026-01-02 10:00:00', 'w1_doc_id' => 1],
            ],
            $params
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testASaveThroughAProjectionShouldKeepTheChangesItDoesNotWrite()
    {
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params, $metadataRepository);
        $full = DocumentRepository::initMetadata($this->services->serializerFactory());
        $light = $this->lightDocumentMetadata();

        // Another projection: its id and its payload
        $payload = new Metadata($this->services->serializerFactory());
        $payload->setEntity(Document::class);
        $payload->setConnectionName('main');
        $payload->setDatabase('bouh_world');
        $payload->setTable('T_DOCUMENT_DOC');
        $payload->addField(['primary' => true, 'autoincrement' => true, 'fieldName' => 'id', 'columnName' => 'doc_id', 'type' => 'int']);
        $payload->addField(['fieldName' => 'payload', 'columnName' => 'doc_payload', 'type' => 'json']);

        $entity = $this->createDocument();
        $unitOfWork->manage($entity, $full);
        $entity->setTitle('new');
        $entity->setPayload((object) ['tags' => ['a']]);
        $unitOfWork->pushSave($entity, $light)->process();
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'title'));
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'payload'));
        $entity->setTitle('newer');
        $unitOfWork->pushSave($entity, $payload)->process();
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'payload'));
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'title'));
        $unitOfWork->pushSave($entity, $full)->process();

        $this->assertSame(
            [
                ['v1_doc_title' => 'new', 'w1_doc_id' => 1],
                ['v1_doc_payload' => '{"tags":["a"]}', 'w1_doc_id' => 1],
                ['v1_doc_title' => 'newer', 'w1_doc_id' => 1],
            ],
            $params
        );
    }

    /**
     * Builds a UnitOfWork on Bouh, Event, Document, Slot and Account whose queries are recorded instead of executed.
     *
     * @param list<string>|null $queries SQL of each successfully executed query
     * @param string|null       $failOn  executing a query whose SQL contains it throws a QueryException, and
     *                                   getInsertedId() too with 'getInsertedId'
     * @param list<string>|null $closed  names of the closed statements
     * @param list<array<string, mixed>>|null $params parameters of each successfully executed query
     * @param MetadataRepository|null $metadataRepository the metadata repository of the unit of work
     * @param Driver|null       $driver  the driver of every query, whose transactions can be started, committed and
     *                                   rolled back
     */
    private function createRecordingUnitOfWork(
        ?array &$queries,
        ?string &$failOn,
        ?array &$closed,
        ?array &$params = null,
        ?MetadataRepository &$metadataRepository = null,
        ?Driver &$driver = null
    ): UnitOfWork {
        $queries = [];
        $closed = [];
        $params = [];
        $metadataRepository = new MetadataRepository($this->services->serializerFactory());
        $metadataRepository->addMetadata(
            'tests\fixtures\model\BouhRepository',
            BouhRepository::initMetadata($this->services->serializerFactory())
        );
        foreach ([EventRepository::class, DocumentRepository::class, SlotRepository::class, AccountRepository::class] as $repository) {
            $metadataRepository->addMetadata($repository, $repository::initMetadata($this->services->serializerFactory()));
        }

        $connectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['primary'])->getMock();
        $driver = $this->getMockBuilder(Driver::class)
            ->setConstructorArgs([$this->createStub(FakeMysqli::class)])
            ->onlyMethods(['getInsertedId', 'closeStatement'])
            ->getMock();
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
