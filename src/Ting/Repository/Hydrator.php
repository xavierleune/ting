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

namespace CCMBenchmark\Ting\Repository;

use stdClass;
use CCMBenchmark\Ting\Driver\ResultInterface;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;
use CCMBenchmark\Ting\Exceptions\HydratorException;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Serializer\UnserializeInterface;
use CCMBenchmark\Ting\UnitOfWork;
use Generator;
use WeakMap;

/**
 * Hydrates each row into an array: alias of the table => entity (null when a LEFT JOIN matched nothing), and
 * 0 => stdClass holding the columns without metadata (COUNT(*)...)
 *
 * @template T type of the items, as documented by the caller (the hydrator cannot check it): the rows
 *             (array<int|string, object|null>) for this class, what a subclass makes of them
 * @phpstan-import-type Column from ResultInterface
 * @phpstan-import-type Row from ResultInterface
 * @phpstan-import-type SerializerOptions from UnserializeInterface
 *
 * @template-implements HydratorInterface<T>
 */
class Hydrator implements HydratorInterface
{
    /** @var array<string, list<array{0: string, 1: string}>> alias of the target => [virtual column, setter] */
    protected array $mapAliases         = [];
    /** @var array<string, list<array{0: string, 1: string}>> alias of the target => [alias of the object, setter] */
    protected array $mapObjects         = [];
    /** @var array<string, string> alias => database */
    protected array $objectDatabase     = [];
    /** @var array<string, string> alias => schema */
    protected array $objectSchema       = [];
    /** @var array<string, true> classes the metadata to use are registered under (repositories) => true */
    protected array $preferredRepositories = [];
    /** @var array<string, array{0: UnserializeInterface, 1: SerializerOptions}> virtual column => [unserializer, options] */
    protected array $unserializeAliases = [];
    /** @var WeakMap<NotifyPropertyInterface, bool> */
    protected WeakMap $alreadyManaged;
    /** @var array<string, object|null> identity map: reference key => entity */
    protected array $references         = [];

    /**
     * @var array<string, Metadata<object>> alias => metadata of its table
     */
    protected array $metadataList       = [];

    protected ?ResultInterface $result = null;

    protected ?MetadataRepository $metadataRepository = null;

    protected ?UnitOfWork $unitOfWork = null;

    protected bool $identityMap = false;

    public function __construct()
    {
        $this->alreadyManaged = new WeakMap();
    }

    /**
     * @param bool $enable
     * @return void
     */
    public function identityMap(bool $enable): void
    {
        $this->identityMap = $enable;
    }

    /**
     * @param MetadataRepository $metadataRepository
     * @return void
     */
    public function setMetadataRepository(MetadataRepository $metadataRepository): void
    {
        $this->metadataRepository = $metadataRepository;
    }

    /**
     * @param UnitOfWork $unitOfWork
     * @return void
     */
    public function setUnitOfWork(UnitOfWork $unitOfWork): void
    {
        $this->unitOfWork = $unitOfWork;
    }

    public function setResult(ResultInterface $result): static
    {
        $this->result = $result;
        return $this;
    }

    /**
     * @return Generator<int, mixed> the rows (array<int|string, object|null>), as hydrateColumns() returns them: a
     *                               subclass yields what it makes of them
     * @throws HydratorException
     */
    public function getIterator(): Generator
    {
        yield from $this->hydratedRows();
    }

    /**
     * The rows of the result, hydrated by hydrateColumns(): none without result, as count()
     *
     * @return Generator<int, array<int|string, object|null>>
     * @throws HydratorException when a row comes from a result without connection name or database (a cached
     *                           collection without result has no row)
     */
    protected function hydratedRows(): Generator
    {
        $result = $this->result;
        if ($result === null) {
            return;
        }

        foreach ($result as $key => $columns) {
            $connectionName = $result->getConnectionName();
            $database = $result->getDatabase();
            if ($connectionName === null || $database === null) {
                throw new HydratorException(
                    'Cannot hydrate a row of a result without connection name or database: its metadata cannot be found'
                );
            }

            yield $key => $this->hydrateColumns($connectionName, $database, $columns);
        }
    }

