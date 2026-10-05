# Hydrators

A hydrator turns the rows returned by the database into PHP values: entities described by
[metadata](entities.md), arrays, or plain objects. Every reading query returns a collection
(`CCMBenchmark\Ting\Repository\CollectionInterface`), and the collection hands the result to its hydrator while you
iterate over it.

| Hydrator (`CCMBenchmark\Ting\Repository\...`) | Each row is                                                            |
|-----------------------------------------------|------------------------------------------------------------------------|
| `Hydrator` (default)                          | an array of entities indexed by table alias, plus unmapped columns     |
| `HydratorSingleObject`                        | the first entity of the row                                            |
| `HydratorArray`                               | an associative array `column => value`, no entity                      |
| `HydratorValueObject`                         | an instance of a class of your choice, without metadata                |
| `HydratorAggregator`                          | one row per group of rows, with the grouped data collected             |
| `HydratorRelational`                          | entities nested into each other, on as many levels as needed           |

The examples on this page use three entities, `User`, `Book` and `Author`, each mapped by its repository (see
[Entities](entities.md)) on the tables `user`, `book` and `author`, with an `id` primary key. The methods shown are
repository methods, as in [Queries](queries.md).

## Choosing a hydrator

`query()` uses the default hydrator. To use another one, pass a collection built by the repository:

```php
use CCMBenchmark\Ting\Repository\CollectionInterface;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;

public function findByName(string $name): CollectionInterface
{
    $query = $this->getQuery('SELECT id, name FROM user WHERE name = :name');
    $query->setParams(['name' => $name]);

    return $query->query($this->getCollection(new HydratorSingleObject()));
}
```

Hydrators are created with `new`. They need the `MetadataRepository` (to find the metadata of each table) and the
`UnitOfWork` (to manage the hydrated entities): `Repository::getCollection()` injects both, through the
`CollectionFactory`. If you use a hydrator outside a repository, call `setMetadataRepository()` and `setUnitOfWork()`
yourself. See [Getting started](getting-started.md) for the wiring.

Entities implementing `NotifyPropertyInterface` that come out of `Hydrator`, `HydratorSingleObject`,
`HydratorAggregator` or `HydratorRelational` are managed by the [unit of work](unit-of-work.md): their changes are
tracked and saved by `Repository::save()`. `HydratorArray` and `HydratorValueObject` don't create entities.

## The default hydrator

`CCMBenchmark\Ting\Repository\Hydrator` returns, for each row, an array whose keys are the table names (or their
aliases) and whose values are the entities built from the metadata. A query with joins thus returns all the objects
involved:

```php
public function findWithBooks(string $name): CollectionInterface
{
    $query = $this->getQuery(
        'SELECT u.id, u.name, b.id, b.title
         FROM user u
         LEFT JOIN book b ON (b.user_id = u.id)
         WHERE u.name = :name'
    );
    $query->setParams(['name' => $name]);

    return $query->query();
}
```

Each row looks like this:

```text
[
    'u' => User {id: 1, name: 'Sylvain'},
    'b' => Book {id: 1, title: 'Tintin au Tibet'},
]
```

When a joined table returns no data at all (a `LEFT JOIN` without match), its key holds `null`:

```text
[
    'u' => User {id: 2, name: 'Xavier'},
    'b' => null,
]
```

### Columns without metadata

A column that belongs to no mapped table (an aggregate like `COUNT(*)`, an expression, or a column missing from the
metadata) is put in a `stdClass` under the key `0`, as a property named after the column (or its alias):

```php
public function countBooksByUser(): CollectionInterface
{
    return $this->getQuery(
        'SELECT u.id, u.name, COUNT(b.id) AS nb_books
         FROM user u
         LEFT JOIN book b ON (b.user_id = u.id)
         GROUP BY u.id, u.name'
    )->query();
}
```

```text
[
    'u' => User {id: 1, name: 'Sylvain'},
    0   => stdClass {nb_books: 3},
]
```

