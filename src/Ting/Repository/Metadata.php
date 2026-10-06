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

use CCMBenchmark\Ting\Serializer\ArrayValueInterface;
use CCMBenchmark\Ting\Serializer\DateTime;
use CCMBenchmark\Ting\Serializer\DateTimeImmutable;
use CCMBenchmark\Ting\Serializer\DateTimeZone;
use CCMBenchmark\Ting\Serializer\Json;
use CCMBenchmark\Ting\Serializer\ScalarValueInterface;
use CCMBenchmark\Ting\Serializer\Ip;
use CCMBenchmark\Ting\Serializer\Geometry;
use CCMBenchmark\Ting\Serializer\SerializerInterface;
use CCMBenchmark\Ting\Serializer\Uuid;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;
use CCMBenchmark\Ting\Query\QueryInterface;
use CCMBenchmark\Ting\Connection;
use CCMBenchmark\Ting\ConnectionPoolInterface;
use CCMBenchmark\Ting\Driver\DriverInterface;
use CCMBenchmark\Ting\Driver\SequenceAwareDriverInterface;
use CCMBenchmark\Ting\Driver\Mysqli\Serializer\Boolean as MysqliBoolean;
use CCMBenchmark\Ting\Driver\Pgsql\Serializer\Boolean as PgsqlBoolean;
use CCMBenchmark\Ting\Exception;
use CCMBenchmark\Ting\Exceptions\ConfigException;
use CCMBenchmark\Ting\Exceptions\SyntaxException;
use CCMBenchmark\Ting\Exceptions\ValueException;
use CCMBenchmark\Ting\Query\Generator;
use CCMBenchmark\Ting\Query\PreparedQuery;
use CCMBenchmark\Ting\Query\QueryFactoryInterface;
use CCMBenchmark\Ting\Serializer;
use CCMBenchmark\Ting\Util\PropertyAccessor;
use Closure;

/**
 * @template T of object
 * @phpstan-import-type SerializerOptions from Serializer\SerializeInterface
 * @phpstan-type Field array{
 *     fieldName: string,
 *     columnName: string,
 *     type: string,
 *     primary?: bool,
 *     autoincrement?: bool,
 *     sequenceName?: string,
 *     serializer?: class-string<Serializer\SerializerInterface>,
 *     serializer_options?: array{serialize?: SerializerOptions, unserialize?: SerializerOptions},
 *     setter?: string,
 *     getter?: string,
 *     mutable?: bool
 * }
 */
class Metadata
{
    protected ?string $connectionName     = null;
    protected ?string $databaseName = null;
    /** @var class-string<Repository<T>>|null */
    protected ?string $repository = null;
    /** @var class-string<T>|null */
    protected ?string $entity = null;
    protected ?string $table = null;
    protected string $schemaName = '';
    /** @phpstan-var array<string, Field> */
    protected array $fields = [];
    /** @phpstan-var array<string, Field> */
    protected array $fieldsByProperty = [];
    /** @phpstan-var array<string, Field> column name => field */
    protected array $primaries = [];
    /** @phpstan-var Field|null */
    protected array|null $autoincrement = null;
    /** @var array<string, class-string<SerializerInterface>> type => serializer of the fields without one */
    protected array $defaultSerializers = [
        'datetime' => DateTime::class,
        'datetime_immutable' => DateTimeImmutable::class,
        'datetimezone' => DateTimeZone::class,
        'json'     => Json::class,
        'ip'       => Ip::class,
        'geometry' => Geometry::class,
        'uuid'     => Uuid::class,
    ];
    /**
     * Serializers whose PHP values cannot be modified in place: their fields are immutable by default
     *
     * @var list<class-string<SerializerInterface>>
     */
    private const IMMUTABLE_SERIALIZERS = [
        DateTimeImmutable::class,
        DateTimeZone::class,
        Uuid::class,
        Ip::class,
        Geometry::class,
        Serializer\BackedEnum::class,
        MysqliBoolean::class,
        PgsqlBoolean::class,
    ];
    /** @var array<string, bool> property name => true when the field is mutable (written on every save) */
    private array $mutableProperties = [];
    /**
     * Fields of type "datetime" without serializer, as given to addField(): their serializer depends on the type of
     * their property, resolved again when the entity is set
     *
     * @phpstan-var array<string, Field> property name => field parameters
     */
    private array $dateTimeFieldsToResolve = [];
    /** @var array<string, array<string, true>> names of the columns read, joined by "\0" => properties not read */
    private array $propertiesNotRead = [];
    public PropertyAccessor $propertyAccessor;