    /**
     * @return int Not int<0, max> as Countable states: the number of rows comes from the driver, untyped
     */
    public function count(): int
    {
        if ($this->result === null) {
            return 0;
        }

        // mysqli reports the number of rows as a string beyond PHP_INT_MAX
        return (int) $this->result->getNumRows();
    }

    /**
     * @param string $alias
     * @param UnserializeInterface $unserialize
     * @param SerializerOptions $options
     * @return $this
     */
    public function unserializeAliasWith(string $alias, UnserializeInterface $unserialize, array $options = []): static
    {
        $this->unserializeAliases[$alias] = [$unserialize, $options];

        return $this;
    }


    /**
     * @param string $from
     * @param string $to
     * @param string $column
     *
     * @return $this
     */
    public function mapAliasTo(string $from, string $to, string $column): static
    {
        if (isset($this->mapAliases[$to]) === false) {
            $this->mapAliases[$to] = [];
        }
        $this->mapAliases[$to][] = [$from, $column];

        return $this;
    }

    /**
     * @param string $from
     * @param string $to
     * @param string $column
     *
     * @return $this
     */
    public function mapObjectTo(string $from, string $to, string $column): static
    {
        if (isset($this->mapObjects[$to]) === false) {
            $this->mapObjects[$to] = [];
        }
        $this->mapObjects[$to][] = [$from, $column];

        return $this;
    }

    /**
     * @param string $object
     * @param string $database
     *
     * @return $this
     */
    public function objectDatabaseIs(string $object, string $database): static
    {
        $this->objectDatabase[$object] = $database;

        return $this;
    }

    /**
     * @param string $object
     * @param string $schema
     *
     * @return $this
     */
    public function objectSchemaIs(string $object, string $schema): static
    {
        $this->objectSchema[$object] = $schema;

        return $this;
    }

    /**
     * Hydrates the tables this repository maps with its metadata, whatever the database and the schema read: when
     * several repositories map the same table (a full entity and a lighter projection), the table is otherwise
     * hydrated with the metadata of the same database and schema, and a HydratorException is thrown when several
     * repositories share them. Call it once per repository to prefer for the tables of the query.
     * The reads of a repository (get(), getBy(), getQuery()..., getCollection()) prefer it already.
     *
     * @param string $repositoryClass a repository, or the class initializing metadata used for hydration only
     *
     * @return $this
     */
    public function preferRepository(string $repositoryClass): static
    {
        $this->preferredRepositories[$repositoryClass] = true;

        return $this;
    }

    /**
     * @param Column $column
     *
     * @return string
     */
    private function extractSchemaFromColumn(array $column): string
    {
        // As in 3.x, objectSchemaIs() overrides the schema read from the query
        return $this->objectSchema[$column['table']] ?? $column['schema'] ?? '';
    }

    /**
     * @param array<int|string, object|null> $result
     *
     * @return bool
     */
    private function hasVirtualObject(array $result): bool
    {
        return isset($result[0]);
    }

    /**
     * @param object $virtualObject the stdClass of the virtual columns (key 0 of the row, which a table aliased "0"
     *                              would take as well)
     */
    private function unserializeVirtualObjectProperty(object $virtualObject): object
    {
        foreach ($this->unserializeAliases as $aliasName => [$unserialize, $options]) {
            if (isset($virtualObject->$aliasName)) {
                $virtualObject->$aliasName = $unserialize->unserialize($virtualObject->$aliasName, $options);
            }
        }
        return $virtualObject;
    }

