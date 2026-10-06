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

namespace CCMBenchmark\Ting\Serializer;

use JsonException;
use stdClass;

class Json implements SerializerInterface, ArrayValueInterface
{
    public const JSON_DEFAULT_DEPTH   = 512;
    public const JSON_DEFAULT_OPTIONS = 0;

    /**
     * @param mixed $toSerialize
     * @param array{options?: int, depth?: int<1, max>} $options options and depth given to json_encode()
     * @throws RuntimeException
     */
    public function serialize($toSerialize, array $options = []): ?string
    {
        if ($toSerialize === null) {
            return null;
        }

        $jsonOptions = isset($options['options']) === true ? $options['options'] : self::JSON_DEFAULT_OPTIONS;

        $jsonDepth = isset($options['depth']) === true ? $options['depth'] : self::JSON_DEFAULT_DEPTH;

        // Always thrown, whatever the options: without it, json_last_error() would be read, which JSON_THROW_ON_ERROR
        // given in the options leaves as the previous call set it. JSON_PARTIAL_OUTPUT_ON_ERROR still takes
        // precedence and returns the partial output.
        try {
            $json = json_encode($toSerialize, $jsonOptions | JSON_THROW_ON_ERROR, $jsonDepth);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Could not convert value to json. Error was : ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        return $json;
    }

    /**
     * @param string|null $serialized
     * @param array{assoc?: bool|null, depth?: int<1, max>, options?: int} $options given to json_decode(); without
     *                                                                      assoc (or null), objects are decoded to
     *                                                                      arrays only with JSON_OBJECT_AS_ARRAY
     * @return null|stdClass|array<mixed>
     * @throws RuntimeException
     */
    public function unserialize($serialized, array $options = []): mixed
    {
        // The empty string decodes to null, as in 3.x
        if ($serialized === null || $serialized === '') {
            return null;
        }

        // null, as json_decode(): JSON_OBJECT_AS_ARRAY in the options decides
        $jsonAssoc = $options['assoc'] ?? null;

        $jsonDepth = isset($options['depth']) === true ? $options['depth'] : self::JSON_DEFAULT_DEPTH;

        $jsonOptions = isset($options['options']) === true ? $options['options'] : self::JSON_DEFAULT_OPTIONS;

        // Always thrown, whatever the options (see serialize())
        try {
            $value = json_decode($serialized, $jsonAssoc, $jsonDepth, $jsonOptions | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(
                'Could not decode value from json. Error was : ' . $exception->getMessage() . ' on ' . $serialized,
                0,
                $exception
            );
        }

        return $value;
    }
}
