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

namespace CCMBenchmark\Ting;

use Closure;
use ReflectionClass;
use CCMBenchmark\Ting\Exceptions\HydratorException;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\MetadataInitializer;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;
use Psr\Cache\CacheItemPoolInterface;
use function is_object;

class MetadataRepository
{
    /**
     * This array matches a repository (class name) and the corresponding metadata object
     *
     * @var array<string, Metadata>
     */
    protected $metadataList = [];

    /**
     * This array matches an entity name and the corresponding repository name
     * @var array
     */
    protected $entityToRepository = [];

    /**
     * @var array Fast array access to RepositoryClassName
     */
    private array $tableWithConnectionToMetadata = [];

    public function __construct(
        protected SerializerFactoryInterface $serializerFactory,
        private ?CacheItemPoolInterface $cacheItemPool = null
    ) {
    }

    /**
     * Finds the metadata of a table read in a result. Several metadata can share a table on a connection (one per
     * database or schema): the result tells the database and the schema of the table, but not always reliably
     * (Mysqli fields carry no database, Pgsql\Result lowercases the schemas, a query without schema has none).
     * In this order, the metadata found is:
     * - the one of the same schema and database (the schema compared case-insensitively when no exact match),
     * - the only one registered for the table,
     * - the only one of the same database, or when none shares it, the only one of the same schema.
     * Otherwise, the choice is ambiguous and a HydratorException is thrown.
     *
     * @param string   $connectionName
     * @param string   $database
     * @param string   $schema
     * @param string   $table
     * @param Closure $callbackFound called with applicable Metadata if applicable
     * @param Closure $callbackNotFound called if unknown table - no parameter
     *
     * @throws HydratorException when several metadata could be the one of the table
     *
     * @internal
     */
    public function findMetadataForTable(
        string $connectionName,
        string $database,
        string $schema,
        string $table,
        Closure $callbackFound,
        ?Closure $callbackNotFound = null
    ): void {

        $connectionKey = $connectionName . '#' . $table;

        if (isset($this->tableWithConnectionToMetadata[$connectionKey]) === false) {
            if ($callbackNotFound instanceof Closure) {
                $callbackNotFound();
            }
            return;
        }

        $repositories = $this->tableWithConnectionToMetadata[$connectionKey];
        $repository = $repositories[$schema . '#' . $database]
            ?? $this->findSingleCandidate($connectionName, $database, $schema, $table, $repositories);

        $callbackFound($this->metadataList[$repository]);
    }

    /**
     * @param array<string, string> $repositories the repositories registered for the table, by "schema#database"
     *
     * @throws HydratorException
     */
    private function findSingleCandidate(
        string $connectionName,
        string $database,
        string $schema,
        string $table,
        array $repositories
    ): string {
        $candidates = array_values(array_unique($repositories));
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        $sameSchema = [];
        $sameDatabase = [];
        foreach ($candidates as $repository) {
            $metadata = $this->metadataList[$repository];
            if (strcasecmp((string) $metadata->getSchema(), $schema) === 0) {
                $sameSchema[] = $repository;
            }
            if ($metadata->getDatabase() === $database) {
                $sameDatabase[] = $repository;
            }
        }

        $sameSchemaAndDatabase = array_values(array_intersect($sameSchema, $sameDatabase));
        $partialMatches = $sameDatabase !== [] ? $sameDatabase : $sameSchema;
        foreach ([$sameSchemaAndDatabase, $partialMatches] as $matches) {
            if (count($matches) === 1) {
                return $matches[0];
            }
            if ($matches !== []) {
                $candidates = $matches;
                break;
            }
        }

        $candidateList = [];
        foreach ($candidates as $repository) {
            $candidateList[] = sprintf(
                '%s (database "%s", schema "%s")',
                $repository,
                $this->metadataList[$repository]->getDatabase(),
                $this->metadataList[$repository]->getSchema()
            );
        }

        throw new HydratorException(sprintf(
            'Cannot choose the metadata of the table "%s" read on the connection "%s" from the database "%s" and '
            . 'the schema "%s": it can be %s. Name the database or the schema of its alias with '
            . 'Hydrator::objectDatabaseIs() or Hydrator::objectSchemaIs().',
            $table,
            $connectionName,
            $database,
            $schema,
            implode(' or ', $candidateList)
        ));
    }

    /**
     * @param class-string<Repository<T>> $repositoryName
     * @param Closure(Metadata<T>):void $callbackFound Called with applicable Metadata if applicable
     * @param Closure():void $callbackNotFound called if unknown entity - no parameter
     *
     * @template T of object
     *
     * @internal
     */
    public function findMetadataForRepository(
        string $repositoryName,
        Closure $callbackFound,
        ?Closure $callbackNotFound = null
    ): void {
        if (isset($this->metadataList[$repositoryName])) {
            $callbackFound($this->metadataList[$repositoryName]);
        } elseif ($callbackNotFound instanceof Closure) {
            $callbackNotFound();
        }
    }

