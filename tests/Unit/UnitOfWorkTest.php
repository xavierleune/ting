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
use CCMBenchmark\Ting\Driver\QueryException;
use CCMBenchmark\Ting\Exception;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Query\PreparedQuery;
use CCMBenchmark\Ting\Query\QueryFactory;
use CCMBenchmark\Ting\Tests\Support\TingServices;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use CCMBenchmark\Ting\UnitOfWork;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use tests\fixtures\model\Bouh;
use tests\fixtures\model\BouhRepository;
use tests\fixtures\model\Event;
use tests\fixtures\model\EventRepository;

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
        $unitOfWork->propertyChanged($mockEntity, 'firstname', 'Sylvain', 'Sylvain 2');
        $this->assertTrue($unitOfWork->isPropertyChanged($mockEntity, 'firstname'));
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

    public function testRemove()
    {
        $mockEntity = new Bouh();

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
                ['UPDATE `T_BOUH_BOO` SET `boo_name` = :boo_name WHERE `boo_id` = :#boo_id', ['boo_name' => 'newName', '#boo_id' => 3]],
                ['DELETE FROM `T_BOUH_BOO` WHERE `boo_id` = :#boo_id', ['#boo_id' => 3]],
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

        $this->assertSame(['UPDATE `T_BOUH_BOO` SET `boo_name` = :boo_name WHERE `boo_id` = :#boo_id'], $queries);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingADateTimeModifiedInPlaceShouldUpdateIt()
    {
        $entity = $this->createEvent(1, '2026-01-01 10:00:00');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);
        $unitOfWork->manage($entity);

        // The old value given to propertyChanged() is the object already modified
        $startAt = $entity->getStartAt();
        $startAt->modify('+1 day');
        $entity->setStartAt($startAt);
        $this->assertTrue($unitOfWork->isPropertyChanged($entity, 'startAt'));
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(['UPDATE `T_EVENT_EVT` SET `evt_start_at` = :evt_start_at WHERE `evt_id` = :#evt_id'], $queries);
        $this->assertSame([['evt_start_at' => '2026-01-02 10:00:00', '#evt_id' => 1]], $params);
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'startAt'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSavingADateTimeReplacedByAnEqualOneShouldRunNoQuery()
    {
        $entity = $this->createEvent(1, '2026-01-01 10:00:00');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $unitOfWork->manage($entity);

        $entity->setStartAt(new \DateTime('2026-01-01 10:00:00'));
        $this->assertFalse($unitOfWork->isPropertyChanged($entity, 'startAt'));
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame([], $queries);
        $this->assertFalse($unitOfWork->shouldBePersisted($entity));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnUpdateShouldRefreshTheDatabaseValuesOfTheEntity()
    {
        $entity = $this->createEvent(1, '2026-01-01 10:00:00');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);
        $unitOfWork->manage($entity);

        $entity->setStartAt(new \DateTime('2026-01-02 10:00:00'));
        $unitOfWork->pushSave($entity)->process();
        // Equal to the value written by the UPDATE
        $entity->setStartAt(new \DateTime('2026-01-02 10:00:00'));
        $unitOfWork->pushSave($entity)->process();
        // Back to the value read at first
        $entity->getStartAt()->modify('-1 day');
        $entity->setStartAt($entity->getStartAt());
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(
            [['evt_start_at' => '2026-01-02 10:00:00', '#evt_id' => 1], ['evt_start_at' => '2026-01-01 10:00:00', '#evt_id' => 1]],
            $params
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testAnInsertShouldTakeTheDatabaseValuesOfTheEntity()
    {
        $entity = $this->createEvent(null, '2026-01-01 10:00:00');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed, $params);
        $unitOfWork->pushSave($entity)->process();

        $entity->setStartAt(new \DateTime('2026-01-01 10:00:00'));
        $unitOfWork->pushSave($entity)->process();
        $entity->getStartAt()->modify('+1 hour');
        $entity->setStartAt($entity->getStartAt());
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame(['INSERT', 'UPDATE'], array_map(fn (string $sql): string => strtok($sql, ' '), $queries));
        $this->assertSame(['evt_start_at' => '2026-01-01 11:00:00', '#evt_id' => 1], $params[1]);
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
                ['boo_id' => 4, 'boo_name' => 'name', '#boo_id' => 3],
                ['boo_name' => 'other', '#boo_id' => 4],
                ['#boo_id' => 4],
            ],
            $params
        );
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDetachShouldForgetTheDatabaseValuesOfTheEntity()
    {
        $entity = $this->createEvent(1, '2026-01-01 10:00:00');
        $unitOfWork = $this->createRecordingUnitOfWork($queries, $failOn, $closed);
        $unitOfWork->manage($entity);
        $unitOfWork->detach($entity);

        // Read again from the database, as it is now
        $entity->setStartAt(new \DateTime('2026-01-02 10:00:00'));
        $unitOfWork->manage($entity);
        $entity->setStartAt(new \DateTime('2026-01-02 10:00:00'));
        $unitOfWork->pushSave($entity)->process();

        $this->assertSame([], $queries);
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
        $entity->setStartAt(new \DateTime($startAt));

        return $entity;
    }

    /**
     * Builds a UnitOfWork on Bouh and Event whose queries are recorded instead of executed.
     *
     * @param list<string>|null $queries SQL of each successfully executed query
     * @param string|null       $failOn  executing a query whose SQL contains it throws a QueryException
     * @param list<string>|null $closed  names of the closed statements
     * @param list<array<string, mixed>>|null $params parameters of each successfully executed query
     */
    private function createRecordingUnitOfWork(
        ?array &$queries,
        ?string &$failOn,
        ?array &$closed,
        ?array &$params = null
    ): UnitOfWork {
        $queries = [];
        $closed = [];
        $params = [];
        $metadataRepository = new MetadataRepository($this->services->serializerFactory());
        $metadataRepository->addMetadata(
            'tests\fixtures\model\BouhRepository',
            BouhRepository::initMetadata($this->services->serializerFactory())
        );
        $metadataRepository->addMetadata(
            EventRepository::class,
            EventRepository::initMetadata($this->services->serializerFactory())
        );

        $connectionPool = $this->getMockBuilder(ConnectionPool::class)->onlyMethods(['primary'])->getMock();
        $driver = $this->getMockBuilder(Driver::class)->onlyMethods(['getInsertedId', 'closeStatement'])->getMock();
        $driver->method('getInsertedId')->willReturn(1);
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