    /**
     * @param Serializer\SerializerFactoryInterface $serializerFactory
     */
    public function __construct(private readonly SerializerFactoryInterface $serializerFactory)
    {
        $this->propertyAccessor = new PropertyAccessor();
    }

    /**
     * Return applicable connection
     * @param ConnectionPoolInterface $connectionPool
     * @return Connection
     * @throws ConfigException when the connection name or the database is not set
     *
     * @internal
     */
    public function getConnection(ConnectionPoolInterface $connectionPool): Connection
    {
        if ($this->connectionName === null || $this->databaseName === null) {
            throw new ConfigException(
                $this->describe() . ' used before setConnectionName() and setDatabase(): no connection to query'
            );
        }

        return new Connection($connectionPool, $this->connectionName, $this->databaseName);
    }

    /**
     * @param list<string> $fields
     * @throws ConfigException when the table is not set
     */
    private function queryGenerator(Connection $connection, QueryFactoryInterface $queryFactory, array $fields): Generator
    {
        if ($this->table === null) {
            throw new ConfigException($this->describe() . ' used before setTable(): no table to query');
        }

        return new Generator($connection, $queryFactory, $this->schemaName, $this->table, $fields);
    }

    private function describe(): string
    {
        return 'Metadata of ' . ($this->entity ?? 'an unknown entity');
    }

    /**
     * Set connection name related to configuration
     */
    public function setConnectionName(string $connectionName): static
    {
        $this->connectionName = $connectionName;

        return $this;
    }

    /**
     * Retrieve the connection name
     */
    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    public function setDatabase(string $databaseName): static
    {
        $this->databaseName = $databaseName;

        return $this;
    }

    /**
     * @return string
     *
     */
    public function getDatabase(): ?string
    {
        return $this->databaseName;
    }

    /**
     * Set repository name
     * @param class-string<Repository<T>> $className
     * @throws SyntaxException
     */
    public function setRepository(string $className): static
    {
        if (($className[0] ?? '') === '\\') {
            throw new SyntaxException('Class must not start with a \\');
        }

        $this->repository = (string) $className;

        return $this;
    }

    /**
     * @return class-string<Repository<T>>|null
     *
     * @internal
     */
    public function getRepository(): ?string
    {
        return $this->repository;
    }

    /**
     * Set entity name
     * @param class-string<T> $className
     * @throws SyntaxException
     */
    public function setEntity(string $className): static
    {
        if (($className[0] ?? '') === '\\') {
            throw new SyntaxException('Class must not start with a \\');
        }

        $this->entity = (string) $className;

        // The serializer of these fields depends on the type of their property in the entity
        foreach ($this->dateTimeFieldsToResolve as $params) {
            $this->addField($params);
        }

        return $this;
    }

    /**
     * @return class-string<T>|null null until setEntity() is called
     *
     * @internal
     */
    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setTable(string $tableName): static
    {
        $this->table = $tableName;

        return $this;
    }

    /**
     * Get table name
     * @return string
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    public function setSchema(string $schemaName): static
    {
        $this->schemaName = $schemaName;

        return $this;
    }

    /**
     * Get schema name
     * @return string
     */
    public function getSchema(): ?string
    {
        return $this->schemaName;
    }


