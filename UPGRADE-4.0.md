UPGRADE FROM 3.X to 4.0
=======================

PHP Version
-----------

* PHP 8.0 and 8.1 support has been dropped. The minimum required version is now **PHP 8.2**.
* Update your `composer.json` to require `"php": ">=8.2"`

Symfony
-------

* Symfony 6 support has been dropped: `symfony/property-access` now requires `^7.0 || ^8.0`. The optional
  `symfony/uid` and `symfony/cache` (suggested, not required) are tested with `^7.0 || ^8.0`.

Primary / Replica (formerly Master / Slave)
-------------------------------------------

The master / slave terminology has been replaced by primary / replica. The old names have been removed, without
aliases:

| Before (3.x)                                                      | After (4.0)                                                         |
|-------------------------------------------------------------------|---------------------------------------------------------------------|
| `ConnectionPool(Interface)::master(string $name, string $database)` | `ConnectionPool(Interface)::primary(string $name, string $database)` |
| `ConnectionPool(Interface)::slave(string $name, string $database)`  | `ConnectionPool(Interface)::replica(string $name, string $database)` |
| `Connection::master()`                                            | `Connection::primary()`                                             |
| `Connection::slave()`                                             | `Connection::replica()`                                             |
| `Query::selectMaster($value)` (not declared by `QueryInterface`)  | `Query(Interface)::selectPrimary(bool $usePrimary)`                 |
| `Repository::pingMaster()`                                        | `Repository::pingPrimary()`                                         |
| `$forceMaster` parameter of `Repository::get()`, `getAll()`, `getBy()`, `getOneBy()`, `Metadata` and `Generator` methods | `$forcePrimary` |
| `master` configuration key                                        | `primary`                                                           |
| `slaves` configuration key                                        | `replicas`                                                          |

```php
// Before (3.x):
$connectionPool->setConfig([
    'main' => [
        'namespace' => '\CCMBenchmark\Ting\Driver\Mysqli',
        'master'    => ['host' => 'db-primary', 'user' => 'app', 'password' => 'secret', 'port' => 3306],
        'slaves'    => [
            ['host' => 'db-replica-1', 'user' => 'app', 'password' => 'secret', 'port' => 3306],
        ],
    ],
]);
$city = $cityRepository->get(3, forceMaster: true);

// After (4.0):
$connectionPool->setConfig([
    'main' => [
        'namespace' => '\CCMBenchmark\Ting\Driver\Mysqli',
        'primary'   => ['host' => 'db-primary', 'user' => 'app', 'password' => 'secret', 'port' => 3306],
        'replicas'  => [
            ['host' => 'db-replica-1', 'user' => 'app', 'password' => 'secret', 'port' => 3306],
        ],
    ],
]);
$city = $cityRepository->get(3, forcePrimary: true);
```

* Ting 3.14 already offers the new names and deprecates the old ones: you can migrate on 3.x first, fix the
  deprecations, then upgrade to 4.0.
* Named arguments must be renamed too, otherwise PHP throws an `Error` (unknown named parameter): `forceMaster:`
  becomes `forcePrimary:`, and the `value:` of `selectMaster()` / `selectPrimary()` becomes `usePrimary:`. They are
  the exception to the migration on 3.x: on 3.14 / 3.15 the parameters are still called `$forceMaster` (repository
  methods) and `$value` (`selectPrimary()`), so `forcePrimary:` and `usePrimary:` only work once on 4.0. Rename them
  when switching to 4.0, or pass these arguments positionally.
* `ConnectionPool::setConfig()` throws a `CCMBenchmark\Ting\Exceptions\ConfigException` when a connection still uses
  the `master` or `slaves` key (e.g. `Connection "main": the "master" key was renamed "primary" in Ting 4.0`).
* As before, a replica falls back to the primary when no replica is configured.
* The `port` key is now required for the primary and every replica: without it, PHP warns `Undefined array key "port"`
  and the driver throws a `TypeError` (`connect()` takes an `int $port`). 3.x only warned, and connected to the
  default port.

Database Timezone Option: an Invalid Value Now Fails Every Connection
---------------------------------------------------------------------

**Check the `timezone` of your database options before upgrading.** In 3.x, the `timezone` option
(`ConnectionPool::setDatabaseOptions(['world' => ['timezone' => ...]])`) was sent to the server and its result
ignored: a value the server rejected (a typo, a named zone such as `Europe/Paris` on a MySQL server whose time zone
tables are not loaded) was silently dropped, and the session kept the server's time zone.

In 4.0, `setTimezone()` (Mysqli and Pgsql) throws a `CCMBenchmark\Ting\Exceptions\DriverException` when the server
rejects the time zone (`Can't set timezone Europe/Pari (...)`, with the database error), and does not record it. As
`ConnectionPool` applies the option on **every** `primary()` / `replica()`, the rejected value is sent again, and
throws again, on every access to the connection: a configuration that worked on 3.x (with its time zone silently
ignored) now makes **every query on that database fail**.

The fix is to correct the value, not to catch the exception:

* MySQL accepts an offset (`'+00:00'`), `'SYSTEM'`, or a named zone (`'Europe/Paris'`) only when its time zone tables
  are loaded (`mysql_tzinfo_to_sql`);
* PostgreSQL accepts the names listed by `SELECT name FROM pg_timezone_names` (`Europe/Paris`, `UTC`...);
* to keep the server's time zone, which is what 3.x did with a rejected value, remove the option.

Try the value on each server first: `SET time_zone = '<value>';` (MySQL) or `SET timezone = '<value>';`
(PostgreSQL). With Mysqli, the value is now sent as a single-quoted string: under the sql_mode `ANSI_QUOTES`, 3.x sent
it as an identifier, which always failed silently, so the time zone configured there now applies for the first time.

Repository reads: property names and entity values
--------------------------------------------------

`Repository::get()`, `getBy()` and `getOneBy()` now take **property names** (`fieldName`) everywhere, and convert the
values of the criteria with the serializers of the fields, as `save()` does:

| Argument                                  | Before (3.x)                            | After (4.0)                                    |
|-------------------------------------------|-----------------------------------------|------------------------------------------------|
| Composite primary key of `get([...])`     | column names, raw values                | property names, converted values               |
| Criteria of `getBy()` / `getOneBy()`      | property names, raw values              | property names, converted values               |
| `$order` of `getBy()`                     | column names                            | property names                                 |
| Order direction other than `ASC` / `DESC` | ignored (invalid SQL when all were)     | `ValueException`                               |
| Empty array in the criteria               | invalid SQL (`IN ()`)                   | `ValueException`                               |

The `CountryLanguage` call below uses the sample model (`sample/src/model/CountryLanguageRepository.php`). The `City`
criteria are illustrative: they assume `status` (an enum `CityStatus`) and `createdAt` (a `datetime`) fields, which the
sample `City` does not have.

```php
// Before (3.x):
$language = $countryLanguageRepository->get(['cou_code' => 'AGO', 'col_language' => 'Kongo']);
$cities = $cityRepository->getBy(
    ['status' => CityStatus::Active->value, 'createdAt' => $date->format('Y-m-d H:i:s')],
    order: ['cit_name' => 'ASC'],
);

// After (4.0):
$language = $countryLanguageRepository->get(['code' => 'AGO', 'language' => 'Kongo']);
$cities = $cityRepository->getBy(
    ['status' => CityStatus::Active, 'createdAt' => $date],
    order: ['name' => 'ASC'],
);
```

Each value of the criteria (and of a composite key given to `get()`) is converted as follows:

