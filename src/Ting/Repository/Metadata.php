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
use CCMBenchmark\Ting\Serializer\Ip;
use CCMBenchmark\Ting\Serializer\Geometry;
use CCMBenchmark\Ting\Serializer\SerializerInterface;
use CCMBenchmark\Ting\Serializer\Uuid;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;
use CCMBenchmark\Ting\Query\QueryInterface;
use CCMBenchmark\Ting\Connection;
use CCMBenchmark\Ting\ConnectionPoolInterface;
use CCMBenchmark\Ting\Driver\DriverInterface;
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
 * @template T
 * @phpstan-type Field array{
 *     fieldName: string,
 *     columnName: string,
 *     type: string,
 *     primary?: bool,
 *     autoincrement?: bool,
 *     serializer?: class-string<Serializer\SerializerInterface>,
 *     serializer_options?: array{serialize?: array<mixed>, unserialize?: array<mixed>},
 *     setter?: string,
 *     getter?: string
 * }
 */
class Metadata
{
    protected ?string $connectionName     = null;
    protected ?string $databaseName = null;
    /** @var class-string<Repository<T>>|null */
    protected $repository = null;
    /** @var class-string<T>|null */
    protected ?string $entity = null;
    protected ?string $table = null;
    protected string $schemaName = '';
    /** @phpstan-var array<string, Field> */
    protected array $fields = [];
    /** @phpstan-var array<string, Field> */
    protected array $fieldsByProperty = [];
    protected array $primaries = [];
    protected array|null $autoincrement = null;
    protected array $defaultSerializers = [
        'datetime' => DateTime::class,
        'datetime_immutable' => DateTimeImmutable::class,
        'datetimezone' => DateTimeZone::class,
        'json'     => Json::class,
        'ip'       => Ip::class,
        'geometry' => Geometry::class,
        'uuid'     => Uuid::class,
    ];
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
     *
     * @internal
     */
    public function getConnection(ConnectionPoolInterface $connectionPool): Connection
    {
        return new Connection($connectionPool, $this->connectionName, $this->databaseName);
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
    public function getRepository()
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
     * @param array $params. Associative array with :
     *      fieldName : string : name of the property on the object
     *      columnName : string : name of the mysql column
     *      primary : boolean : is this field a primary - optional
     *      autoincrement : boolean : is this field an autoincrement - optional
     * @throws ConfigException
     * @return $this
     */
    public function addField(array $params): static
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

        if (isset($params['primary']) && $params['primary'] === true) {
            $this->primaries[$params['columnName']] = $params;

            if (isset($params['autoincrement']) && $params['autoincrement'] === true) {
                $this->autoincrement = $params;
            }
        }

        if (isset($params['serializer']) === false && isset($this->defaultSerializers[$params['type']])) {
            $params['serializer'] = $this->defaultSerializers[$params['type']];
        }

        $this->fieldsByProperty[$params['fieldName']] = $params;
        $this->fields[$params['columnName']] = $params;

        return $this;
    }

    /**
     * Retrieve all defined primaries.
     *
     * @return array
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
     * Create a new entity
     * @return T
     *
     * @internal
     */
    public function createEntity()
    {
        return new $this->entity();
    }

    /**
     * Set the provided value to autoincrement if applicable
     * @internal
     */
    public function setEntityPropertyForAutoIncrement(object $entity, DriverInterface $driver): Metadata|false
    {
        if ($this->autoincrement === null) {
            return false;
        }

        if (method_exists($driver, 'getInsertedIdForSequence')
            && isset($this->autoincrement['sequenceName'])
        ) {
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
                    $value = (bool) $value;
                    break;
            }
        }