    /**
     * Add a field to metadata.
     * @param Field $params Associative array with :
     *      fieldName : string : name of the property on the object
     *      columnName : string : name of the mysql column
     *      primary : boolean : is this field a primary - optional
     *      autoincrement : boolean : is this field an autoincrement - optional
     *      mutable : boolean : its PHP value can be modified in place, so it is written on every save of a managed
     *                          entity - optional, see isMutable() for the default
     * @throws ConfigException
     * @return $this
     */
    public function addField(array $params): static
    {
        $this->assertFieldIsValid($params);

        // Before the field is stored anywhere: primaries and autoincrement need the serializer too
        if (isset($params['serializer']) === false && $params['type'] === 'datetime') {
            $this->dateTimeFieldsToResolve[$params['fieldName']] = $params;
            $params['serializer'] = $this->getDateTimeSerializer($params['fieldName']);
        } elseif (isset($params['serializer']) === false && isset($this->defaultSerializers[$params['type']])) {
            unset($this->dateTimeFieldsToResolve[$params['fieldName']]);
            $params['serializer'] = $this->defaultSerializers[$params['type']];
        } else {
            unset($this->dateTimeFieldsToResolve[$params['fieldName']]);
        }

        if ($params['mutable'] ?? $this->isMutableByDefault($params)) {
            $this->mutableProperties[$params['fieldName']] = true;
        } else {
            unset($this->mutableProperties[$params['fieldName']]);
        }

        if (isset($params['primary']) && $params['primary'] === true) {
            $this->primaries[$params['columnName']] = $params;

            if (isset($params['autoincrement']) && $params['autoincrement'] === true) {
                $this->autoincrement = $params;
            }
        }

        $this->fieldsByProperty[$params['fieldName']] = $params;
        $this->fields[$params['columnName']] = $params;
        $this->propertiesNotRead = [];

        return $this;
    }

    /**
     * The configuration comes from the application: check it even though it is documented as a Field.
     *
     * @param array<mixed> $params
     * @throws ConfigException
     */
    private function assertFieldIsValid(array $params): void
    {
        if (isset($params['fieldName']) === false) {
            throw new ConfigException('Field configuration must have "fieldName" property');
        }

        if (isset($params['columnName']) === false) {
            throw new ConfigException('Field configuration must have "columnName" property');
        }

        if (isset($params['type']) === false) {
            throw new ConfigException('Field configuration must have "type" property');
        }

        if (isset($params['mutable']) && is_bool($params['mutable']) === false) {
            throw new ConfigException(
                sprintf('The "mutable" option of field "%s" must be a boolean', $params['fieldName'])
            );
        }
    }

    /**
     * A "datetime" field without serializer hydrates a \DateTimeImmutable when its property is typed
     * \DateTimeImmutable (nullable or not), and a \DateTime otherwise, as in 3.x: typed \DateTimeInterface (it can
     * hold a \DateTime, which Serializer\DateTimeImmutable cannot write), typed \DateTime, not typed, typed with a
     * union, not found (written through a setter), or entity not set yet.
     *
     * @return class-string<SerializerInterface>
     */
    private function getDateTimeSerializer(string $fieldName): string
    {
        $type = $this->entity === null ? null : $this->getPropertyType($this->entity, $fieldName);

        return $type === \DateTimeImmutable::class ? DateTimeImmutable::class : DateTime::class;
    }