| Value                             | Field                                                               | Sent as                                       |
|-----------------------------------|---------------------------------------------------------------------|-----------------------------------------------|
| `null`                            | any                                                                 | `IS NULL`                                     |
| array                             | serializer implementing `Serializer\ArrayValueInterface` (`Json`)   | serialized as a whole, `=`                    |
| empty array                       | other fields                                                        | `ValueException`: nothing can match           |
| array                             | other fields                                                        | `IN (...)`, each element converted as below   |
| object                            | with a serializer                                                   | serialized, `=` (or element of the `IN` list) |
| `Stringable` object               | without serializer                                                  | as is (the driver casts it to string)         |
| other object                      | without serializer                                                  | `ValueException`                              |
| scalar (string, int, float, bool) | serializer implementing `Serializer\ScalarValueInterface` (`Ip`, `Boolean`) | serialized, `=` (or element of the `IN` list) |
| scalar (string, int, float, bool) | other fields                                                        | as is                                         |

* An unknown key throws a `CCMBenchmark\Ting\Exceptions\ValueException`, as in 3.x for the criteria, but with a new
  message (`Undefined property "foo" in the criteria of Repository::getBy()` instead of
  `Undefined property foo in your criteria`). When the key is a column name, the message names the property to use,
  e.g.
  `"cit_name" is a column name: use the property name "name" in the order of Repository::getBy()`.
* An order direction other than `ASC` or `DESC` (case-insensitive) throws a `ValueException` (3.x ignored it, and
  produced an invalid `ORDER BY` without column when every direction was invalid), e.g.
  `Invalid direction "UP" for property "name" in the order of Repository::getBy(): use "ASC" or "DESC"`.
* An empty array throws a `ValueException`
  (`Empty array for property "id" in the criteria of Repository::getBy(): nothing can match`)
  instead of sending invalid SQL: return early when the list may be empty (`if ($ids === []) { return ...; }`).
* `null` or a nested array inside an `IN` list throws a `ValueException`: an `IN` list never matches `NULL`.
* Empty criteria (`getBy([])`, `getOneBy([])`) throw a `ValueException` instead of sending invalid SQL (`WHERE ` with
  nothing after it), e.g. `No criteria in Repository::getBy(): use Repository::getAll() to read every row`; so does
  `get([])` (`No primary key value in Repository::get()`). Use `getAll()` to read every row.
* Scalars are still sent as is: database values (`CityStatus::Active->value`, a formatted date) keep working, only
  column-name keys must be renamed. Passing the PHP value (enum, `DateTime`...) is now possible.
* Except for a field whose serializer's PHP value is a scalar, i.e. implements the new marker interface
  `CCMBenchmark\Ting\Serializer\ScalarValueInterface` (`Serializer\Ip`, `Driver\Mysqli\Serializer\Boolean`,
  `Driver\Pgsql\Serializer\Boolean`): its scalars (and each scalar of an `IN` list) are serialized, like `save()`
  does. Pass the PHP value: `['ip' => '10.0.0.1']` instead of `['ip' => 167772161]` (which `Ip` now rejects with a
  `Serializer\RuntimeException`; in 3.x, `'10.0.0.1'` was compared with the integer column and MySQL cast it to `10`),
  `['active' => false]` instead of `['active' => 'f']` or `0`. A value the serializer converts to `NULL` throws a
  `ValueException`, e.g. `Invalid value 'f' for property "active" in the criteria of Repository::getBy(): the
  serializer of the field converts it to NULL`. A custom serializer whose PHP value is a scalar can implement the
  interface too.
