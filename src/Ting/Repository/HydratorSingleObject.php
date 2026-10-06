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

namespace CCMBenchmark\Ting\Repository;

use Generator;

use function reset;

/**
 * Hydrates each row into its first value: the entity of the first table of the query, null when a LEFT JOIN matched
 * nothing, the stdClass of the virtual columns when no table has metadata
 *
 * @template T type of the items, as documented by the caller (e.g. the entity of the repository)
 *
 * @template-extends Hydrator<T>
 */
class HydratorSingleObject extends Hydrator
{
    /**
     * @return Generator<int, object|false|null> false for a row without value
     */
    public function getIterator(): Generator
    {
        foreach ($this->hydratedRows() as $key => $data) {
            yield $key => reset($data);
        }
    }
}
