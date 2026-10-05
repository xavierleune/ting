# Using Ting without a framework

This guide builds a small application on top of Ting, from installation to the first queries, without any framework.

> Using Symfony? Install [ting_bundle](https://github.com/xavierleune/ting_bundle) instead: it declares every Ting
> service in the Symfony container, so you can skip the [wiring](#wiring-ting) step below.

## Setup

This guide assumes [Composer](https://getcomposer.org/) is installed. The application code lives in
`src/VendorName/ApplicationName`, so declare that path in the autoload section of your `composer.json`:

```json
{
    "autoload": {
        "psr-4": { "VendorName\\ApplicationName\\": "src/VendorName/ApplicationName" }
    }
}
```

## Installation

Ting requires PHP 8.2 or later and the `mysqli` (MySQL / MariaDB) or `pgsql` (PostgreSQL) extension.

```bash
composer require xavierleune/ting
```

Ting needs a cache pool to build repositories (it backs [cached queries](cache.md)). Any
[Symfony cache](https://symfony.com/doc/current/components/cache.html) pool works; `symfony/cache` provides them:

```bash
composer require symfony/cache
```

## Database

This guide uses the MySQL [world database](https://dev.mysql.com/doc/world-setup/en/). Follow the
[official installation guide](https://dev.mysql.com/doc/world-setup/en/world-setup-installation.html) to load it into
your MySQL server.

It contains three tables. To keep the guide short, only some of their columns are mapped:

| `city`        |            |
|---------------|------------|
| `ID`          | `int`      |
| `Name`        | `char(35)` |
| `CountryCode` | `char(3)`  |
| `Population`  | `int`      |

| `country`    |            |
|--------------|------------|
| `Code`       | `char(3)`  |
| `Name`       | `char(52)` |
| `Region`     | `char(26)` |
| `Population` | `int`      |

| `countrylanguage` |                |
|-------------------|----------------|
| `CountryCode`     | `char(3)`      |
| `Language`        | `char(30)`     |
| `Percentage`      | `decimal(4,1)` |

## Creating the repositories

A repository extends `CCMBenchmark\Ting\Repository\Repository` and implements
`CCMBenchmark\Ting\Repository\MetadataInitializer`: its static `initMetadata()` method describes the table (connection,
database, table) and how each column maps to a property of the entity. See [Repositories](repositories.md) for every
option.

### City repository

```php
<?php
// src/VendorName/ApplicationName/Repository/CityRepository.php

namespace VendorName\ApplicationName\Repository;

use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\MetadataInitializer;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;
use VendorName\ApplicationName\Entity\City;

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
        $metadata->setTable('city');

        $metadata
            ->addField([
                'primary'       => true,
                'autoincrement' => true,
                'fieldName'     => 'id',
                'columnName'    => 'ID',
                'type'          => 'int',
            ])
            ->addField([
                'fieldName'  => 'name',
                'columnName' => 'Name',
                'type'       => 'string',
            ])
            ->addField([
                'fieldName'  => 'countryCode',
                'columnName' => 'CountryCode',
                'type'       => 'string',
            ])
            ->addField([
                'fieldName'  => 'population',
                'columnName' => 'Population',
                'type'       => 'int',
            ]);

        return $metadata;
    }
}
```

`setConnectionName('main')` refers to the connection declared in the [wiring](#wiring-ting) step, and the table name
must match the one returned by the database server (it is case-sensitive on most Linux setups).

### Country repository

```php
<?php
// src/VendorName/ApplicationName/Repository/CountryRepository.php

namespace VendorName\ApplicationName\Repository;

use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\MetadataInitializer;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;
use VendorName\ApplicationName\Entity\Country;

/**
 * @extends Repository<Country>
 */
class CountryRepository extends Repository implements MetadataInitializer
{
    public static function initMetadata(SerializerFactoryInterface $serializerFactory, array $options = []): Metadata
    {
        $metadata = new Metadata($serializerFactory);
        $metadata->setEntity(Country::class);
        $metadata->setConnectionName('main');
        $metadata->setDatabase('world');
        $metadata->setTable('country');

        $metadata
            ->addField([
                'primary'    => true,
                'fieldName'  => 'code',
                'columnName' => 'Code',
                'type'       => 'string',
            ])
            ->addField([
                'fieldName'  => 'name',
                'columnName' => 'Name',
                'type'       => 'string',
            ])
            ->addField([
                'fieldName'  => 'region',
                'columnName' => 'Region',
                'type'       => 'string',
            ])
            ->addField([
                'fieldName'  => 'population',
                'columnName' => 'Population',
                'type'       => 'int',
            ]);

        return $metadata;
    }
}
```

### CountryLanguage repository

This table has a composite primary key: both columns are flagged as `primary`.

```php
<?php
// src/VendorName/ApplicationName/Repository/CountryLanguageRepository.php

namespace VendorName\ApplicationName\Repository;

use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\MetadataInitializer;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;
use VendorName\ApplicationName\Entity\CountryLanguage;

/**
 * @extends Repository<CountryLanguage>
 */
class CountryLanguageRepository extends Repository implements MetadataInitializer
{
    public static function initMetadata(SerializerFactoryInterface $serializerFactory, array $options = []): Metadata
    {
        $metadata = new Metadata($serializerFactory);
        $metadata->setEntity(CountryLanguage::class);
        $metadata->setConnectionName('main');
        $metadata->setDatabase('world');
        $metadata->setTable('countrylanguage');

        $metadata
            ->addField([
                'primary'    => true,
                'fieldName'  => 'countryCode',
                'columnName' => 'CountryCode',
                'type'       => 'string',
            ])
            ->addField([
                'primary'    => true,
                'fieldName'  => 'language',
                'columnName' => 'Language',
                'type'       => 'string',
            ])
            ->addField([
                'fieldName'  => 'percentage',
                'columnName' => 'Percentage',
                'type'       => 'double',
            ]);

        return $metadata;
    }
}
```

## Creating the entities

Each repository declares its entity with `setEntity()`. An entity is a plain PHP object implementing
`CCMBenchmark\Ting\Entity\NotifyPropertyInterface`: the `NotifyProperty` trait provides the implementation, and every
setter calls `propertyChanged()` so that the [UnitOfWork](unit-of-work.md) knows what to write when the entity is saved.
See [Entities](entities.md) for details, including entities with public properties.

```php
<?php
// src/VendorName/ApplicationName/Entity/City.php

namespace VendorName\ApplicationName\Entity;

use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;

class City implements NotifyPropertyInterface
{
    use NotifyProperty;

    private ?int $id = null;
    private string $name = '';
    private string $countryCode = '';
    private int $population = 0;

    public function setId(int $id): void
    {
        $this->propertyChanged('id', $this->id, $id);
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setName(string $name): void
    {
        $this->propertyChanged('name', $this->name, $name);
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setCountryCode(string $countryCode): void
    {
        $this->propertyChanged('countryCode', $this->countryCode, $countryCode);
        $this->countryCode = $countryCode;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setPopulation(int $population): void
    {
        $this->propertyChanged('population', $this->population, $population);
        $this->population = $population;
    }

    public function getPopulation(): int
    {
        return $this->population;
    }
}
```

```php
<?php
// src/VendorName/ApplicationName/Entity/Country.php

namespace VendorName\ApplicationName\Entity;

use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;

class Country implements NotifyPropertyInterface
{
    use NotifyProperty;

    private string $code = '';
    private string $name = '';
    private string $region = '';
    private int $population = 0;

    public function setCode(string $code): void
    {
        $this->propertyChanged('code', $this->code, $code);
        $this->code = $code;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setName(string $name): void
    {
        $this->propertyChanged('name', $this->name, $name);
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setRegion(string $region): void
    {
        $this->propertyChanged('region', $this->region, $region);
        $this->region = $region;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function setPopulation(int $population): void
    {
        $this->propertyChanged('population', $this->population, $population);
        $this->population = $population;
    }

    public function getPopulation(): int
    {
        return $this->population;
    }
}
```

```php
<?php
// src/VendorName/ApplicationName/Entity/CountryLanguage.php

namespace VendorName\ApplicationName\Entity;

use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;

class CountryLanguage implements NotifyPropertyInterface
{
    use NotifyProperty;

    private string $countryCode = '';
    private string $language = '';
    private float $percentage = 0.0;

    public function setCountryCode(string $countryCode): void
    {
        $this->propertyChanged('countryCode', $this->countryCode, $countryCode);
        $this->countryCode = $countryCode;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setLanguage(string $language): void
    {
        $this->propertyChanged('language', $this->language, $language);
        $this->language = $language;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setPercentage(float $percentage): void
    {
        $this->propertyChanged('percentage', $this->percentage, $percentage);
        $this->percentage = $percentage;
    }

    public function getPercentage(): float
    {
        return $this->percentage;
    }
}
```

## Wiring Ting

Ting is a plain library: every dependency is passed to the constructors. Without a framework, create the objects once
per process in a bootstrap file (or register them in any PSR-11 container):

```php
<?php
// src/VendorName/ApplicationName/bootstrap.php

namespace VendorName\ApplicationName;

use CCMBenchmark\Ting\Cache\Cache;
use CCMBenchmark\Ting\ConnectionPool;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Query\QueryFactory;
use CCMBenchmark\Ting\Repository\CollectionFactory;
use CCMBenchmark\Ting\Repository\Hydrator;
use CCMBenchmark\Ting\Repository\RepositoryFactory;
use CCMBenchmark\Ting\Serializer\SerializerFactory;
use CCMBenchmark\Ting\UnitOfWork;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

require __DIR__ . '/../../../vendor/autoload.php';

// 1. Connections
$connectionPool = new ConnectionPool();
$connectionPool->setConfig([
    'main' => [
        'namespace' => '\CCMBenchmark\Ting\Driver\Mysqli',
        'charset'   => 'utf8mb4',
        'primary'   => [
            'host'     => 'localhost',
            'user'     => 'root',
            'password' => '',
            'port'     => 3306,
        ],
    ],
]);

// 2. Metadata: load every repository of the directory
$serializerFactory = new SerializerFactory();
$metadataRepository = new MetadataRepository($serializerFactory);
$metadataRepository->batchLoadMetadata(
    'VendorName\ApplicationName\Repository',
    __DIR__ . '/Repository/*Repository.php'
);

// 3. Shared services
$queryFactory = new QueryFactory();
$unitOfWork = new UnitOfWork($connectionPool, $metadataRepository, $queryFactory);
$collectionFactory = new CollectionFactory($metadataRepository, $unitOfWork, new Hydrator());
$cache = new Cache();
$cache->setCache(new ArrayAdapter());

// 4. The factory that builds your repositories
$repositoryFactory = new RepositoryFactory(
    $connectionPool,
    $metadataRepository,
    $queryFactory,
    $collectionFactory,
    $unitOfWork,
    $cache
);
```

### Connections

`ConnectionPool::setConfig()` takes one entry per connection name, the name used by `Metadata::setConnectionName()`:

| Key         | Description                                                                                                                                   |
|-------------|-----------------------------------------------------------------------------------------------------------------------------------------------|
| `namespace` | Driver namespace: `\CCMBenchmark\Ting\Driver\Mysqli` or `\CCMBenchmark\Ting\Driver\Pgsql`                                                     |
| `primary`   | `host`, `port`, and optionally `user` and `password`. Used for writes, and for reads when there is no replica                                 |
| `replicas`  | Optional list of servers with the same keys as `primary`. Reads go to one of them, picked at random once per connection                       |
| `charset`   | Optional connection charset                                                                                                                   |

Connections are opened lazily, on the first query. Per-database options can be set with
`ConnectionPool::setDatabaseOptions()`; for now, `timezone` sets the session time zone (for MySQL, a value accepted by
`SET time_zone`):

```php
$connectionPool->setDatabaseOptions([
    'world' => ['timezone' => '+00:00'],
]);
```

### Metadata

`MetadataRepository::batchLoadMetadata($namespace, $globPattern)` reads every file matching the pattern and calls the
`initMetadata()` method of each class implementing `MetadataInitializer`. It must run before the first repository is
created: otherwise the repository constructor throws a `RepositoryException` ("Metadata not found for ...").
`batchLoadMetadata()` returns the loaded classes (`repository class => metadata class`); in production, store that array
and pass it to `batchLoadMetadataFromCache()` to skip the directory scan.

### Shared objects

`ConnectionPool`, `MetadataRepository`, `UnitOfWork`, `QueryFactory`, `SerializerFactory`, `Cache` and
`RepositoryFactory` hold state or configuration: create them once and share them. `CollectionFactory` clones the
hydrator it receives for each collection; other hydrators are created on demand (see [Hydrators](hydrators.md)).

`ArrayAdapter` keeps cached query results in memory for the current process only: use a persistent pool
(`RedisAdapter`, `MemcachedAdapter`, `ApcuAdapter`...) in production. See [Cache](cache.md).

## Querying

The repositories are ready:

```php
<?php
// src/VendorName/ApplicationName/index.php

namespace VendorName\ApplicationName;

use VendorName\ApplicationName\Entity\City;
use VendorName\ApplicationName\Repository\CityRepository;

require __DIR__ . '/bootstrap.php';

$cityRepository = $repositoryFactory->get(CityRepository::class);

// Fetch one entity by its primary key
$city = $cityRepository->get(3);
var_dump($city); // listeners are hidden by NotifyProperty::__debugInfo()

// Fetch by criteria (property names), with an order (column names) and a limit
$cities = $cityRepository->getBy(['countryCode' => 'FRA'], order: ['Population' => 'DESC'], limit: 10);
foreach ($cities as $city) {
    echo $city->getName(), ': ', $city->getPopulation(), "\n";
}

// Fetch the first match only
$paris = $cityRepository->getOneBy(['name' => 'Paris']);

// Update: the repository only writes the properties that changed
$paris->setPopulation(2_200_000);
$cityRepository->save($paris);

// Insert
$city = new City();
$city->setName('Ting City');
$city->setCountryCode('FRA');
$city->setPopulation(42);
$cityRepository->save($city);
echo $city->getId(), "\n"; // filled with the auto-increment value

// Delete
$cityRepository->delete($city);

// Your own SQL: results are grouped by table alias
$query = $cityRepository->getQuery(
    'SELECT c.* FROM city c WHERE c.CountryCode = :code AND c.Population > :population'
);
$collection = $query->setParams(['code' => 'FRA', 'population' => 1_000_000])->query();
foreach ($collection as $row) {
    echo $row['c']->getName(), "\n";
}
```

`get()` and `getOneBy()` return the entity or `null`; `getBy()` and `getAll()` return a collection of entities. With a
composite primary key, pass an array indexed by column name:

```php
use VendorName\ApplicationName\Repository\CountryLanguageRepository;

$french = $repositoryFactory
    ->get(CountryLanguageRepository::class)
    ->get(['CountryCode' => 'FRA', 'Language' => 'French']);
```

## Next steps

- [Repositories](repositories.md): metadata options, field types, serializers, custom methods.
- [Entities](entities.md): change tracking, public and hooked properties.
- [Queries](queries.md): queries, prepared queries, the query builder, transactions.
- [Hydrators](hydrators.md): joins, aggregation, value objects.
- [UnitOfWork](unit-of-work.md): batching saves and deletes.
- [Cache](cache.md): cached queries.

Upgrading from Ting 3.x? See [UPGRADE-4.0.md](../UPGRADE-4.0.md).
