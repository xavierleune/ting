# Ting

[![Tests](https://github.com/xavierleune/ting/actions/workflows/tests.yml/badge.svg)](https://github.com/xavierleune/ting/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/xavierleune/ting?include_prereleases)](https://packagist.org/packages/xavierleune/ting)
[![PHP](https://img.shields.io/packagist/dependency-v/xavierleune/ting/php)](composer.json)
[![License](https://img.shields.io/packagist/l/xavierleune/ting)](LICENSE)

**A data mapper for PHP built around your SQL.** Plain PHP objects, explicit mapping and a unit of work for writes: you
write the SQL, Ting maps the rows.

```php
$cities = $cityRepository->getBy(['status' => CityStatus::Active], order: ['name' => 'ASC'], limit: 10);
```

## Why Ting

* **Fast and light.** Rows are hydrated straight into plain PHP objects, prepared statements are reused, and only the
  properties you change are written.
* **Your SQL, your database.** Write MySQL, PostgreSQL or SphinxQL as you would in a console, and get entities back.
* **Plain PHP objects.** Your entities are your own classes with typed properties: dates, enums and JSON columns arrive
  as PHP values.
* **Everyday reads in one line.** `get`, `getBy`, `getOneBy`, `countBy`, `existsBy`, with criteria, order and
  pagination.
* **Writes you control.** The unit of work writes the changes your entities notify; `transactional()` wraps them in a
  transaction.
* **Joins your way.** Write the join, and the hydrators give you every object of the row.
* **Typed parameters.** Dates, backed enums and arrays (`IN` lists) are passed as they are.

Ting needs PHP 8.2 or later, with the `mysqli` or `pgsql` extension.

## Installation

```bash
composer require xavierleune/ting
```

With Symfony, install [ting_bundle](https://github.com/xavierleune/ting_bundle): it wires Ting in the container and
lets you map your entities with attributes, as below: follow its
[getting started](https://github.com/xavierleune/ting_bundle/blob/main/docs/getting-started.md) for the configuration.
Without a framework, see [Getting started](docs/getting-started.md), which maps them in PHP.

## Quick tour

### Map an entity

With ting_bundle, an entity is a class with attributes, and the type of each column comes from the type of its
property. Ting writes the properties whose changes are notified: each mapped property calls `propertyChanged()`, here
in a PHP 8.4 property hook (a setter works too), see [Unit of work](docs/unit-of-work.md).

```php
use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\TingBundle\Schema\Column;
use CCMBenchmark\TingBundle\Schema\Table;

enum CityStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}

#[Table(name: 'city', repository: CityRepository::class)]
class City implements NotifyPropertyInterface
{
    use NotifyProperty;

    #[Column(autoIncrement: true, primary: true)]
    public ?int $id = null;

    #[Column]
    public string $name = '' {
        set(string $name) {
            $this->propertyChanged('name', $this->name, $name);
            $this->name = $name;
        }
    }

    #[Column]
    public CityStatus $status = CityStatus::Active {
        set(CityStatus $status) {
            $this->propertyChanged('status', $this->status, $status);
            $this->status = $status;
        }
    }
}

/** @extends Repository<City> */
class CityRepository extends Repository
{
}
```

### Read

```php
$city = $cityRepository->get(3); // by primary key, or null
$paris = $cityRepository->getOneBy(['name' => 'Paris']);
$page = $cityRepository->getBy(['status' => CityStatus::Active], order: ['name' => 'ASC'], limit: 20, offset: 40);
$active = $cityRepository->countBy(['status' => CityStatus::Active]);
$hasParis = $cityRepository->existsBy(['name' => 'Paris']);
```

### Write

```php
$city = new City();
$city->name = 'Lyon';
$cityRepository->save($city); // INSERT, then $city->id holds the generated id

$city->name = 'Lyon 2e';
$cityRepository->save($city); // UPDATE city SET name = ... WHERE id = ...: only the changed column

$cityRepository->delete($city);

// Committed when the callback returns, rolled back when it throws
$cityRepository->transactional(function () use ($cityRepository, $cities): void {
    foreach ($cities as $city) {
        $city->status = CityStatus::Archived;
        $cityRepository->save($city);
    }
});
```

### Write your own SQL

```php
// Entities of the repository from a query of your own: dates, enums and arrays are converted
$cities = $cityRepository->getBySql(
    'SELECT id, name, status FROM city WHERE id IN (:ids) AND status = :status AND created_at > :since',
    ['ids' => [3, 7, 12], 'status' => CityStatus::Active, 'since' => new \DateTimeImmutable('-7 days')]
);

// A single value
$lastCreation = $cityRepository->getQuery('SELECT MAX(created_at) FROM city WHERE status = :status')
    ->setParams(['status' => CityStatus::Active])
    ->queryValue();
```

Joins returning several entities, computed columns and aggregates go through a [hydrator](docs/hydrators.md). When the
SQL has to be built dynamically, an [aura/sqlquery](https://github.com/auraphp/Aura.SqlQuery) query builder is there:
see [Queries](docs/queries.md#query-builder).

## Documentation

* [Documentation](docs/README.md): entities, repositories, queries, hydrators, unit of work, cache
* [Getting started without a framework](docs/getting-started.md)
* [ting_bundle](https://github.com/xavierleune/ting_bundle) for Symfony
* [Samples](sample/src)
* [Changelog](CHANGELOG)
* [Issues](https://github.com/xavierleune/ting/issues)
* [Contributing](CONTRIBUTING.md)

## Coming from ccmbenchmark/ting

Ting was created at [CCM Benchmark Group](https://github.com/ccmbenchmark/ting), where it is no longer maintained
publicly; development continues here as `xavierleune/ting`. The PHP namespaces (`CCMBenchmark\Ting\...`) are
unchanged: to upgrade from 3.x, change the package name in your `composer.json` and follow
[UPGRADE-4.0.md](UPGRADE-4.0.md).

## License

Apache License 2.0, see [LICENSE](LICENSE) and [NOTICE](NOTICE).