        $this->propertyAccessor->setValue($entity, $this->fields[$column]['fieldName'], $value, $this->fields[$column]['setter'] ?? null);
    }

    /**
     * Retrieve property of entity according to the field (unserialize if needed)
     * @param array{fieldName: string, getter?: string, serializer?: class-string<SerializerInterface>, serializer_options?: array{serialize?: array, unserialize?: array}} $field
     */
    protected function getEntityProperty(object $entity, array $field): mixed
    {
        $value = $this->propertyAccessor->getValue($entity, $field['fieldName'], $field['getter'] ?? null);

        if (isset($field['serializer'])) {
            $options = [];

            if (isset($field['serializer_options']['serialize'])) {
                $options = $field['serializer_options']['serialize'];
            }
            $value = $this->serializerFactory->get($field['serializer'])->serialize($value, $options);
        }

        return $value;
    }
    
    public function getEntityPropertyByFieldName(object $entity, string $fieldName): mixed
    {
        $field = $this->fieldsByProperty[$fieldName];
        return $this->getEntityProperty($entity, $field);
    }
    
    /**
     * Return a Query to get one object by it's primaries
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
        $queryGenerator = new Generator(
            $connection,
            $queryFactory,
            $this->schemaName,
            $this->table,
            $fields
        );

        $primariesKeyValue = $this->getPrimariesKeyValuesAsArray($primariesKeyValue);

        return $queryGenerator->getOneByCriteria($primariesKeyValue, $collectionFactory, $forcePrimary);
    }


    /**
     * Return a Query to get one object by an associative array of criterias
     *
     * @return QueryInterface<T>
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
        $queryGenerator = new Generator(
            $connection,
            $queryFactory,
            $this->schemaName,
            $this->table,
            $fields
        );

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
     * - a scalar is sent as is.
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
            return $value;
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
     * @param CollectionFactoryInterface $collectionFactory
     * @param bool                       $forcePrimary
     * @return QueryInterface
     *
     * @internal
     */
    public function getAll(
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        CollectionFactoryInterface $collectionFactory,
        $forcePrimary = false
    ): QueryInterface {
        $fields = array_keys($this->fields);
        $queryGenerator = new Generator(
            $connection,
            $queryFactory,
            $this->schemaName,
            $this->table,
            $fields
        );

        return $queryGenerator->getAll($collectionFactory, $forcePrimary);
    }

    /**
     * Retrieve matching lines from the table, according to the criteria
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
        $queryGenerator = new Generator(
            $connection,
            $queryFactory,
            $this->schemaName,
            $this->table,
            $fields
        );

        $criteriaColumn = $this->convertCriteria($criteria, 'the criteria of Repository::getBy()');

        return $queryGenerator->getByCriteria($criteriaColumn, $collectionFactory, $forcePrimary);
    }

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
        $queryGenerator = new Generator(
            $connection,
            $queryFactory,
            $this->schemaName,
            $this->table,
            $fields
        );
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

        return $this->convertCriteria($originalValue, $context);
    }

    /**
     * Return a query to insert a row in database
     *
     * @param Connection $connection
     * @param QueryFactoryInterface $queryFactory
     * @param $entity
     * @return PreparedQuery
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
        $queryGenerator = new Generator(
            $connection,
            $queryFactory,
            $this->schemaName,
            $this->table,
            $fields
        );

        return $queryGenerator->insert($values);
    }

    /**
     * Return a query to update a row in database
     *
     * @param array<string, array{0: mixed, 1: mixed}> $properties changed properties: name => [old value, new value]
     *
     * @internal
     */
    public function generateQueryForUpdate(
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        object $entity,
        array $properties
    ): PreparedQuery {
        $queryGenerator = new Generator(
            $connection,
            $queryFactory,
            $this->schemaName,
            $this->table,
            array_keys($properties)
        );

        // Get new values affected to entity
        $values = [];
        foreach ($properties as $name => $value) {
            $columnName = $this->fieldsByProperty[$name]['columnName'];

            // Public typed properties non initialized is non-readable
            // In this case we don't update it, so it will keep the current value
            if ($this->propertyAccessor->isReadable($entity, $this->fieldsByProperty[$name]['fieldName'], $this->fieldsByProperty[$name]['getter'] ?? null)) {
                $values[$columnName] = $this->getEntityProperty($entity, $this->fieldsByProperty[$name]);
            }
        }

        $primariesKeyValue = $this->getPrimariesKeyValuesByProperties($properties, $entity);

        return $queryGenerator->update($values, $primariesKeyValue);
    }

    /**
     * Return a query to delete a row from database
     *
     * @param Connection            $connection
     * @param QueryFactoryInterface $queryFactory
     * @param array<string, array{0: mixed, 1: mixed}> $properties changed properties: name => [old value, new value]
     * @param object                $entity
     * @return PreparedQuery
     *
     * @internal
     */
    public function generateQueryForDelete(
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        array $properties,
        object $entity
    ): PreparedQuery {
        $queryGenerator = new Generator(
            $connection,
            $queryFactory,
            $this->schemaName,
            $this->table,
            array_keys($properties)
        );

        $primariesKeyValue = $this->getPrimariesKeyValuesByProperties($properties, $entity);

        return $queryGenerator->delete($primariesKeyValue);
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}> $properties changed properties: name => [old value, new value]
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
                $primariesKeyValue[$key] = $this->propertyAccessor->getValue(
                    $entity,
                    $fieldName,
                    $primary['getter'] ?? null
                );
            }
        }
        return $primariesKeyValue;
    }
}
