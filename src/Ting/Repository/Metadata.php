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

/**
 * @template T of object
 * @phpstan-type Field array{
 *     fieldName: string,
 *     columnName: string,
 *     type: string,
 *     primary?: bool,
 *     autoincrement?: bool,
 *     serializer?: class-string<Serializer\SerializerInterface>,
 *     serializer_options?: array{serialize?: array<mixed>, unserialize?: array<mixed>}
 * }
 */
class Metadata
{
    protected $connectionName     = null;
    protected $databaseName       = null;
    /** @var class-string<Repository<T>>|null */
    protected $repository         = null;
    /** @var class-string<T>|null */
    protected $entity             = null;
    protected $table              = null;
    protected $schemaName         = '';
    /** @phpstan-var array<string, Field> */
    protected $fields             = [];
    /** @phpstan-var array<string, Field> */
    protected $fieldsByProperty   = [];
    protected $primaries          = [];
    protected $autoincrement      = null;
    protected $defaultSerializers = [
        'datetime' => Serializer\DateTime::class,
        'datetime_immutable' => Serializer\DateTimeImmutable::class,
        'datetimezone' => Serializer\DateTimeZone::class,
        'json'     => Serializer\Json::class,
        'ip'       => Serializer\Ip::class,
        'geometry' => Serializer\Geometry::class,
        'uuid'     => Serializer\Uuid::class,
    ];
    public PropertyAccessor $propertyAccessor;

    /**
     * @param Serializer\SerializerFactoryInterface $serializerFactory
     */
    public function __construct(private Serializer\SerializerFactoryInterface $serializerFactory)
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
    public function getConnection(ConnectionPoolInterface $connectionPool)
    {
        return new Connection($connectionPool, $this->connectionName, $this->databaseName);
    }

    /**
     * Set connection name related to configuration
     * @param string $connectionName
     * @return $this
     */
    public function setConnectionName($connectionName)
    {
        $this->connectionName = (string) $connectionName;

        return $this;
    }

    /**
     * Retrieve the connection name
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connectionName;
    }

    /**
     * @param string $databaseName
     * @return $this
     *
     */
    public function setDatabase($databaseName)
    {
        $this->databaseName = (string) $databaseName;

        return $this;
    }

    /**
     * @return string
     *
     */
    public function getDatabase()
    {
        return $this->databaseName;
    }

    /**
     * Set repository name
     * @param class-string<Repository<T>> $className
     * @return $this
     * @throws SyntaxException
     */
    public function setRepository($className)
    {
        if (($className[0] ?? '') === '\\') {
            throw new SyntaxException('Class must not start with a \\');
        }

        $this->repository = (string) $className;

        return $this;
    }

    /**
     * @return class-string<Repository<T>>
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
     * @return $this
     * @throws SyntaxException
     */
    public function setEntity($className)
    {
        if (($className[0] ?? '') === '\\') {
            throw new SyntaxException('Class must not start with a \\');
        }

        $this->entity = (string) $className;

        return $this;
    }

    /**
     * @return class-string<T>
     *
     * @internal
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * Set table name
     * @param string $tableName
     * @return $this
     */
    public function setTable($tableName)
    {
        $this->table = (string) $tableName;

        return $this;
    }

    /**
     * Get table name
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * @param string $schemaName
     * @return $this
     *
     */
    public function setSchema($schemaName)
    {
        $this->schemaName = (string) $schemaName;

        return $this;
    }

    /**
     * Get schema name
     * @return string
     */
    public function getSchema()
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
     * @phpstan-param Field $params
     * @throws ConfigException
     * @return $this
     */
    public function addField(array $params)
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

        if (isset($params['primary']) === true && $params['primary'] === true) {
            $this->primaries[$params['columnName']] = $params;

            if (isset($params['autoincrement']) === true && $params['autoincrement'] === true) {
                $this->autoincrement = $params;
            }
        }

