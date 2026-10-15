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

namespace CCMBenchmark\Ting\Query;

use CCMBenchmark\Ting\Driver\Placeholder;

/**
 * Converts the parameters of a query before they reach the driver:
 * - a \DateTimeInterface is sent as Y-m-d H:i:s (the default format of Serializer\DateTime), without timezone
 *   conversion;
 * - a \BackedEnum is sent as its value; a pure enum has none and is refused;
 * - an array is an IN list: each placeholder of the parameter in the SQL becomes one placeholder per element
 *   (:ids becomes :ids__1, :ids__2), each element converted as above;
 * - null, scalars and \Stringable objects are sent as they are (a \Stringable \DateTimeInterface too, as before).
 *
 * The SQL changes only when a parameter is an array, and is then the same for lists of the same length: a prepared
 * query prepares one statement per list length.
 *
 * @internal used by the queries
 */
final class ParameterConverter
{
    /**
     * Separator between the name of a list parameter and the position of an element, as Generator names its IN lists
     */
    private const LIST_SEPARATOR = '__';

    /**
     * @param array<string, mixed> $params parameter name => value
     * @return array{0: string, 1: array<string, mixed>} the SQL and the parameters to give to the driver
     * @throws QueryException when a value cannot be sent
     */
    public static function convert(string $sql, array $params): array
    {
        // The queries built by Generator, and most queries, only have values sent as they are: no copy, no regex
        foreach ($params as $value) {
            if ($value !== null && !\is_scalar($value) && !$value instanceof \Stringable) {
                return self::convertParameters($sql, $params);
            }
        }

        return [$sql, $params];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{0: string, 1: array<string, mixed>}
     * @throws QueryException
     */
    private static function convertParameters(string $sql, array $params): array
    {
        $converted = [];
        // List parameter name => the placeholders replacing it
        $lists = [];
        // Generated parameter name => list parameter name
        $generated = [];

        foreach ($params as $name => $value) {
            $name = (string) $name;
            if (!\is_array($value)) {
                $converted[$name] = self::convertValue($name, $value);

                continue;
            }

            if ($value === []) {
                throw new QueryException(sprintf(
                    'The parameter "%s" is an empty array: an empty IN list is not valid SQL, do not run the query'
                    . ' (or write a condition that is always false) when there is no value',
                    $name
                ));
            }
            $placeholders = [];
            $position = 0;
            foreach ($value as $element) {
                $position++;
                if ($element === null) {
                    throw new QueryException(sprintf(
                        'The element %d of the parameter "%s" is null: an IN list never matches NULL, remove it and'
                        . ' test IS NULL in its own condition',
                        $position,
                        $name
                    ));
                }
                if (\is_array($element)) {
                    throw new QueryException(sprintf(
                        'The element %d of the parameter "%s" is an array: an IN list holds values, not lists',
                        $position,
                        $name
                    ));
                }
                $elementName = $name . self::LIST_SEPARATOR . $position;
                $converted[$elementName] = self::convertValue($name, $element, $position);
                $generated[$elementName] = $name;
                $placeholders[] = ':' . $elementName;
            }
            $lists[$name] = implode(', ', $placeholders);
        }

        if ($lists === []) {
            return [$sql, $converted];
        }

        // A name generated for an element cannot be the name of another parameter: a value would be lost
        foreach (array_intersect_key($generated, $params) as $elementName => $name) {
            throw self::collision((string) $elementName, $name);
        }

        $sql = preg_replace_callback(
            '/' . Placeholder::PATTERN . '/',
            static function (array $match) use ($lists, $generated): string {
                $name = $match[1];
                if (isset($lists[$name])) {
                    return $lists[$name];
                }
                if (isset($generated[$name])) {
                    // A placeholder of the SQL would silently receive an element of the list
                    throw self::collision($name, $generated[$name]);
                }

                return $match[0];
            },
            $sql
        );
        if ($sql === null) {
            throw new QueryException('Cannot parse the parameters of the query: ' . preg_last_error_msg());
        }

        return [$sql, $converted];
    }

    /**
     * @param int|null $position position of the element in its list parameter, null for a parameter
     * @throws QueryException
     */
    private static function convertValue(string $name, mixed $value, ?int $position = null): mixed
    {
        if ($value === null || \is_scalar($value) || $value instanceof \Stringable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        $subject = $position === null
            ? sprintf('The parameter "%s"', $name)
            : sprintf('The element %d of the parameter "%s"', $position, $name);
        if ($value instanceof \UnitEnum) {
            throw new QueryException(sprintf(
                '%s is the case %s::%s of a pure enum, which has no value to send: use a backed enum, or give a value',
                $subject,
                $value::class,
                $value->name
            ));
        }
        if (\is_object($value)) {
            throw new QueryException(sprintf(
                '%s is an object of class %s, which cannot be sent: give a scalar, null, a \DateTimeInterface, a'
                . ' \BackedEnum, a \Stringable object or an array (IN list)',
                $subject,
                $value::class
            ));
        }

        // A resource: given to the driver as before
        return $value;
    }

    private static function collision(string $elementName, string $listName): QueryException
    {
        return new QueryException(sprintf(
            'The parameter "%s" is a list whose elements are sent as %s: "%s" is also another parameter or'
            . ' placeholder of the query, rename one of them',
            $listName,
            $listName . self::LIST_SEPARATOR . '1, ...',
            $elementName
        ));
    }
}
