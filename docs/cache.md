# Cache

Ting can cache the result of reading queries. Caching relies on
[Symfony Cache Contracts](https://symfony.com/doc/current/components/cache.html#cache-contracts): any Symfony cache
pool can be used (`RedisAdapter`, `MemcachedAdapter`, `ApcuAdapter`, `ArrayAdapter`...).

## How it works

A cached query first looks for its key in the cache:

* on a hit, the collection is filled from the cached data and the database is not queried;
* on a miss, the query runs on the database and its result is stored in the cache with the given TTL.

What is stored is the raw result of the query (rows and column information), not the hydrated objects. Hydration
happens on each read, so the cached data doesn't depend on the [hydrator](hydrators.md) used, and the same key can be
read with different hydrators.

`CollectionInterface::isFromCache()` tells whether a collection was filled from the cache.

Concurrent misses on the same key are protected against cache stampede by the pools of `symfony/cache`: by default,
their lock (Symfony's `LockRegistry`, made of local file locks) only coordinates the processes of a same host. On a
host, one process computes the value while the others wait for it; each host still computes it once, so a key missing
from a shared pool (Redis, Memcached) is computed by as many processes as there are hosts.

## Setting up the cache

Install a cache pool implementation, for instance `symfony/cache`:

```bash
composer require symfony/cache
```

Repositories receive the cache through the `$cache` argument of `CCMBenchmark\Ting\Repository\RepositoryFactory`
(see [Getting started](getting-started.md)). It accepts any `Symfony\Contracts\Cache\CacheInterface`. Ting provides
`CCMBenchmark\Ting\Cache\Cache`, which decorates a Symfony pool to log cache operations:

```php
use CCMBenchmark\Ting\Cache\Cache;
use Symfony\Component\Cache\Adapter\RedisAdapter;

$cache = new Cache();
$cache->setCache(new RedisAdapter(RedisAdapter::createConnection('redis://localhost'), 'ting'));

// $cache is then given to the RepositoryFactory
```

`setCache()` must be called before the cache is used, otherwise `get()` and `delete()` throw a
`CCMBenchmark\Ting\Exceptions\ConfigException`. To disable caching (in tests for instance), use
`Symfony\Component\Cache\Adapter\NullAdapter`: every read is a miss and cached queries always hit the database.

## Cached queries

Cached queries are created from a [repository](repositories.md) with `getCachedQuery()` or `getCachedPreparedQuery()`.
They work like `Query` and `PreparedQuery` (see [Queries](queries.md)), with a cache key and a TTL that must be set
before `query()`:

```php
namespace App\Repository;

use CCMBenchmark\Ting\Repository\CollectionInterface;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;
use CCMBenchmark\Ting\Repository\MetadataInitializer;
use CCMBenchmark\Ting\Repository\Repository;

class UserRepository extends Repository implements MetadataInitializer
{
    // initMetadata()...

    public function findAllCached(): CollectionInterface
    {
        $query = $this->getCachedQuery('SELECT id, name FROM user');
        $query->setCacheKey('users_all');
        $query->setTtl(300); // 5 minutes

        return $query->query();
    }

    public function findByNameCached(string $name, bool $refresh = false): CollectionInterface
    {
        $query = $this->getCachedPreparedQuery('SELECT id, name FROM user WHERE name = :name');
        $query->setParams(['name' => $name])
            ->setCacheKey('users_by_name_' . md5($name))
            ->setTtl(0) // no lifetime of its own: the pool's default lifetime applies
            ->setForce($refresh);

        return $query->query($this->getCollection(new HydratorSingleObject()));
    }
}
```

```php
$users = $userRepository->findAllCached();
$users->isFromCache(); // false: read from the database, then stored

$users = $userRepository->findAllCached();
$users->isFromCache(); // true
```

### Options

| Method                          | Description                                                                                       |
|---------------------------------|---------------------------------------------------------------------------------------------------|
| `setCacheKey(string $cacheKey)` | Required. Key of the result in the cache.                                                         |
| `setTtl(int $ttl)`              | Required. Lifetime in seconds. `0` gives the item no lifetime of its own: the pool's default lifetime applies, so it never expires only in a pool created without default lifetime. A negative TTL throws a `QueryException`. |
| `setForce(bool $value)`         | When true, always run the query and store its result, even if the key is in the cache.            |

All of them return the query, so calls can be chained. Calling `query()` without a TTL or without a cache key throws a
`CCMBenchmark\Ting\Query\QueryException`.

The cache key must be unique for each distinct query and set of parameters: include the parameters in the key, hashed
if needed, since Symfony rejects keys containing `{}()/\@:`.

Only `query()` uses the cache. `execute()` runs a writing query on the primary as usual and doesn't touch the cache.

### Invalidation

Delete a key when the data it holds changes. Inside a repository, the cache is available as `$this->cache`:

```php
public function renameUser(int $id, string $name): void
{
    $query = $this->getPreparedQuery('UPDATE user SET name = :name WHERE id = :id');
    $query->setParams(['id' => $id, 'name' => $name])->execute();

    $this->cache->delete('users_all');
}
```

Alternatively, refresh an entry by running its query with `setForce(true)`.

## Using the cache directly

`CCMBenchmark\Ting\Cache\Cache` implements `CCMBenchmark\Ting\Cache\CacheInterface`, which extends Symfony's
`CacheInterface`. Besides cached queries, you can use it for any value with the Symfony API: `get()` returns the cached
value, or calls the callback to compute and store it on a miss; `delete()` removes a key.

```php
use CCMBenchmark\Ting\Cache\Cache;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\ItemInterface;

$cache = new Cache();
$cache->setCache(new ArrayAdapter());

$stats = $cache->get('stats', function (ItemInterface $item): array {
    $item->expiresAfter(3600);

    return ['users' => 2]; // computed on a miss only
});

$cache->delete('stats');
```

## Logging cache operations

`Cache::setLogger()` takes a `CCMBenchmark\Ting\Logger\CacheLoggerInterface`, called around each operation:

* `startOperation(string $operation, array|string $keys)`, with `CacheLoggerInterface::OPERATION_GET` for `get()`
  (cached queries included) or `CacheLoggerInterface::OPERATION_DELETE` for `delete()`;
* `stopOperation(bool $miss = false)`, where `$miss` is `true` when a `get()` had to compute (and store) the value.

```php
use CCMBenchmark\Ting\Logger\CacheLoggerInterface;

final class EchoCacheLogger implements CacheLoggerInterface
{
    private float $start = 0;

    public function startOperation(string $operation, array|string $keys): void
    {
        $this->start = microtime(true);
        echo $operation . ' ' . implode(', ', (array) $keys);
    }

    public function stopOperation(bool $miss = false): void
    {
        printf(" %s (%.2f ms)\n", $miss ? 'miss' : 'hit', (microtime(true) - $this->start) * 1000);
    }
}

$cache->setLogger(new EchoCacheLogger());
```

Upgrading from doctrine/cache (Ting 3.x): see [UPGRADE-4.0.md](../UPGRADE-4.0.md).
