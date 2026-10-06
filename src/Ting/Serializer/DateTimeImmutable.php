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

use Exception;

class DateTimeImmutable implements SerializerInterface
{
    /**
     * @var array
     * format => always used for serialization. Used first for unserialization when unSerializeUseFormat is true
     * unSerializeUseFormat => true: the value is read with format then, when it does not match, with the PHP
     *     date parser (new \DateTimeImmutable()), so that values written with another format are still read;
     *     false: the value is read with the PHP date parser only. An empty string is never a date
     * @see https://www.php.net/manual/en/datetime.formats.php
     */
    private static array $defaultOptions = ['format' => 'Y-m-d H:i:s', 'unSerializeUseFormat' => true];

    /**
     * @param mixed $toSerialize
     * @param array $options
     * @return string|null
     * @throws RuntimeException
     */
    public function serialize(mixed $toSerialize, array $options = []): ?string
    {
        if ($toSerialize === null) {
            return null;
        }

        if (($toSerialize instanceof \DateTimeImmutable) === false) {
            throw new RuntimeException(
                'Cannot convert this value to DateTimeImmutable. Type was : ' . \gettype($toSerialize) .
                '. Instance of DateTimeImmutable expected.'
            );
        }
        $options = array_merge(self::$defaultOptions, $options);
        return $toSerialize->format($options['format']);
    }

    /**
     * @param string|null $serialized
     * @param array  $options
     * @return \DateTimeImmutable|null
     * @throws RuntimeException
     */
    public function unserialize($serialized, array $options = []): ?\DateTimeImmutable
    {
        if ($serialized === null) {
            return null;
        }

        $options = array_merge(self::$defaultOptions, $options);
        if ($options['unSerializeUseFormat'] === true) {
            $value = \DateTimeImmutable::createFromFormat(self::readFormat($options['format']), (string) $serialized);
            if ($value !== false) {
                return $value;
            }
        }

        // new \DateTimeImmutable('') would be the current time
        if (trim((string) $serialized) === '') {
            throw new RuntimeException('Cannot convert an empty string to DateTimeImmutable.');
        }

        try {
            return new \DateTimeImmutable((string) $serialized);
        } catch (Exception $e) {
            throw new RuntimeException(
                'Cannot convert ' . $serialized . ' to DateTimeImmutable. Error is : ' . $e->getMessage()
            );
        }
    }

    /**
     * Without "!" or "|", createFromFormat() takes the fields missing from the format from the current time: a date
     * read with 'Y-m-d' would get the current time of day
     */
    private static function readFormat(string $format): string
    {
        return str_starts_with($format, '!') || str_contains($format, '|') ? $format : '!' . $format;
    }
}
