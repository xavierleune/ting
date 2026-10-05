<?php

/***********************************************************************
 *
 * Ting - PHP Datamapper
 * ==========================================
 *
 * Copyright (C) 2014 CCM Benchmark Group. (http://www.ccmbenchmark.com)
 * Copyright (C) 2026 Xavier Leune
 *
 ***********************************************************************
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you
 * may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
 * implied. See the License for the specific language governing
 * permissions and limitations under the License.
 *
 **********************************************************************/

namespace sample\src;

use CCMBenchmark\Ting\Exception;
use CCMBenchmark\Ting\Repository\Hydrator\AggregateFrom;
use CCMBenchmark\Ting\Repository\Hydrator\AggregateTo;
use CCMBenchmark\Ting\Repository\Hydrator\RelationMany;
use CCMBenchmark\Ting\Repository\Hydrator\RelationOne;
use CCMBenchmark\Ting\Repository\HydratorAggregator;
use CCMBenchmark\Ting\Repository\HydratorRelational;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;
use CCMBenchmark\Ting\Serializer\DateTime;
use sample\src\model\City;
use sample\src\model\CityRepository;
use sample\src\model\CountryLanguage;
use sample\src\model\Producer;
use sample\src\model\ProducerRepository;

// Ting and the sample are both autoloaded by the sample's own vendor (run "composer install" in sample/)
require __DIR__ . '/../vendor/autoload.php';

$services = new TingServices();
$repositories =
    $services
        ->metadataRepository()
        ->batchLoadMetadata('sample\src\model', __DIR__ . '/model/*Repository.php');

echo str_repeat("-", 40) . "\n";
echo 'Load Repositories: ' . count($repositories) . "\n";
echo str_repeat("-", 40) . "\n";

$connections = [
    'main' => [
        'namespace' => '\CCMBenchmark\Ting\Driver\Mysqli',
        'primary' => [
            'host'      => '127.0.0.1',
            'user'      => 'root',
            'password'  => 'p455w0rd',
            'port'      => 3306,
        ]
    ]
];

$services->connectionPool()->setConfig($connections);

// Options by database name. The timezone is sent as is to "SET time_zone": an offset always works, a named zone
// (Europe/Paris) needs the MySQL timezone tables to be loaded
$options = [
    'world' => [
        'timezone' => '+01:00'
    ]
];
$services->connectionPool()->setDatabaseOptions($options);

$cityRepository = $services->repositoryFactory()->get(CityRepository::class);

/**
 * HydratorAggregator: one City per group of consecutive rows, with the languages of its country.
 * Rows are grouped while they come in sequence: the query is sorted on the aggregation key.
 */
