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

/**
 * @phpstan-import-type SerializerOptions from SerializeInterface
 */
class Ip implements SerializerInterface, ScalarValueInterface
{
    /**
     * @param mixed $toSerialize
     * @param SerializerOptions $options unused
     * @throws RuntimeException
     */
    public function serialize($toSerialize, array $options = []): ?int
    {
        if ($toSerialize === null) {
            return null;
        }

        $value = ip2long($toSerialize);

        if ($value === false) {
            throw new RuntimeException('IPv4 Internet network address is invalid');
        }

        return $value;
    }

    /**
     * @param mixed $serialized an integer, or a string holding one, from -2147483648 to 4294967295 (a signed or an
     *                          unsigned 32 bits column)
     * @param SerializerOptions $options unused
     * @throws RuntimeException when $serialized is not such an integer
     */
    public function unserialize($serialized, array $options = []): null|string|bool
    {
        if ($serialized === null) {
            return null;
        }

        // long2ip() would throw a TypeError on 'abc', truncate '1.5' with a deprecation and wrap beyond 32 bits
        $value = false;
        if (is_int($serialized) || (is_string($serialized) && trim($serialized) === $serialized)) {
            $value = filter_var(
                $serialized,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => -2147483648, 'max_range' => 4294967295]]
            );
        }
        if ($value === false) {
            throw new RuntimeException('IPv4 Internet network address is invalid: an integer is expected');
        }

        return long2ip($value);
    }
}
