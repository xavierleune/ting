# Repositories

A repository links an [entity](entities.md) to a table. It:

* declares the **metadata**: which connection, database and table the entity is stored in, and how each column maps to
  a property;
* provides ready-made methods to read (`get()`, `getBy()`...) and write (`save()`, `delete()`) entities;
* is the place for your own queries, built with `getQuery()`, `getPreparedQuery()`, `getCachedQuery()`... (see
  [Queries](queries.md)).

## Defining a repository

A repository extends `CCMBenchmark\Ting\Repository\Repository` and implements
`CCMBenchmark\Ting\Repository\MetadataInitializer`, whose static `initMetadata()` method returns the
`CCMBenchmark\Ting\Repository\Metadata`:

```php
<?php

namespace App\Repository;

use App\Entity\City;
use App\Entity\CityStatus;
use CCMBenchmark\Ting\Driver\Mysqli\Serializer\Boolean;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\MetadataInitializer;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\Ting\Serializer\BackedEnum;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;

/**
 * @extends Repository<City>
 */
class CityRepository extends Repository implements MetadataInitializer
{
    public static function initMetadata(SerializerFactoryInterface $serializerFactory, array $options = []): Metadata
    {
        $metadata = new Metadata($serializerFactory);

        $metadata->setEntity(City::class);
        $metadata->setConnectionName('main');
        $metadata->setDatabase('world');
        $metadata->setTable('t_city_cit');

        $metadata
            ->addField([
                'primary'       => true,
                'autoincrement' => true,
                'fieldName'     => 'id',
                'columnName'    => 'cit_id',
                'type'          => 'int',
            ])
            ->addField([
                'fieldName'  => 'name',
                'columnName' => 'cit_name',
                'type'       => 'string',
            ])
            ->addField([
                'fieldName'  => 'capitalCity',
                'columnName' => 'cit_capital',
                'type'       => 'bool',
                'serializer' => Boolean::class,
                'getter'     => 'isCapitalCity',
                'setter'     => 'capitalCityIs',
            ])
            ->addField([
                'fieldName'          => 'status',
                'columnName'         => 'cit_status',
                'type'               => 'string',
                'serializer'         => BackedEnum::class,
                'serializer_options' => ['unserialize' => ['enum' => CityStatus::class]],
            ])
            ->addField([
                'fieldName'          => 'tags',
                'columnName'         => 'cit_tags',
                'type'               => 'json',
                'serializer_options' => ['unserialize' => ['assoc' => true]],
            ])
            ->addField([
                'fieldName'          => 'createdAt',
                'columnName'         => 'cit_created_at',
                'type'               => 'datetime_immutable',
                'serializer_options' => [
                    'serialize'   => ['format' => 'Y-m-d H:i:s'],
                    'unserialize' => ['format' => 'Y-m-d H:i:s'],
                ],
            ]);

        return $metadata;
    }
}
```

