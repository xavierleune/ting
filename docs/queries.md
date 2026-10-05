# Queries

Ting does not invent a query language: you write SQL, Ting runs it on the right connection and hydrates the result.
Queries are created from a [repository](repositories.md), which knows the connection and the database of its entity.

For simple lookups (`get()`, `getBy()`, `getOneBy()`, `getAll()`), the repository builds the SQL for you: see
[Repositories](repositories.md). This page covers the queries you write yourself.

The examples below use this repository (see [Getting started](getting-started.md) to wire Ting and get a repository
instance):

```php
namespace App\Repository;

use App\Entity\User;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\MetadataInitializer;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;

/** @extends Repository<User> */
class UserRepository extends Repository implements MetadataInitializer
{
    public static function initMetadata(SerializerFactoryInterface $serializerFactory, array $options = []): Metadata
    {
        $metadata = new Metadata($serializerFactory);
        $metadata->setEntity(User::class);
        $metadata->setConnectionName('main');
        $metadata->setDatabase('app');
        $metadata->setTable('user');

        $metadata->addField([
            'primary'       => true,
            'autoincrement' => true,
            'fieldName'     => 'id',
            'columnName'    => 'id',
            'type'          => 'int',
        ]);
        $metadata->addField([
            'fieldName'  => 'name',
            'columnName' => 'name',
            'type'       => 'string',
        ]);

        return $metadata;
    }

    // Methods shown in this page go here
}
```

## Query types

A repository gives you four kinds of query objects:

| Method                                | Returns                                   | Use for                                       |
|---------------------------------------|-------------------------------------------|-----------------------------------------------|
| `getQuery(string $sql)`               | `CCMBenchmark\Ting\Query\Query`           | a query run once                              |
| `getPreparedQuery(string $sql)`       | `CCMBenchmark\Ting\Query\PreparedQuery`   | a query run several times with other params   |
| `getCachedQuery(string $sql)`         | `CCMBenchmark\Ting\Query\Cached\Query`    | a read query whose result is cached           |
| `getCachedPreparedQuery(string $sql)` | `CCMBenchmark\Ting\Query\Cached\PreparedQuery` | the same, as a prepared statement        |

Cached queries are described in [Cache](cache.md).

## Reading

### Parameters

Put named placeholders (`:name`) in the SQL and give their values with `setParams()`, an associative array without
the leading colon. Values are escaped (MySQL) or sent as query parameters (PostgreSQL) by the driver: never
concatenate user input in the SQL.

```php
public function findByName(string $name): CollectionInterface
{
    $query = $this->getQuery('SELECT id, name FROM user WHERE name = :name');
    $query->setParams(['name' => $name]);

    return $query->query();
}
```

(`use CCMBenchmark\Ting\Repository\CollectionInterface;` in the repository.)

`setParams()` replaces the previous parameters and returns the query, so calls can be chained. Give a value for every
placeholder: with the MySQL driver, a missing one raises a `CCMBenchmark\Ting\Driver\QueryException`. PostgreSQL
casts (`::text`) and times (`12:30`) are not taken for placeholders.

### Executing and reading the results

`query()` runs a reading query (`SELECT`, `SHOW`...) and returns a `CCMBenchmark\Ting\Repository\CollectionInterface`.
A collection is iterable and countable:

```php
public function printUsersNamed(string $name): void
{
    $collection = $this->findByName($name);

    echo count($collection) . " user(s)\n";
    foreach ($collection as $row) {
        echo $row['user']->getId() . ' ' . $row['user']->getName() . "\n";
    }
}
```

`$collection->first()` returns the first row, or `null` when the collection is empty.

With the default hydrator, each row is an array indexed by table name (or alias), holding the hydrated entities, so
that a query with joins returns all the objects involved. The shape of the rows depends on the hydrator: see
[Hydrators](hydrators.md).

To get the entities directly when the query reads a single table, pass a collection using `HydratorSingleObject` to
`query()`:

```php
use CCMBenchmark\Ting\Repository\HydratorSingleObject;

public function findEntitiesByName(string $name): CollectionInterface
{
    $query = $this->getQuery('SELECT id, name FROM user WHERE name = :name');
    $query->setParams(['name' => $name]);

    // Each row is now a User
    return $query->query($this->getCollection(new HydratorSingleObject()));
}
```

