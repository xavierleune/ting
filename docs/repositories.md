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
                'fieldName'  => 'createdAt',
                'columnName' => 'cit_created_at',
                'type'       => 'datetime_immutable',
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
`batchLoadMetadataFromCache()`): the `default` key applies to every repository, a key named after the class declaring
`initMetadata()` (as built from the namespace and file name) applies to that class only and is merged over `default`.
This is the repository class in the usual case; when `initMetadata()` lives in another class that calls
`setRepository()`, use the name of that class, not the one given to `setRepository()`. This lets you vary the metadata
by environment, for instance the database name:

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
| `sequenceName`       |          | PostgreSQL (any driver implementing `Driver\SequenceAwareDriverInterface`): sequence used to read the generated value (`currval()`). Without it, or with another driver, the driver's inserted id is used (`lastval()` in PostgreSQL). |
| `serializer`         |          | Class of the serializer converting the value, see [Serializers](#serializers).                                 |
| `serializer_options` |          | Options of the serializer: `['serialize' => [...], 'unserialize' => [...]]`.                                   |
| `mutable`            |          | `true` if the PHP value can be modified in place (a `\DateTime`, a JSON object...): the field is then written by every save of a managed entity. Defaults to `true` for `Serializer\DateTime`, `Serializer\Json` decoding objects (without `assoc` nor the `JSON_OBJECT_AS_ARRAY` flag) and serializers of your own, `false` otherwise. See [mutable values](entities.md#mutable-values). |
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

An `autoincrement` key is always generated by the database: a value set on an entity not managed (a new entity, one
detached, read from a cache or a session) before it is saved is ignored by the `INSERT`, then replaced by the generated
id. To update the row of such an entity instead, [manage it](unit-of-work.md#managing-an-entity-yourself) first; the
clone of a managed entity updates the row of its original (see [Saving a clone](unit-of-work.md#saving-a-clone)). To
insert a row with a key of your choice, declare the key without `autoincrement`.

### Types

Values read from the database are cast according to `type`:

| Type                 | PHP value                                                                                    |
|----------------------|----------------------------------------------------------------------------------------------|
| `int`                | `int`                                                                                        |
| `double`             | `float`                                                                                      |
| `bool`               | `bool`, see the note below                                                                   |
| `string`             | `string`, no conversion                                                                      |
| `datetime`           | `\DateTimeImmutable` if the property is typed `\DateTimeImmutable` (serializer `DateTimeImmutable`), `\DateTime` otherwise, `\DateTimeInterface` included (serializer `DateTime`) |
| `datetime_immutable` | `\DateTimeImmutable`, serializer `DateTimeImmutable` by default                              |
| `datetimezone`       | `\DateTimeZone`, serializer `DateTimeZone` by default                                        |
| `json`               | decoded JSON, serializer `Json` by default                                                   |
| `uuid`               | `Symfony\Component\Uid\Uuid`, serializer `Uuid` by default (requires `symfony/uid`)          |
| `ip`                 | IPv4 address as a string, stored as an integer, serializer `Ip` by default (IPv4 only)       |
| `geometry`           | `Brick\Geo\Geometry`, serializer `Geometry` by default (MySQL / MariaDB, requires `brick/geo`) |

`NULL` is never cast: it stays `null`.

The way booleans are stored depends on the database, so declare the serializer of your driver on `bool` fields:
`CCMBenchmark\Ting\Driver\Mysqli\Serializer\Boolean` (`1` / `0`) or `CCMBenchmark\Ting\Driver\Pgsql\Serializer\Boolean`
(`t` / `f`). Without it, a `bool` field still reads PostgreSQL's `'f'` as `false`, and the Pgsql driver sends a PHP
boolean as `'1'` / `'0'` (PHP would send `false` as `''`, which PostgreSQL rejects).

## Serializers

A serializer converts a PHP value into a value the database understands (`serialize()`), and back (`unserialize()`).
Types listed above with a default serializer use it unless the field declares another one with the `serializer` key.

| Serializer (`CCMBenchmark\Ting\...`) | PHP value                     | Options                                                                                                      |
|--------------------------------------|-------------------------------|--------------------------------------------------------------------------------------------------------------|
| `Serializer\DateTime`                | `\DateTime`                   | `format` (default `Y-m-d H:i:s`, a MySQL `DATETIME` / PostgreSQL `timestamp`; `Y-m-d` for a `DATE` reads midnight); `unSerializeUseFormat` (default `true`: read with `format`, then, when it does not match, with the formats databases return — `Y-m-d`, `Y-m-d H:i:s` with optional fractional seconds and UTC offset, ATOM / RFC 3339 — anything else throws; `false`: with the PHP date parser `new \DateTime()` only, which also accepts `now`, `+1 day`, `05/06/2024`...) |
| `Serializer\DateTimeImmutable`       | `\DateTimeImmutable`          | Same                                                                                                         |
| `Serializer\DateTimeZone`            | `\DateTimeZone`               | None                                                                                                         |
| `Serializer\Json`                    | `array`, `\stdClass`...       | `options` and `depth` of `json_encode()` / `json_decode()`, `assoc` on unserialize (default `null`, as `json_decode()`: objects, or arrays with the `JSON_OBJECT_AS_ARRAY` flag); `JSON_THROW_ON_ERROR` makes no difference, errors throw a `Serializer\RuntimeException` |
| `Serializer\BackedEnum`              | backed enum                   | `enum` on unserialize (required): the enum class                                                             |
| `Serializer\Uuid`                    | `Symfony\Component\Uid\Uuid`  | None                                                                                                         |
| `Serializer\Ip`                      | IPv4 as a string              | None. IPv4 only: an IPv6 address, or a stored value which is not a 32 bits integer, throws a `Serializer\RuntimeException` (store IPv6 as a `string`) |
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

use CCMBenchmark\Ting\Serializer\ArrayValueInterface;
use CCMBenchmark\Ting\Serializer\RuntimeException;
use CCMBenchmark\Ting\Serializer\SerializerInterface;

final class CommaSeparatedList implements SerializerInterface, ArrayValueInterface
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
    'mutable'            => false,
]);
```

Two choices in this example depend on the PHP value of the serializer:

* Its PHP value is an array, so it implements the marker interface `Serializer\ArrayValueInterface`: an array given in
  the criteria of `getBy()` / `getOneBy()` / `get()` is serialized as a whole and compared with `=`
  (`getBy(['roles' => ['admin', 'editor']])` looks for `'admin|editor'`). Without the interface, it would become an
  `IN` list of its elements, each sent as is (see [criteria keys and values](#criteria-keys-and-values)). Likewise, a
  serializer whose PHP value is a scalar converted for the database (as `Ip`) implements
  `Serializer\ScalarValueInterface`.
* A PHP array cannot be modified in place, behind the entity's back: changing it means calling the setter, which
  notifies the change. `'mutable' => false` says so; without it, the field of a serializer of your own is
  [mutable](entities.md#mutable-values) by default, and written by every save of a managed entity. Keep the default
  (`true`) for a serializer whose PHP value is an object that can be modified in place.

## Reading

```php
public function get(mixed $primariesKeyValue, bool $forcePrimary = false): ?object;
public function getOneBy(array $criteria, bool $forcePrimary = false): ?object;
public function getBy(array $criteria, bool $forcePrimary = false, array $order = [], int $limit = 0): CollectionInterface;
public function getAll(bool $forcePrimary = false): CollectionInterface;
```

`get()` and `getOneBy()` return the entity, or `null` when no row matches. `getBy()` and `getAll()` return a
collection of entities. Reads go to a replica connection when the connection has replicas; pass `$forcePrimary = true`
to read from the primary, for instance right after a write.

These methods are declared with native types: a repository overriding one of them must declare compatible types,
return type included (`public function getAll(bool $forcePrimary = false): CollectionInterface`), otherwise PHP raises
a fatal error when loading the class.

```php
use App\Entity\CityStatus;

// By primary key
$city = $cityRepository->get(3);

// Composite primary key: an array indexed by property name
$language = $countryLanguageRepository->get(['countryCode' => 'FRA', 'language' => 'French']);

// First entity matching the criteria
$paris = $cityRepository->getOneBy(['name' => 'Paris']);

// Every entity matching the criteria, ordered and limited
$cities = $cityRepository->getBy(
    ['status' => CityStatus::Active, 'id' => [1, 2, 3]],
    order: ['name' => 'ASC'],
    limit: 10,
);
foreach ($cities as $city) {
    echo $city->getName();
}

// The whole table
$all = $cityRepository->getAll();
```

### Criteria: keys and values

The keys of the criteria of `getBy()` and `getOneBy()`, of the `$order` of `getBy()` and of a composite primary key
given to `get()` are **property names** (`fieldName`), never column names. An unknown key throws a
`CCMBenchmark\Ting\Exceptions\ValueException`; when the key is a column name, the message names the property to
use:

```text
"cit_name" is a column name: use the property name "name" in the order of Repository::getBy()
```

The criteria are combined with `AND`. Each value is converted for the database with the field, like `save()` does:

| Value                             | Field                                                             | Sent as                                       |
|-----------------------------------|-------------------------------------------------------------------|-----------------------------------------------|
| `null`                            | any                                                               | `IS NULL`                                     |
| array                             | serializer implementing `Serializer\ArrayValueInterface` (`Json`) | serialized as a whole, `=`                    |
| empty array                       | other fields                                                      | `ValueException`: nothing can match           |
| array                             | other fields                                                      | `IN (...)`, each element converted as below   |
| object                            | with a serializer                                                 | serialized, `=` (or element of the `IN` list) |
| `Stringable` object               | without serializer                                                | as is (the driver casts it to string)         |
| other object                      | without serializer                                                | `ValueException`                              |
| scalar (string, int, float, bool) | serializer implementing `Serializer\ScalarValueInterface` (`Ip`, `Boolean`) | serialized, `=` (or element of the `IN` list) |
| scalar (string, int, float, bool) | other fields                                                      | as is                                         |

So an enum, a `DateTime` or a `Uuid` can be passed as is for a field with the matching serializer (or type), and a
database value (`CityStatus::Active->value`, a formatted date) still works.

A serializer whose PHP value is a scalar implements the marker interface `CCMBenchmark\Ting\Serializer\ScalarValueInterface`:
`Ip` (`'10.0.0.1'`, stored as an integer) and the `Boolean` serializers of the drivers (`true` / `false`, stored as
`1` / `0` or `'t'` / `'f'`). For such a field, a scalar is the PHP value and goes through the serializer, exactly as
`save()` stores it: `getBy(['ip' => '10.0.0.1'])` compares the column with `167772161`, and
`getBy(['active' => false])` works with PostgreSQL (`false` sent as is would be an empty string). The database value
is no longer accepted: `Ip` throws a `Serializer\RuntimeException` for `167772161`, and a value the serializer
converts to `NULL` (`'t'` or `1` for a `Boolean` field) throws a `ValueException` instead of matching no row. The serialize options of the field
(`serializer_options.serialize`) are used. `get()` with a single value (one primary key) follows the same rules.

`null` or a nested array inside an `IN` list throws a `ValueException` as well: an `IN` list never matches `NULL`.

Empty criteria throw a `ValueException` too (`No criteria in Repository::getBy(): use Repository::getAll() to read
every row`), as does `get([])` (`No primary key value in Repository::get()`): use `getAll()` to read every row.

An empty array throws instead of sending a query that can match nothing. When the list may be empty, return early:

```php
/**
 * @param list<int> $ids
 * @return list<City>
 */
public function getByIds(array $ids): array
{
    if ($ids === []) {
        return [];
    }

    return iterator_to_array($this->getBy(['id' => $ids]), false);
}
```

**JSON fields.** With the `Json` serializer (type `json`), an array is encoded and compared with `=`, which compares
text, not JSON documents:

* a `TEXT` / `VARCHAR` column matches when the stored text has exactly the same encoding (key order, spaces, escaping,
  e.g. `JSON_UNESCAPED_SLASHES`): keep the same `serializer_options.serialize` for writing and reading;
* a MySQL `JSON` column compared with a string is always false: write the query with `CAST(:value AS JSON)` or
  `JSON_CONTAINS()`;
* a PostgreSQL `json` column has no `=` operator (the query fails), `jsonb` has one and compares the documents.

A custom serializer whose PHP value is an array implements the marker interface
`CCMBenchmark\Ting\Serializer\ArrayValueInterface` to be serialized as a whole as well.

The `$order` argument of `getBy()` is indexed by property name, with `ASC` or `DESC` (case-insensitive) as values; any
other direction throws a `ValueException`.

For anything else (joins, `OR`, aggregates...), write the query: see below.

## Writing

```php
public function save(NotifyPropertyInterface $entity): void;
public function delete(NotifyPropertyInterface $entity): void;
```

`save()` inserts a new entity or updates a managed one (an entity read from the database), `delete()` deletes it by its
primary key. Both are shortcuts for the [unit of work](unit-of-work.md): `pushSave()` / `pushDelete()` followed by
`process()`.

When the metadata of the repository map the class of the entity, `save()` and `delete()` write with them, even when
another repository maps the same class (another table such as an archive, or a lighter projection without some
columns): `$mainArtRepository->save($art)` writes into the table of `MainArtRepository`, `$userRepository->save($user)`
writes every column of `UserRepository`. `pushSave()` and `pushDelete()` called on the unit of work, and `save()`
through a repository of another class, use the metadata registered for the class of the entity: those of the last
repository built, or registered last.

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

The rows of these queries, and of the reading methods above, hydrate the tables the repository maps with its own
metadata when several of them share the database and the schema of the table: two repositories can map the same
table (a full entity and a lighter projection), see
[Several repositories on the same table](hydrators.md#several-repositories-on-the-same-table).

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

`startTransaction()`, `commit()` and `rollback()` act on the primary connection of the repository. `ConnectionPool`
opens one connection per driver class and connection parameters: repositories whose primaries use the same driver
(`namespace`) and the same `host`, `port`, `user` and `password` share that connection, hence the transaction,
whatever the name of their connection in the configuration (with PostgreSQL, the database must be the same too).
Parameters that differ open distinct connections, with distinct transactions, even when they reach the same server
(a host name and its IP address, two users).

```php
$cityRepository->startTransaction();
try {
    $cityRepository->save($city);
    $countryRepository->save($country);
} catch (\Throwable $e) {
    $cityRepository->rollback();
    throw $e;
}
$cityRepository->commit();
```

Starting a transaction while another one is open on the same connection, or committing / rolling back without one,
throws a `CCMBenchmark\Ting\Exceptions\TransactionException`. So does a `START TRANSACTION`, `COMMIT` or `ROLLBACK`
refused by the database (its error is in the message), including the `COMMIT` of a PostgreSQL transaction aborted by a
failed query, which the server answers with a `ROLLBACK`. A failed `commit()` or `rollback()` leaves no transaction
open: don't call `rollback()` after a failed `commit()` (it would throw "Cannot rollback no transaction"), which is why
`commit()` is outside the `try` above.

A transaction lives in the database session: when the connection is lost, the server rolls it back. When the driver
replaces or closes the connection while a transaction is open (`ping()` or `reconnect()` re-establishing a lost
connection, a failed reconnection, `close()`), the transaction is marked as lost: the next `commit()` throws a
`TransactionException` ("The transaction was lost with the connection: the server rolled it back") instead of
committing on the new connection, `rollback()` succeeds without sending anything to the new connection, and
`startTransaction()` starts a new transaction. Writes run on the new connection before that `commit()` were not part of
any transaction: each of them was committed on its own.

## Other methods

* `getMetadata(): Metadata` returns the metadata of the repository.
* `ping(): bool` and `pingPrimary(): bool` check that the replica / primary connection of the repository is alive.
* `reset(): void` resets the repository between two requests of a long-running process: see
  [Worker mode](unit-of-work.md#worker-mode).

Upgrading from 3.x: `Metadata::getGetter()` / `getSetter()` were removed in favour of the `getter` / `setter` field
options, and `getInsertId()` became `getInsertedId()`; see [UPGRADE-4.0.md](../UPGRADE-4.0.md).
