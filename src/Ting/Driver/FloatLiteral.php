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

namespace CCMBenchmark\Ting\Driver;

/**
 * @internal
 */
final class FloatLiteral
{
    /**
     * The decimal form of a finite float that reads back as the same float, as MySQL and PostgreSQL read it, with the
     * fewest significant digits from 15 to 17 (1234567.123456789, 0.1, 1.0E+25, -0): (string) keeps only `precision`
     * significant digits (14 by default), and var_export() depends on `serialize_precision`
     *
     * %H ignores the locale (always a '.'), and switches to an exponent from 15 digits before the point
     */
    public static function format(float $value): string
    {
        for ($digits = 15; $digits < 17; $digits++) {
            $literal = sprintf('%.' . $digits . 'H', $value);
            if ((float) $literal === $value) {
                return $literal;
            }
        }

        return sprintf('%.17H', $value);
    }
}
