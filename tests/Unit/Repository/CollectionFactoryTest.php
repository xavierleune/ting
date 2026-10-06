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
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Tests\Support\TingServices;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\FakeDriver\MysqliResult;
use tests\fixtures\model\SameTable\User;
use tests\fixtures\model\SameTable\UserLight;
use tests\fixtures\model\SameTable\UserLightRepository;
use tests\fixtures\model\SameTable\UserRepository;

class CollectionFactoryTest extends TestCase
{
    public function testGetShouldReturnInstanceOfCollection()
    {
        $services = new TingServices();

        $collectionFactory = $services->collectionFactory();
        $this->assertInstanceOf(Collection::class, $collectionFactory->get());
    }

    public function testGetShouldReturnInstanceOfCollectionWithNewHydrator()
    {
        $services = new TingServices();

        $result = new Result();
        $result->setResult($this->createMysqliResult([['a-Bouh']]));
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        $result2 = new Result();
        $result2->setResult($this->createMysqliResult([['b-Bouh']]));
        $result2->setConnectionName('main');
        $result2->setDatabase('bouh_world');

        $collectionFactory = $services->collectionFactory();
        $collection = $collectionFactory->get();
        $collection->set($result);
        $collection2 = $collectionFactory->get();
        $collection2->set($result2);

        $stdClass = $collection2->getIterator()->current()[0];
        $this->assertSame('b-Bouh', $stdClass->name);
        $stdClass = $collection->getIterator()->current()[0];
        $this->assertSame('a-Bouh', $stdClass->name);
    }

    public function testForRepositoryShouldHydrateTheTablesOfTheRepositoryWithItsMetadata()
    {
        $services = new TingServices();
        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model\SameTable',
            __DIR__ . '/../../fixtures/model/SameTable/*Repository.php'
        );
        $sharedFactory = $services->collectionFactory();

        $users = $sharedFactory->forRepository(UserRepository::class);
        $lightUsers = $sharedFactory->forRepository(UserLightRepository::class);

        $collection = $users->get();
        $collection->set($this->createUserResult());
        $this->assertInstanceOf(User::class, $collection->first()['user']);

        $collection = $lightUsers->get(new HydratorSingleObject());
        $collection->set($this->createUserResult());
        $this->assertInstanceOf(UserLight::class, $collection->first());

        // The shared factory is left as is: two repositories map the table of the same database
        $collection = $sharedFactory->get();
        $collection->set($this->createUserResult());
        $exception = $this->assertThrows(HydratorException::class, fn () => $collection->first());
        $this->assertStringStartsWith('Cannot choose the metadata of the table "user"', $exception->getMessage());

        // A hydrator of your own, for a query run elsewhere
        $hydrator = $services->hydrator()->preferRepository(UserLightRepository::class);
        $collection = $sharedFactory->get($hydrator);
        $collection->set($this->createUserResult());
        $this->assertInstanceOf(UserLight::class, $collection->first()['user']);
    }

    /**
     * The repository running the query prefers its metadata among those of the database and the schema read: it
     * does not override objectDatabaseIs() (a join on the same table of another database)
     */
    public function testForRepositoryShouldNotOverrideTheDatabaseOfTheAlias()
    {
        $services = $this->servicesWithSameTableRepositories();
        $archive = new Metadata($services->serializerFactory());
        $archive->setEntity(UserLight::class);
        $archive->setConnectionName('main');
        $archive->setDatabase('bouh_archive');
        $archive->setTable('user');
        $archive->addField(['primary' => true, 'fieldName' => 'id', 'columnName' => 'id', 'type' => 'int']);
        $services->metadataRepository()->addMetadata('ArchivedUsers', $archive);

        $users = $services->collectionFactory()->forRepository(UserRepository::class);
        $hydrator = $services->hydrator()->objectDatabaseIs('user', 'bouh_archive');
        $collection = $users->get($hydrator);
        $collection->set($this->createUserResult());

        $this->assertInstanceOf(UserLight::class, $collection->first()['user']);
    }

    public function testAPreferenceOfTheHydratorShouldWinOverTheOneOfTheRepository()
    {
        $services = $this->servicesWithSameTableRepositories();
        $users = $services->collectionFactory()->forRepository(UserRepository::class);

        $collection = $users->get((new HydratorSingleObject())->preferRepository(UserLightRepository::class));
        $collection->set($this->createUserResult());

        $this->assertInstanceOf(UserLight::class, $collection->first());
    }

    /**
     * A hydrator kept and given to the collections of several repositories (a service property) prefers the
     * repository of each collection, not every repository it has been given to
     */
    public function testAHydratorGivenToSeveralRepositoriesShouldPreferTheLastOne()
    {
        $services = $this->servicesWithSameTableRepositories();
        $sharedFactory = $services->collectionFactory();
        $hydrator = new HydratorSingleObject();

        foreach ([UserRepository::class => User::class, UserLightRepository::class => UserLight::class] as $repository => $entity) {
            $collection = $sharedFactory->forRepository($repository)->get($hydrator);
            $collection->set($this->createUserResult());
            $this->assertInstanceOf($entity, $collection->first(), $repository);
        }

        // Given to the shared factory, it prefers none of them
        $collection = $sharedFactory->get($hydrator);
        $collection->set($this->createUserResult());
        $this->assertThrows(HydratorException::class, fn () => $collection->first());
    }

    private function servicesWithSameTableRepositories(): TingServices
    {
        $services = new TingServices();
        $services->metadataRepository()->batchLoadMetadata(
            'tests\fixtures\model\SameTable',
            __DIR__ . '/../../fixtures/model/SameTable/*Repository.php'
        );

        return $services;
    }

    private function createUserResult(): Result
    {
        $mysqliResult = new MysqliResult([[1, 'Ann', 'ann@example.com']]);
        $mysqliResult->setFieldsCallback(function () {
            $fields = [];
            $columns = ['id' => MYSQLI_TYPE_LONG, 'name' => MYSQLI_TYPE_VAR_STRING, 'email' => MYSQLI_TYPE_VAR_STRING];
            foreach ($columns as $column => $type) {
                $field = new \stdClass();
                $field->name     = $column;
                $field->orgname  = $column;
                $field->table    = 'user';
                $field->orgtable = 'user';
                $field->type     = $type;
                $fields[] = $field;
            }
            return $fields;
        });

        $result = new Result();
        $result->setResult($mysqliResult);
        $result->setConnectionName('main');
        $result->setDatabase('bouh_world');

        return $result;
    }

    /**
     * Fake mysqli result describing a single bouh.name column
     */
    private function createMysqliResult(array $data): MysqliResult
    {
        $mockMysqliResult = new MysqliResult($data);
        $mockMysqliResult->setFieldsCallback(function () {
            $fields = [];
            $stdClass = new \stdClass();
            $stdClass->name     = 'name';
            $stdClass->orgname  = 'boo_name';
            $stdClass->table    = 'bouh';
            $stdClass->orgtable = 'T_BOUH_BOO';
            $stdClass->type     = MYSQLI_TYPE_VAR_STRING;
            $fields[] = $stdClass;
            return $fields;
        });

        return $mockMysqliResult;
    }
}
