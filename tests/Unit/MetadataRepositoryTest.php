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

use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Services;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\model\Bouh;
use tests\fixtures\model\BouhRepository;

class MetadataRepositoryTest extends TestCase
{
    public function testFindMetadataForEntityShouldCallCallbackFound()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setEntity('tests\fixtures\model\Bouh');

        $metadataRepository = new MetadataRepository($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setEntity(Bouh::class);

        $metadataRepository = new MetadataRepository($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setEntity('tests\fixtures\model\Bouh');

        $metadataRepository = new MetadataRepository($services->get('SerializerFactory'));
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

    public function testFindMetadataForTableShouldCallCallbackFound()
    {
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setTable('T_BOUH_BOO');

        $metadataRepository = new MetadataRepository($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setTable('T_BOUH_BOO');

        $metadataRepository = new MetadataRepository($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setConnectionName('connectionName');
        $metadata->setDatabase('database');
        $metadata->setTable('T_BOUH_BOO');
        $metadata->setSchema('schemaName');

        $metadataRepository = new MetadataRepository($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setTable('T_BOUH_BOO');
        $metadata->setSchema('SchemaName');

        $metadataRepository = new MetadataRepository($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadataRepository = $services->get('MetadataRepository');
        $metadataRepository->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../fixtures/model/*Repository.php',
            ['default' => ['connection' => 'connectionName', 'database' => 'databaseName']]
        );
        $bouhRepository = $services->get('RepositoryFactory')->get('\tests\fixtures\model\BouhRepository');
        $this->assertIsObject($bouhRepository);
        $this->assertSame(
            ['connection' => 'connectionName', 'database' => 'databaseName'],
            $bouhRepository::$options
        );
    }

    public function testBatchLoadMetadataShouldCallInitMetadataWithDefaultAndRepositoryOptions()
    {
        $services = new Services();
        $metadataRepository = $services->get('MetadataRepository');
        $metadataRepository->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../fixtures/model/*Repository.php',
            [
                'default' => ['connection' => 'connectionName', 'database' => 'databaseName'],
                'tests\fixtures\model\BouhRepository' => ['database' => 'dbBouh']
            ]
        );
        $bouhRepository = $services->get('RepositoryFactory')->get('\tests\fixtures\model\BouhRepository');
        $this->assertIsObject($bouhRepository);
        $this->assertSame(['connection' => 'connectionName', 'database' => 'dbBouh'], $bouhRepository::$options);
    }

    public function testBatchLoadMetadataShouldCallInitMetadataWithRepositoryOptions()
    {
        $services = new Services();
        $metadataRepository = $services->get('MetadataRepository');
        $metadataRepository->batchLoadMetadata(
            'tests\fixtures\model',
            __DIR__ . '/../fixtures/model/*Repository.php',
            ['tests\fixtures\model\BouhRepository' => ['connection' => 'conBouh', 'database' => 'dbBouh']]
        );
        $bouhRepository = $services->get('RepositoryFactory')->get('\tests\fixtures\model\BouhRepository');
        $this->assertIsObject($bouhRepository);
        $this->assertSame(['connection' => 'conBouh', 'database' => 'dbBouh'], $bouhRepository::$options);
    }

    public function testBatchLoadMetadataShouldLoad5Repositories()
    {
        $services = new Services();
        $metadataRepository = new MetadataRepository(
            $services->get('SerializerFactory')
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
                'tests\fixtures\model\ParkRepository'                        => 'tests\fixtures\model\ParkRepository',
            ],
            $metadataRepository->batchLoadMetadata(
                'tests\fixtures\model',
                __DIR__ . '/../fixtures/model/*Repository.php'
            )
        );
    }

    public function testBatchLoadMetadataWithInvalidPathShouldReturnEmptyArray()
    {
        $services = new Services();
        $metadataRepository = new MetadataRepository(
            $services->get('SerializerFactory')
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
        $paths = ['tests\fixtures\model\BouhRepository'];
        $services = new Services();
        $metadataRepository = new MetadataRepository(
            $services->get('SerializerFactory')
        );
        $result = $metadataRepository->batchLoadMetadataFromCache($paths);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testBatchLoadMetadataForRepositoryWhichNotImplementMetadataInitializerShouldDoNothing()
    {
        $services = new Services();
        $metadataRepository = new MetadataRepository(
            $services->get('SerializerFactory')
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setConnectionName('connection2');
        $metadata->setDatabase('bouh_world');
        $metadata->setTable('bouh');

        $metadataRepository = new MetadataRepository($services->get('SerializerFactory'));
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
        $services = new Services();
        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setConnectionName('connection');
        $metadata->setDatabase('bouh_world_2');
        $metadata->setTable('bouh');

        $metadataRepository = new MetadataRepository($services->get('SerializerFactory'));
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
        $services = new Services();

        $metadataRepository = new MetadataRepository($services->get('SerializerFactory'));

        $metadata = new Metadata($services->get('SerializerFactory'));
        $metadata->setEntity('tests\fixtures\model\Bouh');
        $metadata->setConnectionName('connection');
        $metadata->setDatabase('bouh_world');
        $metadata->setTable('bouh');
        $metadataRepository->addMetadata('tests\fixtures\model\BouhRepository', $metadata);

        $metadata = new Metadata($services->get('SerializerFactory'));
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
}
