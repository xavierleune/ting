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

namespace CCMBenchmark\Ting\Serializer;

/**
 * Reads the values of Serializer\DateTime and Serializer\DateTimeImmutable: with their format, then with the formats
 * databases return, not with the PHP date parser (it reads "now" or "+1 day", and "05/06/2024" as May 6th)
 *
 * @internal
 */
trait DatabaseDateReader
{
    /**
     * A date (Y-m-d) and, optionally, a time (H:i:s) with optional fractional seconds and UTC offset (+01, +0100,
     * +01:00, Z): a MySQL DATE / DATETIME, a PostgreSQL date / timestamp / timestamptz. With a "T" separator, the
     * offset is required (ATOM, RFC 3339)
     */
    private const DATABASE_DATE = '/^\d{4}-\d{2}-\d{2}'
        . '(?:(?<separator>[ T])\d{2}:\d{2}:\d{2}(?<fraction>\.\d{1,6})?(?<offset>Z|[+-]\d{2}(?::?\d{2})?)?)?$/';

    /**
     * @template D of \DateTime|\DateTimeImmutable
     * @param class-string<D> $class
     * @return D|null null when the value matches neither the format nor a database format
     */
    private static function readDate(string $class, string $value, string $format): ?object
    {
        $date = $class::createFromFormat(self::readFormat($format), $value);
        if ($date !== false) {
            return $date;
        }

        if (preg_match(self::DATABASE_DATE, $value, $matches) !== 1) {
            return null;
        }

        $separator = $matches['separator'] ?? '';
        $offset = $matches['offset'] ?? '';
        if ($separator === 'T' && $offset === '') {
            return null;
        }

        $databaseFormat = '!Y-m-d';
        if ($separator !== '') {
            $databaseFormat .= ($separator === 'T' ? '\T' : ' ') . 'H:i:s'
                . (($matches['fraction'] ?? '') !== '' ? '.u' : '')
                . ($offset !== '' ? 'P' : '');
        }

        $date = $class::createFromFormat($databaseFormat, $value);

        return $date === false ? null : $date;
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
