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

namespace CCMBenchmark\Ting\Util;

use ReflectionObject;

class Debug
{
    /**
     * Dump Ting object
     */
    public function dump(mixed $var, int $maxDepth = 10): void
    {
        var_dump($this->export($var, $maxDepth));
    }

    /**
     * Export Ting object
     */
    public function export(mixed $var, int $maxDepth = 10): mixed
    {
        $return = [];

        if ($maxDepth === 0) {
            if (\is_object($var)) {
                return $var::class;
            }
            if (\is_array($var)) {
                return 'Array(' . \count($var) . ')';
            }
        }

        if (is_iterable($var)) {
            foreach ($var as $key => $subVar) {
                if (is_iterable($subVar)) {
                    $return[$key] = $this->export($subVar, $maxDepth - 1);
                } elseif (\is_object($subVar)) {
                    $return[$key] = $this->clean($subVar, $maxDepth - 1);
                } else {
                    $return[$key] = $subVar;
                }
            }
        } elseif (is_object($var)) {
            $return = $this->clean($var, $maxDepth - 1);
        } else {
            $return = $var;
        }

        return $return;
    }

    /**
     * Describe an object as an array, without its property listeners
     *
     * The object itself is left untouched: typed properties can't hold the exported values.
     *
     * @return array<string, mixed>|class-string
     */
    private function clean(object $object, int $maxDepth): array|string
    {
        if ($maxDepth === 0) {
            return $object::class;
        }

        $export = ['__CLASS__' => $object::class];
        foreach ((new ReflectionObject($object))->getProperties() as $reflectionProperty) {
            if (
                $reflectionProperty->isStatic()
                || $reflectionProperty->getName() === 'listeners'
                || $reflectionProperty->isInitialized($object) === false
            ) {
                continue;
            }

            $export[$reflectionProperty->getName()] = $this->export(
                $reflectionProperty->getValue($object),
                $maxDepth - 1
            );
        }

        return $export;
    }
}
