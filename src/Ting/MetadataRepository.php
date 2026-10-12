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
     * @var array<string, Metadata<object>>
     */
    protected array $metadataList = [];

    /**
     * This array matches an entity name and the corresponding repository name
     * @var array<class-string, class-string<Repository<object>>|class-string<MetadataInitializer>>
     */
    protected array $entityToRepository = [];

    /**
     * @var array<string, array<string, string>> Every class the metadata of a table are registered under:
     *      "connection#table" => repository => "schema#database". Several repositories can map the same table of
     *      the same database and schema (a full entity and a lighter projection)
     */
    private array $tableWithConnectionToMetadata = [];

    /**
     * @var array<string, array<string, true>> "connection#lowercase table" => the tables of metadata of that name,
     *      whatever their case
     */
    private array $tablesOfAnyCase = [];

    /**
     * @var array<string, array<string, list<Metadata<object>>>> entity class => property name => the metadata of the
     *      class mapping it
     */
    private array $mappingMetadata = [];

    public function __construct(
        protected SerializerFactoryInterface $serializerFactory,
        private ?CacheItemPoolInterface $cacheItemPool = null
    ) {
    }

    /**
     * Finds the metadata of a table read in a result. Several metadata can share a table on a connection (one per
     * database or schema, or several on the same one): the result tells the database and the schema of the table,
     * but not always reliably (Mysqli fields carry no database, Pgsql\Result folds unquoted schemas to lowercase, a
     * query without schema has none).
     * The candidates are the metadata of the same schema and database (the schema compared case-insensitively when
     * none has the same case), every metadata of the table when none matches. The first group of preferred
     * repositories mapping one of the candidates (the repositories given to the hydrator with
     * Hydrator::preferRepository(), then the one running the query) restricts them. Then, the metadata found is:
     * - the only candidate,
     * - without exact match, the only one of the same database, or when none shares it, the only one of the same
     *   schema,
     * - any of the remaining ones when they all hydrate the same entity with the same fields (the one registered
     *   under the first class name in alphabetical order).
     * Otherwise, the choice is ambiguous and a HydratorException is thrown: the last registered never wins.
     *
     * @param string   $connectionName
     * @param string   $database
     * @param string   $schema
     * @param string   $table
     * @param Closure $callbackFound called with applicable Metadata if applicable
     * @param Closure $callbackNotFound called if unknown table - no parameter
     * @param list<list<string>> $preferredRepositories groups of classes the metadata to use are registered under,
     *                                                  by priority
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
        ?Closure $callbackNotFound = null,
        array $preferredRepositories = []
    ): void {

        // The metadata of the table of the same case, of the schema and the database read
        $sameCase = $this->tableWithConnectionToMetadata[$connectionName . '#' . $table] ?? [];
        $candidates = $this->candidatesOfSchema($sameCase, $database, $schema);
        $exactMatch = $candidates !== [];

        // Else the metadata of every table whose name differs only by case (a table created quoted "Users" declared
        // setTable('users'), as 3.x lowercased every table): those of the schema and the database read first, else
        // those of a preferred repository, else those of the same case, else all of them. A query without schema
        // tells nothing: the table of the same case is not pulled away by a case variant without schema
        if ($exactMatch === false && $schema === '' && $sameCase !== []) {
            $candidates = array_keys($sameCase);
        } elseif ($exactMatch === false) {
            $anyCase = [];
            foreach (array_keys($this->tablesOfAnyCase[$connectionName . '#' . strtolower($table)] ?? []) as $variant) {
                $anyCase += $this->tableWithConnectionToMetadata[$connectionName . '#' . $variant] ?? [];
            }
            if ($anyCase === []) {
                if ($callbackNotFound instanceof Closure) {
                    $callbackNotFound();
                }
                return;
            }
            $candidates = $this->candidatesOfSchema($anyCase, $database, $schema);
            $exactMatch = $candidates !== [];
            if ($exactMatch === false) {
                $candidates = array_keys($anyCase);
                $preferred = $this->preferredCandidates($candidates, $preferredRepositories);
                if ($preferred === null && $sameCase !== []) {
                    $candidates = array_keys($sameCase);
                }
            }
        }

        $candidates = $this->preferredCandidates($candidates, $preferredRepositories) ?? $candidates;

        $repository = $this->findSingleCandidate($connectionName, $database, $schema, $table, $candidates, $exactMatch);

        $callbackFound($this->metadataList[$repository]);
    }

    /**
     * The candidates of the first group of preferred repositories holding some, null when none does
     *
     * @param list<string> $candidates
     * @param list<list<string>> $preferredRepositories
     * @return list<string>|null
     */
    private function preferredCandidates(array $candidates, array $preferredRepositories): ?array
    {
        foreach ($preferredRepositories as $group) {
            $preferred = array_values(array_intersect($candidates, $group));
            if ($preferred !== []) {
                return $preferred;
            }
        }

        return null;
    }

    /**
     * The repositories of the schema and the database read: the same schema, else the schema of another case
     *
     * @param array<string, string> $repositories repository => "schema#database"
     * @return list<string>
     */
    private function candidatesOfSchema(array $repositories, string $database, string $schema): array
    {
        $candidates = array_keys($repositories, $schema . '#' . $database, true);
        if ($candidates === []) {
            foreach (array_keys($repositories) as $repository) {
                $metadata = $this->metadataList[$repository];
                if (
                    $metadata->getDatabase() === $database
                    && strcasecmp((string) $metadata->getSchema(), $schema) === 0
                ) {
                    $candidates[] = $repository;
                }
            }
        }

        return $candidates;
    }

    /**
     * @param list<string> $candidates the repositories the metadata of the table can be registered under
     * @param bool $exactMatch true when the candidates are of the schema and the database read
     *
     * @throws HydratorException
     */
    private function findSingleCandidate(
        string $connectionName,
        string $database,
        string $schema,
        string $table,
        array $candidates,
        bool $exactMatch
    ): string {
        if (count($candidates) === 1) {
            return $candidates[0];
        }

        if ($exactMatch === false) {
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

            $partialMatches = $sameDatabase !== [] ? $sameDatabase : $sameSchema;
            if (count($partialMatches) === 1) {
                return $partialMatches[0];
            }
            if ($partialMatches !== []) {
                $candidates = $partialMatches;
            }
        }

        // The same entity hydrated the same way (a repository and its subclass): whichever is chosen. Chosen by
        // name, not by registration order
        sort($candidates);
        if ($this->hydrateTheSameWay($candidates)) {
            return $candidates[0];
        }

        $candidateList = [];
        foreach ($candidates as $repository) {
            $metadata = $this->metadataList[$repository];
            $candidateList[] = sprintf(
                '%s (database "%s", schema "%s"%s)',
                $repository,
                $metadata->getDatabase(),
                $metadata->getSchema(),
                // A table whose name differs only by case
                $metadata->getTable() !== $table ? sprintf(', table "%s"', $metadata->getTable()) : ''
            );
        }

        throw new HydratorException(sprintf(
            'Cannot choose the metadata of the table "%s" read on the connection "%s" from the database "%s" and '
            . 'the schema "%s": it can be %s. Name the database or the schema of its alias with '
            . 'Hydrator::objectDatabaseIs() or Hydrator::objectSchemaIs(), or the repository whose metadata to use '
            . 'with Hydrator::preferRepository().',
            $table,
            $connectionName,
            $database,
            $schema,
            implode(' or ', $candidateList)
        ));
    }

    /**
     * @param list<string> $repositories
     * @return bool true when the metadata of the repositories hydrate the same entity with the same fields
     */
    private function hydrateTheSameWay(array $repositories): bool
    {
        $first = null;
        foreach ($repositories as $repository) {
            $metadata = $this->metadataList[$repository];
            $first ??= $metadata;
            // Fields by column: the order in which they are added does not matter
            if ($metadata->getEntity() !== $first->getEntity()
                || !self::isSameValue(
                    array_column($metadata->getFields(), null, 'columnName'),
                    array_column($first->getFields(), null, 'columnName')
                )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Strict comparison, whatever the order of the keys of an array: assoc null and false, 1 and true, or "1" and 1
     * are different options, two NAN the same. Objects are the same when identical, or of the same class (not a closure) with the same
     * properties
     */
    private static function isSameValue(mixed $value, mixed $other): bool
    {
        if (\is_array($value) && \is_array($other)) {
            if (\count($value) !== \count($other)) {
                return false;
            }
            foreach ($value as $key => $item) {
                if (!\array_key_exists($key, $other) || !self::isSameValue($item, $other[$key])) {
                    return false;
                }
            }

            return true;
        }

        if (\is_object($value) && \is_object($other)) {
            return $value === $other
                || (
                    $value::class === $other::class
                    && !$value instanceof Closure
                    && self::isSameValue((array) $value, (array) $other)
                );
        }

        // NAN is never equal to itself, but two NAN options are the same option
        if (\is_float($value) && \is_float($other) && is_nan($value) && is_nan($other)) {
            return true;
        }

        return $value === $other;
    }

    /**
     * @param class-string<Repository<T>>|class-string<MetadataInitializer> $repositoryName the class the metadata are
     *        registered under: a repository, or the class initializing metadata used for hydration only
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
            // A map of the metadata of every entity: addMetadata() registers Metadata<T> under the repository of T
            /** @var Metadata<T> $metadata */
            $metadata = $this->metadataList[$repositoryName];
            $callbackFound($metadata);
        } elseif ($callbackNotFound instanceof Closure) {
            $callbackNotFound();
        }
    }

    /**
     * @param T|class-string<T> $entity an instance or the class string of the entity
     * @param Closure(Metadata<T>):void $callbackFound Called with applicable Metadata if applicable
     * @param (Closure():void)|null $callbackNotFound called if unknown entity - no parameter
     *
     * @template T of object
     *
     * @internal
     */
    public function findMetadataForEntity(object|string $entity, Closure $callbackFound, ?Closure $callbackNotFound = null): void
    {
        if (is_object($entity)) {
            $entity = $entity::class;
        }

        if (isset($this->entityToRepository[$entity]) === false) {
            if ($callbackNotFound instanceof Closure) {
                $callbackNotFound();
            }
            return;
        }

        // addMetadata() registers the class the metadata of T are registered under
        /** @var class-string<Repository<T>>|class-string<MetadataInitializer> $repository */
        $repository = $this->entityToRepository[$entity];
        $this->findMetadataForRepository($repository, $callbackFound, $callbackNotFound);
    }

    /**
     * @param class-string<Repository<T>>|class-string<MetadataInitializer> $repositoryClass a repository, or the class
     *        initializing metadata used for hydration only, without repository
     * @param Metadata<T> $metadata
     *
     * @template T of object
     *
     * @internal
     */
    public function addMetadata(string $repositoryClass, Metadata $metadata): void
    {
        $metadata->propertyAccessor->setCacheItemPool($this->cacheItemPool);
        // Metadata registered under a repository without Metadata::setRepository() (loaded by batchLoadMetadata(), its
        // cache, or a repository built without it) name that repository
        if ($metadata->getRepository() === null && is_subclass_of($repositoryClass, Repository::class)) {
            $metadata->setRepository($repositoryClass);
        }
        $this->metadataList[$repositoryClass] = $metadata;
        $this->mappingMetadata = [];
        // By repository: registered again (a repository registers its metadata when built), it is not duplicated
        $this->tableWithConnectionToMetadata[$metadata->getConnectionName() . '#' . $metadata->getTable()]
            [$repositoryClass] = $metadata->getSchema() . '#' . $metadata->getDatabase();
        $this->tablesOfAnyCase[$metadata->getConnectionName() . '#' . strtolower((string) $metadata->getTable())]
            [(string) $metadata->getTable()] = true;
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
     * @param array<string, array<string, mixed>> $options Options you can use to custom initialization of Metadata:
     *        by repository class, or "default" for every repository
     * @return array<class-string<Repository<object>>|class-string<MetadataInitializer>, class-string<MetadataInitializer>>
     *         the class initializing the metadata of each repository
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

            /** @var Metadata<object> $metadata */
            $metadata = $metadataClass::initMetadata(
                $this->serializerFactory,
                $this->getOptionForRepository($metadataClass, $options)
            );

            // Without Metadata::setRepository(), the metadata are registered under the class initializing them:
            // a repository, or a class giving metadata for hydration only
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
     * @param array<class-string<Repository<object>>|class-string<MetadataInitializer>, class-string<MetadataInitializer>> $paths
     *        as returned by batchLoadMetadata(): the class initializing the metadata of each repository
     * @param array<string, array<string, mixed>> $options Options you can use to custom initialization of Metadata:
     *        by repository class, or "default" for every repository
     * @return list<class-string<Repository<object>>|class-string<MetadataInitializer>>
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
     * @param array<string, array<string, mixed>> $options by repository class, or "default" for every repository
     * @return array<string, mixed> the default options, merged with those of the repository
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
     * @param class-string $entityClass
     * @return bool true when some metadata registered for the class (of any repository) map the property
     *
     * @internal
     */
    public function isPropertyMapped(string $entityClass, string $property): bool
    {
        return $this->getMetadataMappingProperty($entityClass, $property) !== [];
    }

    /**
     * @param class-string $entityClass
     * @return list<Metadata<object>> the metadata registered for the class (of every repository) mapping the property
     *
     * @internal
     */
    public function getMetadataMappingProperty(string $entityClass, string $property): array
    {
        if (isset($this->mappingMetadata[$entityClass][$property]) === false) {
            $mapping = [];
            foreach ($this->metadataList as $metadata) {
                if ($metadata->getEntity() === $entityClass && $metadata->hasProperty($property)) {
                    $mapping[] = $metadata;
                }
            }
            $this->mappingMetadata[$entityClass][$property] = $mapping;
        }

        return $this->mappingMetadata[$entityClass][$property];
    }

    /**
     * @return list<string>
     */
    public function getAllEntities(): array
    {
        return array_keys($this->entityToRepository);
    }
}
