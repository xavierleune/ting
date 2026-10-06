<?php

/***********************************************************************
 *
 * Ting - PHP Datamapper
 * ==========================================
 *
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

namespace tests\fixtures\Serializer;

use CCMBenchmark\Ting\Serializer\Json;

/**
 * Json serializer counting its calls. A serializer of its own: its fields are mutable by default.
 */
class CountingJson extends Json
{
    public static int $serialized = 0;
    public static int $unserialized = 0;

    public static function resetCounters(): void
    {
        self::$serialized = 0;
        self::$unserialized = 0;
    }

    public function serialize($toSerialize, array $options = []): ?string
    {
        self::$serialized++;

        return parent::serialize($toSerialize, $options);
    }

    public function unserialize($serialized, array $options = []): mixed
    {
        self::$unserialized++;

        return parent::unserialize($serialized, $options);
    }
}