        if (isset($params['serializer']) === false) {
            if (isset($this->defaultSerializers[$params['type']]) === true) {
                $params['serializer'] = $this->defaultSerializers[$params['type']];
            }
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
    public function getPrimaries()
    {
        return $this->primaries;
    }

    /**
     * Retrieve all defined fields.
     *
     * @return list<Field>
     */
    public function getFields()
    {
        return array_values($this->fields);
    }

    /**
     * Execute callback if the provided table is the actual
     * @param string   $connectionName
     * @param string   $database
     * @param string   $table
     * @param \Closure $callback
     * @return bool
     *
     * @internal
     */
    public function ifTableKnown($connectionName, $database, $table, \Closure $callback)
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
     * @param $column
     * @return bool
     *
     * @internal
     */
    public function hasColumn($column)
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
     * @param object          $entity
     * @param DriverInterface $driver
     * @return $this|bool
     *
     * @throws
     *
     * @internal
     */
    public function setEntityPropertyForAutoIncrement($entity, DriverInterface $driver)
    {
        if ($this->autoincrement === null) {
            return false;
        }

        if (method_exists($driver, 'getInsertedIdForSequence') === true
            && isset($this->autoincrement['sequenceName']) === true
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
     * @param $entity
     * @param $column
     * @param $value
     *
     * @internal
     */
    public function setEntityProperty($entity, $column, $value)
    {
        if (isset($this->fields[$column]['serializer']) === true) {
            $options = [];

            if (isset($this->fields[$column]['serializer_options']['unserialize']) === true) {
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
     * @param object $entity
     * @param array $field
     * @return mixed
     *
     */
    protected function getEntityProperty($entity, $field)
    {
        $value = $this->propertyAccessor->getValue($entity, $field['fieldName'], $field['getter'] ?? null);

        if (isset($field['serializer']) === true) {
            $options = [];

            if (isset($field['serializer_options']['serialize']) === true) {
                $options = $field['serializer_options']['serialize'];
            }
            $value = $this->serializerFactory->get($field['serializer'])->serialize($value, $options);
        }

        return $value;
    }
    
    public function getEntityPropertyByFieldName($entity, $fieldName)
    {
        $field = $this->fieldsByProperty[$fieldName];
        return $this->getEntityProperty($entity, $field);
    }
    
    /**
     * Return a Query to get one object by it's primaries
     *
     * @param Connection $connection
     * @param QueryFactoryInterface $queryFactory
     * @param CollectionFactoryInterface $collectionFactory
     * @param $primariesKeyValue array|mixed property => value, or the value when there is only one primary key
     * @param $forceMaster boolean
     * @return \CCMBenchmark\Ting\Query\Query
     *
     * @internal
     */
    public function getByPrimaries(
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        CollectionFactoryInterface $collectionFactory,
        $primariesKeyValue,
        $forceMaster = false
    ) {
        $fields = array_keys($this->fields);
        $queryGenerator = new Generator(
            $connection,
            $queryFactory,
            $this->schemaName,
            $this->table,
            $fields
        );

        if (\is_array($primariesKeyValue) === true) {
            // A key that is neither a property nor a column is sent as is, as before 3.15
            $primariesKeyValue = $this->getCriteriaByColumn($primariesKeyValue, 'Repository::get()', false);
        } else {
            // Only one primary key, given as a value: the array is built by Ting, keyed by column
            $primariesKeyValue = $this->getPrimariesKeyValuesAsArray($primariesKeyValue);
            foreach ($primariesKeyValue as $column => $value) {
                if (isset($this->fields[$column]) === true) {
                    $primariesKeyValue[$column] = $this->getCriterionValue($this->fields[$column], $value, 'Repository::get()');
                }
            }
        }

        return $queryGenerator->getOneByCriteria($primariesKeyValue, $collectionFactory, $forceMaster);
    }


    /**
     * Return a Query to get one object by an associative array of criterias
     *
     * @param Connection $connection
     * @param QueryFactoryInterface $queryFactory
     * @param CollectionFactoryInterface $collectionFactory
     * @param $criteria array
     * @param $forceMaster boolean
     * @return \CCMBenchmark\Ting\Query\Query
     * @throws Exception
     *
     * @internal
     */
    public function getOneByCriteria(
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        CollectionFactoryInterface $collectionFactory,
        array $criteria,
        $forceMaster = false
    ) {
        $fields = array_keys($this->fields);
        $queryGenerator = new Generator(
            $connection,
            $queryFactory,
            $this->schemaName,
            $this->table,
            $fields
        );

        $criteriaColumn = $this->getCriteriaByColumn($criteria, 'the criteria of Repository::getOneBy()');

        return $queryGenerator->getOneByCriteria($criteriaColumn, $collectionFactory, $forceMaster);
    }

    /**
     * No longer used by Ting, kept for the classes extending Metadata.
     *
     * @param array $criteria property => value
     * @return array column => database value
     * @throws ValueException
     */
    protected function getColumnsFromCriteria(array $criteria)
    {
        return $this->getCriteriaByColumn($criteria, 'the criteria of Repository::getBy()');
    }

    /**
     * Converts criteria keyed by property into criteria keyed by column, with database values (see getCriterionValue()).
     *
     * @param array  $criteria          property => value
     * @param string $context           where the criteria come from, for the messages
     * @param bool   $unknownKeyIsError false to send as is a key that is neither a property nor a column
     * @return array column => database value
     * @throws ValueException
     */
    private function getCriteriaByColumn(array $criteria, string $context, bool $unknownKeyIsError = true): array
    {
        $criteriaColumn = [];
        foreach ($criteria as $key => $value) {
            $field = $this->getFieldByKey($key, $context);
            if ($field === null) {
                if ($unknownKeyIsError === true) {
                    throw new ValueException(sprintf('Undefined property %s in your criteria', $key));
                }
                $criteriaColumn[$key] = $value;
                continue;
            }
            $criteriaColumn[$field['columnName']] = $this->getCriterionValue($field, $value, $context);
        }

        return $criteriaColumn;
    }

    /**
     * Converts an order keyed by property into an order keyed by column.
     * A key that is neither a property nor a column is sent as is, as before 3.15.
     *
     * @param array<string, string> $orderBy property => ASC|DESC
     * @return array<string, string> column => ASC|DESC
     */
    private function getOrderByColumn(array $orderBy): array
    {
        $context = 'the order of Repository::getBy()';
        $orderColumn = [];
        foreach ($orderBy as $key => $direction) {
            $field = $this->getFieldByKey($key, $context);
            if (\is_string($direction) === false || \in_array(strtoupper($direction), ['ASC', 'DESC'], true) === false) {
                // The query generator ignores this direction
                @trigger_error(sprintf(
                    'Using the direction "%s" for "%s" in %s is deprecated since Ting 3.15 and it is ignored: '
                    . 'it will throw a ValueException in 4.0, use "ASC" or "DESC".',
                    \is_scalar($direction) === true ? $direction : get_debug_type($direction),
                    $key,
                    $context
                ), E_USER_DEPRECATED);
            }
            $orderColumn[$field['columnName'] ?? $key] = $direction;
        }

        return $orderColumn;
    }

    /**
     * Returns the field of a key of criteria, order or primary key: its property name,
     * or its column name (deprecated). A key being both a property and the column of another field is the property.
     *
     * @param string|int $key
     * @param string     $context where the key comes from, for the deprecation message
     * @phpstan-return Field|null
     */
    private function getFieldByKey($key, string $context): ?array
    {
        if (isset($this->fieldsByProperty[$key]) === true) {
            return $this->fieldsByProperty[$key];
        }

        if (isset($this->fields[$key]) === true) {
            @trigger_error(sprintf(
                'Using the column name "%s" in %s is deprecated since Ting 3.15, use the property name "%s" instead.',
                $key,
                $context,
                $this->fields[$key]['fieldName']
            ), E_USER_DEPRECATED);

            return $this->fields[$key];
        }

        return null;
    }

    /**
     * Returns the database value of a criterion:
     *  - null: unchanged (IS NULL)
     *  - array, for a field whose serializer implements Serializer\ArrayValueInterface: serialized as a whole (=)
     *  - empty array, for other fields: ValueException, nothing can match
     *  - array, for other fields: each element is converted as below (IN)
     *  - object: serialized by the serializer of the field, ValueException when it has none
     *    (unless the object is Stringable, it is then sent as is like before 3.15)
     *  - scalar: unchanged
     *
     * @phpstan-param Field $field
     * @param mixed  $value
     * @param string $context where the criterion comes from, for the messages
     * @return mixed
     * @throws ValueException
     */
    private function getCriterionValue(array $field, $value, string $context)
    {
        if ($value === null) {
            return null;
        }

        $serializer = null;
        if (isset($field['serializer']) === true) {
            $serializer = $this->serializerFactory->get($field['serializer']);
        }

        if (\is_array($value) === false) {
            return $this->getCriterionElementValue($field, $serializer, $value, $context);
        }

        if ($serializer instanceof Serializer\ArrayValueInterface) {
            return $serializer->serialize($value, $field['serializer_options']['serialize'] ?? []);
        }

        if ($value === []) {
            throw new ValueException(
                sprintf('Empty array for property "%s" in %s: nothing can match', $field['fieldName'], $context)
            );
        }

        return array_map(
            fn ($element) => $this->getCriterionElementValue($field, $serializer, $element, $context),
            $value
        );
    }

    /**
     * @phpstan-param Field $field
     * @param mixed $value
     * @return mixed
     * @throws ValueException
     */
    private function getCriterionElementValue(
        array $field,
        ?Serializer\SerializeInterface $serializer,
        $value,
        string $context
    ) {
        if (\is_object($value) === false) {
            return $value;
        }

        if ($serializer !== null) {
            return $serializer->serialize($value, $field['serializer_options']['serialize'] ?? []);
        }

        if ($value instanceof \Stringable) {
            // The drivers cast it to a string
            return $value;
        }

        throw new ValueException(sprintf(
            'Cannot use an object of class "%s" for property "%s" in %s: its field has no serializer',
            $value::class,
            $field['fieldName'],
            $context
        ));
    }

    /**
     * Retrieve all lines from the table
     *
     * @param Connection                 $connection
     * @param QueryFactoryInterface      $queryFactory
     * @param CollectionFactoryInterface $collectionFactory
     * @param bool                       $forceMaster
     * @return \CCMBenchmark\Ting\Query\QueryInterface
     *
     * @internal
     */
    public function getAll(
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        CollectionFactoryInterface $collectionFactory,
        $forceMaster = false
    ) {
        $fields = array_keys($this->fields);
        $queryGenerator = new Generator(
            $connection,
            $queryFactory,
            $this->schemaName,
            $this->table,
            $fields
        );

        return $queryGenerator->getAll($collectionFactory, $forceMaster);
    }

    /**
     * Retrieve matching lines from the table, according to the criteria
     *
     * @param array                      $criteria
     * @param Connection                 $connection
     * @param QueryFactoryInterface      $queryFactory
     * @param CollectionFactoryInterface $collectionFactory
     * @param bool                       $forceMaster
     * @return \CCMBenchmark\Ting\Query\Query
     *
     * @internal
     */
    public function getByCriteria(
        array $criteria,
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        CollectionFactoryInterface $collectionFactory,
        $forceMaster = false
    ) {
        $fields = array_keys($this->fields);
        $queryGenerator = new Generator(
            $connection,
            $queryFactory,
            $this->schemaName,
            $this->table,
            $fields
        );

        $criteriaColumn = $this->getCriteriaByColumn($criteria, 'the criteria of Repository::getBy()');

        return $queryGenerator->getByCriteria($criteriaColumn, $collectionFactory, $forceMaster);
    }

    public function getByCriteriaWithOrderAndLimit(
        array $criteria,
        array $orderBy,
        int $limit,
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        CollectionFactoryInterface $collectionFactory,
        bool $forceMaster = false
    ) {
        $fields = array_keys($this->fields);
        $queryGenerator = new Generator(
            $connection,
            $queryFactory,
            $this->schemaName,
            $this->table,
            $fields
        );
        $criteriaColumn = $this->getCriteriaByColumn($criteria, 'the criteria of Repository::getBy()');
        $orderColumn = $this->getOrderByColumn($orderBy);

        return $queryGenerator->getByCriteriaWithOrderAndLimit($criteriaColumn, $collectionFactory, $forceMaster, $orderColumn, $limit);
    }

    /**
     * @param $originalValue
     * @return array
     * @throws Exception
     */
    protected function getPrimariesKeyValuesAsArray($originalValue)
    {
        if (is_array($originalValue) === false) {
            $primariesKeyValue = [];
            if (count($this->primaries) == 1) {
                $columnName = array_key_first($this->primaries);
                $primariesKeyValue[$columnName] = $originalValue;
                return $primariesKeyValue;
            } else {
                throw new \CCMBenchmark\Ting\Exception('Incorrect format for primaries');
            }
        } else {
            return $originalValue;
        }
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
        $entity
    ) {
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
     * @param Connection            $connection
     * @param QueryFactoryInterface $queryFactory
     * @param                       $entity
     * @param                       $properties
     * @return PreparedQuery
     *
     * @internal
     */
    public function generateQueryForUpdate(
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        $entity,
        $properties
    ) {
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
     * @param                       $properties
     * @param                       $entity
     * @return PreparedQuery
     *
     * @internal
     */
    public function generateQueryForDelete(
        Connection $connection,
        QueryFactoryInterface $queryFactory,
        $properties,
        $entity
    ) {
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
     * @param $properties
     * @param $entity
     * @return array
     */
    protected function getPrimariesKeyValuesByProperties($properties, $entity)
    {
        $primariesKeyValue = [];
        foreach ($this->primaries as $key => $primary) {
            $fieldName = $this->fields[$key]['fieldName'];
            // Key value has been updated : we need the old one
            if (isset($properties[$fieldName]) === true) {
                $primariesKeyValue[$key] = $properties[$fieldName];
            } else {
                // No update, get the actual
                $primariesKeyValue[$key] = $this->propertyAccessor->getValue($entity, $primary['fieldName']);
            }
        }
        return $primariesKeyValue;
    }

    /**
     * Returns the getter name for a given field name
     *
     * @param $fieldName
     * @return string
     * 
     * @deprecated Use getEntityProperty, never try to get the value from the outside
     */
    public function getGetter($fieldName)
    {
        if (isset($this->fieldsByProperty[$fieldName]['getter']) === true) {
            return $this->fieldsByProperty[$fieldName]['getter'];
        }
        return 'get' . $fieldName;
    }

    /**
     * Returns the setter name for a given field name
     *
     * @param $fieldName
     * @return string
     * 
     * @deprecated Use setEntityProperty, never try to set the value from the outside
     */
    public function getSetter($fieldName)
    {
        if (isset($this->fieldsByProperty[$fieldName]['setter']) === true) {
            return $this->fieldsByProperty[$fieldName]['setter'];
        }
        return 'set' . $fieldName;
    }
}