echo "HydratorAggregator\n";
try {
    $query = $cityRepository->getQuery("
    select t_city_cit.*, t_country_cou.*, t_countrylanguage_col.*
    from t_city_cit
    left join t_country_cou on t_country_cou.cou_code = t_city_cit.cou_code
    left join t_countrylanguage_col on t_countrylanguage_col.cou_code = t_country_cou.cou_code
    where t_city_cit.cou_code = :code
    order by t_city_cit.cit_id
    ");

    $hydrator = new HydratorAggregator();
    $hydrator
        ->callableIdIs(fn (array $row) => $row['t_city_cit']->getId())
        ->callableDataIs(fn (array $row) => $row['t_countrylanguage_col'])
        ->callableFinalizeAggregate(function (array $row, array $countryLanguages) {
            // A LEFT JOIN without match gives null
            $row['t_country_cou']?->countryLanguagesAre(array_filter($countryLanguages));
            $row['t_city_cit']->countryIs($row['t_country_cou']);

            return $row['t_city_cit'];
        });

    $collection = $query->setParams(['code' => 'NLD'])->query($cityRepository->getCollection($hydrator));

    /** @var City $city */
    foreach ($collection as $city) {
        echo "City: " . $city->getName() . "\n";
        $country = $city->getCountry();
        echo "\tCountry: " . $country->getName() . "\n";
        /** @var CountryLanguage $countryLanguage */
        foreach ($country->getCountryLanguages() as $countryLanguage) {
            echo "\t\tLanguage: " . $countryLanguage->getLanguage() . "\n";
        }
        echo str_repeat("-", 40) . "\n";
    }
} catch (Exception $e) {
    var_dump($e->getMessage());
}

/**
 * HydratorRelational: the same result, with the relations declared instead of written by hand.
 * The identity map is always on: the cities of a country share the same Country instance.
 */
echo "HydratorRelational\n";
try {
    $query = $cityRepository->getQuery("
    select t_city_cit.*, t_country_cou.*, t_countrylanguage_col.*
    from t_city_cit
    left join t_country_cou on t_country_cou.cou_code = t_city_cit.cou_code
    left join t_countrylanguage_col on t_countrylanguage_col.cou_code = t_country_cou.cou_code
    where t_city_cit.cou_code = :code
    ");

    $hydrator = new HydratorRelational();
    $hydrator->addRelation(new RelationMany(
        new AggregateFrom('t_countrylanguage_col'),
        new AggregateTo('t_country_cou'),
        'countryLanguagesAre'
    ));
    $hydrator->addRelation(new RelationOne(
        new AggregateFrom('t_country_cou'),
        new AggregateTo('t_city_cit'),
        'countryIs'
    ));
    $hydrator->callableFinalizeAggregate(fn (array $row) => $row['t_city_cit']);

    $collection = $query->setParams(['code' => 'NLD'])->query($cityRepository->getCollection($hydrator));

    /** @var City $city */
    foreach ($collection as $city) {
        echo "City: " . $city->getName() . "\n";
        $country = $city->getCountry();
        echo "\tCountry: " . $country->getName() . ' #' . spl_object_id($country) . "\n";
        /** @var CountryLanguage $countryLanguage */
        foreach ($country->getCountryLanguages() as $countryLanguage) {
            echo "\t\tLanguage: " . $countryLanguage->getLanguage() . "\n";
        }
        echo str_repeat("-", 40) . "\n";
    }
} catch (Exception $e) {
    var_dump($e->getMessage());
}

/**
 * HydratorRelational on several levels:
 * producer(id)->hasMany->worker(id)
 * producer(id)->hasMany->movie(id)
 * movie(id)->hasMany->actor(id)
 *
 * The producer, worker, movie and actor tables (and their join tables) are not part of world.sql:
 * create them to run this example.
 */
echo "HydratorRelational on several levels\n";
try {
    $producerRepository = $services->repositoryFactory()->get(ProducerRepository::class);
    $query = $producerRepository->getQuery(
        "select producer.*, worker.*, movie.*, actor.*
    from producer
    left join work_for_producer on producer.id = work_for_producer.producer_id
    left join worker on worker.id = work_for_producer.worker_id
    left join produce_movie on producer.id = produce_movie.producer_id
    left join movie on movie.id = produce_movie.movie_id
    left join actor_in_movie on actor_in_movie.movie_id = movie.id
    left join actor on actor.id = actor_in_movie.actor_id"
    );

    $hydrator = new HydratorRelational();
    $hydrator->addRelation(new RelationMany(new AggregateFrom('worker'), new AggregateTo('producer'), 'workersAre'));
    $hydrator->addRelation(new RelationMany(new AggregateFrom('movie'), new AggregateTo('producer'), 'moviesAre'));
    $hydrator->addRelation(new RelationMany(new AggregateFrom('actor'), new AggregateTo('movie'), 'actorsAre'));
    $hydrator->callableFinalizeAggregate(fn (array $row) => $row['producer']);

    $collection = $query->query($producerRepository->getCollection($hydrator));

    /** @var Producer $producer */
    foreach ($collection as $producer) {
        echo "Producer: " . $producer->getName() . "\n";
        foreach ($producer->getWorkers() as $worker) {
            echo "\tWorker: " . $worker->getName() . ' #' . spl_object_id($worker) . "\n";
        }
        foreach ($producer->getMovies() as $movie) {
            echo "\tMovie: " . $movie->getName() . "\n";
            foreach ($movie->getActors() as $actor) {
                // The same actor in several movies is the same instance
                echo "\t\tActor: " . $actor->getName() . ' #' . spl_object_id($actor) . "\n";
            }
        }
        echo str_repeat("-", 40) . "\n";
    }
} catch (Exception $e) {
    var_dump($e->getMessage());
}

echo 'Memory: ' . (memory_get_usage(true) / 1024) . " KiB\n";

echo 'Cached query' . "\n";
try {
    $queryCached = $cityRepository->getCachedQuery(
        "select cit_id, cit_name, c.cou_code, cit_district, cit_population, last_modified,
                    co.cou_code, cou_name, cou_continent, cou_region, cou_head_of_state
                 from t_city_cit as c
                inner join t_country_cou as co on (c.cou_code = co.cou_code)
                where co.cou_code = :code limit 1"
    );

    $queryCached->setTtl(10)->setCacheKey('cityFRA');

    // TingServices uses an ArrayAdapter, which only lives for the process: the second run reads from the cache
    for ($run = 1; $run <= 2; $run++) {
        $collection = $queryCached->setParams(['code' => 'FRA'])->query();
        echo 'From Cache : ' . (int) $collection->isFromCache() . "\n";
        foreach ($collection as $result) {
            var_dump($result['c']->getName());
            echo str_repeat("-", 40) . "\n";
        }
    }
} catch (Exception $e) {
    var_dump($e->getMessage());
}

echo 'City1'."\n";
try {
    $cityRepository = $services->repositoryFactory()->get(CityRepository::class);

    var_dump($cityRepository->get(3));
    echo str_repeat("-", 40) . "\n";

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
        echo str_repeat("-", 40) . "\n";
    }

    $collection = $query->setParams(['code' => 'BEL'])->query();

    foreach ($collection as $result) {
        var_dump($result);
        echo str_repeat("-", 40) . "\n";
    }
} catch (Exception $e) {
    var_dump($e->getMessage());
}

echo 'City2'."\n";
try {
    $cityRepository = $services->repositoryFactory()->get(CityRepository::class);

    var_dump($cityRepository->get(3));
    echo str_repeat("-", 40) . "\n";

    $preparedQuery = $cityRepository->getPreparedQuery(
        "select c.* from t_city_cit as c
        inner join t_country_cou as co on (c.cou_code = co.cou_code)
        where co.cou_code = :code limit 2"
    );

    $preparedQuery->prepareQuery();
    $collection = $preparedQuery->setParams(['code' => 'FRA'])->query();

    foreach ($collection as $result) {
        var_dump($result);
        echo str_repeat("-", 40) . "\n";
    }

    $collection2 = $preparedQuery->setParams(['code' => 'BEL'])->query();

    foreach ($collection2 as $result) {
        var_dump($result);
        echo str_repeat("-", 40) . "\n";
    }

    foreach ($collection as $result) {
        var_dump($result);
        echo str_repeat("-", 40) . "\n";
    }
} catch (Exception $e) {
    var_dump($e->getMessage());
}


echo 'City3'."\n";
try {
    $cityRepository = $services->repositoryFactory()->get(CityRepository::class);

    $query = $cityRepository->getQuery(
        "select
          c.*, 1 as toto, NOW() as broum,
          co.cou_code, co.cou_name, cou_continent, cou_region, cou_head_of_state,
          col.cou_code, col.col_language, col_is_official, col_percentage
        from t_city_cit as c
        inner join t_country_cou as co on (c.cou_code = co.cou_code)
        inner join t_countrylanguage_col as col on (col.cou_code = co.cou_code)
        where co.cou_code = :code limit 3"
    );
    $query->selectPrimary(true);

    $hydrator = new HydratorSingleObject();
    $hydrator
        ->mapAliasTo('broum', 'c', 'setBroum')
        ->mapAliasTo('toto', 'c', 'setTutu')
        ->mapObjectTo('co', 'c', 'countryIs')
        ->unserializeAliasWith('broum', new DateTime())
        ->mapObjectTo('col', 'co', 'countryLanguageIs');
    $collection = $query->setParams(['code' => 'FRA'])->query($cityRepository->getCollection($hydrator));

    foreach ($collection as $result) {
        var_dump($result);
        echo str_repeat("-", 40) . "\n";
    }
} catch (Exception $e) {
    var_dump($e->getMessage());
}

try {
    $cityRepository = $services->repositoryFactory()->get(CityRepository::class);
    $collection = $cityRepository->getZCountryWithLotsPopulation();

    foreach ($collection as $result) {
        var_dump($result);
        echo str_repeat("-", 40) . "\n";
    }
} catch (Exception $e) {
    var_dump($e->getMessage());
}

try {
    $cityRepository = $services->repositoryFactory()->get(CityRepository::class);
    $nb = $cityRepository->getNumberOfCities();
    var_dump(['initial' => $nb]);
    $cityRepository->startTransaction();
    $query = $cityRepository->getQuery(
        "INSERT INTO t_city_cit
                (cit_name, cit_population) VALUES
                (:name, :pop)"
    );

    $query->setParams(['name' => 'BOUH_TEST', 'pop' => 25000])->execute();
    $cityRepository->rollback();
    $nb = $cityRepository->getNumberOfCities();
    var_dump(['after' => $nb]);
} catch (Exception $e) {
    var_dump($e->getMessage());
}
