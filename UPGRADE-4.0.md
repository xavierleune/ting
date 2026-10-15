UPGRADE FROM 3.X to 4.0
=======================

Ting 3.14 and 3.15 already offer most of the new names and forms of 4.0 and deprecate the old ones: upgrade to 3.15
first, fix its deprecations, then upgrade to 4.0. This guide lists the major changes first, then the interfaces and
extension points to adapt, the type hints, and finally the [smaller behaviour changes](#smaller-behaviour-changes),
which only matter in edge cases.

Requirements
------------

* PHP 8.2 minimum (PHP 8.0 and 8.1 dropped): require `"php": ">=8.2"`.
* Symfony 6 dropped: `symfony/property-access` requires `^7.0 || ^8.0`. The optional `symfony/uid` and
  `symfony/cache` are tested with `^7.0 || ^8.0`.

Primary / Replica (formerly Master / Slave)
-------------------------------------------

The master / slave names are removed, without aliases:

| Before (3.x)                                                     | After (4.0)                                           |
|------------------------------------------------------------------|-------------------------------------------------------|
| `ConnectionPool(Interface)::master()` / `slave()`                | `ConnectionPool(Interface)::primary()` / `replica()`  |
| `Connection::master()` / `slave()`                               | `Connection::primary()` / `replica()`                 |
| `Query::selectMaster($value)` (not declared by `QueryInterface`) | `Query(Interface)::selectPrimary(bool $usePrimary)`   |
| `Repository::pingMaster()`                                       | `Repository::pingPrimary()`                           |
| `$forceMaster` of `Repository::get()`, `getAll()`, `getBy()`, `getOneBy()`, `Metadata` and `Generator` methods | `$forcePrimary` |
| `master` / `slaves` configuration keys                           | `primary` / `replicas`                                |

```php
// Before (3.x):
$connectionPool->setConfig(['main' => [
    'namespace' => '\CCMBenchmark\Ting\Driver\Mysqli',
    'master'    => ['host' => 'db-primary', 'user' => 'app', 'password' => 'secret', 'port' => 3306],
    'slaves'    => [['host' => 'db-replica-1', 'user' => 'app', 'password' => 'secret', 'port' => 3306]],
]]);
$city = $cityRepository->get(3, forceMaster: true);

// After (4.0):
$connectionPool->setConfig(['main' => [
    'namespace' => '\CCMBenchmark\Ting\Driver\Mysqli',
    'primary'   => ['host' => 'db-primary', 'user' => 'app', 'password' => 'secret', 'port' => 3306],
    'replicas'  => [['host' => 'db-replica-1', 'user' => 'app', 'password' => 'secret', 'port' => 3306]],
]]);
$city = $cityRepository->get(3, forcePrimary: true);
```

* `ConnectionPool::setConfig()` throws a `ConfigException` on a `master` or `slaves` key
  (`Connection "main": the "master" key was renamed "primary" in Ting 4.0`).
* Named arguments must be renamed too (otherwise an `Error`, unknown named parameter): `forceMaster:` becomes
  `forcePrimary:`, the `value:` of `selectMaster()` / `selectPrimary()` becomes `usePrimary:`. 3.14 / 3.15 still name
  them `$forceMaster` and `$value`: rename them when switching to 4.0, or pass them positionally.

Services Container Removed
--------------------------

`CCMBenchmark\Ting\Services`, `CCMBenchmark\Ting\ContainerInterface`, the optional `pimple/pimple` dependency and the
unused `Repository::$services` property are removed: every dependency is passed to the constructors.

* **With Symfony**, use [ting_bundle](https://github.com/xavierleune/ting_bundle): it declares every service.
* **Without a framework**, build the objects yourself or with any PSR-11 container. The `ConnectionPool`,
  `MetadataRepository`, `UnitOfWork`, `QueryFactory`, `SerializerFactory`, `Cache` and `RepositoryFactory` are shared;
  hydrators and `CollectionFactory` are usually created on demand (`sample/src/TingServices.php` shows it in a class):

```php
// Before (3.x):
$services = new \CCMBenchmark\Ting\Services();
$services->get('ConnectionPool')->setConfig($connections);
$repository = $services->get('RepositoryFactory')->get(CityRepository::class);

// After (4.0):
$serializerFactory = new SerializerFactory();
$connectionPool = new ConnectionPool();
$connectionPool->setConfig($connections);
$metadataRepository = new MetadataRepository($serializerFactory);
$metadataRepository->batchLoadMetadata('App\Repository', __DIR__ . '/src/Repository/*Repository.php');
$queryFactory = new QueryFactory();
$unitOfWork = new UnitOfWork($connectionPool, $metadataRepository, $queryFactory);
$hydrator = new Hydrator();
$hydrator->setMetadataRepository($metadataRepository);
$hydrator->setUnitOfWork($unitOfWork);
$cache = new Cache();
$cache->setCache(new ArrayAdapter()); // any Symfony cache pool
$collectionFactory = new CollectionFactory($metadataRepository, $unitOfWork, $hydrator);
$repositoryFactory = new RepositoryFactory(
    $connectionPool, $metadataRepository, $queryFactory, $collectionFactory, $unitOfWork, $cache
);
$repository = $repositoryFactory->get(CityRepository::class);
```

`RepositoryFactory::__construct()` and `Repository::__construct()` lost their last argument, the unused
`SerializerFactoryInterface`: a repository overriding its constructor must drop it and not pass it to
`parent::__construct()`.

Cache: doctrine/cache Replaced by Symfony Cache Contracts
--------------------------------------------------------

`doctrine/cache` is abandoned: Ting now uses [Symfony Cache Contracts](https://symfony.com/doc/current/components/cache.html#cache-contracts)
(`symfony/cache-contracts`, the "get with a callback" API).

* Every argument typed `Doctrine\Common\Cache\Cache` now expects a `Symfony\Contracts\Cache\CacheInterface`
  (`Repository` and `RepositoryFactory` constructors, `QueryFactory(Interface)::getCached()` / `getCachedPrepared()`,
  `Query\Cached\Query::setCache()`, `Cache\Cache::setCache()`). Any pool of `symfony/cache` (suggested) works;
  `NullAdapter` replaces `VoidCache`.
* `Ting\Cache\CacheInterface` extends Symfony's `CacheInterface`. `Ting\Cache\Cache` only exposes `get()` and
  `delete()`: `fetch()`, `contains()`, `save()` and `getStats()` are removed.

```php
// Before (3.x):
$cache->setCache(new \Doctrine\Common\Cache\MemcachedCache());
$value = $cache->fetch('key');
if ($value === false) {
    $value = compute();
    $cache->save('key', $value, 3600);
}

// After (4.0):
$cache->setCache(new \Symfony\Component\Cache\Adapter\MemcachedAdapter($memcachedClient));
$value = $cache->get('key', function (\Symfony\Contracts\Cache\ItemInterface $item) {
    $item->expiresAfter(3600);

    return compute();
});
```

* Cached queries keep their API (`setTtl()`, `setCacheKey()`, `setForce()`) and are protected against cache stampede
  (see [Cache](docs/cache.md#how-it-works)).
* **Keys holding a reserved character** (`{}()/\@:`, e.g. `user:42`) are rejected with a
  `Psr\Cache\InvalidArgumentException`: replace these characters, or hash the variable part (`'user_' . md5($id)`).
* A TTL of `0` means "the default lifetime of the pool": a pool with a default lifetime expires these items.
* `Query\Cached\Query::setVersion()`, which had no effect, is removed: put the version in the key
  (`$query->setCacheKey('user-list-v2')`).
* Values stored by doctrine/cache cannot be read by the new pools: expect a cold cache after upgrading.

Repository Reads: Property Names and Entity Values
--------------------------------------------------

`Repository::get()`, `getBy()` and `getOneBy()` take **property names** everywhere, and convert the values of the
criteria with the serializers of the fields, as `save()` does:

| Argument                                  | Before (3.x)                        | After (4.0)                          |
|-------------------------------------------|-------------------------------------|--------------------------------------|
| Composite primary key of `get([...])`     | column names, raw values            | property names, converted values     |
| Criteria of `getBy()` / `getOneBy()`      | property names, raw values          | property names, converted values     |
| `$order` of `getBy()`                     | column names                        | property names                       |
| Order direction other than `ASC` / `DESC` | ignored (invalid SQL when all were) | `ValueException`                     |
| Empty array in the criteria               | invalid SQL (`IN ()`)               | `ValueException`                     |
| Empty criteria (`getBy([])`, `get([])`)   | invalid SQL (`WHERE ` alone)        | `ValueException`: use `getAll()`     |

```php
// Before (3.x):
$language = $countryLanguageRepository->get(['cou_code' => 'AGO', 'col_language' => 'Kongo']);
$cities = $cityRepository->getBy(
    ['status' => CityStatus::Active->value, 'createdAt' => $date->format('Y-m-d H:i:s')],
    order: ['cit_name' => 'ASC'],
);

// After (4.0):
$language = $countryLanguageRepository->get(['code' => 'AGO', 'language' => 'Kongo']);
$cities = $cityRepository->getBy(['status' => CityStatus::Active, 'createdAt' => $date], order: ['name' => 'ASC']);
```

Each value of the criteria (and of the keys given to `get()`) is converted as follows:

| Value               | Field                                                                       | Sent as                          |
|---------------------|-----------------------------------------------------------------------------|----------------------------------|
| `null`              | any                                                                         | `IS NULL`                        |
| array               | serializer implementing `Serializer\ArrayValueInterface` (`Json`)           | serialized as a whole, `=`       |
| array               | other fields                                                                | `IN (...)`, each element as below |
| object              | with a serializer                                                           | serialized                       |
| `Stringable` object | without serializer                                                          | as is (cast to string)           |
| other object        | without serializer                                                          | `ValueException`                 |
| scalar              | serializer implementing `Serializer\ScalarValueInterface` (`Ip`, `Boolean`) | serialized                       |
| scalar              | other fields                                                                | as is                            |

* Database values of the other fields (`CityStatus::Active->value`, a formatted date) keep working: only column-name
  keys must be renamed, and the error names the property to use
  (`"cit_name" is a column name: use the property name "name" in the order of Repository::getBy()`).
* **`Ip` and `Boolean` fields** take the PHP value: `['ip' => '10.0.0.1']` instead of `['ip' => 167772161]`,
  `['active' => false]` instead of `'f'` or `0`. 3.15 still sends these scalars as is, without deprecation: check
  them by hand.
* **An empty array** throws: return early when the list may be empty (`if ($ids === []) { return ...; }`).
* With a `Json` field, an array is compared as a whole with the JSON encoding of the value (see
  [the caveats on JSON equality](docs/repositories.md#criteria-keys-and-values)). A custom serializer can implement
  `ArrayValueInterface` or `ScalarValueInterface` to get the same behaviour.
* `Query\Generator` still works at the database level (column names and database values, sent as given).

Database Timezone Option
------------------------

**Check the `timezone` of your database options before upgrading.** 3.x ignored the result of
`ConnectionPool::setDatabaseOptions(['world' => ['timezone' => ...]])`: a value the server rejected (a typo, a named
zone on a MySQL server without time zone tables) was silently dropped. In 4.0, `setTimezone()` (Mysqli, Pgsql) throws
a `DriverException` (`Can't set timezone Europe/Pari (...)`), and as the option is applied on every `primary()` /
`replica()`, **every query on that database fails**.

Fix the value rather than catching the exception. Try it with `SET time_zone = '<value>';` on MySQL (an offset such
as `'+00:00'`, `'SYSTEM'`, or a named zone once `mysql_tzinfo_to_sql` loaded the tables) or `SET timezone =
'<value>';` on PostgreSQL (a name of `pg_timezone_names`). To keep the server's time zone, remove the option.

Unit of Work
------------

### Mutable fields modified in place are written

A change made in place (`$entity->getPublishedAt()->modify('+1 day')`, `$entity->getPayload()->tag = 'x'`) calls no
setter: 3.x silently lost it. 4.0 keeps the database value of each **mutable** field of a managed entity and, on save,
writes those whose value changed, with the notified changes of the other fields. `isPropertyChanged()` follows.

* A field is mutable by default when its serializer is `Serializer\DateTime` (a `datetime` field whose property is
  not typed `\DateTimeImmutable`), `Serializer\Json` decoding objects, or a serializer of your own. The new `mutable`
  field option overrides it. See [entities](docs/entities.md#tracking-changes).
* The value compared is what the getter returns: a getter or setter transforming the value read (a getter returning
  `new \stdClass()` for `null`, a setter converting a date to UTC) makes the next save write the transformed value.
  See [mutable values](docs/entities.md#mutable-values).
* A mutable field is written only once its value is known (read, set through its setter, or inserted): an entity read
  partially never overwrites the columns it did not read.
* Recommended: type date properties `\DateTimeImmutable` and decode JSON to arrays
  (`'serializer_options' => ['unserialize' => ['assoc' => true]]`), which are not compared on every save. A
  `datetime` field hydrates a `\DateTimeImmutable` only for a property typed exactly `\DateTimeImmutable` (nullable or
  not).

### Saving an entity not managed holding its id updates its row

3.x inserted any entity not managed (a clone, a detached entity, one restored from a cache or a session) as a new row,
its `autoincrement` id replaced. In 4.0, when the `autoincrement` property holds a value other than `null`, the entity
is **updated** by its primary key with every readable mapped property, then managed. See
[Saving an entity not managed](docs/unit-of-work.md#saving-an-entity-not-managed).

```php
$copy = clone $city;
$copy->setId(null);          // to duplicate the row: without this line, 4.0 updates the row of $city
$cityRepository->save($copy);
```

* An id defaulting to `0` (`private int $id = 0;`) is an id: declare it `?int $id = null`.
* Every field is written: an entity read partially, then detached or cloned and saved, writes its default values over
  the columns it did not read. `manage()` it first to write its changes only.
* An entity deleted keeps its id: saving it again updates no row.

### Lifecycle

* `isManaged()` is `false` for a new entity until its `INSERT` has run, so a failed `INSERT` can be saved again: use
  `isNew()` or `shouldBePersisted()` to know whether an entity is queued. `pushDelete()` no longer makes an entity
  managed (an entity never inserted is only unqueued).
* **A change made while an entity is detached is lost**: 3.x wrote it after `manage()`. Make it after `manage()`.
* An entity inserted in a transaction rolled back is inserted again by its next save (Mysqli, Pgsql). A `commit()`
  failing on a lost connection is treated as a rollback although the server may have committed: check the rows before
  retrying (see [Transactions](docs/unit-of-work.md#transactions)).

### Several repositories mapping the same entity class

3.x used the metadata of the last repository built. In 4.0, `Repository::save()` and `delete()` write with the
metadata of the repository called (`UnitOfWork::pushSave()` / `pushDelete()` called directly still use those
registered for the class), and an entity is tracked with the metadata that read it. See
[Writing](docs/repositories.md#writing) and, to override `UnitOfWork`, the
[unit of work overrides](#unit-of-work-and-metadata-overrides).

Hydrators
---------

* **One identity map per result**: with `identityMap(true)`, a hydrator given several results (kept as a service, or
  given to several `getCollection()` calls) no longer returns the entities of an earlier result.
* **Same table in several databases or schemas** (`City` in `world`, `CityArchive` in `world_archive`): the hydrator
  takes the metadata of the database, then of the schema, of the result, and throws a `HydratorException` listing the
  candidates when it stays ambiguous (3.x took the first registered). Name the database or schema of the alias with
  `objectDatabaseIs()` / `objectSchemaIs()`, see
  [Tables from another database](docs/hydrators.md#tables-from-another-database).
* **Two repositories on the same table** (`UserRepository` / `User` and `UserLightRepository` / `UserLight`): 3.x
  hydrated with the last repository built (`UserRepository::get(1)` could return a `UserLight`). The reads of a
  repository now use its own metadata; a hydrator used outside a repository throws a `HydratorException`: call
  `Hydrator::preferRepository(UserRepository::class)`, see
  [Several repositories on the same table](docs/hydrators.md#several-repositories-on-the-same-table).
* **`HydratorRelational` throws a `HydratorException`** instead of hydrating wrong objects silently:
  * when its relations form a cycle (a bidirectional relation, an alias given to itself): declare one direction and
    set the back reference in the setter, see [HydratorRelational](docs/hydrators.md#hydratorrelational);
  * when the entity of an alias of a relation has no value for a primary key column (not selected, or null): 3.x
    merged the entities of that alias under one key. Select every primary key column (a `LEFT JOIN` without match
    is still accepted);
  * when an alias of a relation is not in the result (a typo in `AggregateFrom` / `AggregateTo`): 3.x returned a
    single row.

  Rows without root entity (a `LEFT JOIN` on the root side) are skipped.

PostgreSQL
----------

### Keys of the rows

The Pgsql driver reads tables and aliases as PostgreSQL resolves them, and names the columns without alias by its own
rule: some keys change (in the `0` object of `Hydrator`, the arrays of `HydratorArray`, the properties set by
`HydratorValueObject`). **Alias every computed expression with `AS`**, as with MySQL, to keep the key you read.

| Expression                                         | 3.13 key                            | 4.0 key             |
|----------------------------------------------------|-------------------------------------|---------------------|
| `id::text`, `x::double precision`                  | `text`, `precision`                 | `id`, `x`           |
| `count(id)`, `now()::date`                         | `count(id)`, `date`                 | `count`, `now`      |
| `NOT flag`, `-amount`, `'x'::text`, `CASE ... END` | `flag`, `amount`, `text`, its text  | `?column?`          |
| `SELECT nextval('s') AS nextId` (without `FROM`)   | `nextid`                            | `nextId`            |

* `CAST(... AS t)` is named after a plain column only; an expression in parentheses (`(x)::text`) or a call followed
  by `OVER`, `FILTER` or `WITHIN GROUP` is `?column?`. PostgreSQL itself names more expressions than the driver, see
  [Columns without metadata](docs/hydrators.md#columns-without-metadata).
* The entity of a table is keyed by its alias also for a `FROM` list (`FROM users u, orders o`) and the last table of
  a statement ending with `;`, where 3.x keyed the columns without prefix by the table name or by no table.
* **A plain column aliased without `AS`** (`SELECT u.name n FROM users u`) is hydrated into the entity of its table,
  as with `AS`: `$row[0]->n` no longer exists, and `mapAliasTo()` / `unserializeAliasWith()` on such an alias no
  longer receive the value. To keep it in `0`, make it an expression (`u.name || '' AS n`). A column the metadata do
  not map stays in `0`.
* **A query of several statements** is read by the columns of its last statement (`SELECT id FROM t; SELECT name FROM
  u` was keyed `id`, is `name`). When the last statement returns no column (`BEGIN; SELECT ...; COMMIT`), `query()`
  throws a `QueryException`, after the statements ran: run the other statements apart.
* **A query whose columns read are not as many as those of its result** (`INSERT ... SELECT a, b ... RETURNING id`,
  `EXPLAIN SELECT ...`) throws a `QueryException` (`3 columns read from the query, but its result has 4`): 3.13
  shifted the values or threw a `ValueError`. Run it without a collection.

### Case of identifiers and booleans

* Quoted identifiers keep their case, unquoted ones are folded to lowercase, as PostgreSQL does. Tables and columns are
  matched to the metadata exactly, then case-insensitively, so a 3.x application keeps working (and
  `setTable('Users')` for a table `"Users"`, never matched by 3.x, now is). The same fallback applies to MySQL (a
  column read as `BOO_NAME` matches `boo_name`). See
  [Case of the table and column names](docs/hydrators.md#case-of-the-table-and-column-names).
* A `bool` field without serializer reads `'f'` as `false` (3.x: `true`), whatever the driver, and the Pgsql driver
  sends a PHP boolean as `'1'` / `'0'` (3.x sent `false` as `''`, rejected for a boolean column): a `false` bound to a
  text column now stores `'0'`. Declare `bool` fields with `Driver\Pgsql\Serializer\Boolean`, see
  [Types](docs/repositories.md#types).

Serializers
-----------

* **`Serializer\DateTimeImmutable` writes `Y-m-d H:i:s` by default** instead of `\DateTimeInterface::ATOM`. To keep
  ATOM: `'serializer_options' => ['serialize' => ['format' => \DateTimeInterface::ATOM]]`.
* `Serializer\DateTime` and `DateTimeImmutable` read their `format`, then the formats databases return (`Y-m-d` at
  midnight, `Y-m-d H:i:s` with optional fractional seconds and UTC offset, ATOM). Anything else throws a
  `Serializer\RuntimeException`: the PHP date parser, which read `now` or `+1 day` as dates, is no longer a fallback
  (`unSerializeUseFormat => false` still opts in to it).
* `Serializer\Json` honours the `JSON_OBJECT_AS_ARRAY` flag of its unserialize `options`: such a field now decodes
  objects to **arrays** (3.13 forced `\stdClass`). Remove the flag, or set `assoc` to `false`, to keep objects.

Removed Methods
---------------

| Removed                                                      | Use instead                                              |
|--------------------------------------------------------------|----------------------------------------------------------|
| `Query(Interface)::getInsertId()`, `Mysqli\Driver` / `Pgsql\Driver::getInsertId()` | `getInsertedId()`                  |
| `Pgsql\Driver::getInsertIdForSequence($sequence)`            | `getInsertedIdForSequence($sequence)`                    |
| `Generator::getByCriteriaWithOrderAndLimit()`                | `Generator::getByCriteria()` (new `$order`, `$limit`)    |
| `Metadata::getGetter()`, `Metadata::getSetter()`             | the `getter` / `setter` options of the field             |
| `UnitOfWork::generateUid()`, `generateUUID()`                | `uniqid()`, `symfony/uid`...                             |
| `Query\Cached\Query::setVersion()`                           | the version in the cache key                             |
| `Cache\Cache::fetch()`, `contains()`, `save()`, `getStats()` | `Cache\Cache::get()`                                     |

```php
// Before (3.x):
$query->getInsertId();
$generator->getByCriteriaWithOrderAndLimit(['status' => 'active'], $collectionFactory, false, ['name' => 'ASC'], 10);
$getter = $metadata->getGetter('myField');

// After (4.0):
$query->getInsertedId();
$generator->getByCriteria(['status' => 'active'], $collectionFactory, false, ['name' => 'ASC'], 10);
$getter = array_column($metadata->getFields(), null, 'fieldName')['myField']['getter'] ?? 'getMyField';
```

From a repository, use `getBy($criteria, $forcePrimary, $order, $limit)`. Ting itself reads and writes properties
through Symfony PropertyAccess, see
[How Ting reads and writes properties](docs/entities.md#how-ting-reads-and-writes-properties).

Interfaces and Extension Points
-------------------------------

### New methods on the interfaces

* `DriverInterface::ping(): bool` and `setTimezone(?string $timezone = null): void`; `connect()` is typed
  `connect(string $hostname, ?string $username, ?string $password, int $port): static` (`ConnectionPool` passes `null`
  for a missing user or password).
* `ConnectionPoolInterface::setDatabaseOptions(array $options): void`.
* `QueryInterface::selectPrimary(bool $usePrimary): static` (on `Query` since 3.x, now declared).
* `StatementInterface::isStale(): bool`: `true` once the session the statement was prepared in is gone, so that
  prepared queries prepare it again. A statement whose connection cannot be replaced can return `false`.
* `CollectionInterface` and `HydratorInterface`: `count(): int` and `getIterator(): \Generator`.
* `ConnectionPoolInterface`, `StatementInterface` and `QueryInterface` no longer declare a constructor.

### Optional driver interfaces

* `Driver\SequenceAwareDriverInterface` (`getInsertedIdForSequence(string $sequenceName): int`): **required** for the
  `sequenceName` option of an autoincrement field; a method of that name is no longer enough.
* `Driver\RollbackListenerAwareDriverInterface` (`addRollbackListener(RollbackListenerInterface $listener, object
  $subject): bool`): the unit of work registers each entity inserted in a transaction, and expects
  `rolledBack($subject)` when the transaction ends without commit. Mysqli and Pgsql implement it. Without it, an
  entity inserted in a transaction rolled back stays managed: `detach()` it and set its id to `null` to insert it again.
* `Driver\DefaultValuesInsertDriverInterface` (`getDefaultValuesInsertClause(): string`): an `INSERT` without any value
  is written `INSERT INTO t () VALUES ()` (MySQL) unless the driver implements it, as Pgsql does (`DEFAULT VALUES`).

### Unit of work and metadata overrides

* `UnitOfWork::manage()`, `pushSave()` and `pushDelete()` take a new optional `?Metadata $metadata = null`, e.g.
  `manage(NotifyPropertyInterface $entity, ?Metadata $metadata = null): void`: an override must declare it (otherwise
  PHP refuses it) and pass it to the parent (otherwise the entity is tracked with the metadata of its class).
* The protected `Metadata::getPrimariesKeyValuesByProperties()` takes a third `array $storedPrimaries = []` (kept
  values of mutable primary keys): an override must declare and use it.
* The protected `Metadata::getColumnsFromCriteria()` and `getPrimariesKeyValuesAsArray()` validate the keys (property
  names only) and return converted database values: an override must do the same.

### Smaller Changes for Extensions

Elements marked `@internal` are reserved for Ting and [ting_bundle](https://github.com/xavierleune/ting_bundle) and may
change in a minor version. Two of them stay supported for extension, as in 3.x:

* `Repository::__construct()`: internal to **call** (`RepositoryFactory` passes its 6 arguments), but a repository may
  **override** it, passing the same parameters to `parent::__construct()`;
* the protected `Hydrator::hydrateColumns()`, which a hydrator extending `Hydrator` may call (see
  [Writing a hydrator](docs/hydrators.md#writing-a-hydrator)). A subclass overriding `getIterator()` must read its rows
  through the protected `hydratedRows()`: looping over `$this->result` breaks a read nested with the same hydrator.

Other changes:

* `Metadata::getByCriteriaWithOrderAndLimit()` became `@internal`, like the other query methods of `Metadata`.
* The protected `Query\Cached\Query::checkCache()` is replaced by `queryThroughCache()`.
* Protected properties renamed or removed:

  | Class                          | 3.13                                 | 4.0                                     |
  |--------------------------------|--------------------------------------|-----------------------------------------|
  | `ConnectionPool`               | `$connectionSlaves`                  | `$connectionReplicas` (already in 3.14) |
  | `Query\Query`                  | `$selectMaster`                      | `$selectPrimary`                        |
  | `Repository\RepositoryFactory` | `$collection`, `$serializerFactory`  | removed                                 |
  | `Repository\Repository`        | `$services`                          | removed                                 |
  | `Repository\Hydrator`          | `$alreadyManaged`                    | removed (one identity map per result)   |
  | `Repository\HydratorArray`     | `$metadataRepository`, `$unitOfWork` | removed (unused)                        |
  | `Query\Cached\Query`           | `$version`                           | removed                                 |
  | `Driver\Pgsql\Statement`       | `$queryType`                         | removed                                 |

* The Pgsql `setCollectionWithResult()` methods changed their parameters:
  `Driver::setCollectionWithResult(object $resultResource, string $sql, CollectionInterface $collection)` (protected:
  the result is passed first, 3.13 read `$this->result`) and
  `Statement::setCollectionWithResult(object $resultResource, CollectionInterface $collection): bool` (the collection
  is required).
* The parameters of the generated queries (`get()`, `getBy()`, `getOneBy()`, the `INSERT` / `UPDATE` / `DELETE` of
  the unit of work) are named `:<role><position>_<column>` (`v` for a written value, `w` for a condition, characters
  outside `[a-zA-Z0-9_]` replaced by `_`, `__<n>` in an `IN` list), as the query logs show:

  | Query                                 | 3.x                                | 4.0                                     |
  |---------------------------------------|------------------------------------|-----------------------------------------|
  | `getBy(['id' => 3, 'name' => ['a']])` | `id = :#id AND name IN (:name__1)` | `id = :w1_id AND name IN (:w2_name__1)` |
  | INSERT                                | `VALUES (:id, :name)`              | `VALUES (:v1_id, :v2_name)`             |
  | UPDATE                                | `SET name = :name WHERE id = :#id` | `SET name = :v1_name WHERE id = :w1_id` |

* The Mysqli and Pgsql `escapeField()` double a backtick (Mysqli) or a double quote (Pgsql) in the name, and escape a
  colon (`` `x \:y` ``) so that it is not read as a placeholder: their result is meant for SQL run through a Ting
  driver. Code escaping the name before calling `escapeField()` must stop doing it.
* `Connection::__construct()` (internal) throws a `RuntimeException` on an empty `$name` or `$database`.

Type Hints and Strict Types
---------------------------

Every method of every Ting interface, and every public or protected method and property of every non-final class,
declares native types (3.13 declared almost none). Generics and array shapes are in the PHPDoc: `Repository<T>`
(`get()` returns `?T`, `getBy()` a `CollectionInterface<T>`), `CollectionInterface<T>`, `HydratorInterface<T>`,
`QueryInterface<T>`, and `@phpstan-type` aliases to import (`ConnectionConfig`, `Field`...).

To adapt, load every class implementing a Ting interface or extending a Ting class (repositories, hydrators,
serializers, loggers, drivers, entities): an incompatible signature is a fatal error when the class is loaded. Then run
PHPStan or Psalm, which also reports the new generic types. An implementation or override needs the same or a narrower
return type, the same or wider parameter types, and the optional parameters of the parent. What breaks 3.x code:

* **Every repository**: `initMetadata()` must declare its return type.

  ```php
  // Before (3.x):
  public static function initMetadata(SerializerFactoryInterface $serializerFactory, array $options = [])

  // After (4.0):
  public static function initMetadata(SerializerFactoryInterface $serializerFactory, array $options = []): Metadata
  ```

* **A repository overriding a method of `Repository`** must copy its 4.0 signature, e.g.
  `public function getAll(bool $forcePrimary = false): CollectionInterface` (was `getAll($forceMaster = false)`); an
  override of `getCollection()` must return `Collection`.
* **Serializers** implementing `SerializeInterface` / `UnserializeInterface` must declare a return type (`mixed` or
  narrower); a class extending a built-in serializer must copy the return type of the method it overrides
  (`serialize($toSerialize, array $options = []): ?string` for `DateTime`).
* **Loggers**: the methods of `DriverLoggerInterface` and `CacheLoggerInterface` return `void`, and an implementation
  must write `startStatementExecute(string $statement, array $params = [])` (ting_bundle 4.0 is compatible).
* **Drivers** must keep the new defaults of `escapeField(mixed $field = null)` and `setTimezone(?string $timezone =
  null)`. **Entities**: `propertyChanged()` and `addPropertyListener()` return `void`, and the `$listeners` of the
  `NotifyProperty` trait is a `protected array`.
* **Typed properties and parameters**: a subclass redeclaring a property must keep its type; `null` or an object
  passed to a typed parameter is a `TypeError` (`setTtl(null)`), where 3.x cast it.

Smaller Behaviour Changes
-------------------------

Edge cases, misuses and rarely hit changes.

### Configuration and drivers

* The `port` key is required for the primary and every replica: without it, a warning then a `TypeError` (3.x warned
  and used the default port).
* `setCharset()` (Mysqli, Pgsql) throws a `DriverException` when the server rejects the charset (Mysqli under
  `MYSQLI_REPORT_STRICT` threw a `mysqli_sql_exception`).
* Mysqli sends the `timezone` option as a single-quoted string: under the sql_mode `ANSI_QUOTES`, 3.x sent an
  identifier, which failed silently, so the configured time zone now applies.
* `reconnect()` before `connect()` throws a `NeverConnectedException` (Mysqli, Pgsql). Two Pgsql drivers with the
  same parameters no longer share a session, and `reconnect()` opens a new one.
* The Pgsql driver holds `PgSql\Connection` / `PgSql\Result` objects instead of resources (code inspecting its
  internals must follow).
* The named arguments `originalSQL:` of `Pgsql\Driver::execute()` and `id:` of `Cache\Cache::delete()` are renamed
  `sql:` and `key:` (an `Error` otherwise).

### Queries and transactions

* **A missing placeholder value** throws a `Driver\QueryException` (`Value has not been set for param name`) with every
  driver: 3.x sent `NULL` with PostgreSQL and with Mysqli prepared queries. A value set to `null` is still `NULL`.
* Mysqli non-prepared queries send an escaped colon `\:` as `:`, as the other drivers: a query that relied on the
  backslash reaching MySQL must double it.
* SphinxQL sends `null` as `NULL` and booleans as `0` / `1` (3.x: `''` for `null` and `false`).
* Floats are sent with the digits that read back as the same float (15 to 17, 3.x: 14), with a `.` whatever the
  locale. Pgsql sends `INF` / `NAN` as `Infinity` / `NaN`; Mysqli throws a `QueryException` for them in a
  non-prepared query.
* A negative TTL (`setTtl(-1)`) throws a `QueryException` (3.x never cached the result). With Pgsql, a statement
  without column (`SELECT ... INTO`, `SELECT FROM t`) gives rows without column, or an empty collection.
* `startTransaction()`, `commit()` and `rollback()` throw a `TransactionException` when the database refuses them,
  including the `COMMIT` of a PostgreSQL transaction aborted by a failed query; a failed `commit()` leaves no
  transaction open (`rollback()` then throws). When the connection was replaced or closed during a transaction,
  `commit()` throws and `rollback()` sends nothing. See [Transactions](docs/repositories.md#transactions).

### Repository reads

* An unknown key throws a `ValueException` with a new message (`Undefined property "foo" in the criteria of
  Repository::getBy()`), and so does an invalid direction (`Invalid direction "UP" for property "name" in the order of
  Repository::getBy(): use "ASC" or "DESC"`).
* `null` or a nested array inside an `IN` list, and a value its serializer converts to `NULL`, throw a
  `ValueException`.
* `get()` with a single value serializes an object (also with the serializer of the type of the field: `datetime`,
  `uuid`...), and a scalar for a `ScalarValueInterface` serializer.

### Unit of work and entities

* A value that does not survive a round trip (microseconds, a zero date, a JSON big integer) is not rewritten while it
  is not modified; a mutable field whose database value cannot be computed is taken as unchanged unless set through
  its setter. A mutable primary key targets its row by the value read.
* A setter converting dates to another timezone than the one they are read in shifts them at each read and save.
* `NotifyProperty::addPropertyListener()` no longer adds a listener already added (a clone keeps those of its
  original).
* `NotifyProperty::__serialize()` and `__debugInfo()` return mangled keys (`"\0*\0name"`, `"\0App\Entity\City\0name"`)
  so that the private properties of a parent or child class are kept; `serialize()` and `var_dump()` are not affected,
  and payloads stay compatible both ways with 3.13. `__sleep()` is honoured again (ignored since 3.12).

### Hydrators

* `HydratorAggregator` throws a `HydratorException` when the callable of `callableIdIs()` returns `null` (3.x lost
  every row) or an identifier neither int nor string that cannot be serialized.
* An entity returned again by the identity map after its `detach()` is managed again. The keys of the array given to
  a `RelationMany` method changed format (`'book-1-'` in 3.x): use `array_values()`.
* A table with a single metadata is found whatever the database or schema read; schemas are compared
  case-insensitively when none matches exactly.
* Case variants throw: several columns of a row falling back onto one metadata column (`"NAME"` and `"Name"`), or
  several metadata columns matching one column read, throw a `HydratorException`; two aliases differing only by case
  (`"U"` and `u`) a `QueryException`. Under MySQL `lower_case_table_names=0`, declare the metadata of `T_BOUH` and
  `t_bouh` with their exact names, which win.

### Serializers

* `Serializer\BackedEnum` (int-backed) rejects a value that is not an integer (`'1.5'`, `'abc'`, `"1\n"`), and
  `Serializer\Ip` one that is not an integer from -2147483648 to 4294967295, with a `Serializer\RuntimeException`.
* `Serializer\Json` errors always throw a `Serializer\RuntimeException` (with `JSON_THROW_ON_ERROR`, a `JsonException`
  escaped), and `JSON_PARTIAL_OUTPUT_ON_ERROR` returns the partial output.
* With `unSerializeUseFormat => false`, an empty string is rejected (3.x read the current time).

### Cache and debugging

* Cache logging: a read is logged as `OPERATION_GET`, flagged as a miss when no cached value was served, its duration
  excluding the computation of the value; `OPERATION_STORE` and `OPERATION_EXIST` are no longer emitted.
* `Util\Debug::export()` and `dump()` describe objects as arrays (`['__CLASS__' => ..., 'property' => ...]`), dates
  with their value, iterables with object keys (`WeakMap`) as `['key' => ..., 'value' => ...]` pairs, and do not
  iterate generators. For a quick look at an entity, `var_dump($entity)` is enough.