* With a `Json` field, an array is now compared as a whole with the JSON encoding of the value, instead of becoming an
  `IN` list. See [the caveats on JSON equality](docs/repositories.md#criteria-keys-and-values).
* A custom serializer whose PHP value is an array can implement the marker interface
  `CCMBenchmark\Ting\Serializer\ArrayValueInterface` to get the same behaviour.
* `get()` with a single value (one primary key) follows the same rules: an object is now serialized, including by a
  serializer that comes from the type of the field (`datetime`, `uuid`...), and so is a scalar for a
  `ScalarValueInterface` serializer; other scalars and `null` are unchanged.
* Ting 3.15 already accepts both the 3.x and the 4.0 forms, and deprecates the 3.x ones: you can migrate on 3.x first,
  fix the deprecations, then upgrade to 4.0. 3.15 does not cover everything: it still sends the scalars of
  `Ip` and `Boolean` fields as is and doesn't reject empty criteria, without deprecation, so check these by hand.

Services Container Removed
--------------------------

`CCMBenchmark\Ting\Services`, `CCMBenchmark\Ting\ContainerInterface` and the optional `pimple/pimple` dependency have
been removed, as well as the unused `Repository::$services` property. Ting is now a plain library: every dependency is
passed to the constructors, and wiring belongs to the integration.

* **With Symfony**, use [ting_bundle](https://github.com/xavierleune/ting_bundle): it already declares every service in
  the Symfony container, nothing changes for you.
* **Without a framework**, build the objects yourself, or with any PSR-11 container. Shared objects are the
  `ConnectionPool`, `MetadataRepository`, `UnitOfWork`, `QueryFactory`, `SerializerFactory`, `Cache` and
  `RepositoryFactory`; hydrators and `CollectionFactory` are usually created on demand:

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
$repositoryFactory = new RepositoryFactory(
    $connectionPool,
    $metadataRepository,
    $queryFactory,
    new CollectionFactory($metadataRepository, $unitOfWork, $hydrator),
    $unitOfWork,
    $cache
);
$repository = $repositoryFactory->get(CityRepository::class);
```

`sample/src/TingServices.php` shows this wiring in a small class.

Cache: doctrine/cache Replaced by Symfony Cache Contracts
--------------------------------------------------------

`doctrine/cache` is abandoned. Ting now relies on [Symfony Cache Contracts](https://symfony.com/doc/current/components/cache.html#cache-contracts)
(`symfony/cache-contracts`), the "get with a callback" API, rather than PSR-6.

* Every argument typed `Doctrine\Common\Cache\Cache` now expects a `Symfony\Contracts\Cache\CacheInterface`:
  `Repository` and `RepositoryFactory` constructors, `QueryFactory(Interface)::getCached()` /
  `getCachedPrepared()`, `Query\Cached\Query::setCache()` and `Cache\Cache::setCache()`.
* Any Symfony cache pool works as is: `ArrayAdapter`, `RedisAdapter`, `MemcachedAdapter`, `ApcuAdapter`...
  `NullAdapter` replaces `VoidCache`. Pools come from `symfony/cache` (suggested, not required).
* `CCMBenchmark\Ting\Cache\CacheInterface` now extends Symfony's `CacheInterface` instead of Doctrine's.
  `CCMBenchmark\Ting\Cache\Cache` exposes `get()` and `delete()`: `fetch()`, `contains()`, `save()` and
  `getStats()` have been removed.

```php
// Before (3.x):
$cache = new \CCMBenchmark\Ting\Cache\Cache();
$cache->setCache(new \Doctrine\Common\Cache\MemcachedCache());
$value = $cache->fetch('key');
if ($value === false) {
    $value = compute();
    $cache->save('key', $value, 3600);
}

// After (4.0):
$cache = new \CCMBenchmark\Ting\Cache\Cache();
$cache->setCache(new \Symfony\Component\Cache\Adapter\MemcachedAdapter($memcachedClient));
$value = $cache->get('key', function (\Symfony\Contracts\Cache\ItemInterface $item) {
    $item->expiresAfter(3600);

    return compute();
});
```

* Cached queries keep the same API (`setTtl()`, `setCacheKey()`, `setForce()`), and `setForce(true)` still recomputes
  and stores the result. Concurrent misses on the same key are now protected against cache stampede by Symfony,
  between the processes of a same host with its default lock (see [Cache](docs/cache.md#how-it-works)).
  Two behaviours change with Symfony pools:
  * a TTL of `0` stores the item without expiration of its own (`expiresAfter(null)`), so the pool's default lifetime
    applies: a pool created with a default lifetime expires these items, unlike doctrine/cache;
  * keys containing a reserved character (`{}()/\@:`, e.g. `user:42`) are rejected with a
    `Psr\Cache\InvalidArgumentException` (a `Symfony\Component\Cache\Exception\InvalidArgumentException`): replace
    these characters, or hash the variable part of the key (`'user_' . md5($id)`).
* A negative TTL (`setTtl(-1)`) throws a `CCMBenchmark\Ting\Query\QueryException`: 3.x accepted it, and the result
  was never cached. Use `0` or a positive lifetime.
* If you extend cached queries: the protected `Query\Cached\Query::checkCache()` has been replaced by
  `queryThroughCache()`.
* `Query\Cached\Query::setVersion()` has been removed: the version has not been part of the cache key since cache
  keys became manual (`setCacheKey()`), so it had no effect. Put the version in the key instead:
  `$query->setCacheKey('user-list-v2')`.
* Logging (`CacheLoggerInterface`): a read is logged as `OPERATION_GET`, flagged as a miss when the value had to be
  computed, which also stores it. `OPERATION_STORE` and `OPERATION_EXIST` are no longer emitted.
* Values stored by doctrine/cache cannot be read by the new pools: expect a cold cache after upgrading.

Util\Debug
----------

* `Debug::export()` and `Debug::dump()` describe objects as arrays (`['__CLASS__' => ..., 'property' => ...]`)
  instead of cloned objects: typed properties can't hold the exported values. Property listeners are still left out,
  and uninitialized properties are skipped.
* A date is exported as `['__CLASS__' => ..., 'date' => '2026-01-02T03:04:05.000000+01:00', 'timezone' => ...]`,
  other internal objects with the properties `var_dump()` shows. Virtual hooked properties are skipped, and
  generators are not iterated (exported as `['__CLASS__' => 'Generator']`), nor the iterators over a generator
  (`IteratorIterator`...) and `NoRewindIterator`. An iterable with object keys (a `WeakMap`, held for instance by the
  unit of work) is exported as a list of `['key' => ..., 'value' => ...]` pairs.
* For a quick look at an entity, `var_dump($entity)` is enough: `NotifyProperty::__debugInfo()` hides the listeners.

Generator
---------

* The `Generator::getByCriteriaWithOrderAndLimit()` method has been removed.
* Use `Generator::getByCriteria()` instead, which now accepts optional `$order` and `$limit` parameters:

```php
// Before (3.x):
$generator->getByCriteriaWithOrderAndLimit(['status' => 'active'], $collectionFactory, false, ['name' => 'ASC'], 10);

// After (4.0):
$generator->getByCriteria(['status' => 'active'], $collectionFactory, false, ['name' => 'ASC'], 10);
```

* `Generator` is mostly used internally: from a repository, use `getBy($criteria, $forcePrimary, $order, $limit)`.
  Its signature only renames `$forceMaster` to `$forcePrimary` (see "Primary / Replica").
* `Generator` itself still works at the database level, as in 3.x: its criteria and its order take **column names**
  and **database values**, sent as given, and an order direction other than `ASC` / `DESC` is still silently left
  out. The rules of "Repository reads: property names and entity values" (property names, values converted by the
  serializers, `ValueException` on an invalid direction or an empty array) are applied before it, by `Metadata` and
  `Repository`. Code calling `Generator` directly keeps passing column names and database values; only the generated
  parameter names changed (see "Smaller Changes for Extensions").

Repository and RepositoryFactory Constructors
---------------------------------------------

The `SerializerFactoryInterface` argument, unused since 3.x, has been removed from both constructors:

* `Repository::__construct()` takes 6 arguments: if your repository overrides the constructor, drop the last
  `SerializerFactoryInterface $serializerFactory` parameter and don't pass it to `parent::__construct()`. The
  constructor is `@internal` to call, not to override: an override must keep its parameters, which
  `RepositoryFactory` passes (see "Smaller Changes for Extensions").
* `RepositoryFactory::__construct()` takes 6 arguments: drop the last `$serializerFactory` argument (with Symfony,
  ting_bundle does it for you).

Metadata
--------

* The `Metadata::getGetter()` method has been removed.
* The `Metadata::getSetter()` method has been removed.
* They returned the `getter` / `setter` option of the field (an option that already existed in 3.x), or
  `'get' . $fieldName` / `'set' . $fieldName` without it. If you need the method names, read the field configuration:

```php
$fields = array_column($metadata->getFields(), null, 'fieldName');
$getter = $fields['myField']['getter'] ?? 'get' . ucfirst('myField');
$setter = $fields['myField']['setter'] ?? 'set' . ucfirst('myField');
```

* Without these options, Ting itself doesn't call `get<Field>()` blindly: it reads and writes the property through
  Symfony PropertyAccess (`getMyField()`, `isMyField()`, a public property...), see
  [How Ting reads and writes properties](docs/entities.md#how-ting-reads-and-writes-properties).

UnitOfWork
----------

* The `UnitOfWork::generateUid()` method has been removed.
* The `UnitOfWork::generateUUID()` method has been removed (was deprecated in 3.x).
* If you need unique identifiers, use PHP's built-in functions like `uniqid()` or `spl_object_hash()` directly.
* **Mutable fields are written on every save.** A field is mutable when its PHP value can be modified in place
  (`$entity->getPublishedAt()->modify('+1 day')`, `$entity->getPayload()->tag = 'x'`): such a change calls no setter,
  so it is never notified. Ting 3 silently lost it; Ting 4 includes every mutable field in the `UPDATE` of each
  `pushSave()` of a managed entity, with its current value, together with the notified changes of the other fields.
  Consequence: saving a managed entity that has a mutable field always runs an `UPDATE`, even without change, and
  `UnitOfWork::isPropertyChanged()` is always `true` for such a field. By default, a field is mutable when its
  serializer is `Serializer\DateTime` (a `datetime` field whose property is not typed `\DateTimeImmutable`),
  `Serializer\Json` decoding objects (without the `assoc` unserialize option nor the `JSON_OBJECT_AS_ARRAY` flag), or a serializer of your
  own; set the new `mutable` field option to override it. See [entities](docs/entities.md#tracking-changes) for the
  trade-offs.
* A mutable field is only written when its value is known: read from the database, set through its setter, or
  inserted. An entity read partially (a query selecting some of its columns, a join selecting some columns of the
  joined entity) does not write the mutable fields it did not read, so their PHP default (`null`, or a value set by
  the constructor such as `new \DateTime()`) never overwrites the stored value. A value modified in place on such a
  field, without its setter, is not written.
* The other fields (scalars, `DateTimeImmutable`, enums, `json` decoded to arrays...) are updated when notified by
  `propertyChanged()`, as in 3.x: the same value given as old and new value, objects included, is not a change.
* Optional, recommended: type your date properties `\DateTimeImmutable`, and decode your JSON columns to arrays
  (`'serializer_options' => ['unserialize' => ['assoc' => true]]`). A `datetime` field then hydrates a
  `\DateTimeImmutable`, with the same `Y-m-d H:i:s` format, and is no longer written on every save. Only the
  `\DateTimeImmutable` type (nullable or not) does it: a property typed `\DateTimeInterface`, `\DateTime` or a union,
  or not typed, keeps hydrating a `\DateTime`, as in 3.x (a `\DateTimeInterface` property can hold a `\DateTime`, which
  `Serializer\DateTimeImmutable` cannot write).
* A primary key that is mutable (e.g. a `\DateTime`) and modified in place still targets its row: its database value
  is kept when the entity becomes managed, and refreshed by each `UPDATE`.

Serializers
-----------

* **`Serializer\DateTimeImmutable` (`datetime_immutable` fields) writes `Y-m-d H:i:s` by default**, as
  `Serializer\DateTime`, instead of `\DateTimeInterface::ATOM` (`2024-01-31T10:00:00+01:00`). In 3.x, its ATOM default
  was also the only format accepted on read, so a `datetime_immutable` field could not read a MySQL `DATETIME` or a
  PostgreSQL `timestamp` (`2024-01-31 10:00:00`) without a `format` option. Values already stored as ATOM are still
  read (see below). To keep writing ATOM, set the format:
  `'serializer_options' => ['serialize' => ['format' => \DateTimeInterface::ATOM]]`.
* `Serializer\DateTime` and `Serializer\DateTimeImmutable` read a value with their `format` first and, when it does
  not match, with the formats databases return, whatever the format: `Y-m-d` (a `DATE`, read at midnight),
  `Y-m-d H:i:s` with optional fractional seconds (`.123456`) and optional UTC offset (`+01`, `+0100`, `+01:00`, `Z`)
  — a MySQL `DATETIME`, a PostgreSQL `timestamp` or `timestamptz` (`2024-01-31 10:00:00+01`) — and ATOM / RFC 3339
  (`2024-01-31T10:00:00+01:00`, with optional fractional seconds). Anything else throws a
  `Serializer\RuntimeException`, as an unparsable value did in 3.x: the PHP date parser is not used as a fallback, it
  would read `now`, `tomorrow` or `+1 day` as dates, and `05/06/2024` as May 6th.
* `Serializer\Json` honours the `JSON_OBJECT_AS_ARRAY` flag of its unserialize `options`, as `json_decode()` does:
  such a field now decodes JSON objects to **arrays**. 3.13 forced objects (`\stdClass`) whatever the flag, as its
  `assoc` option defaulted to `false` instead of `null`. Remove the flag to keep objects, or set `assoc` to `false`
  explicitly. A field decoded to arrays is not mutable by default (see "UnitOfWork").
* `unSerializeUseFormat` set to `false` is the explicit opt-in to the permissive mode: it skips the format and reads
  with the PHP date parser (`new \DateTime($value)`), as in 3.x, except that an empty string is now rejected (it was
  read as the current time).

Query and Driver Methods
-------------------------

### Removed getInsertId() Methods

All deprecated `getInsertId()` methods have been removed. Use `getInsertedId()` instead:

**QueryInterface and Query class:**
```php
// Before (3.x):
$query->getInsertId();

// After (4.0):
$query->getInsertedId();
```

**DriverInterface, Mysqli\Driver, and Pgsql\Driver:**
```php
// Before (3.x):
$driver->getInsertId();

// After (4.0):
$driver->getInsertedId();
```

**PostgreSQL sequences:**
```php
// Before (3.x):
$pgsqlDriver->getInsertIdForSequence('my_sequence');

// After (4.0):
$pgsqlDriver->getInsertedIdForSequence('my_sequence');
```

PostgreSQL Driver
-----------------

* The PostgreSQL driver now uses native PHP 8.1+ `PgSql\Connection` and `PgSql\Result` classes instead of resources.
* This is an **internal change** and should not affect most user code.
* If you were using reflection or type checking on internal driver properties, update your code:

```php
// Before (3.x):
// $connection was a resource

// After (4.0):
// $connection is a \PgSql\Connection instance
```

DriverInterface - New Required Methods
---------------------------------------

If you have implemented custom drivers, you must implement these new methods:

```php
interface DriverInterface
{
    // New methods in 4.0:
    public function ping(): bool;
    public function setTimezone(?string $timezone = null): void;
}
```

**Implementation example:**
```php
public function ping(): bool
{
    // Check if connection is alive
    // Return true if connected, false otherwise
}

public function setTimezone(?string $timezone = null): void
{
    // Set the database connection timezone
    // e.g., for MySQL: SET time_zone = '+00:00'
}
```

`connect()` is now typed: `connect(string $hostname, ?string $username, ?string $password, int $port): static`.
User and password stay optional in the connection configuration: `ConnectionPool` passes `null` when they are missing.

QueryInterface - New Required Method
------------------------------------

If you have implemented custom queries, you must implement:

```php
interface QueryInterface
{
    // New method in 4.0 (already available on Query in 3.x, as selectMaster() and, since 3.14, selectPrimary()):
    public function selectPrimary(bool $usePrimary): static;
}
```

StatementInterface - New Required Method
----------------------------------------

If you have implemented custom statements (for a custom driver), you must implement:

```php
interface StatementInterface
{
    // New method in 4.0: true once the session the statement was prepared in is gone (reconnection, connection
    // reset); prepared queries then prepare their statement again
    public function isStale(): bool;
}
```

A statement whose connection cannot be replaced can always return `false`.

CollectionInterface and HydratorInterface
-----------------------------------------

If you have implemented custom collections or hydrators, `count()` and `getIterator()` are now declared with their
types:

```php
public function count(): int;
public function getIterator(): \Generator;
```

Constructors Removed from Interfaces
------------------------------------

`ConnectionPoolInterface`, `StatementInterface` and `QueryInterface` no longer declare a constructor. Implementations
are free to define their own.

ConnectionPoolInterface - New Method
-------------------------------------

If you have implemented custom connection pools, you must implement:

```php
interface ConnectionPoolInterface
{
    // New method in 4.0:
    public function setDatabaseOptions(array $options): void;
}
```

Type Hints and Strict Types
----------------------------

**Every method of every Ting interface, and every public or protected method of every non-final class, now declares
a native return type and native parameter types** (3.13 declared almost none; the few parameters left untyped are
listed in "Native types everywhere" below). The signatures quoted in this guide are examples, not an exhaustive list:
an implementation or an override written against 3.x must be compared with the 4.0 declaration of **each** method it
implements or overrides, not only with the methods listed here.

* **Implementing an interface**: every method must declare a return type compatible with the interface (the same
  type, or a narrower one), otherwise PHP raises a fatal error when loading the class. Its parameters may stay
  untyped (an untyped parameter is wider), or take the type of the interface, but they must keep the default values
  the interface declares: a parameter that became optional in 4.0 and is still required by the implementation is a
  fatal error too. Two interface parameters became optional:
  * `Logger\DriverLoggerInterface::startStatementExecute(string $statement, array $params = [])`: a 3.x logger
    declaring `startStatementExecute($statement, $params)` (as the `DriverLogger` of ting_bundle 3.x did; ting_bundle
    4.0 is already compatible) must write `$params = []`;
  * `Driver\DriverInterface::escapeField(mixed $field = null)`: a 3.x driver declaring `escapeField($field)` must write
    `$field = null`.
* **Extending a non-final class** (a repository, a hydrator, a driver, a serializer...): an override must declare a
  compatible return type, with the same fatal error otherwise; a parameter type it declares must be the type of the
  parent, or a wider one. It must also keep the optional parameters of the parent, and the parameters the parent
  added: `escapeField(mixed $field = null)` and `setTimezone(?string $timezone = null)` of the Mysqli, Pgsql and
  SphinxQL drivers (`$timezone` was required in 3.x), `Query\Generator::getByCriteria()` (new optional `$order` and
  `$limit`, see "Generator") and the protected `Driver\Pgsql\Driver::setCollectionWithResult()` (see "Smaller Changes
  for Extensions").

Start with your repositories, which every application has:

* `MetadataInitializer::initMetadata()` now returns `Metadata`: **every repository** must declare it.
  ```php
  // Before (3.x):
  public static function initMetadata(SerializerFactoryInterface $serializerFactory, array $options = [])

  // After (4.0):
  public static function initMetadata(SerializerFactoryInterface $serializerFactory, array $options = []): Metadata
  ```
* A repository overriding a method of `Repository` with its 3.x signature does not load either, e.g.
  `public function getAll($forceMaster = false)` (no return type): write
  `public function getAll(bool $forcePrimary = false): CollectionInterface`. The methods of `Repository` in 4.0:
  ```php
  public function __construct(ConnectionPool $connectionPool, MetadataRepository $metadataRepository, QueryFactory $queryFactory, CollectionFactory $collectionFactory, \Symfony\Contracts\Cache\CacheInterface $cache, UnitOfWork $unitOfWork);
  public function getCollection(?HydratorInterface $hydrator = null): Collection;
  public function getQuery(string $sql): Query;
  public function getPreparedQuery(string $sql): PreparedQuery;
  public function getCachedQuery(string $sql): Cached\Query;
  public function getCachedPreparedQuery(string $sql): Cached\PreparedQuery;
  public function getQueryBuilder(string $type): \Aura\SqlQuery\QueryInterface;
  public function get(mixed $primariesKeyValue, bool $forcePrimary = false): ?object;
  public function getAll(bool $forcePrimary = false): CollectionInterface;
  public function getBy(array $criteria, bool $forcePrimary = false, array $order = [], int $limit = 0): CollectionInterface;
  public function getOneBy(array $criteria, bool $forcePrimary = false): ?object;
  public function save(NotifyPropertyInterface $entity): void;
  public function delete(NotifyPropertyInterface $entity): void;
  public function startTransaction(): void;
  public function rollback(): void;
  public function commit(): void;
  public function ping(): bool;
  public function pingPrimary(): bool;
  public function getMetadata(): Metadata;
  public function reset(): void;
  ```
  An override of `getCollection()` must return `Collection` (or a subclass), not `CollectionInterface`.

The other extension points you are the most likely to implement:

* Loggers (ting_bundle's `DriverLogger` and `CacheLogger` included):
  ```php
  // DriverLoggerInterface
  public function addConnection(string $name, string $connection, array $connectionConfig): void;
  public function startQuery(string $sql, array $params, string $connection, string $database): void;
  public function startPrepare(string $sql, string $connection, string $database): void;
  public function startStatementExecute(string $statement, array $params = []): void;
  public function stopQuery(): void;
  public function stopPrepare(string $statement): void;
  public function stopStatementExecute(string $statement): void;

  // CacheLoggerInterface
  public function startOperation(string $operation, array|string $keys): void;
  public function stopOperation(bool $miss = false): void;
  ```
* Entities and listeners: `NotifyPropertyInterface::addPropertyListener(PropertyListenerInterface $listener): void`,
  `PropertyListenerInterface::propertyChanged(NotifyPropertyInterface $entity, string $propertyName, mixed $oldValue,
  mixed $newValue): void`. The `NotifyProperty` trait declares `protected array $listeners = []`: an entity using the
  trait must not redeclare `$listeners` without this type.
* Serializers: `SerializeInterface::serialize(mixed $toSerialize, array $options = []): mixed` and
  `UnserializeInterface::unserialize(mixed $serialized, array $options = []): mixed`. A serializer implementing them
  directly **must now declare a return type** (`mixed`, or any narrower type such as `?string`), or PHP raises a fatal
  error when loading it; its parameters may stay untyped. The built-in serializers declare native return types too: a
  class **extending** one of them must declare a compatible return type on the methods it overrides (see
  "Smaller changes for extensions").

<details>
<summary>Every method of every Ting interface in 4.0 (generated by reflection)</summary>

Every one of these methods is typed in 4.0; in 3.13, only `ResetInterface::reset()` had a return type. Class names are
short: the classes of Ting, `Generator` is PHP's.

```php
// Cache\CacheInterface
public function setLogger(CacheLoggerInterface $logger): void;

// ConnectionPoolInterface
public function setConfig(array $config): void;
public function primary(string $name, string $database): DriverInterface;
public function replica(string $name, string $database): DriverInterface;
public function closeAll(): void;
public function setDatabaseOptions(array $options): void;

// Driver\DriverInterface
public function connect(string $hostname, ?string $username, ?string $password, int $port): static;
public function close(): static;
public function setName(string $name): static;
public function setCharset(string $charset): void;
public function execute(string $sql, array $params = [], ?CollectionInterface $collection = null): mixed;
public function prepare(string $sql): StatementInterface;
public function setDatabase(string $database): static;
public function ifIsError(callable $callback): static;
public function ifIsNotConnected(callable $callback): static;
public function escapeField(mixed $field = null): string;
public function startTransaction(): void;
public function rollback(): void;
public function commit(): void;
public function getInsertedId(): int;
public function getAffectedRows(): string|int;
public function setLogger(?DriverLoggerInterface $logger = null): static;
public static function getConnectionKey(array $connectionConfig, string $database): string;
public function closeStatement(string $statement): void;
public function ping(): bool;
public function setTimezone(?string $timezone = null): void;

// Driver\DefaultValuesInsertDriverInterface (new in 4.0, optional for a custom driver)
public function getDefaultValuesInsertClause(): string;

// Driver\ResultInterface
public function setConnectionName(string $connectionName): static;
public function setDatabase(string $database): static;
public function setResult(mixed $result): static;
public function getConnectionName(): ?string;
public function getDatabase(): ?string;
public function getNumRows(): mixed;

// Driver\SequenceAwareDriverInterface (new in 4.0, see "Smaller Changes for Extensions")
public function getInsertedIdForSequence(string $sequenceName): int;

// Driver\StatementInterface
public function execute(array $params, ?CollectionInterface $collection = null): CollectionInterface|bool;
public function setLogger(?DriverLoggerInterface $logger = null): void;
public function isStale(): bool;

// Entity\NotifyPropertyInterface
public function addPropertyListener(PropertyListenerInterface $listener): void;

// Entity\PropertyListenerInterface
public function propertyChanged(NotifyPropertyInterface $entity, string $propertyName, mixed $oldValue, mixed $newValue): void;

// Logger\CacheLoggerInterface
public function startOperation(string $operation, array|string $keys): void;
public function stopOperation(bool $miss = false): void;

// Logger\DriverLoggerInterface
public function addConnection(string $name, string $connection, array $connectionConfig): void;
public function startQuery(string $sql, array $params, string $connection, string $database): void;
public function startPrepare(string $sql, string $connection, string $database): void;
public function startStatementExecute(string $statement, array $params = []): void;
public function stopQuery(): void;
public function stopPrepare(string $statement): void;
public function stopStatementExecute(string $statement): void;

// Query\QueryFactoryInterface (CacheInterface: Symfony\Contracts\Cache\CacheInterface)
public function get(string $sql, Connection $connection, ?CollectionFactoryInterface $collectionFactory = null): QueryInterface;
public function getPrepared(string $sql, Connection $connection, ?CollectionFactoryInterface $collectionFactory = null): PreparedQuery;
public function getCached(string $sql, Connection $connection, CacheInterface $cache, ?CollectionFactoryInterface $collectionFactory = null): Cached\Query;
public function getCachedPrepared(string $sql, Connection $connection, CacheInterface $cache, ?CollectionFactoryInterface $collectionFactory = null): Cached\PreparedQuery;

// Query\QueryInterface
public function query(?CollectionInterface $collection = null): CollectionInterface;
public function execute(): mixed;
public function setParams(array $params): static;
public function selectPrimary(bool $usePrimary): static;
public function getInsertedId(): int;
public function getAffectedRows(): string|int;

// Repository\CollectionFactoryInterface
public function get(): Collection;

// Repository\CollectionInterface
public function set(ResultInterface $result): void;
public function first(): mixed;
public function setFromCache(bool $value): void;
public function isFromCache(): bool;
public function toCache(): array;
public function fromCache(array $result): void;
public function getIterator(): Generator;
public function count(): int;

// Repository\HydratorInterface
public function setMetadataRepository(MetadataRepository $metadataRepository): void;
public function setUnitOfWork(UnitOfWork $unitOfWork): void;
public function setResult(ResultInterface $result): static;
public function count(): int;
public function getIterator(): Generator;

// Repository\MetadataInitializer
public static function initMetadata(SerializerFactoryInterface $serializerFactory, array $options = []): Metadata;

// ResetInterface
public function reset(): void;

// Serializer\SerializeInterface
public function serialize(mixed $toSerialize, array $options = []): mixed;

// Serializer\SerializerFactoryInterface
public function get(string $serializerName): SerializerInterface;

// Serializer\UnserializeInterface
public function unserialize(mixed $serialized, array $options = []): mixed;
```

</details>

### Native types everywhere

Every return value of Ting now has a native type, and so do the parameters and properties, with a few exceptions that
PHP cannot or need not type:

* the `$toSerialize` / `$serialized` parameter of the `serialize()` / `unserialize()` methods of the built-in
  serializers, and the `$result` parameter of `setResult()` in `Driver\Mysqli\Result`, `Driver\Pgsql\Result` and
  `Driver\CacheResult`: untyped, which is the same as the `mixed` of their interface;
* `Repository\HydratorRelational::identityMap($enable)`, untyped (`Hydrator::identityMap()` takes a `bool`);
* the callables of `Repository\HydratorAggregator` (`$callableForId`, `$callableForData`, `$callableFinalizeAggregate`):
  PHP has no `callable` property type.

PHPStan (level 8, with a type coverage check) enforces it. The handles of the native extensions (`mysqli`,
`mysqli_stmt`, `mysqli_result`, `PgSql\Connection`, `PgSql\Result`) are typed `object`, with the precise class in the
PHPDoc: the `PgSql` classes are final and only built by a server, and the `mysqli` properties cannot be read on a
stand-in, so tests keep passing fakes.

What breaks depends on the kind of type added. The methods listed below are examples, the rules apply to every
method:

* **Return types on interfaces**: an implementation without a return type is a fatal error at load time. Every
  interface method has one (see the list above), for instance:
  - `Repository\MetadataInitializer::initMetadata(): Metadata`
  - `Serializer\SerializeInterface::serialize(): mixed`, `Serializer\UnserializeInterface::unserialize(): mixed`
  - `Repository\CollectionFactoryInterface::get(): Collection`
  - `Repository\CollectionInterface::first(): mixed`
* **Parameter types on interfaces**: implementations with an untyped parameter keep working (an untyped parameter is
  wider); only callers passing another type are affected.
  - `Driver\ResultInterface::setResult(mixed $result)`
  - `Query\QueryFactoryInterface::get()`, `getPrepared()`, `getCached()`, `getCachedPrepared()`: `string $sql`
  - `Repository\CollectionInterface::setFromCache(bool $value)`
* **Return types on non-final classes**: a subclass overriding the method without a compatible return type is a fatal
  error at load time. Every public and protected method has one, for instance:
  - every method of `Repository\Repository` (see above): `get()` and `getOneBy()` return `?object`, `getAll()` and
    `getBy()` a `CollectionInterface`
  - `Repository\Metadata::getRepository(): ?string` and `createEntity(): object`

  The entity template of `Repository` and `Metadata` is bound to `object` (`@template T of object`).
* **Property types on non-final classes**: a subclass redeclaring the property must use the same type (see
  "Smaller Changes for Extensions" for the list).
* **Parameter types on classes**: an override without type keeps working. Values that were cast before are no longer:
  in non-strict mode PHP still coerces scalars (`setTtl('10')`), but `null` or an object is a `TypeError`. For
  instance:
  - `Query\Cached\Query::setTtl(int $ttl)` and `setForce(bool $value)`
  - `Repository\Collection::setFromCache(bool $value)`
  - `Repository\Hydrator::identityMap(bool $enable)`, `unserializeAliasWith(string $alias, ...)`,
    `mapAliasTo(string $from, string $to, string $column)`, `mapObjectTo(string $from, string $to, string $column)`,
    `objectDatabaseIs(string $object, string $database)`, `objectSchemaIs(string $object, string $schema)`, and the
    protected `hydrateColumns(string $connectionName, string $database, array $columns)`
  - `Repository\Repository::getAll(bool $forcePrimary = false)`
  - `Repository\Metadata::getAll(..., bool $forcePrimary = false)`
  - `Repository\MetadataCacheGenerator::__construct(string $cacheDir, ?string $filename = null)`
  - `MetadataRepository::findMetadataForEntity(object|string $entity, ...)` and
    `addMetadata(string $repositoryClass, ...)`
  - `Query\QueryFactory` (as its interface) and `Query\Query::__construct(string $sql, ...)`
  - `Query\Generator::getDriver(bool $forcePrimary)` (protected)
  - `Driver\Mysqli\Driver::__construct(?object $connection = null, ?mysqli_driver $driver = null)`: the second
    argument must be a `mysqli_driver`
  - `Driver\Mysqli\Driver::setCollectionWithResult(object $resultData, ...)` (protected),
    `Driver\Mysqli\Statement::__construct(object $driverStatement, ...)` and `setCollectionWithResult(object $resultData,
    ...)`, `Driver\Pgsql\Statement::__construct(string $statementName, ...)` and `setConnection(object $connection)`
    (all internal)
  - The `setCollectionWithResult()` methods of the Pgsql driver changed more than their types, see "Smaller Changes
    for Extensions"

Example of updated method signatures:
```php
// Before (3.x):
public function setConfig($config)
{
    // ...
}

// After (4.0):
public function setConfig(array $config): void
{
    // ...
}
```

### Generics and array shapes

For static analysis (PHPStan, Psalm), the PHPDoc of Ting is fully typed. Code analysed against Ting may get new
reports, and the following PHPDoc types changed:

* `Repository<T>`: `get()` and `getOneBy()` return `?T`, `getAll()` and `getBy()` a `CollectionInterface<T>`,
  `getMetadata()` a `Metadata<T>`. Criteria are `array<string, mixed>` (property name => value), orders
  `array<string, string>` (property name => direction).
* The type parameter of `CollectionInterface<T>` and `HydratorInterface<T>` (both covariant) is the type of their
  items: a collection iterates `T`, a hydrator yields `T`. `HydratorArray` yields `array<string, mixed>` and is no
  longer generic. The items of `Hydrator`, `HydratorSingleObject`, `HydratorAggregator` and `HydratorRelational`
  depend on the query: their type parameter is the type documented by the caller.
* `QueryInterface<T>` and the query classes are typed by the items of the collections of their collection factory;
  `query($collection)` returns a `CollectionInterface<U>` for a `CollectionInterface<U>` given.
* `Driver\ResultInterface` is no longer generic: it iterates the rows of the driver, typed by the aliases `Column`
  and `Row` (`list<Column>`) of the interface.
* Array shapes are named by `@phpstan-type` aliases you can import with `@phpstan-import-type`: `ConnectionConfig`,
  `ServerConfig` and `DatabaseOptions` (`ConnectionPoolInterface`), `ConnectionParameters` (`Driver\DriverInterface`),
  `SerializerOptions` (`Serializer\SerializeInterface`), `Field` (`Repository\Metadata`, which now documents the
  `sequenceName` option) and `CachedCollection` (`Repository\CollectionInterface`).

Hydrators and Transactions
--------------------------

* `HydratorRelational` throws a `CCMBenchmark\Ting\Exceptions\HydratorException` when its relations form a cycle (a
  bidirectional relation, or an alias given to itself); 3.x partially ignored them. Declare one direction and set the
  back reference in the setter, see [HydratorRelational](docs/hydrators.md#hydratorrelational).
* When several metadata share a table on a connection (one per database or schema, e.g. `City` in `world` and
  `CityArchive` in `world_archive`, both on `T_CITY_CIT`) and none matches the database and schema of the result, the
  hydrator no longer takes the first metadata registered (an order that depends on the files found or on the service
  configuration). It takes the only metadata of the same database, else the only one of the same schema, and otherwise
  throws a `CCMBenchmark\Ting\Exceptions\HydratorException` listing the candidates: name the database or the schema
  of the alias with `Hydrator::objectDatabaseIs()` / `objectSchemaIs()`, see
  [Tables from another database](docs/hydrators.md#tables-from-another-database). A table with a single metadata is
  still found whatever the database or the schema read, and schemas are now compared case-insensitively when no
  exact match (PostgreSQL results report lowercased schemas).
* **Two repositories mapping the same table of the same database and schema** (a full entity and a lighter
  projection, e.g. `UserRepository` / `User` and `UserLightRepository` / `UserLight`): in 3.x, the last repository
  built (or the last metadata registered) hydrated the table for every read, so `UserRepository::get(1)` could return
  a `UserLight`. The reads of a repository (`get()`, `getBy()`, `getOneBy()`, `getAll()`, its `getQuery()`... and
  `getCollection()`) now hydrate the tables it maps with its own metadata. A hydrator used outside a repository
  throws a `HydratorException` naming the candidates instead of taking the last one: call
  `Hydrator::preferRepository(UserRepository::class)`, see
  [Several repositories on the same table](docs/hydrators.md#several-repositories-on-the-same-table).
* The array given to a `RelationMany` method is still indexed by an internal reference, but its format changed
  (`'book-1-'` in 3.x): use `array_values()` rather than relying on these keys.
* `startTransaction()`, `commit()` and `rollback()` throw a `CCMBenchmark\Ting\Exceptions\TransactionException` when
  the database refuses them (3.x did not check their result), including the `COMMIT` of a PostgreSQL transaction
  aborted by a failed query. A failed `commit()` leaves no transaction open: calling `rollback()` after it throws, see
  [Transactions](docs/repositories.md#transactions).

Queries, Entities and the Unit of Work
--------------------------------------

* **A missing placeholder value throws** a `CCMBenchmark\Ting\Driver\QueryException`
  (`Value has not been set for param name`) with every driver, prepared query or not. In 3.x, only the non-prepared
  Mysqli queries threw: PostgreSQL queries (prepared or not) and Mysqli prepared queries silently sent `NULL` for the
  missing value. A value explicitly set to `null` is still sent as `NULL`. Check the queries whose parameters are
  built conditionally.
* **`UnitOfWork::isManaged()` is `false` for a new entity until its `INSERT` has run.** In 3.x, `pushSave()` made a
  new entity managed at once, before `process()`; an `INSERT` that failed then left it managed, and saving it again ran
  nothing. Use `isNew()` or `shouldBePersisted()` to know whether an entity is queued. Likewise, `pushDelete()` no
  longer makes an entity managed: an entity never inserted (queued for its `INSERT`, or not managed and without primary
  key) is only removed from the queue, without query, and an entity not managed but with its primary key is deleted by
  that key and stays not managed. See [the unit of work](docs/unit-of-work.md#saving-and-deleting).
* **A change made to an entity while it is detached is lost when it is managed again.** In 3.x, `detach()` left the
  unit of work listening to the entity: a setter called while detached was recorded, and written by the first save
  after `manage()`. In 4.0, the unit of work ignores the changes of an entity it does not manage: after `detach()`,
  `$city->setName('Lyon')`, `manage($city)` then `pushSave($city)`, the `UPDATE` does not include the new name (and
  nothing runs when it is the only change). Make the change after `manage()`.
* **Saving an entity not managed whose `autoincrement` primary key is set updates its row.** In 3.x, such an entity (a
  clone of a managed entity, a detached entity, one read from a cache or a session, one built with its id) was
  inserted as a new row: its id was left out of the `INSERT`, then replaced by the generated one. It is now an existing
  row: it is updated by its primary key with every readable mapped property (its changes are not tracked), then
  managed, and `isNew()` is `false` for it. To duplicate a row, set the `autoincrement` property of the copy to `null`
  (or unset it) before saving it. Clone an entity read partially only once read whole: its copy writes the columns not
  read with their current value (`NULL`). An id missing from the database updates no row, without error. Entities
  without `autoincrement` key are still inserted, and an entity deleted by `process()` is inserted again by its next
  save. See [Saving an entity not managed](docs/unit-of-work.md#saving-an-entity-not-managed).
* **Mysqli non-prepared queries turn the escaped colon `\:` into `:`**, as prepared queries and the PostgreSQL driver
  already did. 3.x sent the backslash to MySQL, which dropped it inside a string literal (except under the sql_mode
  `NO_BACKSLASH_ESCAPES`, where the backslash was kept) and received it as is elsewhere. A query that relied on the
  backslash reaching MySQL must double it.
* **SphinxQL quotes values as Mysqli does**: `null` is sent as `NULL` and booleans as `0` / `1`. 3.x sent `null` and
  `false` as `''` (with a "Passing null to parameter" deprecation) and `true` as `'1'`.
* **`NotifyProperty::__serialize()` and `__debugInfo()` return mangled keys** (`"\0*\0name"` for a protected property,
  `"\0App\Entity\City\0name"` for a private one, as `get_mangled_object_vars()`), so that the private properties of a
  parent or child class are no longer lost. `serialize()` / `unserialize()` and `var_dump()` are not affected, and
  payloads are compatible both ways: entities serialized by 3.x are read by 4.0, and payloads written by 4.0 are still
  read by 3.13. Only code calling these methods directly and reading the keys must handle the mangled names.
  An entity defining `__sleep()` is honoured again (ignored since 3.12): only the properties it lists are serialized,
  as with `serialize()` (a private property of a parent class needs its mangled name, `"\0Parent\0name"`).

Smaller Changes for Extensions
------------------------------

These changes only matter if you extend Ting classes or rely on their internals.

Elements marked `@internal` (methods such as `MetadataRepository::findMetadataForEntity()` /
`findMetadataForRepository()`, classes, traits) are reserved for Ting and for
[ting_bundle](https://github.com/xavierleune/ting_bundle), which is released along with Ting and may use them. They are
not covered by the backward compatibility promise for any other code: they may change in a minor version.

Two `@internal` members are nevertheless supported for extension, as in 3.x, and only change in a major version:

* `Repository::__construct()`: it is internal to **call** (repositories are built by `RepositoryFactory`, which passes
  these 6 arguments), but a repository may **override** it, with the same parameters passed to
  `parent::__construct()` (see "Repository and RepositoryFactory Constructors");
* the protected `Hydrator::hydrateColumns()`: a hydrator extending `Hydrator` may call it to hydrate a row, as the
  built-in hydrators do (see [Writing a hydrator](docs/hydrators.md#writing-a-hydrator)).

Elements that became `@internal` in 4.0:

* `Metadata::getByCriteriaWithOrderAndLimit()`, like the other query methods of `Metadata` (`getByCriteria()`,
  `getAll()`...) already were in 3.x: call `Repository::getBy($criteria, $forcePrimary, $order, $limit)`.

* A custom driver supporting sequences must implement `CCMBenchmark\Ting\Driver\SequenceAwareDriverInterface`
  (`getInsertedIdForSequence(string $sequenceName): int`): having a method of that name is no longer enough for the
  `sequenceName` option of an autoincrement field.

* An INSERT without any value (an entity with only an autoincrement field, or with no field initialized) is written
  `INSERT INTO t () VALUES ()` (MySQL syntax) unless the driver implements
  `CCMBenchmark\Ting\Driver\DefaultValuesInsertDriverInterface` (`getDefaultValuesInsertClause(): string`), as the
  PostgreSQL driver does (`INSERT INTO t DEFAULT VALUES`). A custom driver for a database refusing the MySQL syntax
  should implement it.

* The Mysqli and Pgsql `escapeField()` double a backtick (Mysqli) or a double quote (Pgsql) in the name, as the
  databases expect in a quoted identifier (``a`b`` becomes `` `a``b` ``, `a"b` becomes `"a""b"`): 3.x left it as is,
  which ended the identifier early. They also escape a colon in the name (`` `x \:y` ``, `"x \:y"`), so that the
  drivers do not take it for a placeholder; they remove the escape, as in any SQL they are given. Their result is
  meant for SQL run through a Ting driver, not through mysqli / pgsql directly. Code escaping such a name itself
  before calling `escapeField()` must stop doing it.

* Built-in serializers now declare native return types on `serialize()` / `unserialize()`: `Serializer\DateTime`,
  `DateTimeImmutable`, `DateTimeZone`, `Json`, `Ip`, `Uuid`, `BackedEnum` (`unserialize()`),
  `Driver\Mysqli\Serializer\Boolean` and `Driver\Pgsql\Serializer\Boolean`. A subclass overriding one of these methods
  without a compatible return type is a fatal error: copy the return type of the parent method (e.g.
  `public function serialize($toSerialize, array $options = []): ?string` for `DateTime`).
* Protected properties renamed or removed:

  | Class                          | 3.13                                 | 4.0                                       |
  |--------------------------------|--------------------------------------|-------------------------------------------|
  | `ConnectionPool`               | `$connectionSlaves`                  | `$connectionReplicas` (already in 3.14)   |
  | `Query\Query`                  | `$selectMaster`                      | `$selectPrimary`                          |
  | `Repository\RepositoryFactory` | `$collection`, `$serializerFactory`  | removed                                   |
  | `Repository\Repository`        | `$services`                          | removed                                   |
  | `Repository\HydratorArray`     | `$metadataRepository`, `$unitOfWork` | removed (they were unused)                |
  | `Query\Cached\Query`           | `$version`                           | removed (see `setVersion()` above)        |
  | `Driver\Pgsql\Statement`       | `$queryType`                         | removed                                   |

  Every remaining property is now typed: a subclass redeclaring one must use the same type. The properties typed last
  (untyped until 4.0.0-rc.1 included):

  | Class                          | Properties                                                                          |
  |--------------------------------|-------------------------------------------------------------------------------------|
  | `ConnectionPool`               | `array $connectionConfig`, `$databaseOptions`, `$connectionReplicas`, `$connections` |
  | `MetadataRepository`           | `array $metadataList`, `array $entityToRepository`                                   |
  | `Driver\Mysqli\Driver`         | `mysqli_driver $driver`, `object $connection` (set by the constructor, never `null`) |
  | `Driver\Mysqli\Result`         | `?object $result`, `?array $iteratorCurrent`                                         |
  | `Driver\Mysqli\Statement`      | `object $driverStatement`                                                            |
  | `Driver\Pgsql\Driver`          | `?object $connection`, `?object $result`, `string $dsn` (`''` until `connect()`)      |
  | `Driver\Pgsql\Result`          | `?object $result`, `?array $iteratorCurrent`                                         |
  | `Driver\Pgsql\Statement`       | `?object $connection`, `string $statementName`                                       |
  | `Query\Query`                  | `string $sql`                                                                        |
  | `Repository\Metadata`          | `?string $repository`                                                                |
  | `Repository\MetadataCacheGenerator` | `string $cacheDir`                                                              |
  | `Repository\Repository`        | `Metadata $metadata`, `Connection $connection` (no longer `null` before the constructor sets them) |
* The protected / internal `setCollectionWithResult()` of the Pgsql driver changed their parameters, beyond their
  types:

  | Method                                         | 3.13                                                         | 4.0                                                                                  |
  |------------------------------------------------|--------------------------------------------------------------|--------------------------------------------------------------------------------------|
  | `Driver\Pgsql\Driver::setCollectionWithResult()` (protected) | `($sql, CollectionInterface $collection)`, reading `$this->result` | `(object $resultResource, string $sql, CollectionInterface $collection)`: the result is passed first |
  | `Driver\Pgsql\Statement::setCollectionWithResult()` | `($resultResource, ?CollectionInterface $collection = null)` | `(object $resultResource, CollectionInterface $collection): bool`: the collection is required |

  A subclass calling or overriding them must follow.
* `Connection::__construct()` (internal, called by the repositories) types `$name` and `$database` as `string` and
  throws a `RuntimeException` when one of them is empty (3.x only rejected `null`).
* The protected `Metadata::getColumnsFromCriteria()` and `Metadata::getPrimariesKeyValuesAsArray()` now validate the
  keys (property names only, `ValueException` otherwise) and return the converted database values, not the values
  given. An override must do the same, see "Repository reads: property names and entity values".
* The parameters of the generated queries (`get()`, `getBy()`, `getOneBy()`, and the INSERT, UPDATE and DELETE of the
  `UnitOfWork`) are no longer named after the raw column name, which could hold characters invalid in a parameter
  name or collide with another parameter: `:<role><position>_<column>`, the role being `v` for a written value and `w`
  for a WHERE condition, the position that of the column in its role (from 1), and the column name with every
  character but `[a-zA-Z0-9_]` replaced by `_`. An IN list suffixes it with `__<n>`.

  | Query                                   | 3.x                                        | 4.0                                                 |
  |-----------------------------------------|--------------------------------------------|-----------------------------------------------------|
  | `getBy(['id' => 3, 'name' => ['a']])`   | `id = :#id AND name IN (:name__1)`         | `id = :w1_id AND name IN (:w2_name__1)`             |
  | INSERT                                  | `VALUES (:id, :name)`                      | `VALUES (:v1_id, :v2_name)`                         |
  | UPDATE                                  | `SET name = :name WHERE id = :#id`         | `SET name = :v1_name WHERE id = :w1_id`             |

  This is visible in the query logs, and matters to code inspecting the parameters of a generated `Query` /
  `PreparedQuery` (or of `Query\Generator`, whose protected `generateConditionAndParams()` returns the new names).
