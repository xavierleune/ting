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
* Logging (`CacheLoggerInterface`): a read is logged as `OPERATION_GET`, flagged as a miss when the value had to be
  computed, which also stores it. `OPERATION_STORE` and `OPERATION_EXIST` are no longer emitted.
* Values stored by doctrine/cache cannot be read by the new pools: expect a cold cache after upgrading.

Generator
---------

* The `Generator::getByCriteriaWithOrderAndLimit()` method has been removed.
* Use `Generator::getByCriteria()` instead, which now accepts optional `$order` and `$limit` parameters:

```php
// Before (3.x):
$generator->getByCriteriaWithOrderAndLimit(['status' => 'active'], ['name' => 'ASC'], 10);

// After (4.0):
$generator->getByCriteria(['status' => 'active'], ['name' => 'ASC'], 10);
```

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

QueryInterface - New Required Method
------------------------------------

If you have implemented custom queries, you must implement:

```php
interface QueryInterface
{
    // New method in 4.0 (already available on Query in 3.x):
    public function selectMaster(bool $useMaster): static;
}
```

ResultInterface - New Required Method
-------------------------------------

If you have implemented custom results, you must implement the method used by `HydratorValueObject`:

```php
interface ResultInterface
{
    // New method in 4.0:
    /** @param class-string $objectToFetch */
    public function setObjectToFetch(string $objectToFetch): static;
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