    /**
     * @return string|null the class or type name of the property declared in the class or one of its parents, null
     *                     when it is not found, not typed or typed with a union or an intersection
     */
    private function getPropertyType(string $className, string $propertyName): ?string
    {
        if (class_exists($className) === false) {
            return null;
        }

        $class = new \ReflectionClass($className);

        do {
            if ($class->hasProperty($propertyName)) {
                $type = $class->getProperty($propertyName)->getType();

                return $type instanceof \ReflectionNamedType ? $type->getName() : null;
            }
            $class = $class->getParentClass();
        } while ($class !== false);

        return null;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function isMutableByDefault(array $params): bool
    {
        if (isset($params['serializer']) === false) {
            // Scalars
            return false;
        }

        if ($params['serializer'] === Json::class) {
            // Arrays are values, objects (\stdClass) are not. As json_decode(), without assoc (or null), the
            // JSON_OBJECT_AS_ARRAY flag decides
            $unserialize = $params['serializer_options']['unserialize'] ?? [];
            $assoc = $unserialize['assoc'] ?? ((($unserialize['options'] ?? 0) & JSON_OBJECT_AS_ARRAY) !== 0);

            return $assoc !== true;
        }

        // Serializer\DateTime, and any serializer of your own
        return in_array($params['serializer'], self::IMMUTABLE_SERIALIZERS, true) === false;
    }

    /**
     * A mutable field holds a PHP value that can be modified in place (a \DateTime, a \stdClass...): such a change is
     * not notified, so the unit of work writes the field on every save of a managed entity.
     * Set with the "mutable" option of the field; by default, a field is mutable when its serializer is
     * Serializer\DateTime, Serializer\Json decoding objects (neither the "assoc" unserialize option nor, without it, the
     * JSON_OBJECT_AS_ARRAY flag in the "options" unserialize option), or a serializer of your own.
     */
    public function isMutable(string $propertyName): bool
    {
        return isset($this->mutableProperties[$propertyName]);
    }

    /**
     * @return list<string> names of the mutable properties
     *
     * @internal
     */
    public function getMutableProperties(): array
    {
        return array_keys($this->mutableProperties);
    }

    /**
     * Properties whose column was not read: hydrated from a partial row (a SELECT of some columns, a join)
     *
     * @param array<string, true> $columns names of the columns of the metadata read
     * @return array<string, true> property name => true, for the properties left out. The same array for the rows
     *                             read with the same columns, so that the entities read alike share it.
     *
     * @internal
     */
    public function getPropertiesNotRead(array $columns): array
    {
        if (\count($columns) === \count($this->fields)) {
            return [];
        }

        $key = implode("\0", array_keys($columns));
        if (isset($this->propertiesNotRead[$key]) === false) {
            $properties = [];
            foreach ($this->fields as $column => $field) {
                if (isset($columns[$column]) === false) {
                    $properties[$field['fieldName']] = true;
                }
            }
            $this->propertiesNotRead[$key] = $properties;
        }

        return $this->propertiesNotRead[$key];
    }

    /**
     * Retrieve all defined primaries.
     *
     * @return array<string, Field> column name => field
     */
    public function getPrimaries(): array
    {
        return $this->primaries;
    }

    /**
     * Retrieve all defined fields.
     *
     * @return list<Field>
     */
    public function getFields(): array
    {
        return array_values($this->fields);
    }

    /**
     * Execute callback if the provided table is the actual
     *
     * @internal
     */
    public function ifTableKnown(string $connectionName, string $database, string $table, Closure $callback): bool
    {
        if ($this->table === $table
            && $this->connectionName === $connectionName && $this->databaseName === $database
        ) {
            $callback($this);
            return true;
        }

        return false;
    }

    /**
     * Returns true if the column is present in this metadata
     *
     * @internal
     */
    public function hasColumn(string $column): bool
    {
        return isset($this->fields[$column]);
    }

    /**
     * Returns true if the property is mapped to a column in this metadata
     *
     * @internal
     */
    public function hasProperty(string $propertyName): bool
    {
        return isset($this->fieldsByProperty[$propertyName]);
    }

    /**
     * Create a new entity
     * @return T
     *
     * @internal
     */
    public function createEntity(): object
    {
        return new $this->entity();
    }

    /**
     * Set the provided value to autoincrement if applicable
     *
     * @return Metadata<T>|false false without autoincrement
     *
     * @internal
     */
    public function setEntityPropertyForAutoIncrement(object $entity, DriverInterface $driver): Metadata|false
    {
        if ($this->autoincrement === null) {
            return false;
        }

        if ($driver instanceof SequenceAwareDriverInterface && isset($this->autoincrement['sequenceName'])) {
            $insertId = $driver->getInsertedIdForSequence($this->autoincrement['sequenceName']);
        } else {
            $insertId = $driver->getInsertedId();
        }

        $this->propertyAccessor->setValue($entity, $this->autoincrement['fieldName'], $insertId, $this->fieldsByProperty[$this->autoincrement['fieldName']]['setter'] ?? null);
        return $this;
    }

    /**
     * Set a property to the provided value
     *
     * @internal
     */
    public function setEntityProperty(object $entity, string $column, mixed $value): void
    {
        if (isset($this->fields[$column]['serializer'])) {
            $options = [];

            if (isset($this->fields[$column]['serializer_options']['unserialize'])) {
                $options = $this->fields[$column]['serializer_options']['unserialize'];
            }
            $value = $this->serializerFactory->get($this->fields[$column]['serializer'])->unserialize($value, $options);
        } elseif ($value !== null) {
            switch ($this->fields[$column]['type']) {
                case "int":
                    $value = (int) $value;
                    break;
                case "double":
                    $value = (float) $value;
                    break;
                case "bool":
                    // PostgreSQL returns a boolean as 't' or 'f', and (bool) 'f' is true
                    $value = $value !== 'f' && (bool) $value;
                    break;
            }
        }

        $this->propertyAccessor->setValue($entity, $this->fields[$column]['fieldName'], $value, $this->fields[$column]['setter'] ?? null);
    }

    /**
     * Retrieve property of entity according to the field (unserialize if needed)
     * @param Field $field
     */
    protected function getEntityProperty(object $entity, array $field): mixed
    {
        return $this->serializeFieldValue(
            $field,
            $this->propertyAccessor->getValue($entity, $field['fieldName'], $field['getter'] ?? null)
        );
    }

    /**
     * Database value of a property value: serialized by the serializer of its field, if any
     *
     * @internal
     */
    public function getDatabaseValueOfProperty(string $fieldName, mixed $value): mixed
    {
        return $this->serializeFieldValue($this->fieldsByProperty[$fieldName], $value);
    }

    /**
     * Database value of a field: the value serialized by the serializer of the field, if any
     *
     * @param Field $field
     */
    private function serializeFieldValue(array $field, mixed $value): mixed
    {
        if (isset($field['serializer']) === false) {
            return $value;
        }

        return $this->serializerFactory->get($field['serializer'])->serialize(
            $value,
            $field['serializer_options']['serialize'] ?? []
        );
    }
    
    public function getEntityPropertyByFieldName(object $entity, string $fieldName): mixed
    {
        $field = $this->fieldsByProperty[$fieldName];
        return $this->getEntityProperty($entity, $field);
    }

    /**
     * Returns false for a public typed property not initialized
     *
     * @internal
     */
    public function isEntityPropertyReadable(object $entity, string $fieldName): bool
    {
        return $this->propertyAccessor->isReadable(
            $entity,
            $fieldName,
            $this->fieldsByProperty[$fieldName]['getter'] ?? null
        );
    }

    /**
     * Database values of the mutable primary keys of the entity: once modified in place, the row is still stored with
     * these values
     *
     * @return array<string, mixed> property name => database value
     *
     * @internal
     */
    public function getEntityMutablePrimaryValues(object $entity): array
    {
        $values = [];
        foreach ($this->primaries as $primary) {
            $fieldName = $primary['fieldName'];
            if (isset($this->mutableProperties[$fieldName])
                && $this->propertyAccessor->isReadable($entity, $fieldName, $primary['getter'] ?? null)
            ) {
                $values[$fieldName] = $this->getEntityProperty($entity, $primary);
            }
        }

        return $values;
    }

    /**
     * Return a Query to get one object by it's primaries
     *
     * @template U
     * @param CollectionFactoryInterface<U> $collectionFactory
     * @return QueryInterface<U>
     *
     * @internal
     */
    public function getByPrimaries(
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        CollectionFactoryInterface $collectionFactory,
        mixed $primariesKeyValue,
        bool $forcePrimary = false
    ): QueryInterface {
        $fields = array_keys($this->fields);
        $queryGenerator = $this->queryGenerator($connection, $queryFactory, $fields);

        $primariesKeyValue = $this->getPrimariesKeyValuesAsArray($primariesKeyValue);

        return $queryGenerator->getOneByCriteria($primariesKeyValue, $collectionFactory, $forcePrimary);
    }


    /**
     * Return a Query to get one object by an associative array of criterias
     *
     * @template U
     * @param CollectionFactoryInterface<U> $collectionFactory
     * @param array<string, mixed> $criteria property name => value
     * @return QueryInterface<U>
     *
     * @internal
     */
    public function getOneByCriteria(
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        CollectionFactoryInterface $collectionFactory,
        array $criteria,
        bool $forcePrimary = false
    ): QueryInterface {
        $fields = array_keys($this->fields);
        $queryGenerator = $this->queryGenerator($connection, $queryFactory, $fields);

        $this->assertCriteriaNotEmpty($criteria, 'Repository::getOneBy()');
        $criteriaColumn = $this->convertCriteria($criteria, 'the criteria of Repository::getOneBy()');

        return $queryGenerator->getOneByCriteria($criteriaColumn, $collectionFactory, $forcePrimary);
    }

    /**
     * Convert criteria keyed by property name into database-ready values keyed by column name
     *
     * @param array<string, mixed> $criteria property name => value
     * @return array<string, mixed> column name => value (null, scalar, or list of scalars for an IN list)
     * @throws ValueException
     */
    protected function getColumnsFromCriteria(array $criteria): array
    {
        return $this->convertCriteria($criteria, 'the criteria');
    }

    /**
     * Without criteria, the WHERE clause would be empty (invalid SQL)
     *
     * @param array<string, mixed> $criteria
     * @throws ValueException
     */
    private function assertCriteriaNotEmpty(array $criteria, string $method): void
    {
        if ($criteria === []) {
            throw new ValueException(sprintf('No criteria in %s: use Repository::getAll() to read every row', $method));
        }
    }

    /**
     * @param array<string, mixed> $criteria property name => value
     * @param string $context where the criteria come from, for the exception messages
     * @return array<string, mixed> column name => value (null, scalar, or list of scalars for an IN list)
     * @throws ValueException
     */
    private function convertCriteria(array $criteria, string $context): array
    {
        $criteriaColumn = [];
        foreach ($criteria as $property => $value) {
            $field = $this->getFieldByProperty((string) $property, $context);
            $criteriaColumn[$field['columnName']] = $this->getDatabaseValue($field, $value, $context);
        }

        return $criteriaColumn;
    }

    /**
     * Convert an order keyed by property name into directions keyed by column name
     *
     * @param array<string, mixed> $order property name => "ASC" or "DESC" (case-insensitive)
     * @return array<string, string> column name => "ASC" or "DESC"
     * @throws ValueException
     */
    private function getColumnsFromOrder(array $order): array
    {
        $context = 'the order of Repository::getBy()';
        $orderColumn = [];
        foreach ($order as $property => $direction) {
            $field = $this->getFieldByProperty((string) $property, $context);
            $normalizedDirection = is_string($direction) ? strtoupper($direction) : null;
            if ($normalizedDirection !== 'ASC' && $normalizedDirection !== 'DESC') {
                throw new ValueException(sprintf(
                    'Invalid direction "%s" for property "%s" in %s: use "ASC" or "DESC"',
                    is_string($direction) ? $direction : get_debug_type($direction),
                    $property,
                    $context
                ));
            }
            $orderColumn[$field['columnName']] = $normalizedDirection;
        }

        return $orderColumn;
    }

    /**
     * @return Field
     * @throws ValueException when the property is unknown, naming the property to use when a column name is given
     */
    private function getFieldByProperty(string $property, string $context): array
    {
        if (isset($this->fieldsByProperty[$property])) {
            return $this->fieldsByProperty[$property];
        }

        if (isset($this->fields[$property])) {
            throw new ValueException(sprintf(
                '"%s" is a column name: use the property name "%s" in %s',
                $property,
                $this->fields[$property]['fieldName'],
                $context
            ));
        }

        throw new ValueException(sprintf('Undefined property "%s" in %s', $property, $context));
    }

    /**
     * Convert a criterion value into a value for the database:
     * - null is kept (IS NULL);
     * - an array is serialized as a whole when the serializer of the field implements ArrayValueInterface,
     *   otherwise each element is converted (IN list);
     * - an object is serialized by the serializer of the field (a Stringable object without serializer is sent as is);
     * - a scalar is serialized when the serializer of the field implements ScalarValueInterface, otherwise sent as is.
     *
     * @param Field $field
     * @throws ValueException
     */
    private function getDatabaseValue(array $field, mixed $value, string $context): mixed
    {
        if ($value === null) {
            return null;
        }

        $serializer = isset($field['serializer']) ? $this->serializerFactory->get($field['serializer']) : null;

        if (is_array($value) === false) {
            return $this->getDatabaseScalarValue($field, $serializer, $value, $context);
        }

        if ($serializer instanceof ArrayValueInterface) {
            return $serializer->serialize($value, $field['serializer_options']['serialize'] ?? []);
        }

        if ($value === []) {
            throw new ValueException(sprintf(
                'Empty array for property "%s" in %s: nothing can match',
                $field['fieldName'],
                $context
            ));
        }

        $databaseValues = [];
        foreach ($value as $key => $element) {
            if ($element === null) {
                throw new ValueException(sprintf(
                    'Null in the array for property "%s" in %s: an IN list never matches NULL',
                    $field['fieldName'],
                    $context
                ));
            }
            if (is_array($element)) {
                throw new ValueException(sprintf(
                    'Nested array for property "%s" in %s',
                    $field['fieldName'],
                    $context
                ));
            }
            $databaseValues[$key] = $this->getDatabaseScalarValue($field, $serializer, $element, $context);
        }

        return $databaseValues;
    }

    /**
     * @param Field $field
     * @throws ValueException
     */
    private function getDatabaseScalarValue(
        array $field,
        ?SerializerInterface $serializer,
        mixed $value,
        string $context
    ): mixed {
        if (is_object($value) === false) {
            if ($serializer instanceof ScalarValueInterface === false) {
                return $value;
            }

            $databaseValue = $serializer->serialize($value, $field['serializer_options']['serialize'] ?? []);
            if ($databaseValue === null) {
                // Compared with "=", NULL would never match: reject the value instead of returning no row
                throw new ValueException(sprintf(
                    'Invalid value %s for property "%s" in %s: the serializer of the field converts it to NULL',
                    var_export($value, true),
                    $field['fieldName'],
                    $context
                ));
            }

            return $databaseValue;
        }

        if ($serializer === null) {
            if ($value instanceof \Stringable) {
                // Cast to string by the driver, e.g. a Symfony Uuid on a plain string field
                return $value;
            }

            throw new ValueException(sprintf(
                'Cannot use an object of class "%s" for property "%s" in %s: its field has no serializer',
                $value::class,
                $field['fieldName'],
                $context
            ));
        }

        return $serializer->serialize($value, $field['serializer_options']['serialize'] ?? []);
    }

    /**
     * Retrieve all lines from the table
     *
     * @param Connection                 $connection
     * @param QueryFactoryInterface      $queryFactory
     * @template U
     * @param CollectionFactoryInterface<U> $collectionFactory
     * @param bool                       $forcePrimary
     * @return QueryInterface<U>
     *
     * @internal
     */
    public function getAll(
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        CollectionFactoryInterface $collectionFactory,
        bool $forcePrimary = false
    ): QueryInterface {
        $fields = array_keys($this->fields);
        $queryGenerator = $this->queryGenerator($connection, $queryFactory, $fields);

        return $queryGenerator->getAll($collectionFactory, $forcePrimary);
    }

    /**
     * Retrieve matching lines from the table, according to the criteria
     *
     * @template U
     * @param array<string, mixed> $criteria property name => value
     * @param CollectionFactoryInterface<U> $collectionFactory
     * @return QueryInterface<U>
     *
     * @internal
     */
    public function getByCriteria(
        array $criteria,
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        CollectionFactoryInterface $collectionFactory,
        bool $forcePrimary = false
    ): QueryInterface {
        $fields = array_keys($this->fields);
        $queryGenerator = $this->queryGenerator($connection, $queryFactory, $fields);

        $this->assertCriteriaNotEmpty($criteria, 'Repository::getBy()');
        $criteriaColumn = $this->convertCriteria($criteria, 'the criteria of Repository::getBy()');

        return $queryGenerator->getByCriteria($criteriaColumn, $collectionFactory, $forcePrimary);
    }

    /**
     * Retrieve matching lines from the table, according to the criteria, ordered and limited
     *
     * @template U
     * @param array<string, mixed> $criteria property name => value
     * @param array<string, string> $orderBy property name => "ASC" or "DESC"
     * @param CollectionFactoryInterface<U> $collectionFactory
     * @return QueryInterface<U>
     *
     * @internal
     */
    public function getByCriteriaWithOrderAndLimit(
        array $criteria,
        array $orderBy,
        int $limit,
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        CollectionFactoryInterface $collectionFactory,
        bool $forcePrimary = false
    ): QueryInterface {
        $fields = array_keys($this->fields);
        $queryGenerator = $this->queryGenerator($connection, $queryFactory, $fields);
        $this->assertCriteriaNotEmpty($criteria, 'Repository::getBy()');
        $criteriaColumn = $this->convertCriteria($criteria, 'the criteria of Repository::getBy()');
        $orderColumn = $this->getColumnsFromOrder($orderBy);

        return $queryGenerator->getByCriteria($criteriaColumn, $collectionFactory, $forcePrimary, $orderColumn, $limit);
    }

    /**
     * @param mixed $originalValue property name => value, or just the value when there is one primary key
     * @return array<string, mixed> column name => database-ready value
     * @throws Exception
     * @throws ValueException
     */
    protected function getPrimariesKeyValuesAsArray(mixed $originalValue): array
    {
        $context = 'Repository::get()';

        if (is_array($originalValue) === false) {
            if (count($this->primaries) == 1) {
                $columnName = array_key_first($this->primaries);
                return [$columnName => $this->getDatabaseValue($this->primaries[$columnName], $originalValue, $context)];
            }
            throw new Exception('Incorrect format for primaries');
        }

        if ($originalValue === []) {
            throw new ValueException('No primary key value in ' . $context);
        }

        return $this->convertCriteria($originalValue, $context);
    }

    /**
     * Return a query to insert a row in database
     *
     * @param Connection $connection
     * @param QueryFactoryInterface $queryFactory
     * @param $entity
     * @return PreparedQuery<mixed>
     *
     * @internal
     */
    public function generateQueryForInsert(
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        object $entity
    ): PreparedQuery {
        $values = [];

        foreach ($this->fields as $column => $field) {
            if ($field['autoincrement'] ?? false) {
                continue;
            }
            
            // Public typed properties non initialized is non-readable
            // In this case we don't insert it, relying on database default value
            if ($this->propertyAccessor->isReadable($entity, $field['fieldName'], $field['getter'] ?? null)) {
                $values[$column] = $this->getEntityProperty($entity, $field);
            }
        }

        $fields = array_keys($this->fields);
        $queryGenerator = $this->queryGenerator($connection, $queryFactory, $fields);

        return $queryGenerator->insert($values);
    }

    /**
     * Return a query to update a row in database
     *
     * @param array<string, array{0: mixed, 1: mixed}> $properties changed properties: name => [database value before
     *                                                            the change, new database value]
     * @return PreparedQuery<mixed>
     *
     * @internal
     */
    public function generateQueryForUpdate(
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        object $entity,
        array $properties
    ): PreparedQuery {
        $queryGenerator = $this->queryGenerator($connection, $queryFactory, array_keys($properties));

        $values = [];
        foreach ($properties as $name => [, $value]) {
            $values[$this->fieldsByProperty[$name]['columnName']] = $value;
        }

        $primariesKeyValue = $this->getPrimariesKeyValuesByProperties($properties, $entity);

        return $queryGenerator->update($values, $primariesKeyValue);
    }

    /**
     * Return a query to delete a row from database
     *
     * @param Connection            $connection
     * @param QueryFactoryInterface $queryFactory
     * @param array<string, array{0: mixed, 1: mixed}> $properties changed properties: name => [database value before
     *                                                            the change, new database value]
     * @param object                $entity
     * @return PreparedQuery<mixed>
     *
     * @internal
     */
    public function generateQueryForDelete(
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        array $properties,
        object $entity
    ): PreparedQuery {
        $queryGenerator = $this->queryGenerator($connection, $queryFactory, array_keys($properties));

        $primariesKeyValue = $this->getPrimariesKeyValuesByProperties($properties, $entity);

        return $queryGenerator->delete($primariesKeyValue);
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $properties changed properties: name => [database value before
     *                                                            the change, new database value]
     * @return array<string, mixed> primary key values by column name, as currently stored in the database
     */
    protected function getPrimariesKeyValuesByProperties(array $properties, object $entity): array
    {
        $primariesKeyValue = [];
        foreach ($this->primaries as $key => $primary) {
            $fieldName = $primary['fieldName'];
            if (isset($properties[$fieldName])) {
                // Key value has been updated: the row is still stored with the old one
                $primariesKeyValue[$key] = $properties[$fieldName][0];
            } else {
                $primariesKeyValue[$key] = $this->getEntityProperty($entity, $primary);
            }
        }
        return $primariesKeyValue;
    }
}
