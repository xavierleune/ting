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

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function tearDown(): void
    {
        NativeFunctionMock::reset();
        parent::tearDown();
    }

    /**
     * Asserts that $callable throws, without ending the test (unlike expectException).
     *
     * @template T of \Throwable
     * @param class-string<T> $class
     * @return T
     */
    protected function assertThrows(string $class, callable $callable, ?string $message = null): \Throwable
    {
        try {
            $callable();
        } catch (\Throwable $throwable) {
            $this->assertInstanceOf($class, $throwable);
            if ($message !== null) {
                $this->assertSame($message, $throwable->getMessage());
            }

            return $throwable;
        }

        $this->fail(sprintf('Failed asserting that %s is thrown.', $class));
    }

    /**
     * Runs $callable and returns the types (E_*) of the PHP errors it raised.
     * A throwable thrown by $callable is caught and stored in $thrown.
     *
     * @return list<int>
     */
    protected function collectErrorTypes(callable $callable, ?\Throwable &$thrown = null): array
    {
        $types = [];
        set_error_handler(static function (int $type) use (&$types): bool {
            $types[] = $type;

            return true;
        });
        try {
            $callable();
        } catch (\Throwable $throwable) {
            $thrown = $throwable;
        } finally {
            restore_error_handler();
        }

        return $types;
    }

    /**
     * Runs $callable and returns the messages of the E_USER_DEPRECATED it triggered, silenced or not
     * (a custom error handler is called even for errors silenced with @).
     *
     * @return list<string>
     */
    protected function collectDeprecations(callable $callable): array
    {
        $messages = [];
        set_error_handler(static function (int $type, string $message) use (&$messages): bool {
            $messages[] = $message;

            return true;
        }, E_USER_DEPRECATED);
        try {
            $callable();
        } finally {
            restore_error_handler();
        }

        return $messages;
    }
}
