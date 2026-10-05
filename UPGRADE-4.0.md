UPGRADE FROM 3.X to 4.0
=======================

PHP Version
-----------

* PHP 8.0 and 8.1 support has been dropped. The minimum required version is now **PHP 8.2**.
* Update your `composer.json` to require `"php": ">=8.2"`

Symfony
-------

* Symfony 6 support has been dropped: `symfony/property-access` (and the optional `symfony/uid` / `symfony/cache`)
  now require `^7.0 || ^8.0`.

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
| `Query(Interface)::selectMaster(bool $useMaster)`                 | `Query(Interface)::selectPrimary(bool $usePrimary)`                 |
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
* `ConnectionPool::setConfig()` throws a `CCMBenchmark\Ting\Exceptions\ConfigException` when a connection still uses
  the `master` or `slaves` key (e.g. `Connection "main": the "master" key was renamed "primary" in Ting 4.0`).
* Named arguments must be renamed as well: `forceMaster:` becomes `forcePrimary:` (and `useMaster:` becomes
  `usePrimary:`), otherwise PHP throws an `Error` (unknown named parameter).
* As before, a replica falls back to the primary when no replica is configured.

Repository reads: property names and entity values
--------------------------------------------------

`Repository::get()`, `getBy()` and `getOneBy()` now take **property names** (`fieldName`) everywhere, and convert the
values of the criteria with the serializers of the fields, as `save()` does:

| Argument                                  | Before (3.x)                            | After (4.0)                                    |
|-------------------------------------------|-----------------------------------------|------------------------------------------------|
| Composite primary key of `get([...])`     | column names, raw values                | property names, converted values               |
| Criteria of `getBy()` / `getOneBy()`      | property names, raw values              | property names, converted values               |
| `$order` of `getBy()`                     | column names                            | property names                                 |
| Order direction other than `ASC` / `DESC` | silently ignored                        | `ValueException`                               |
| Empty array in the criteria               | invalid SQL (`IN ()`)                   | `ValueException`                               |

```php
// Before (3.x):
$language = $countryLanguageRepository->get(['cou_code' => 'AGO', 'col_language' => 'Kongo']);
$cities = $cityRepository->getBy(
    ['status' => CityStatus::Active->value, 'createdAt' => $date->format('Y-m-d H:i:s')],
    order: ['cit_name' => 'ASC'],
);

// After (4.0):
$language = $countryLanguageRepository->get(['countryCode' => 'AGO', 'language' => 'Kongo']);
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
| scalar (string, int, float, bool) | any                                                                 | as is                                         |

* An unknown key throws a `CCMBenchmark\Ting\Exceptions\ValueException`. When the key is a column name, the message
  names the property to use, e.g.
  `"cit_name" is a column name: use the property name "name" in the order of Repository::getBy()`.
* An order direction other than `ASC` or `DESC` (case-insensitive) throws a `ValueException`, e.g.
  `Invalid direction "UP" for property "name" in the order of Repository::getBy(): use "ASC" or "DESC"`.
* An empty array throws a `ValueException`
  (`Empty array for property "id" in the criteria of Repository::getBy(): nothing can match`)
  instead of sending invalid SQL: return early when the list may be empty (`if ($ids === []) { return ...; }`).
* `null` or a nested array inside an `IN` list throws a `ValueException`: an `IN` list never matches `NULL`.
* Scalars are still sent as is: database values (`CityStatus::Active->value`, a formatted date) keep working, only
  column-name keys must be renamed. Passing the PHP value (enum, `DateTime`...) is now possible.
* With a `Json` field, an array is now compared as a whole with the JSON encoding of the value, instead of becoming an
  `IN` list. See [the caveats on JSON equality](docs/repositories.md#criteria-keys-and-values).
* A custom serializer whose PHP value is an array can implement the marker interface
  `CCMBenchmark\Ting\Serializer\ArrayValueInterface` to get the same behaviour.
* `get()` with a single value (one primary key) follows the same rules: scalars and `null` are unchanged, an object is
  now serialized.
* Ting 3.15 already accepts both the 3.x and the 4.0 forms, and deprecates the 3.x ones: you can migrate on 3.x first,
  fix the deprecations, then upgrade to 4.0.

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

* Cached queries keep the same API (`setTtl()`, `setCacheKey()`, `setForce()`) and behaviour: a TTL of `0` still means
  "no expiration", `setForce(true)` still recomputes and stores the result. Concurrent misses on the same key are now
  protected against cache stampede by Symfony.
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

* `Generator` is mostly used internally: from a repository, `getBy($criteria, $forcePrimary, $order, $limit)` is
  unchanged (apart from `$forceMaster` renamed `$forcePrimary`, see "Primary / Replica").

Repository and RepositoryFactory Constructors
---------------------------------------------

The `SerializerFactoryInterface` argument, unused since 3.x, has been removed from both constructors:

* `Repository::__construct()` takes 6 arguments: if your repository overrides the constructor, drop the last
  `SerializerFactoryInterface $serializerFactory` parameter and don't pass it to `parent::__construct()`.
* `RepositoryFactory::__construct()` takes 6 arguments: drop the last `$serializerFactory` argument (with Symfony,
  ting_bundle does it for you).

Metadata
--------

* The `Metadata::getGetter()` method has been removed.
* The `Metadata::getSetter()` method has been removed.
* If you were using these methods, you should define custom getters/setters directly in your field configuration:

```php
// In your Repository::initMetadata():
$metadata->addField([
    'fieldName' => 'myField',
    'columnName' => 'my_field',
    'type' => 'string',
    'getter' => 'getMyCustomField',  // Custom getter method name
    'setter' => 'setMyCustomField',  // Custom setter method name
]);
```

UnitOfWork
----------

* The `UnitOfWork::generateUid()` method has been removed.
* The `UnitOfWork::generateUUID()` method has been removed (was deprecated in 3.x).
* If you need unique identifiers, use PHP's built-in functions like `uniqid()` or `spl_object_hash()` directly.
* Changes are detected against the database values: a notified property is updated when its value, serialized by the
  serializer of its field, differs from the one read from (or last written to) the database. A `DateTime` modified in
  place then given to its setter is now updated; one replaced by an equal instance no longer is.
  `NotifyProperty::propertyChanged()` therefore notifies the listeners when the old and new values are the same object
  (a custom `PropertyListenerInterface` receives these calls too). The values given to `propertyChanged()` are no
  longer used to build the `UPDATE`.

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

* All methods have full type hints for parameters and return types.
* If you extend Ting classes or implement Ting interfaces, ensure your signatures match exactly.
* Pay special attention to:
  - Return type declarations (`: void`, `: static`, `: mixed`, etc.)
  - Parameter types (`string`, `array`, `?int`, etc.)
  - Nullable types where applicable

The extension points you are the most likely to implement:

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
* Repositories: `Repository::getCollection(?HydratorInterface $hydrator = null): Collection`; an override must return
  `Collection` (or a subclass), not `CollectionInterface`.
* `SerializeInterface` and `UnserializeInterface` keep their 3.x signatures (no native return type).

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