### Master and slaves

When slaves are configured for a connection (see [Getting started](getting-started.md)), reading queries run on a
slave. To read on the master, for instance just after a write, call `selectMaster(true)` before `query()`:

```php
public function findByNameOnMaster(string $name): CollectionInterface
{
    $query = $this->getQuery('SELECT id, name FROM user WHERE name = :name');
    $query->setParams(['name' => $name]);
    $query->selectMaster(true);

    return $query->query();
}
```

Writing queries (`execute()`) always run on the master.

## Writing

Writing queries (`INSERT`, `UPDATE`, `DELETE`...) are run with `execute()` instead of `query()`. They always run on
the master connection.

```php
public function insertUser(string $name): int
{
    $query = $this->getQuery('INSERT INTO user (name) VALUES (:name)');
    $query->setParams(['name' => $name]);
    $query->execute();

    return $query->getInsertedId();
}

public function disableInactiveUsers(): int|string
{
    $query = $this->getQuery('UPDATE user SET enabled = 0 WHERE last_login < :date');
    $query->setParams(['date' => '2025-01-01']);
    $query->execute();

    return $query->getAffectedRows();
}
```

* `getInsertedId(): int` returns the last auto-generated id of the master connection (`mysqli::$insert_id` on MySQL,
  `lastval()` on PostgreSQL). For a specific PostgreSQL sequence, the driver offers
  `CCMBenchmark\Ting\Driver\Pgsql\Driver::getInsertedIdForSequence(string $sequenceName)`.
* `getAffectedRows(): int|string` returns the number of rows changed by the last writing query.
* The value returned by `execute()` depends on the driver: rely on the two methods above rather than on it.

To save entities, you usually don't write these queries: `Repository::save()` and `Repository::delete()` go through
the [unit of work](unit-of-work.md).

## Prepared queries

A `PreparedQuery` is prepared on its first execution, then the statement is reused. Use it to run the same query many
times with different parameters:

```php
public function renameAll(array $renames): void
{
    $query = $this->getPreparedQuery('UPDATE user SET name = :name WHERE id = :id');
    foreach ($renames as $id => $name) {
        $query->setParams(['id' => $id, 'name' => $name])->execute();
    }
}
```

`query()`, `execute()`, `setParams()` and `selectMaster()` behave as for `Query`. A reading prepared query is prepared
on a slave, unless `selectMaster(true)` was called before its first execution. Once prepared, a `PreparedQuery` must
be used either for reading or for writing, not both.

## Query builder

`Repository::getQueryBuilder(string $type)` returns an [aura/sqlquery](https://github.com/auraphp/Aura.SqlQuery)
builder for the dialect of the repository's connection (MySQL or PostgreSQL). `$type` is one of the
`Repository::QUERY_SELECT`, `QUERY_INSERT`, `QUERY_UPDATE` or `QUERY_DELETE` constants. Turn the builder into SQL with
`getStatement()` and run it with a Ting query: Aura's named placeholders are the same as Ting's.

```php
public function findWithBuilder(string $name): CollectionInterface
{
    $select = $this->getQueryBuilder(self::QUERY_SELECT);
    $select->cols(['id', 'name'])
        ->from('user')
        ->where('name = :name')
        ->orderBy(['id DESC'])
        ->limit(10);

    $query = $this->getQuery($select->getStatement());
    $query->setParams(['name' => $name]);

    return $query->query($this->getCollection(new HydratorSingleObject()));
}
```

The builder is only available with the MySQL, PostgreSQL and SphinxQL drivers, otherwise a
`CCMBenchmark\Ting\Exceptions\DriverException` is thrown.

## Errors

* A query that fails in the database (syntax error, constraint violation...) throws a
  `CCMBenchmark\Ting\Driver\QueryException`, whose message contains the database error and the SQL.
* A misconfigured cached query (no TTL or no cache key) throws a `CCMBenchmark\Ting\Query\QueryException`.

Both extend `CCMBenchmark\Ting\Exception`.
