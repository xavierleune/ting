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

use CCMBenchmark\Ting\Exceptions\HydratorException;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Tests\Support\TingServices;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\model\Bouh;
use tests\fixtures\model\BouhRepository;

class MetadataRepositoryTest extends TestCase
{
    public function testFindMetadataForEntityShouldCallCallbackFound()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity('tests\fixtures\model\Bouh');

        $metadataRepository = new MetadataRepository($services->serializerFactory());
        $metadataRepository->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $entity = new Bouh();

        $metadataRepository->findMetadataForEntity(
            $entity,
            function ($metadata) use (&$outerCallbackFound): void {
                $outerCallbackFound = true;
            },
            function () use (&$outerCallbackNotFound): void {
                $outerCallbackNotFound = true;
            }
        );
        $this->assertTrue($outerCallbackFound);
        $this->assertNull($outerCallbackNotFound);
    }

    public function testFindMetadataForClassStringShouldCallCallbackFound()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);

        $metadataRepository = new MetadataRepository($services->serializerFactory());
        $metadataRepository->addMetadata(BouhRepository::class, $metadata);

        $metadataRepository->findMetadataForEntity(
            Bouh::class,
            function ($metadata) use (&$outerCallbackFound): void {
                $outerCallbackFound = true;
            },
            function () use (&$outerCallbackNotFound): void {
                $outerCallbackNotFound = true;
            }
        );
        $this->assertTrue($outerCallbackFound);
        $this->assertNull($outerCallbackNotFound);
    }

    public function testFindMetadataForEntityShouldCallCallbackNotFound()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity('tests\fixtures\model\Bouh');

        $metadataRepository = new MetadataRepository($services->serializerFactory());
        $metadataRepository->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        // Any entity of a class without metadata (atoum generated a "mock\tests\fixtures\model\Bouh2" class)
        $entity = new \stdClass();

        $metadataRepository->findMetadataForEntity(
            $entity,
            function ($metadata) use (&$outerCallbackFound): void {
                $outerCallbackFound = true;
            },
            function () use (&$outerCallbackNotFound): void {
                $outerCallbackNotFound = true;
            }
        );
        $this->assertTrue($outerCallbackNotFound);
        $this->assertNull($outerCallbackFound);
    }

    public function testAddMetadataWithoutEntityShouldNotRegisterAnEntity()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setTable('T_BOUH_BOO');

        $metadataRepository = new MetadataRepository($services->serializerFactory());

        $this->assertSame([], $this->collectErrorTypes(function () use ($metadataRepository, $metadata): void {
            $metadataRepository->addMetadata('tests\fixtures\model\BouhRepository', $metadata);
        }));
        $this->assertSame([], $metadataRepository->getAllEntities());
    }

    public function testFindMetadataForTableShouldCallCallbackFound()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setTable('T_BOUH_BOO');

        $metadataRepository = new MetadataRepository($services->serializerFactory());
        $metadataRepository->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadataRepository->findMetadataForTable(
            'connectionName',
            'database',
            '',
            'T_BOUH_BOO',
            function ($metadata) use (&$outerCallbackFound): void {
                $outerCallbackFound = true;
            },
            function () use (&$outerCallbackNotFound): void {
                $outerCallbackNotFound = true;
            }
        );
        $this->assertTrue($outerCallbackFound);
        $this->assertNull($outerCallbackNotFound);
    }

    public function testFindMetadataForTableShouldCallCallbackNotFound()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setTable('T_BOUH_BOO');

        $metadataRepository = new MetadataRepository($services->serializerFactory());
        $metadataRepository->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadataRepository->findMetadataForTable(
            'connectionName',
            'database',
            '',
            'T_BOUH2_BOO',
            function ($metadata) use (&$outerCallbackFound): void {
                $outerCallbackFound = true;
            },
            function () use (&$outerCallbackNotFound): void {
                $outerCallbackNotFound = true;
            }
        );
        $this->assertTrue($outerCallbackNotFound);
        $this->assertNull($outerCallbackFound);
    }

    public function testFindMetadataForTableWithRightSchemaShouldCallCallbackFound()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setTable('T_BOUH_BOO');
        $metadata->setSchema('schemaName');

        $metadataRepository = new MetadataRepository($services->serializerFactory());
        $metadataRepository->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadataRepository->findMetadataForTable(
            'connectionName',
            'database',
            'schemaName',
            'T_BOUH_BOO',
            function ($metadata) use (&$outerCallbackFound): void {
                $outerCallbackFound = true;
            },
            function () use (&$outerCallbackNotFound): void {
                $outerCallbackNotFound = true;
            }
        );
        $this->assertTrue($outerCallbackFound);
        $this->assertNull($outerCallbackNotFound);
    }

    public function testFindMetadataForTableWithWrongSchemaShouldCallCallbackNotFound()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setTable('T_BOUH_BOO');
        $metadata->setSchema('SchemaName');

        $metadataRepository = new MetadataRepository($services->serializerFactory());
        $metadataRepository->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadataRepository->findMetadataForTable(
            'connectionName',
            'database',
            'otherSchema',
            'T_BOUH2_BOO',
            function ($metadata) use (&$outerCallbackFound): void {
                $outerCallbackFound = true;
            },
            function () use (&$outerCallbackNotFound): void {
                $outerCallbackNotFound = true;
            }
        );
        $this->assertTrue($outerCallbackNotFound);
        $this->assertNull($outerCallbackFound);
    }

    public function testBatchLoadMetadataShouldCallInitMetadataWithDefaultOptions()
    {
        $services = new TingServices();
        $metadataRepository = $services->metadataRepository();
        $metadataRepository->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../fixtures/model/*Repository.php',
            ['default' => ['connection' => 'connectionName', 'database' => 'databaseName']]
        );
        $bouhRepository = $services->repositoryFactory()->get('\tests\fixtures\model\BouhRepository');
        $this->assertIsObject($bouhRepository);
        $this->assertSame(
            ['connection' => 'connectionName', 'database' => 'databaseName'],
            $bouhRepository::$options
        );
    }

    public function testBatchLoadMetadataShouldCallInitMetadataWithDefaultAndRepositoryOptions()
    {
        $services = new TingServices();
        $metadataRepository = $services->metadataRepository();
        $metadataRepository->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../fixtures/model/*Repository.php',
            [
                'default' => ['connection' => 'connectionName', 'database' => 'databaseName'],
                'tests\fixtures\model\BouhRepository' => ['database' => 'dbBouh']
            ]
        );
        $bouhRepository = $services->repositoryFactory()->get('\tests\fixtures\model\BouhRepository');
        $this->assertIsObject($bouhRepository);
        $this->assertSame(['connection' => 'connectionName', 'database' => 'dbBouh'], $bouhRepository::$options);
    }

    public function testBatchLoadMetadataShouldCallInitMetadataWithRepositoryOptions()
    {
        $services = new TingServices();
        $metadataRepository = $services->metadataRepository();
        $metadataRepository->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../fixtures/model/*Repository.php',
            ['tests\fixtures\model\BouhRepository' => ['connection' => 'conBouh', 'database' => 'dbBouh']]
        );
        $bouhRepository = $services->repositoryFactory()->get('\tests\fixtures\model\BouhRepository');
        $this->assertIsObject($bouhRepository);
        $this->assertSame(['connection' => 'conBouh', 'database' => 'dbBouh'], $bouhRepository::$options);
    }

    public function testBatchLoadMetadataShouldLoad5Repositories()
    {
        $services = new TingServices();
        $metadataRepository = new MetadataRepository(
            $services->serializerFactory()
        );
        $this->assertSame(
            [
                'tests\fixtures\model\BouhMySchemaRepository'                => 'tests\fixtures\model\BouhMySchemaRepository',
                'tests\fixtures\model\BouhReadOnlyRepository'                => 'tests\fixtures\model\BouhReadOnlyRepository',
                'tests\fixtures\model\BouhRepository'                        => 'tests\fixtures\model\BouhRepository',
                'tests\fixtures\model\CityRepository'                        => 'tests\fixtures\model\CityRepository',
                'tests\fixtures\model\CitySecondRepository'                  => 'tests\fixtures\model\CitySecondMetadataRepository',
                'tests\fixtures\model\CityWithPublicPropertiesRepository'    => 'tests\fixtures\model\CityWithPublicPropertiesRepository',
                'tests\fixtures\model\CountryWithPublicPropertiesRepository' => 'tests\fixtures\model\CountryWithPublicPropertiesRepository',
                'tests\fixtures\model\DocumentRepository'                    => 'tests\fixtures\model\DocumentRepository',
                'tests\fixtures\model\EventRepository'                       => 'tests\fixtures\model\EventRepository',
                'tests\fixtures\model\ParkRepository'                        => 'tests\fixtures\model\ParkRepository',
                'tests\fixtures\model\SlotRepository'                        => 'tests\fixtures\model\SlotRepository',
            ],
            $metadataRepository->batchLoadMetadata(
                'tests\fixtures\model',
                __DIR__ . '/../fixtures/model/*Repository.php'
            )
        );
    }

    public function testBatchLoadMetadataWithInvalidPathShouldReturnEmptyArray()
    {
        $services = new TingServices();
        $metadataRepository = new MetadataRepository(
            $services->serializerFactory()
        );
        $result = $metadataRepository->batchLoadMetadata(
            'tests\fixtures\model',
            '/not/valid/path/*Repository.php'
        );
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testBatchLoadMetadataFromCacheShouldLoad1Repository()
    {
        // As returned by batchLoadMetadata(): the class initializing the metadata of each repository
        $paths = ['tests\fixtures\model\BouhRepository' => 'tests\fixtures\model\BouhRepository'];
        $services = new TingServices();
        $metadataRepository = new MetadataRepository(
            $services->serializerFactory()
        );
        $result = $metadataRepository->batchLoadMetadataFromCache($paths);
        $this->assertSame(['tests\fixtures\model\BouhRepository'], $result);

        $found = null;
        $metadataRepository->findMetadataForRepository(
            BouhRepository::class,
            function (Metadata $metadata) use (&$found): void {
                $found = $metadata;
            }
        );
        $this->assertSame('T_BOUH_BOO', $found?->getTable());
    }

    public function testBatchLoadMetadataForRepositoryWhichNotImplementMetadataInitializerShouldDoNothing()
    {
        $services = new TingServices();
        $metadataRepository = new MetadataRepository(
            $services->serializerFactory()
        );
        $result = $metadataRepository->batchLoadMetadata(
            'tests\fixtures\model',
            '/not/valid/path/NoMetadataRepository.php'
        );
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindMetadataForOtherConnectionShouldCallCallbackNotFound()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setConnectionName('connection2');
        $metadata->setDatabase('bouh_world');
        $metadata->setTable('bouh');

        $metadataRepository = new MetadataRepository($services->serializerFactory());
        $metadataRepository->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadataRepository->findMetadataForTable(
            'connection',
            'bouh_world',
            '',
            'bouh',
            function ($metadata) use (&$outerCallbackFound): void {
                $outerCallbackFound = true;
            },
            function () use (&$outerCallbackNotFound): void {
                $outerCallbackNotFound = true;
            }
        );
        $this->assertNull($outerCallbackFound);
        $this->assertTrue($outerCallbackNotFound);
    }

    public function testFindMetadataForOtherDatabaseShouldFailbackAndCallCallbackFound()
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setConnectionName('connection');
        $metadata->setDatabase('bouh_world_2');
        $metadata->setTable('bouh');

        $metadataRepository = new MetadataRepository($services->serializerFactory());
        $metadataRepository->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadataRepository->findMetadataForTable(
            'connection',
            'bouh_world',
            '',
            'bouh',
            function ($metadata) use (&$outerCallbackFound): void {
                $outerCallbackFound = true;
            },
            function () use (&$outerCallbackNotFound): void {
                $outerCallbackNotFound = true;
            }
        );
        $this->assertTrue($outerCallbackFound);
        $this->assertNull($outerCallbackNotFound);
    }

    public function testFindMetadataForRightDatabaseShouldCallCallbackFound()
    {
        $services = new TingServices();

        $metadataRepository = new MetadataRepository($services->serializerFactory());

        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setConnectionName('connection');
        $metadata->setDatabase('bouh_world');
        $metadata->setTable('bouh');
        $metadataRepository->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity('tests\fixtures\model\Bouh2');
        $metadata->setConnectionName('connection');
        $metadata->setDatabase('bouh_world_2');
        $metadata->setTable('bouh');
        $metadataRepository->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadataRepository->findMetadataForTable(
            'connection',
            'bouh_world_2',
            '',
            'bouh',
            function ($metadata) use (&$outerMetadata, &$outerCallbackFound): void {
                $outerMetadata = $metadata;
                $outerCallbackFound = true;
            },
            function () use (&$outerCallbackNotFound): void {
                $outerCallbackNotFound = true;
            }
        );
        $this->assertTrue($outerCallbackFound);
        $this->assertNull($outerCallbackNotFound);
        $this->assertSame('tests\fixtures\model\Bouh2', $outerMetadata->getEntity());
    }

    /**
     * Registers one metadata per [repository, database, schema] for the table T_CITY_CIT of the connection main,
     * its entity being the repository name followed by "Entity"
     *
     * @param list<array{string, string, string}> $definitions
     */
    private function metadataRepositoryFor(array $definitions): MetadataRepository
    {
        $services = new TingServices();
        $metadataRepository = new MetadataRepository($services->serializerFactory());
        foreach ($definitions as [$repository, $database, $schema]) {
            $metadata = new Metadata($services->serializerFactory());
            $metadata->setEntity($repository . 'Entity');
            $metadata->setConnectionName('main');
            $metadata->setDatabase($database);
            $metadata->setSchema($schema);
            $metadata->setTable('T_CITY_CIT');
            $metadataRepository->addMetadata($repository, $metadata);
        }

        return $metadataRepository;
    }

    /**
     * @return string|null the entity of the metadata found, null when the not found callback is called
     */
    private function entityFoundForTable(
        MetadataRepository $metadataRepository,
        string $database,
        string $schema,
        string $table = 'T_CITY_CIT'
    ): ?string {
        $entity = 'nothing called';
        $metadataRepository->findMetadataForTable(
            'main',
            $database,
            $schema,
            $table,
            function (Metadata $metadata) use (&$entity): void {
                $entity = $metadata->getEntity();
            },
            function () use (&$entity): void {
                $entity = null;
            }
        );

        return $entity;
    }

    public function testFindMetadataForTableShouldPreferTheExactDatabaseAndSchema(): void
    {
        $metadataRepository = $this->metadataRepositoryFor([
            ['City', 'bouh_world', ''],
            ['CitySecond', 'bouh_world_2', ''],
        ]);

        $this->assertSame('CityEntity', $this->entityFoundForTable($metadataRepository, 'bouh_world', ''));
        $this->assertSame('CitySecondEntity', $this->entityFoundForTable($metadataRepository, 'bouh_world_2', ''));
    }

    public function testFindMetadataForTableShouldFallBackToTheOnlyCandidate(): void
    {
        // MySQL cross-database read (fields carry no database) or PostgreSQL query without schema
        $metadataRepository = $this->metadataRepositoryFor([['CitySecond', 'bouh_world_2', 'mySchema']]);

        $this->assertSame('CitySecondEntity', $this->entityFoundForTable($metadataRepository, 'bouh_world', ''));
        $this->assertSame('CitySecondEntity', $this->entityFoundForTable($metadataRepository, 'other', 'other'));
    }

    public function testFindMetadataForTableShouldCallCallbackNotFoundWithoutCandidate(): void
    {
        $metadataRepository = $this->metadataRepositoryFor([
            ['City', 'bouh_world', ''],
            ['CitySecond', 'bouh_world_2', ''],
        ]);

        $this->assertNull($this->entityFoundForTable($metadataRepository, 'bouh_world', '', 'T_UNKNOWN'));
    }

    public function testFindMetadataForTableShouldThrowWhenSeveralCandidatesMatch(): void
    {
        $definitions = [
            ['City', 'bouh_world', ''],
            ['CitySecond', 'bouh_world_2', ''],
        ];

        // The registration order must not choose the class
        foreach ([$definitions, array_reverse($definitions)] as $registered) {
            $metadataRepository = $this->metadataRepositoryFor($registered);

            $exception = $this->assertThrows(
                HydratorException::class,
                fn () => $this->entityFoundForTable($metadataRepository, 'bouh_world_3', '')
            );
            $this->assertStringContainsString('T_CITY_CIT', $exception->getMessage());
            $this->assertStringContainsString('"main"', $exception->getMessage());
            $this->assertStringContainsString('"bouh_world_3"', $exception->getMessage());
            $this->assertStringContainsString('City (database "bouh_world"', $exception->getMessage());
            $this->assertStringContainsString('CitySecond (database "bouh_world_2"', $exception->getMessage());
            $this->assertStringContainsString('objectDatabaseIs()', $exception->getMessage());
            $this->assertStringContainsString('objectSchemaIs()', $exception->getMessage());
        }
    }

    public function testFindMetadataForTableShouldPickTheOnlyCandidateOfTheSameDatabase(): void
    {
        // PostgreSQL query without schema: the schema read is '', the database is the one of the connection
        $metadataRepository = $this->metadataRepositoryFor([
            ['City', 'bouh_world', 'mySchema'],
            ['CitySecond', 'bouh_world_2', 'mySchema'],
        ]);

        $this->assertSame('CityEntity', $this->entityFoundForTable($metadataRepository, 'bouh_world', ''));
        $this->assertSame('CitySecondEntity', $this->entityFoundForTable($metadataRepository, 'bouh_world_2', ''));
    }

    public function testFindMetadataForTableShouldThrowWhenSeveralCandidatesShareTheDatabase(): void
    {
        $metadataRepository = $this->metadataRepositoryFor([
            ['City', 'bouh_world', 'schema1'],
            ['CitySecond', 'bouh_world', 'schema2'],
            ['CityThird', 'bouh_world_2', 'schema3'],
        ]);

        // The same schema in another database does not win over the candidates of the same database
        $this->assertThrows(
            HydratorException::class,
            fn () => $this->entityFoundForTable($metadataRepository, 'bouh_world', 'schema3')
        );
    }

    public function testFindMetadataForTableShouldPickTheOnlyCandidateOfTheSameSchema(): void
    {
        // MySQL cross-database read: the database read is the one of the repository running the query
        $metadataRepository = $this->metadataRepositoryFor([
            ['City', 'bouh_world', 'schema1'],
            ['CitySecond', 'bouh_world_2', 'schema2'],
        ]);

        $this->assertSame('CitySecondEntity', $this->entityFoundForTable($metadataRepository, 'bouh_world_3', 'schema2'));
        $this->assertSame('CitySecondEntity', $this->entityFoundForTable($metadataRepository, 'bouh_world_3', 'SCHEMA2'));
        $this->assertThrows(
            HydratorException::class,
            fn () => $this->entityFoundForTable($metadataRepository, 'bouh_world_3', 'schema3')
        );
    }

    public function testFindMetadataForTableShouldCompareSchemasCaseInsensitively(): void
    {
        // Pgsql\Result lowercases the schemas read in the query
        $metadataRepository = $this->metadataRepositoryFor([
            ['City', 'bouh_world', ''],
            ['CityMySchema', 'bouh_world', 'mySchema'],
            ['CityOtherSchema', 'bouh_world', 'otherSchema'],
        ]);

        $this->assertSame('CityMySchemaEntity', $this->entityFoundForTable($metadataRepository, 'bouh_world', 'myschema'));
        $this->assertSame('CityMySchemaEntity', $this->entityFoundForTable($metadataRepository, 'bouh_world', 'mySchema'));
        $this->assertSame('CityEntity', $this->entityFoundForTable($metadataRepository, 'bouh_world', ''));
    }

    public function testFindMetadataForTableShouldPreferTheExactSchemaCase(): void
    {
        $metadataRepository = $this->metadataRepositoryFor([
            ['CityLower', 'bouh_world', 'myschema'],
            ['CityMixed', 'bouh_world', 'mySchema'],
        ]);

        $this->assertSame('CityLowerEntity', $this->entityFoundForTable($metadataRepository, 'bouh_world', 'myschema'));
        $this->assertSame('CityMixedEntity', $this->entityFoundForTable($metadataRepository, 'bouh_world', 'mySchema'));
        $this->assertThrows(
            HydratorException::class,
            fn () => $this->entityFoundForTable($metadataRepository, 'bouh_world', 'MYSCHEMA')
        );
    }
}