    /**
     * Hydrate one object from values
     *
     * Hydrate all column into the right Entity according to the table name and metadata information
     * all virtual columns (COUNT(*), etc) will be set in the array key 0
     * all Entities without any information (a "LEFT JOIN user" can return no information at all about user)
     *    are set to null
     *
     * @internal not to be called from outside; a hydrator extending this class may call it (a supported extension
     *           point)
     *
     * @param string $connectionName
     * @param string $database
     * @param Row    $columns
     *
     * @return array<int|string, object|null> alias => entity, 0 => stdClass of the virtual columns
     * @throws HydratorException when the metadata repository or the unit of work is not set
     */
    protected function hydrateColumns(string $connectionName, string $database, array $columns): array
    {
        $metadataRepository = $this->metadataRepository;
        if ($metadataRepository === null) {
            throw new HydratorException(
                'Hydrator used before setMetadataRepository(): the metadata of the rows cannot be found'
            );
        }

        $result        = [];
        $tmpEntities   = []; // Temporary entity when all properties are null for the moment (LEFT/RIGHT JOIN)
        $validEntities = []; // Entity marked as valid will fill an object
        // (a valid Entity is a entity with at less one property not null)
        $fromReferences = []; // Prevents from hydrating if an entity is already ref for a table
        $rowReferences  = []; // Identity map key of each table of the row, false without a complete primary key
        $readColumns    = []; // Columns set on each entity created by this row
        foreach ($columns as $column) {

            // Bypass if an entity has already been hydrated with this column
            if (
                \array_key_exists($column['table'], $fromReferences)
                && \array_key_exists($column['table'], $this->metadataList)
                && $this->metadataList[$column['table']]->hasColumn($column['orgName'])
            ) {
                continue;
            }

            // We have the information table, it's not a virtual column like COUNT(*)
            if (isset($result[$column['table']]) === false && isset($this->metadataList[$column['table']]) === false) {
                $schema = $this->extractSchemaFromColumn($column);
                $metadataRepository->findMetadataForTable(
                    $connectionName,
                    // objectDatabaseIs() applies to its alias only, not to the tables read after it
                    $this->objectDatabase[$column['table']] ?? $database,
                    $schema,
                    $column['orgTable'],

                    // Callback if table metadata found
                    function (Metadata $metadata) use ($column, &$result): void {
                        $this->metadataList[$column['table']] = $metadata;
                        $result[$column['table']]             = $metadata->createEntity();
                        $tmpEntities[$column['table']]        = [];
                    },
                    null,
                    array_keys($this->preferredRepositories)
                );
            }

            if (isset($this->metadataList[$column['table']])) {
                if (
                    $this->identityMap
                    && $this->metadataList[$column['table']]->hasColumn($column['orgName'])
                    && ($ref = $rowReferences[$column['table']] ??= $this->referenceFromColumns($column['table'], $columns)) !== false
                    && isset($this->references[$ref]) === true
                ) {
                    // This entity was already created and stored into references
                    // If identityMap is enabled, reuse the same object
                    $result[$column['table']] = $this->references[$ref];
                    $validEntities[$column['table']]  = true;
                    $fromReferences[$column['table']] = true;
                    continue;
                }

                if (isset($result[$column['table']]) === false) {
                    $result[$column['table']]      = $this->metadataList[$column['table']]->createEntity();
                    $tmpEntities[$column['table']] = [];
                }
            }

            // We have a metadata defined for the column
            if (isset($this->metadataList[$column['table']]) &&
                $this->metadataList[$column['table']]->hasColumn($column['orgName']) === true
            ) {
                // Column value is null or entity is still not marked as valid
                if ($column['value'] === null && isset($validEntities[$column['table']]) === false) {
                    $tmpEntities[$column['table']][$column['orgName']] = $result[$column['table']];
                } else {
                    // Entity was previously marked as a temporary entity, we set all previous columns retrieved
                    if (isset($tmpEntities[$column['table']]) && $tmpEntities[$column['table']] !== []) {
                        foreach ($tmpEntities[$column['table']] as $entityColumn => $entity) {
                            $this->metadataList[$column['table']]->setEntityProperty(
                                $entity,
                                $entityColumn,
                                null
                            );
                            $readColumns[$column['table']][$entityColumn] = true;
                        }
                        unset($tmpEntities[$column['table']]);
                    }

                    $validEntities[$column['table']] = true;

                    $this->metadataList[$column['table']]->setEntityProperty(
                        $result[$column['table']],
                        $column['orgName'],
                        $column['value']
                    );
                    $readColumns[$column['table']][$column['orgName']] = true;
                }

                // Table is not mapped or column is a virtual column
            } else {
                $validEntities[0] = true;
                if (isset($result[0]) === false) {
                    $result[0] = new stdClass();
                }

                $result[0]->{$column['name']} = $column['value'];
            }
        }

        // Virtual object
        if ($this->hasVirtualObject($result) === true) {
            $result[0] = $this->unserializeVirtualObjectProperty($result[0]);
        }

        // A mutable field whose column is not in the row holds a value that was not read (null, a default set by the
        // constructor...): the unit of work must not write it
        foreach ($readColumns as $table => $tableColumns) {
            $notRead = $this->metadataList[$table]->getMutablePropertiesNotRead($tableColumns);
            if ($notRead !== [] && $result[$table] instanceof NotifyPropertyInterface) {
                $this->unitOfWork?->setMutablePropertiesNotRead($result[$table], $notRead);
            }
        }

        foreach ($result as $table => $entity) {

            // All no valid entity is replaced by a null value, unless mapObjectTo() already removed it from the row
            if (isset($validEntities[$table]) === false && \array_key_exists($table, $result)) {
                $result[$table] = null;
            }

            // It's a valid entity (unknown data are put in a value table 0)
            if (\is_int($table) === false && $this->identityMap) {
                $ref = $rowReferences[$table] ??= $this->referenceFromColumns($table, $columns);
                if ($ref !== false && isset($this->references[$ref]) === false) {
                    $this->references[$ref] = $entity;
                }
            }

            if (isset($this->mapAliases[$table])) {
                foreach ($this->mapAliases[$table] as $fromAndColumn) {
                    $this->manageIfYouCan($result[0]->{$fromAndColumn[0]});
                    $entity->{$fromAndColumn[1]}($result[0]->{$fromAndColumn[0]});
                    unset($result[0]->{$fromAndColumn[0]});
                }
            }

            if (isset($this->mapObjects[$table])) {
                foreach ($this->mapObjects[$table] as $fromAndColumn) {
                    // A null entity (LEFT JOIN without match) may not be replaced by null yet: it depends on the
                    // order of the aliases in the row, so check its validity rather than its value
                    if (isset($validEntities[$fromAndColumn[0]]) && isset($result[$fromAndColumn[0]])) {
                        $this->manageIfYouCan($result[$fromAndColumn[0]]);
                        $entity->{$fromAndColumn[1]}($result[$fromAndColumn[0]]);
                    }
                    unset($result[$fromAndColumn[0]]);
                }
            }

            $this->manageIfYouCan($entity);
        }

        if (isset($result[0]) && get_object_vars($result[0]) === []) {
            unset($result[0]);
        }

        return $result;
    }