    /**
     * @param T|class-string<T> $entity an instance or the class string of the entity
     * @param Closure(Metadata<T>):void $callbackFound Called with applicable Metadata if applicable
     * @param Closure():void $callbackNotFound called if unknown entity - no parameter
     *
     * @template T of object
     *
     * @internal
     */
    public function findMetadataForEntity($entity, Closure $callbackFound, ?Closure $callbackNotFound = null): void
    {
        if (is_object($entity)) {
            $entity = $entity::class;
        }

        if (isset($this->entityToRepository[$entity]) === false) {
            $callbackNotFound();
            return;
        }

        $this->findMetadataForRepository(
            $this->entityToRepository[$entity],
            $callbackFound,
            $callbackNotFound
        );
    }

    /**
     * @param class-string<Repository<T>> $repositoryClass
     * @param Metadata<T> $metadata
     *
     * @template T of object
     *
     * @internal
     */
    public function addMetadata($repositoryClass, Metadata $metadata): void
    {
        $metadata->propertyAccessor->setCacheItemPool($this->cacheItemPool);
        $this->metadataList[$repositoryClass] = $metadata;
        $metadataTable = $metadata->getTable();
        $metadataConnection = $metadata->getConnectionName();
        if (isset($this->tableWithConnectionToMetadata[$metadataConnection . '#' . $metadataTable]) === false) {
            $this->tableWithConnectionToMetadata[$metadataConnection . '#' . $metadataTable] = [];
        }

        $this->tableWithConnectionToMetadata
            [$metadataConnection . '#' . $metadataTable]
            [$metadata->getSchema() . '#' . $metadata->getDatabase()] = $repositoryClass;
        $entity = $metadata->getEntity();
        if ($entity !== null) {
            $this->entityToRepository[$entity] = $repositoryClass;
        }
    }

    /**
     * Read every files from given globPattern and load in memory all metadatas
     * This method should be used to discover the files and then create cache,
     * because glob uses directory reading at every hit.
     *
     * @param string $namespace
     * @param string $globPattern
     * @param array  $options Options you can use to custom initialization of Metadata
     * @return array
     */
    public function batchLoadMetadata(string $namespace, string $globPattern, array $options = []): array
    {
        $loaded = [];

        if (file_exists(dirname($globPattern)) === false) {
            return $loaded;
        }

        $files = glob($globPattern);
        if ($files === false) {
            return $loaded;
        }

        foreach ($files as $metadataFile) {
            /** @var class-string<MetadataInitializer> $metadataClass */
            $metadataClass = $namespace . '\\' . basename($metadataFile, '.php');
            $class = new ReflectionClass($metadataClass);
            if ($class->isInterface()) {
                continue;
            }
            if ($class->isAbstract()) {
                continue;
            }
            if (!$class->isSubclassOf(MetadataInitializer::class)) {
                continue;
            }

            /** @var Metadata $metadata */
            $metadata = $metadataClass::initMetadata(
                $this->serializerFactory,
                $this->getOptionForRepository($metadataClass, $options)
            );

            $repository = $metadata->getRepository() ?? $metadataClass;

            $this->addMetadata($repository, $metadata);
            $loaded[$repository] = $metadataClass;
        }

        return $loaded;
    }


    /**
     * Read every classes (should be fully qualified namespaces) to load metadatas in memory.
     * This method is far more efficient than batchLoadMetadata : with opcache enabled, files
     * are not read from disk anymore.
     *
     * @param array $paths
     * @param array $options Options you can use to custom initialization of Metadata
     * @return array
     */
    public function batchLoadMetadataFromCache(array $paths, array $options = []): array
    {
        $loaded = [];
        foreach ($paths as $repository => $metadataClass) {
            $this->addMetadata(
                $repository,
                $metadataClass::initMetadata(
                    $this->serializerFactory,
                    $this->getOptionForRepository($metadataClass, $options)
                )
            );
            $loaded[] = $repository;
        }

        return $loaded;
    }

    /**
     * @param string $repository
     * @param array  $options
     * @return array
     */
    protected function getOptionForRepository(string $repository, array $options): array
    {
        $repositoryOptions = isset($options['default']) === true ? $options['default'] : [];

        if (isset($options[$repository])) {
            return array_merge($repositoryOptions, $options[$repository]);
        }

        return $repositoryOptions;
    }

    /**
     * @return list<string>
     */
    public function getAllEntities(): array
    {
        return array_keys($this->entityToRepository);
    }
}
