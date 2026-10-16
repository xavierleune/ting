# Ting

[![Tests](https://github.com/xavierleune/ting/actions/workflows/tests.yml/badge.svg)](https://github.com/xavierleune/ting/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/xavierleune/ting?include_prereleases)](https://packagist.org/packages/xavierleune/ting)
[![PHP](https://img.shields.io/packagist/dependency-v/xavierleune/ting/php)](composer.json)
[![License](https://img.shields.io/packagist/l/xavierleune/ting)](LICENSE)

**A data mapper for PHP that lets you write SQL.** Plain PHP objects, explicit mapping, a unit of work for writes, and
no query language to learn: what you write is what your database runs.

```php
$cities = $cityRepository->getBy(['status' => CityStatus::Active], order: ['name' => 'ASC'], limit: 10);
```

## Why Ting

* **Your SQL, your database.** No abstraction layer and no DSL: write MySQL or PostgreSQL (or SphinxQL) as you would
  in a console, and get entities back.
* **No magic objects.** Entities are plain PHP classes: no proxies, no lazy loading, no hidden queries when you read a
  property.
* **Everyday reads in one line.** `get`, `getBy`, `getOneBy`, `countBy`, `existsBy`: criteria, order and pagination
  without writing SQL for the simple cases.
* **Writes you control.** A unit of work tracks the properties you change and writes only those; `transactional()`
  wraps your changes in a transaction.
* **Joins without an object graph.** Ting is not an ORM: there are no relations to configure. Write the join, and the
  hydrators give you every object of the row.
* **Typed parameters.** Dates, backed enums and arrays (`IN` lists) are passed as they are.

Ting needs PHP 8.2 or later, with the `mysqli` or `pgsql` extension.

## Installation

```bash
composer require xavierleune/ting
```

With Symfony, install [ting_bundle](https://github.com/xavierleune/ting_bundle): it wires Ting in the container and
lets you map your entities with attributes, as below. Without a framework, see
[Getting started](docs/getting-started.md), which maps them in PHP.

## Quick tour

### Map an entity

With ting_bundle, an entity is a class with attributes, and the type of each column comes from the type of its
property. Ting writes the properties whose changes are notified: here with PHP 8.4 property hooks, setters work too.

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
