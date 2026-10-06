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
use CCMBenchmark\Ting\Repository\Repository;
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

    public function testBatchLoadMetadataShouldGiveTheMetadataTheRepositoryTheyAreRegisteredUnder(): void
    {
        $services = new TingServices();
        $metadataRepository = new MetadataRepository($services->serializerFactory());
        $loaded = $metadataRepository->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../fixtures/model/*Repository.php'
        );

        foreach (array_keys($loaded) as $class) {
            // A class giving metadata for hydration only is not a repository
            $expected = is_subclass_of($class, Repository::class) ? $class : null;
            $metadataRepository->findMetadataForRepository(
                $class,
                function (Metadata $metadata) use ($expected): void {
                    $this->assertSame($expected, $metadata->getRepository());
                }
            );
        }
        $this->assertSame(
            BouhRepository::class,
            $this->repositoryOf($metadataRepository, BouhRepository::class)
        );
        $this->assertNull(
            $this->repositoryOf($metadataRepository, \tests\fixtures\model\CityWithPublicPropertiesRepository::class)
        );
    }

    private function repositoryOf(MetadataRepository $metadataRepository, string $class): ?string
    {
        $repository = 'not found';
        $metadataRepository->findMetadataForRepository(
            $class,
            function (Metadata $metadata) use (&$repository): void {
                $repository = $metadata->getRepository();
            }
        );

        return $repository;
    }

    public function testBatchLoadMetadataFromCacheShouldGiveTheMetadataTheirRepository(): void
    {
        $services = new TingServices();
        $metadataRepository = new MetadataRepository($services->serializerFactory());
        $metadataRepository->batchLoadMetadataFromCache([BouhRepository::class => BouhRepository::class]);

        $repository = null;
        $metadataRepository->findMetadataForEntity(
            Bouh::class,
            function (Metadata $metadata) use (&$repository): void {
                $repository = $metadata->getRepository();
            }
        );
        $this->assertSame(BouhRepository::class, $repository);
    }

    public function testAddMetadataUnderAClassThatIsNotARepositoryShouldNotSetTheRepository(): void
    {
        $services = new TingServices();
        $metadata = new Metadata($services->serializerFactory());
        $metadata->setEntity(Bouh::class);
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setTable('T_BOUH_BOO');

        (new MetadataRepository($services->serializerFactory()))->addMetadata(Bouh::class, $metadata);

        $this->assertNull($metadata->getRepository());
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
     * its entity being the repository name followed by "Entity" unless given, with the columns given (none by default)
     *
     * @param list<array{0: string, 1: string, 2: string, 3?: string, 4?: list<string>}> $definitions
     */
    private function metadataRepositoryFor(array $definitions): MetadataRepository
    {
        $services = new TingServices();
        $metadataRepository = new MetadataRepository($services->serializerFactory());
        foreach ($definitions as $definition) {
            [$repository, $database, $schema] = $definition;
            $metadata = new Metadata($services->serializerFactory());
            $metadata->setEntity($definition[3] ?? $repository . 'Entity');
            $metadata->setConnectionName('main');
            $metadata->setDatabase($database);
            $metadata->setSchema($schema);
            $metadata->setTable('T_CITY_CIT');
            foreach ($definition[4] ?? [] as $column) {
                $metadata->addField(['fieldName' => $column, 'columnName' => $column, 'type' => 'string']);
            }
            $metadataRepository->addMetadata($repository, $metadata);
        }

        return $metadataRepository;
    }

    /**
     * @param list<list<string>> $preferredRepositories groups of repositories, by priority
     * @return string|null the entity of the metadata found, null when the not found callback is called
     */
    private function entityFoundForTable(
        MetadataRepository $metadataRepository,
        string $database,
        string $schema,
        string $table = 'T_CITY_CIT',
        array $preferredRepositories = []
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
            },
            $preferredRepositories
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

    public function testFindMetadataForTableShouldThrowWhenSeveralRepositoriesShareTheDatabaseAndTheSchema(): void
    {
        // A full entity and a lighter projection of the same table
        $definitions = [
            ['User', 'bouh_world', ''],
            ['UserLight', 'bouh_world', ''],
            ['UserArchive', 'bouh_world_2', ''],
        ];

        // The last registered must not win
        foreach ([$definitions, array_reverse($definitions)] as $registered) {
            $metadataRepository = $this->metadataRepositoryFor($registered);

            $exception = $this->assertThrows(
                HydratorException::class,
                fn () => $this->entityFoundForTable($metadataRepository, 'bouh_world', '')
            );
            $this->assertStringContainsString('User (database "bouh_world"', $exception->getMessage());
            $this->assertStringContainsString('UserLight (database "bouh_world"', $exception->getMessage());
            $this->assertStringNotContainsString('UserArchive', $exception->getMessage());
            $this->assertStringContainsString('preferRepository()', $exception->getMessage());
            $this->assertSame('UserArchiveEntity', $this->entityFoundForTable($metadataRepository, 'bouh_world_2', ''));
        }
    }

    public function testFindMetadataForTableShouldPreferTheRepositoriesGiven(): void
    {
        $metadataRepository = $this->metadataRepositoryFor([
            ['User', 'bouh_world', ''],
            ['UserLight', 'bouh_world', ''],
        ]);

        $found = fn (string $database, string $schema, array $preferred, string $table = 'T_CITY_CIT') =>
            $this->entityFoundForTable($metadataRepository, $database, $schema, $table, $preferred);

        $this->assertSame('UserEntity', $found('bouh_world', '', [['User']]));
        $this->assertSame('UserLightEntity', $found('bouh_world', '', [['Other', 'UserLight']]));
        // Without exact match, whatever the database and the schema read
        $this->assertSame('UserLightEntity', $found('bouh_world_3', 'mySchema', [['UserLight']]));
        // A preferred repository not mapping the table leaves the choice to the database and the schema
        $this->assertThrows(HydratorException::class, fn () => $found('bouh_world', '', [['Other']]));
        $this->assertNull($found('bouh_world', '', [['User']], 'T_UNKNOWN'));
    }

    /**
     * The preference of the repository running the query breaks ties between the metadata of the database and the
     * schema read: it does not override them (a join on the same table of another database or schema)
     */
    public function testFindMetadataForTableShouldPreferTheExactMatchOverThePreferredRepositories(): void
    {
        $metadataRepository = $this->metadataRepositoryFor([
            ['FrUser', 'db_fr', ''],
            ['EnUser', 'db_en', ''],
            ['User', 'db', 'public'],
            ['AuditUser', 'db', 'Audit'],
        ]);

        $this->assertSame('EnUserEntity', $this->entityFoundForTable($metadataRepository, 'db_en', '', preferredRepositories: [['FrUser']]));
        $this->assertSame('FrUserEntity', $this->entityFoundForTable($metadataRepository, 'db_fr', '', preferredRepositories: [['FrUser']]));
        $this->assertSame('AuditUserEntity', $this->entityFoundForTable($metadataRepository, 'db', 'Audit', preferredRepositories: [['User']]));
        // Pgsql\Result lowercases the schemas read
        $this->assertSame('AuditUserEntity', $this->entityFoundForTable($metadataRepository, 'db', 'audit', preferredRepositories: [['User']]));
        // No metadata of the database and schema read: the preference chooses
        $this->assertSame('FrUserEntity', $this->entityFoundForTable($metadataRepository, 'db_other', '', preferredRepositories: [['FrUser']]));
    }

    /**
     * A subclass of a repository, or two repositories hydrating the same entity with the same fields: whichever
     * metadata is chosen, the entity is the same
     */
    public function testFindMetadataForTableShouldNotBeAmbiguousForTheSameEntityAndFields(): void
    {
        $definitions = [
            ['UserRepository', 'bouh_world', '', 'User', ['id', 'name']],
            ['AdminUserRepository', 'bouh_world', '', 'User', ['id', 'name']],
        ];

        foreach ([$definitions, array_reverse($definitions)] as $registered) {
            $metadataRepository = $this->metadataRepositoryFor($registered);
            $found = null;
            // Read by a third repository (a join) or a query of the QueryFactory: no preference
            $metadataRepository->findMetadataForTable(
                'main',
                'bouh_world',
                '',
                'T_CITY_CIT',
                function (Metadata $metadata) use (&$found): void {
                    $found = $metadata;
                },
                preferredRepositories: [['OrderRepository']]
            );

            // Whatever the registration order
            $metadataRepository->findMetadataForRepository(
                'AdminUserRepository',
                fn (Metadata $metadata) => $this->assertSame($metadata, $found)
            );
        }
    }

    public function testFindMetadataForTableShouldThrowForTheSameEntityWithOtherFields(): void
    {
        $metadataRepository = $this->metadataRepositoryFor([
            ['User', 'bouh_world', '', 'User', ['id', 'name', 'email']],
            ['UserName', 'bouh_world', '', 'User', ['id', 'name']],
        ]);

        $this->assertThrows(
            HydratorException::class,
            fn () => $this->entityFoundForTable($metadataRepository, 'bouh_world', '')
        );
    }

    public function testFindMetadataForTableShouldApplyTheRulesToThePreferredRepositories(): void
    {
        $metadataRepository = $this->metadataRepositoryFor([
            ['City', 'bouh_world', ''],
            ['CitySecond', 'bouh_world_2', ''],
            ['CityLight', 'bouh_world_2', ''],
        ]);

        $preferred = [['City', 'CitySecond']];
        $this->assertSame(
            'CitySecondEntity',
            $this->entityFoundForTable($metadataRepository, 'bouh_world_2', '', preferredRepositories: $preferred)
        );
        $this->assertSame(
            'CityEntity',
            $this->entityFoundForTable($metadataRepository, 'bouh_world', '', preferredRepositories: $preferred)
        );
    }

    public function testAddMetadataAgainShouldNotMakeTheRepositoryItsOwnCandidate(): void
    {
        $metadataRepository = $this->metadataRepositoryFor([['City', 'bouh_world', '']]);

        // A repository registers its metadata again when it is built
        $metadataRepository->findMetadataForRepository(
            'City',
            fn (Metadata $metadata) => $metadataRepository->addMetadata('City', $metadata)
        );

        $this->assertSame('CityEntity', $this->entityFoundForTable($metadataRepository, 'bouh_world', ''));
    }

    public function testFindMetadataForAnUnknownEntityWithoutCallbackNotFoundShouldDoNothing()
    {
        $services = new TingServices();
        $metadataRepository = new MetadataRepository($services->serializerFactory());

        $errors = $this->collectErrorTypes(function () use ($metadataRepository): void {
            $metadataRepository->findMetadataForEntity(
                new \stdClass(),
                fn () => $this->fail('No metadata should be found')
            );
        }, $thrown);

        $this->assertNull($thrown);
        $this->assertSame([], $errors);
    }
}