These values are not converted by Ting: they are passed as the driver returns them (PostgreSQL returns strings, for
instance). Use [`unserializeAliasWith()`](#unserializing-a-column-without-metadata) to convert them. When there is no
such column, the key `0` is absent.

## HydratorSingleObject

The default hydrator is designed for queries returning several objects per row. When a query reads a single entity,
`HydratorSingleObject` returns this entity directly instead of an array:

```php
use CCMBenchmark\Ting\Repository\CollectionInterface;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;

public function findByName(string $name): CollectionInterface
{
    $query = $this->getQuery('SELECT id, name FROM user WHERE name = :name');
    $query->setParams(['name' => $name]);

    // A collection of User
    return $query->query($this->getCollection(new HydratorSingleObject()));
}
```

The repository's own methods (`get()`, `getBy()`, `getAll()`...) use it. It returns the **first** value of the row,
in the order of the selected columns: if the query starts with an unmapped column (`SELECT COUNT(*) AS n, id, ...`),
you get the `stdClass` instead of the entity. Select the entity columns first, or map the extra columns into the entity
with [`mapAliasTo()`](#mapping-a-column-into-an-entity), which works with every hydrator extending `Hydrator`.

## HydratorArray

`HydratorArray` skips the metadata entirely and returns each row as an associative array indexed by column name (or
alias). Values are left as returned by the driver.

```php
use CCMBenchmark\Ting\Repository\HydratorArray;

public function listNames(): CollectionInterface
{
    return $this->getQuery('SELECT id, name FROM user')
        ->query($this->getCollection(new HydratorArray()));
}
```

```text
['id' => 1, 'name' => 'Sylvain']
```

Columns with the same name overwrite each other (`u.id` and `b.id` both become `id`): give them distinct aliases.

## HydratorValueObject

`HydratorValueObject` hydrates instances of any class, without metadata: a DTO, a read model, a projection. It takes
the class name and builds one object per row, the same way whether the result comes from the database or from the
[cache](cache.md).

```php
namespace App\ReadModel;

final class UserSummary
{
    public int $id;
    public string $name;
    public int $nbBooks;
}
```

```php
use App\ReadModel\UserSummary;
use CCMBenchmark\Ting\Repository\CollectionInterface;
use CCMBenchmark\Ting\Repository\HydratorValueObject;

/** @return CollectionInterface<UserSummary> */
public function getSummaries(): CollectionInterface
{
    $query = $this->getQuery(
        'SELECT u.id, u.name, COUNT(b.id) AS nbBooks
         FROM user u
         LEFT JOIN book b ON (b.user_id = u.id)
         GROUP BY u.id, u.name'
    );

    return $query->query($this->getCollection(new HydratorValueObject(UserSummary::class)));
}
```

```php
foreach ($this->getSummaries() as $summary) {
    echo $summary->name . ': ' . $summary->nbBooks . "\n";
}
```

Rules, the same as the native `fetch_object()` functions:

* each column is written to the property named after the column or its alias, whatever its visibility, so alias the
  columns to match the property names; a column without matching property creates a dynamic property (deprecated
  since PHP 8.2);
* properties are set **before** the constructor is called, and the constructor receives no argument: give the class
  no constructor, or one without required parameters;
* Ting serializers are not applied: values are typed by the driver as for entities (with MySQL, integers and floats,
  `DECIMAL` as float; with PostgreSQL, strings).

The objects are not entities: the unit of work doesn't manage them.

## Customising the default hydrator

`Hydrator` (and therefore `HydratorSingleObject`, `HydratorAggregator` and `HydratorRelational`) can be configured
before the query runs. All these methods return the hydrator, so calls can be chained.

### Mapping a column into an entity

`mapAliasTo(string $from, string $to, string $column)` passes the unmapped column `$from` to the method `$column` of
the entity of alias `$to`, instead of leaving it in the `stdClass`:

```php
use CCMBenchmark\Ting\Repository\Hydrator;

public function findWithNbBooks(): CollectionInterface
{
    $query = $this->getQuery(
        'SELECT u.id, u.name, COUNT(b.id) AS nb_books
         FROM user u
         LEFT JOIN book b ON (b.user_id = u.id)
         GROUP BY u.id, u.name'
    );

    $hydrator = new Hydrator();
    $hydrator->mapAliasTo('nb_books', 'u', 'setNbBooks');

    return $query->query($this->getCollection($hydrator));
}
```

`User::setNbBooks()` is a plain method of the entity, unrelated to the metadata. The row becomes:

```text
[
    'u' => User {id: 1, name: 'Sylvain', nbBooks: 3},
]
```

### Unserializing a column without metadata

Mapped columns are converted by the serializer of their field. For unmapped columns, `unserializeAliasWith(string
$alias, UnserializeInterface $unserialize, array $options = [])` sets the serializer to use; `$options` are passed to
its `unserialize()` method. Any `CCMBenchmark\Ting\Serializer\*` class works, or your own implementation of
`CCMBenchmark\Ting\Serializer\UnserializeInterface`.

```php
use CCMBenchmark\Ting\Repository\Hydrator;
use CCMBenchmark\Ting\Serializer\DateTime;

public function findWithFetchDate(): CollectionInterface
{
    $query = $this->getQuery('SELECT id, name, NOW() AS fetched_at FROM user');

    $hydrator = new Hydrator();
    $hydrator->unserializeAliasWith('fetched_at', new DateTime());

    return $query->query($this->getCollection($hydrator));
}
```

```text
[
    'user' => User {id: 1, name: 'Sylvain'},
    0      => stdClass {fetched_at: DateTime('2026-01-13 10:41:36')},
]
```

Unserialization happens before `mapAliasTo()`, so both can be combined to inject a converted value into an entity.

### Injecting an entity into another one

`mapObjectTo(string $from, string $to, string $column)` passes the entity of alias `$from` to the method `$column` of
the entity of alias `$to`, and removes `$from` from the row:

```php
use CCMBenchmark\Ting\Repository\Hydrator;

public function findBooksWithAuthor(): CollectionInterface
{
    $query = $this->getQuery(
        'SELECT b.id, b.title, a.id, a.name
         FROM book b
         INNER JOIN author a ON (a.id = b.author_id)'
    );

    $hydrator = new Hydrator();
    $hydrator->mapObjectTo('a', 'b', 'setAuthor');

    return $query->query($this->getCollection($hydrator));
}
```

```text
[
    'b' => Book {id: 1, title: 'Dune', author: Author {id: 2, name: 'Frank Herbert'}},
]
```

Several `mapObjectTo()` calls can chain objects on several levels. When the joined entity is `null` (`LEFT JOIN`
without match), the method is not called.

### Tables from another database

The hydrator looks for the metadata of each table in the database of the connection that ran the query. When a query
reads a table of another database, tell the hydrator where this alias comes from with `objectDatabaseIs(string
$object, string $database)`:

```php
$hydrator = new Hydrator();
$hydrator->objectDatabaseIs('a', 'library_archive');
```

With PostgreSQL, the schema of each table is read from the query (`FROM my_schema.book b`). When the query doesn't
name it (the table is found through the `search_path`), or to look the metadata up in another schema, set it with
`objectSchemaIs(string $object, string $schema)`:

```php
$hydrator = new Hydrator();
$hydrator->objectSchemaIs('b', 'my_schema');
```

It takes precedence over the schema written in the query.

### Identity map

By default, every row produces new objects, even when the same entity appears in several rows. With
`identityMap(true)`, the hydrator keeps the entities it has built, by alias and primary key, and returns the same
instance when it meets the same primary key again:

```php
$hydrator = new Hydrator();
$hydrator->mapObjectTo('a', 'b', 'setAuthor');
$hydrator->identityMap(true);

// Two books by Frank Herbert now share the same Author instance
```

The map lives as long as the hydrator. It requires the entity's primary key in the selected columns. To enable it for
every query, enable it on the hydrator given to the `CollectionFactory` (see [Getting started](getting-started.md)):
the factory clones that hydrator for each collection.

## HydratorAggregator

`HydratorAggregator` groups consecutive rows sharing the same identifier, for instance to return each user with the
list of the books they own, from a query returning one row per book:

```php
use CCMBenchmark\Ting\Repository\CollectionInterface;
use CCMBenchmark\Ting\Repository\HydratorAggregator;

public function findWithBooks(): CollectionInterface
{
    $query = $this->getQuery(
        'SELECT user.id, user.name, book.id, book.title
         FROM user
         INNER JOIN book ON (book.user_id = user.id)
         ORDER BY user.id'
    );

    $hydrator = new HydratorAggregator();
    $hydrator->callableIdIs(fn (array $row) => $row['user']->getId())
        ->callableDataIs(fn (array $row) => $row['book'])
        ->callableFinalizeAggregate(function (array $row, array $books) {
            $row['user']->setBooks($books);

            return $row['user'];
        });

    return $query->query($this->getCollection($hydrator));
}
```

* `callableIdIs(callable $callableForId)` receives a row hydrated by the default hydrator and returns the aggregation
  key (here the user id).
* `callableDataIs(callable $callableForData)` returns the data to collect from each row (here the book).
* `callableFinalizeAggregate(callable $callableFinalizeAggregate)` is optional. It receives the row and the collected
  data, and returns what the collection yields. Without it, the collection yields the row with the collected data
  under the key `aggregate`:

```text
[
    'user'      => User {id: 1, name: 'Sylvain'},
    'book'      => Book {id: 1, title: 'Tintin au Tibet'},
    'aggregate' => [Book {id: 1, ...}, Book {id: 2, ...}],
]
```

With the callable above, each row is a `User`:

```text
User {
    id: 1,
    name: 'Sylvain',
    books: [
        Book {id: 1, title: 'Tintin au Tibet'},
        Book {id: 2, title: "L'Oreille cassée"},
    ],
}
```

Rows are grouped while they come in sequence: **sort the query on the aggregation key**. Once a group is complete,
later rows with the same key are ignored, so an unsorted result gives partial groups.

The aggregator only nests one level. For deeper structures, use `HydratorRelational`.

## HydratorRelational

`HydratorRelational` assembles entities on as many levels as needed, for instance users, with their books, each book
with its author:

```php
use CCMBenchmark\Ting\Repository\CollectionInterface;
use CCMBenchmark\Ting\Repository\Hydrator\AggregateFrom;
use CCMBenchmark\Ting\Repository\Hydrator\AggregateTo;
use CCMBenchmark\Ting\Repository\Hydrator\RelationMany;
use CCMBenchmark\Ting\Repository\Hydrator\RelationOne;
use CCMBenchmark\Ting\Repository\HydratorRelational;

public function findWithBooksAndAuthors(): CollectionInterface
{
    $query = $this->getQuery(
        'SELECT user.id, user.name, book.id, book.title, author.id, author.name
         FROM user
         INNER JOIN book ON (book.user_id = user.id)
         INNER JOIN author ON (author.id = book.author_id)'
    );

    $hydrator = new HydratorRelational();
    $hydrator->addRelation(new RelationMany(
        new AggregateFrom('book'),
        new AggregateTo('user'),
        'setBooks'
    ));
    $hydrator->addRelation(new RelationOne(
        new AggregateFrom('author'),
        new AggregateTo('book'),
        'setAuthor'
    ));
    $hydrator->callableFinalizeAggregate(fn (array $row) => $row['user']);

    return $query->query($this->getCollection($hydrator));
}
```

A relation takes the alias of the entities to inject (`AggregateFrom`), the alias of the entity receiving them
(`AggregateTo`) and the name of the receiving method:

* `RelationMany`: the method receives an array of all the distinct `from` entities related to the target;
* `RelationOne`: the method receives a single entity.

Relations can be added in any order. `callableFinalizeAggregate(callable $callableFinalizeAggregate)` is optional: it
receives each resulting row and returns what the collection yields. Without it, each row is an array holding the root
entity (`['user' => User {...}]`). With it:

```text
User {
    id: 1,
    name: 'Sylvain',
    books: [
        'book-1-' => Book {id: 1, title: 'Tintin au Tibet', author: Author {id: 1, name: 'Hergé'}},
        'book-2-' => Book {id: 2, title: "L'Oreille cassée", author: Author {id: 1, name: 'Hergé'}},
    ],
}
```

The array given to a `RelationMany` method is indexed by an internal reference: use `array_values()` if you need a
list.

Things to know:

* the identity map is always on (`identityMap(false)` throws a `CCMBenchmark\Ting\Exceptions\HydratorException`):
  both books above share the same `Author` instance;
* every entity involved in a relation needs a primary key in its metadata (otherwise a `HydratorException` is thrown),
  and its primary key columns must be selected;
* rows don't need to be sorted, but the whole result is read and hydrated before the first row is returned: there is
  no lazy hydration.

## Collections

A collection is iterable and countable, and can be serialized to JSON:

```php
$users = $repository->findWithBooks();

foreach ($users as $key => $row) {
    // ...
}

$first = $users->first();    // first row, or null for an empty collection
$total = count($users);      // int
$json  = json_encode($users); // the hydrated rows
```

Rows are hydrated while you iterate: with all the hydrators but `HydratorRelational`, a row is only built when it is
read.

`count()` returns the number of rows returned by the database. With `HydratorAggregator` and `HydratorRelational`,
which merge rows, the number of items you iterate over is usually lower: count them with
`iterator_count($collection)` instead.

The keys of the iteration follow the rows of the result: with `HydratorAggregator`, they skip the rows merged into a
group. Don't rely on them being consecutive.

## Writing a hydrator

A hydrator implements `CCMBenchmark\Ting\Repository\HydratorInterface`:

```php
public function setMetadataRepository(MetadataRepository $metadataRepository): void;
public function setUnitOfWork(UnitOfWork $unitOfWork): void;
public function setResult(ResultInterface $result): static;
public function count(): int;
public function getIterator(): \Generator;
```

`setResult()` receives the driver result (`CCMBenchmark\Ting\Driver\ResultInterface`). Iterating over it gives, for
each row, a list of columns, each an array with the keys `name` (alias), `orgName` (column name), `table` (table
alias), `orgTable` (table name) and `value` (plus `schema` with PostgreSQL). Extending `Hydrator` and calling its
protected `hydrateColumns()` gives you the default hydration of a row to build on.

Pass an instance to `getCollection()` like any other hydrator. Changes to the interface since 3.x are listed in
[UPGRADE-4.0.md](../UPGRADE-4.0.md).
