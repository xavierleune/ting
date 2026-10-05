> **ℹ️ This is a fork of [ccmbenchmark/ting](https://github.com/ccmbenchmark/ting).**
> The upstream project is no longer maintained publicly. Development continues here as `xavierleune/ting`.
> PHP namespaces (`CCMBenchmark\Ting\...`) are unchanged. Coming from `ccmbenchmark/ting` 3.x, update the package name
> in your `composer.json` and follow [UPGRADE-4.0.md](UPGRADE-4.0.md).

# Ting - PHP Datamapper

Ting is a simple DataMapper implementation for PHP. It runs with MySQL and PostgreSQL and is
under Apache-2.0 licence.

It has some distinctive features and design choices:

* Pure PHP implementation (no PDO, no XML)
* No abstraction layer: you speak the language of your RDBMS
* Fast, low memory consumption
* Simple to use, simple to extend

## Installation

```bash
composer require xavierleune/ting
```

Ting requires PHP 8.2 or later. With Symfony, install [ting_bundle](https://github.com/xavierleune/ting_bundle),
which wires everything in the container. Without a framework, see
[Getting started](docs/getting-started.md).

## Retrieve object by ID

```php
// $cityRepository is a \sample\src\model\CityRepository, given by the container (or a RepositoryFactory)
$city = $cityRepository->get(3);
```

## Simple query

```php
// This query supports the same syntax as prepared statements, but it'll be a regular query
$query = $cityRepository->getQuery(
    "select cit_id, cit_name, c.cou_code, cit_district, cit_population, last_modified,
        co.cou_code, cou_name, cou_continent, cou_region, cou_head_of_state
    from t_city_cit as c
    inner join t_country_cou as co on (c.cou_code = co.cou_code)
    where co.cou_code = :code limit 3"
);

$collection = $query->setParams(['code' => 'FRA'])->query();

foreach ($collection as $result) {
    var_dump($result);
}
```

## Prepared statement

```php
// Simple query:
$query = $cityRepository->getQuery('SQL Statement');
// Prepared statement:
$query = $cityRepository->getPreparedQuery('SQL Statement');
```

## Query builder provided by [aura/sqlquery](https://github.com/auraphp/Aura.SqlQuery)

```php
$queryBuilder = $cityRepository->getQueryBuilder($cityRepository::QUERY_SELECT);
$queryBuilder
    ->cols(['cit_id', 'cit_name as name'])
    ->from('t_city_cit');
$query = $cityRepository->getQuery($queryBuilder->getStatement());
```

## More

* [Documentation](docs/README.md)
* [Samples](sample/src)
* [Upgrading to 4.0](UPGRADE-4.0.md)
* [Issues](https://github.com/xavierleune/ting/issues)
* [Contributing](CONTRIBUTING.md)