    /**
     * Identity map key of the entity of $table in the row: every primary value, read from the columns
     *
     * @param Row $columns
     * @return string|false false when a primary of the table is missing from the row or null
     */
    private function referenceFromColumns(string $table, array $columns): string|false
    {
        $values = [];
        foreach ($this->metadataList[$table]->getPrimaries() as $columnName => $primary) {
            foreach ($columns as $column) {
                if ($column['table'] === $table && $column['orgName'] === $columnName) {
                    if ($column['value'] === null) {
                        return false;
                    }
                    $values[] = $column['value'];
                    continue 2;
                }
            }

            return false;
        }

        return $values === [] ? false : $this->referenceKey($table, $values);
    }

    /**
     * Key identifying an entity of $alias by its primary values, without collision between values
     * (concatenated, ("a-b", "c") and ("a", "b-c") would share the same key)
     *
     * @param list<mixed> $primaryValues
     */
    protected function referenceKey(string $alias, array $primaryValues): string
    {
        return serialize([$alias, $primaryValues]);
    }

    /**
     * @param mixed $entity
     * @throws HydratorException
     */
    private function manageIfYouCan(mixed $entity): void
    {
        if ($entity instanceof NotifyPropertyInterface && $this->alreadyManaged->offsetExists($entity) === false) {
            if ($this->unitOfWork === null) {
                throw new HydratorException('Hydrator used before setUnitOfWork(): the entities cannot be managed');
            }
            $this->unitOfWork->manage($entity);
            $this->alreadyManaged[$entity] = true;
        }
    }
}
