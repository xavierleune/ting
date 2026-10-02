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

namespace CCMBenchmark\Ting\Tests\Support;

/**
 * Overrides native functions called (unqualified) from Ting namespaces.
 *
 * Only functions declared in native_functions.php can be overridden. Overrides are reset after each test
 * by Support\TestCase.
 */
final class NativeFunctionMock
{
    /** @var array<string, mixed> */
    private static array $overrides = [];

    /** @var array<string, true> */
    private static array $declared = [];

    public static function declare(string $function): void
    {
        self::$declared[$function] = true;
    }

    /**
     * @param mixed $return A Closure receives the call arguments and its result is returned, any other value is returned as is
     */
    public static function override(string $function, mixed $return): void
    {
        if (!isset(self::$declared[$function])) {
            throw new \UnexpectedValueException(
                'Trying to override a function not declared in tests/Support/native_functions.php: ' . $function
            );
        }
        self::$overrides[$function] = $return;
    }

    public static function reset(): void
    {
        self::$overrides = [];
    }

    public static function call(string $function, array $args): mixed
    {
        if (!array_key_exists($function, self::$overrides)) {
            return ('\\' . $function)(...$args);
        }
        if (self::$overrides[$function] instanceof \Closure) {
            return self::$overrides[$function](...$args);
        }

        return self::$overrides[$function];
    }
}
