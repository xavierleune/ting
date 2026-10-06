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

use CCMBenchmark\Ting\Exception;
use sample\src\model\CityRepository;

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
        'namespace' => '\CCMBenchmark\Ting\Driver\Pgsql',
        'primary'   => [
            'host'      => 'localhost',
            'user'      => 'postgres',
            'password'  => '',
            'port'      => 5432
        ]
    ],
];

$services->connectionPool()->setConfig($connections);

$options = [
    'world' => [
        'timezone' => 'Europe/Paris'
    ]
];
$services->connectionPool()->setDatabaseOptions($options);


try {
    $cityRepository = $services->repositoryFactory()->get(CityRepository::class);
    var_dump($cityRepository->get(['id' => 3]));
    echo str_repeat("-", 40) . "\n";

    $query = $cityRepository->getQuery(
        'select
            c.cit_id, c.cit_name, c.cou_code, c.cit_district, c.cit_population
        from t_city_cit as c
        where c.cou_code = :code limit 1'
    );

    $collection = $query->setParams(['code' => 'FRA'])->query();

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

    echo str_repeat("-", 40) . "\n";

    $query = $cityRepository->getCachedPreparedQuery(
        'select
                cit_id, cit_name, c.cou_code, cit_district, cit_population, last_modified,
                co.cou_code, cou_name, cou_continent, cou_region, cou_head_of_state
            from t_city_cit as c
            inner join t_country_cou as co on (c.cou_code = co.cou_code)
            where co.cou_code = :code limit 1'
    );

    $query->setTtl(10)->setCacheKey('cit_FRA_pgsql');

    $collection = $query->setParams(['code' => 'FRA'])->query();
    echo 'From Cache : ' . (int) $collection->isFromCache() . "\n";

    foreach ($collection as $result) {
        var_dump($result);
        echo str_repeat("-", 40) . "\n";
    }
} catch (Exception $e) {
    var_dump($e->getMessage());
}
