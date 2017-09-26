<?php
/***********************************************************************
 *
 * Ting - PHP Datamapper
 * ==========================================
 *
 * Copyright (C) 2014 CCM Benchmark Group. (http://www.ccmbenchmark.com)
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

// ting autoloader
use CCMBenchmark\Ting\Exception;
use CCMBenchmark\Ting\Repository\Collection;
use CCMBenchmark\Ting\Repository\Hydrator;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;
use CCMBenchmark\Ting\Serializer\DateTime;
use CCMBenchmark\Ting\Serializer\Json;
use Doctrine\Common\Cache\MemcachedCache;
use sample\src\model\CityRepository;

require __DIR__ . '/../../vendor/autoload.php';
// sample autoloader
require __DIR__ . '/../vendor/autoload.php';

$services = new \CCMBenchmark\Ting\Services();
$repositories =
    $services
        ->get('MetadataRepository')
        ->batchLoadMetadata('sample\src\model', __DIR__ . '/model/*Repository.php');

echo str_repeat("-", 40) . "\n";
echo 'Load Repositories: ' . count($repositories) . "\n";
echo str_repeat("-", 40) . "\n";

$connections = [
    'main' => [
        'namespace' => '\CCMBenchmark\Ting\Driver\Mysqli',
        'master' => [
            'host'      => 'localhost',
            'user'      => 'world_sample',
            'password'  => 'world_sample',
            'port'      => 3306,
        ],
        'slaves' => [
            [
                'host'      => '127.0.0.1',
                'user'      => 'world_sample',
                'password'  => 'world_sample',
                'port'      => 3306,
            ],
            [
                'host'      => '127.0.1.1', // Loopback : used to have a different connection opened
                'user'      => 'world_sample_readonly',
                'password'  => 'world_sample_readonly',
                'port'      => 3306,
            ]
        ]
    ]
];

$services->get('ConnectionPool')->setConfig($connections);

$memcached = new \Memcached('ting.test');
$memcached->addServer('127.0.0.1', 11211);
$memcached->setOptions(
    [
        \Memcached::OPT_LIBKETAMA_COMPATIBLE => true,
        //\Memcached::OPT_SERIALIZER           => \Memcached::SERIALIZER_IGBINARY
        \Memcached::OPT_SERIALIZER           => \Memcached::SERIALIZER_PHP,
        \Memcached::OPT_PREFIX_KEY           => 'sample-'
    ]
);
$memcachedCache = new MemcachedCache();
$memcachedCache->setMemcached($memcached);

$services->get('Cache')->setCache($memcachedCache);
$services->get('Cache')->save('key', 'storedInCacheValue', 10);
echo 'Test cache : ' . $services->get('Cache')->fetch('key') . "\n";

$cityRepository = $services->get('RepositoryFactory')->get('\sample\src\model\CityRepository');

$query = $cityRepository->getQuery(
    "select
        distinct c.*, col.*
        from t_city_cit as c
        left join t_countrylanguage_col as col on (col.cou_code = 'AFG' and c.cou_code = 'AFG')
        where cit_id < 5
        limit 30"
);

$hydrator = $services->get('HydratorAggregator');
$hydrator->callableIdIs(function ($result) {
    return $result['c']->getId();
});
$hydrator->callableDataIs(function ($result) {
    return $result['col'];
});

$collection = $query->setParams(['code' => 'FRA'])->query(new Collection($hydrator));

foreach ($collection as $result) {
    var_dump($result);
    echo str_repeat("-", 40) . "\n";
}

/**
 * @var $cityRepository CityRepository
 */
$cityRepository = $services->get('RepositoryFactory')->get('\sample\src\model\CityRepository');

$queryCached = $cityRepository->getCachedQuery(
    "select cit_id, cit_name, c.cou_code, cit_district, cit_population, last_modified,
                co.cou_code, cou_name, cou_continent, cou_region, cou_head_of_state
             from t_city_cit as c
            inner join t_country_cou as co on (c.cou_code = co.cou_code)
            where co.cou_code = :code limit 1"
);

$queryCached->setTtl(10)->setCacheKey('cityFRA');
$collection = $queryCached->setParams(['code' => 'FRA'])->query();
echo 'From Cache : ' . (int) $collection->isFromCache() . "\n";
foreach ($collection as $result) {
    var_dump($result['c']->getName());
    echo str_repeat("-", 40) . "\n";
}

echo 'City1'."\n";
try {
    $cityRepository = $services->get('RepositoryFactory')->get('\sample\src\model\CityRepository');

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
    $cityRepository = $services->get('RepositoryFactory')->get('\sample\src\model\CityRepository');

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
    $cityRepository = $services->get('RepositoryFactory')->get('\sample\src\model\CityRepository');

    $query = $cityRepository->getQuery(
        "select
          c.*, SUM(1) as toto, NOW() as broum,
          co.cou_code, co.cou_name, cou_continent, cou_region, cou_head_of_state,
          col.cou_code, col.col_language, col_is_official, col_percentage
        from t_city_cit as c
        inner join t_country_cou as co on (c.cou_code = co.cou_code)
        inner join t_countrylanguage_col as col on (col.cou_code = co.cou_code)
        where co.cou_code = :code limit 3"
    );
    $query->selectMaster(true);

    $hydrator = $services->get('HydratorSingleObject');
    $hydrator
        ->mapAliasTo('broum', 'c', 'setBroum')
        ->mapAliasTo('toto', 'c', 'setTutu')
        ->mapObjectTo('co', 'c', 'countryIs')
        ->unserializeAliasWith('broum', new DateTime())
        ->mapObjectTo('col', 'co', 'countryLanguageIs');
    $collection = $query->setParams(['code' => 'FRA'])->query(new Collection($hydrator));

    foreach ($collection as $result) {
        var_dump($result);
        echo str_repeat("-", 40) . "\n";
    }
} catch (Exception $e) {
    var_dump($e->getMessage());
}

try {
    $cityRepository = $services->get('RepositoryFactory')->get('\sample\src\model\CityRepository');
    $collection = $cityRepository->getZCountryWithLotsPopulation();

    foreach ($collection as $result) {
        var_dump($result);
        echo str_repeat("-", 40) . "\n";
    }
} catch (Exception $e) {
    var_dump($e->getMessage());
}

try {
    $cityRepository = $services->get('RepositoryFactory')->get('\sample\src\model\CityRepository');
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
    var_dump(['apres' => $nb]);
} catch (Exception $e) {
    var_dump($e);
}