The `City` entity is shown in [Entities](entities.md#tracking-changes).

Metadata are loaded once, at boot, by `MetadataRepository::batchLoadMetadata()`, and repositories are instantiated by
`RepositoryFactory::get()`: see [Getting started](getting-started.md#wiring-ting).

```php
$cityRepository = $repositoryFactory->get(CityRepository::class);
```

### Table

| Method                                | Description                                                                                         |
|---------------------------------------|-----------------------------------------------------------------------------------------------------|
| `setEntity(string $className)`        | Class of the entity, without leading `\` (use `City::class`).                                       |
| `setConnectionName(string $name)`     | Name of the connection, as declared in `ConnectionPool::setConfig()`.                               |
| `setDatabase(string $databaseName)`   | Name of the database.                                                                               |
| `setTable(string $tableName)`         | Name of the table.                                                                                  |
| `setSchema(string $schemaName)`       | PostgreSQL schema, optional.                                                                        |
| `setRepository(string $className)`    | Repository the metadata belong to, when `initMetadata()` is declared in another class. Optional.    |

### Options

The second argument of `initMetadata()` receives options passed to `batchLoadMetadata()` (or
`batchLoadMetadataFromCache()`): the `default` key applies to every repository, a key named after the repository class
(as built from the namespace and file name) applies to that repository only and is merged over `default`. This lets you
vary the metadata by environment, for instance the database name:

```php
$metadataRepository->batchLoadMetadata(
    'App\Repository',
    __DIR__ . '/src/Repository/*Repository.php',
    [
        'default'                         => ['database' => 'world'],
        'App\Repository\CountryRepository' => ['database' => 'world_ref'],
    ]
);

// in initMetadata():
$metadata->setDatabase($options['database'] ?? 'world');
```

## Field options

Each column is declared with `Metadata::addField(array $params)`, which returns the metadata so calls can be chained.

| Key                  | Required | Description                                                                                                    |
|----------------------|----------|----------------------------------------------------------------------------------------------------------------|
| `fieldName`          | yes      | Name of the entity property.                                                                                   |
| `columnName`         | yes      | Name of the column.                                                                                            |
| `type`               | yes      | Type of the value, see [Types](#types).                                                                        |
| `primary`            |          | `true` if the column is (part of) the primary key. Declare every column of a composite key.                   |
| `autoincrement`      |          | `true` if the primary key is generated by the database: it is left out of the `INSERT` and set on the entity afterwards. |
| `sequenceName`       |          | PostgreSQL only: sequence used to read the generated value (`currval()`). Without it, `lastval()` is used.     |
| `serializer`         |          | Class of the serializer converting the value, see [Serializers](#serializers).                                 |
| `serializer_options` |          | Options of the serializer: `['serialize' => [...], 'unserialize' => [...]]`.                                   |
| `getter`             |          | Method used to read the property, instead of the `getX()` / `isX()` / `hasX()` convention.                      |
| `setter`             |          | Method used to write the property, instead of the `setX()` convention.                                         |

A PostgreSQL `serial` primary key, with its sequence:

```php
$metadata->addField([
    'primary'       => true,
    'autoincrement' => true,
    'sequenceName'  => 'city_id_seq',
    'fieldName'     => 'id',
    'columnName'    => 'id',
    'type'          => 'int',
]);
```

### Types

Values read from the database are cast according to `type`:

| Type                 | PHP value                                                                                    |
|----------------------|----------------------------------------------------------------------------------------------|
| `int`                | `int`                                                                                        |
| `double`             | `float`                                                                                      |
| `bool`               | `bool`, see the note below                                                                   |
| `string`             | `string`, no conversion                                                                      |
| `datetime`           | `\DateTime`, serializer `DateTime` by default                                                |
| `datetime_immutable` | `\DateTimeImmutable`, serializer `DateTimeImmutable` by default                              |
| `datetimezone`       | `\DateTimeZone`, serializer `DateTimeZone` by default                                        |
| `json`               | decoded JSON, serializer `Json` by default                                                   |
| `uuid`               | `Symfony\Component\Uid\Uuid`, serializer `Uuid` by default (requires `symfony/uid`)          |
| `ip`                 | IPv4 address as a string, stored as an integer, serializer `Ip` by default                   |
| `geometry`           | `Brick\Geo\Geometry`, serializer `Geometry` by default (MySQL / MariaDB, requires `brick/geo`) |

`NULL` is never cast: it stays `null`.

The way booleans are stored depends on the database, so declare the serializer of your driver on `bool` fields:
`CCMBenchmark\Ting\Driver\Mysqli\Serializer\Boolean` (`1` / `0`) or `CCMBenchmark\Ting\Driver\Pgsql\Serializer\Boolean`
(`t` / `f`). Without it, PostgreSQL's `'f'` would be cast to `true`.

## Serializers

A serializer converts a PHP value into a value the database understands (`serialize()`), and back (`unserialize()`).
Types listed above with a default serializer use it unless the field declares another one with the `serializer` key.

| Serializer (`CCMBenchmark\Ting\...`) | PHP value                     | Options                                                                                                      |
|--------------------------------------|-------------------------------|--------------------------------------------------------------------------------------------------------------|
| `Serializer\DateTime`                | `\DateTime`                   | `format` (default `Y-m-d H:i:s`); `unSerializeUseFormat` (default `true`, `false` accepts any format `new \DateTime()` understands) |
| `Serializer\DateTimeImmutable`       | `\DateTimeImmutable`          | Same, but `format` defaults to `\DateTimeInterface::ATOM`: set `Y-m-d H:i:s` for a MySQL `DATETIME`         |
| `Serializer\DateTimeZone`            | `\DateTimeZone`               | None                                                                                                         |
| `Serializer\Json`                    | `array`, `\stdClass`...       | `options` and `depth` of `json_encode()` / `json_decode()`, `assoc` on unserialize (default `false`: objects) |
| `Serializer\BackedEnum`              | backed enum                   | `enum` on unserialize (required): the enum class                                                             |
| `Serializer\Uuid`                    | `Symfony\Component\Uid\Uuid`  | None                                                                                                         |
| `Serializer\Ip`                      | IPv4 as a string              | None                                                                                                         |
| `Serializer\Geometry`                | `Brick\Geo\Geometry`          | None                                                                                                         |
| `Driver\Mysqli\Serializer\Boolean`   | `bool`                        | None                                                                                                         |
| `Driver\Pgsql\Serializer\Boolean`    | `bool`                        | None                                                                                                         |

Options are passed with `serializer_options`, separately for each direction:

```php
$metadata->addField([
    'fieldName'          => 'tags',
    'columnName'         => 'cit_tags',
    'type'               => 'json',
    'serializer_options' => [
        'serialize'   => ['options' => JSON_UNESCAPED_UNICODE],
        'unserialize' => ['assoc' => true],
    ],
]);
```

### Writing a serializer

A serializer implements `CCMBenchmark\Ting\Serializer\SerializerInterface`, which extends `SerializeInterface`
(PHP to database) and `UnserializeInterface` (database to PHP). It is instantiated by the `SerializerFactory`, without
constructor arguments, and shared by every field using it: keep it stateless. Throw
`CCMBenchmark\Ting\Serializer\RuntimeException` on invalid values.

```php
<?php

namespace App\Serializer;

use CCMBenchmark\Ting\Serializer\RuntimeException;
use CCMBenchmark\Ting\Serializer\SerializerInterface;

final class CommaSeparatedList implements SerializerInterface
{
    public function serialize($toSerialize, array $options = []): ?string
    {
        if ($toSerialize === null) {
            return null;
        }
        if (!is_array($toSerialize)) {
            throw new RuntimeException('CommaSeparatedList expects an array');
        }

        return implode($options['separator'] ?? ',', $toSerialize);
    }

    public function unserialize($serialized, array $options = []): ?array
    {
        if ($serialized === null) {
            return null;
        }

        return $serialized === '' ? [] : explode($options['separator'] ?? ',', $serialized);
    }
}
```

```php
$metadata->addField([
    'fieldName'          => 'roles',
    'columnName'         => 'usr_roles',
    'type'               => 'string',
    'serializer'         => CommaSeparatedList::class,
    'serializer_options' => [
        'serialize'   => ['separator' => '|'],
        'unserialize' => ['separator' => '|'],
    ],
]);
```

## Reading

```php
public function get(mixed $primariesKeyValue, bool $forcePrimary = false);
public function getOneBy(array $criteria, bool $forcePrimary = false);
public function getBy(array $criteria, bool $forcePrimary = false, array $order = [], int $limit = 0): CollectionInterface;
public function getAll($forcePrimary = false): CollectionInterface;
```

`get()` and `getOneBy()` return the entity, or `null` when no row matches. `getBy()` and `getAll()` return a
collection of entities. Reads go to a replica connection when the connection has replicas; pass `$forcePrimary = true`
to read from the primary, for instance right after a write.

```php
use App\Entity\CityStatus;

// By primary key
$city = $cityRepository->get(3);

// Composite primary key: an array indexed by column name
$language = $countryLanguageRepository->get(['CountryCode' => 'FRA', 'Language' => 'French']);

// First entity matching the criteria
$paris = $cityRepository->getOneBy(['name' => 'Paris']);

// Every entity matching the criteria, ordered and limited
$cities = $cityRepository->getBy(
    ['status' => CityStatus::Active->value, 'id' => [1, 2, 3]],
    order: ['cit_name' => 'ASC'],
    limit: 10,
);
foreach ($cities as $city) {
    echo $city->getName();
}

// The whole table
$all = $cityRepository->getAll();
```

The criteria of `getBy()` and `getOneBy()`:

* are indexed by **property name** (`fieldName`); an unknown property throws a
  `CCMBenchmark\Ting\Exceptions\ValueException`;
* are combined with `AND`: an array value becomes `IN (...)`, `null` becomes `IS NULL`, any other value `=`;
* are sent as is, **without serialization**: pass the database value (`CityStatus::Active->value`, a formatted date...).

The `$order` argument of `getBy()` is indexed by **column name** (`cit_name`), with `ASC` or `DESC` as values.

For anything else (joins, `OR`, aggregates...), write the query: see below.

## Writing

```php
public function save(NotifyPropertyInterface $entity): void;
public function delete(NotifyPropertyInterface $entity): void;
```

`save()` inserts a new entity or updates a managed one (an entity read from the database), `delete()` deletes it by its
primary key. Both are shortcuts for the [unit of work](unit-of-work.md): `pushSave()` / `pushDelete()` followed by
`process()`.

```php
use App\Entity\City;

$city = new City();
$city->setName('Lyon');
$cityRepository->save($city); // INSERT, then $city->getId() returns the generated id

$city->setName('Lyon 2e');
$cityRepository->save($city); // UPDATE t_city_cit SET cit_name = ... WHERE cit_id = ...

$cityRepository->delete($city); // DELETE FROM t_city_cit WHERE cit_id = ...
```

## Writing your own queries

Methods of the repository build queries bound to its connection:

| Method                                | Returns                                                     |
|---------------------------------------|-------------------------------------------------------------|
| `getQuery(string $sql)`               | `CCMBenchmark\Ting\Query\Query`                             |
| `getPreparedQuery(string $sql)`       | `CCMBenchmark\Ting\Query\PreparedQuery`                     |
| `getCachedQuery(string $sql)`         | `CCMBenchmark\Ting\Query\Cached\Query`, see [Cache](cache.md) |
| `getCachedPreparedQuery(string $sql)` | `CCMBenchmark\Ting\Query\Cached\PreparedQuery`              |
| `getCollection(?HydratorInterface $hydrator = null)` | an empty `Collection` using that hydrator, to pass to `query()` |

Add them as methods of your repository:

```php
use CCMBenchmark\Ting\Repository\CollectionInterface;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;

/**
 * @return CollectionInterface<City>
 */
public function getCapitals(int $limit): CollectionInterface
{
    $query = $this->getQuery(
        'SELECT cit_id, cit_name, cit_capital, cit_status, cit_tags, cit_created_at
        FROM t_city_cit WHERE cit_capital = :capital ORDER BY cit_name LIMIT :limit'
    );
    $query->setParams(['capital' => 1, 'limit' => $limit]);

    return $query->query($this->getCollection(new HydratorSingleObject()));
}

public function archiveOlderThan(\DateTimeImmutable $date): int
{
    $query = $this->getPreparedQuery(
        'UPDATE t_city_cit SET cit_status = :status WHERE cit_created_at < :date'
    );
    $query->setParams(['status' => CityStatus::Archived->value, 'date' => $date->format('Y-m-d H:i:s')]);
    $query->execute();

    return (int) $query->getAffectedRows();
}
```

[Queries](queries.md) details parameters, reading and writing, and [Hydrators](hydrators.md) how rows become objects.

### Query builder

`getQueryBuilder(string $type)` returns an [aura/sqlquery](https://github.com/auraphp/Aura.SqlQuery) builder for the
dialect of the connection. `$type` is `Repository::QUERY_SELECT`, `QUERY_INSERT`, `QUERY_UPDATE` or `QUERY_DELETE`:

```php
$select = $this->getQueryBuilder(self::QUERY_SELECT);
$select
    ->cols(['cit_id', 'cit_name', 'cit_capital', 'cit_status', 'cit_tags', 'cit_created_at'])
    ->from('t_city_cit')
    ->where('cit_status = :status')
    ->orderBy(['cit_name ASC'])
    ->limit(10)
    ->bindValue('status', CityStatus::Active->value);

$query = $this->getQuery($select->getStatement());
$query->setParams($select->getBindValues());

return $query->query($this->getCollection(new HydratorSingleObject()));
```

See [Queries](queries.md#query-builder).

## Transactions

`startTransaction()`, `commit()` and `rollback()` act on the primary connection of the repository. Repositories whose
connection resolves to the same server share that connection, hence the transaction (with PostgreSQL, the database
must be the same too).

```php
$cityRepository->startTransaction();
try {
    $cityRepository->save($city);
    $countryRepository->save($country);
    $cityRepository->commit();
} catch (\Throwable $e) {
    $cityRepository->rollback();
    throw $e;
}
```

Starting a transaction while another one is open on the same connection, or committing / rolling back without one,
throws a `CCMBenchmark\Ting\Exceptions\TransactionException`.

## Other methods

* `getMetadata(): Metadata` returns the metadata of the repository.
* `ping(): bool` and `pingPrimary(): bool` check that the replica / primary connection of the repository is alive.
* `reset(): void` resets the repository between two requests of a long-running process: see
  [Worker mode](unit-of-work.md#worker-mode).

Upgrading from 3.x: `Metadata::getGetter()` / `getSetter()` were removed in favour of the `getter` / `setter` field
options, and `getInsertId()` became `getInsertedId()`; see [UPGRADE-4.0.md](../UPGRADE-4.0.md).
