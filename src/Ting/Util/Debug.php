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

use DateTimeInterface;
use Generator;
use NoRewindIterator;
use OuterIterator;
use ReflectionClass;
use ReflectionObject;
use ReflectionProperty;

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
     *
     * Objects are exported as arrays with a __CLASS__ key, down to $maxDepth levels: deeper, an object is replaced
     * by its class and an array by its size. Generators are not iterated, that would consume them, nor the iterators
     * over a generator (IteratorIterator...) and the NoRewindIterator. An iterable with a key which is neither an int
     * nor a string (a WeakMap, an SplObjectStorage-like iterator) is exported as a list of ['key' => ..., 'value' =>
     * ...] pairs.
     */
    public function export(mixed $var, int $maxDepth = 10): mixed
    {
        $return = [];

        if ($maxDepth <= 0) {
            if (\is_object($var)) {
                return $var::class;
            }
            if (\is_array($var)) {
                return 'Array(' . \count($var) . ')';
            }
        }

        if ($this->isIterated($var)) {
            $pairs = [];
            $scalarKeys = true;
            foreach ($var as $key => $subVar) {
                $scalarKeys = $scalarKeys && (\is_int($key) || \is_string($key));
                $pairs[] = ['key' => $key, 'value' => $this->exportItem($subVar, $maxDepth - 1)];
            }

            foreach ($pairs as $pair) {
                if ($scalarKeys) {
                    $return[$pair['key']] = $pair['value'];
                } else {
                    // An object (WeakMap) or another type can't be an array key
                    $return[] = ['key' => $this->exportItem($pair['key'], $maxDepth - 1), 'value' => $pair['value']];
                }
            }
        } elseif (is_object($var)) {
            $return = $this->clean($var, $maxDepth - 1);
        } else {
            $return = $var;
        }

        return $return;
    }

    private function exportItem(mixed $item, int $maxDepth): mixed
    {
        if ($this->isIterated($item)) {
            return $this->export($item, $maxDepth);
        }
        if (\is_object($item)) {
            return $this->clean($item, $maxDepth);
        }

        return $item;
    }

    /**
     * Iterables are iterated, unless that would consume them: a generator, an iterator over one, a NoRewindIterator
     *
     * @phpstan-assert-if-true iterable<mixed> $var
     */
    private function isIterated(mixed $var): bool
    {
        if (is_iterable($var) === false) {
            return false;
        }

        $iterator = $var;
        while ($iterator instanceof OuterIterator) {
            if ($iterator instanceof NoRewindIterator) {
                return false;
            }
            $iterator = $iterator->getInnerIterator();
        }

        return $iterator instanceof Generator === false;
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
        if ($maxDepth <= 0) {
            return $object::class;
        }

        $export = ['__CLASS__' => $object::class];

        // The state of internal objects is not exposed to reflection
        if ($object instanceof DateTimeInterface) {
            $export['date'] = $object->format('Y-m-d\TH:i:s.uP');
            $export['timezone'] = $object->getTimezone()->getName();

            return $export;
        }

        $reflectionObject = new ReflectionObject($object);
        if ($reflectionObject->isInternal()) {
            // What var_dump() shows; non-public properties are prefixed with "\0Class\0" or "\0*\0"
            foreach ((array) $object as $name => $value) {
                $name = (string) $name;
                $position = strrpos($name, "\0");
                $export[$position === false ? $name : substr($name, $position + 1)] = $this->export(
                    $value,
                    $maxDepth - 1
                );
            }

            return $export;
        }

        foreach ($this->getProperties($reflectionObject) as $name => $reflectionProperty) {
            if (
                $reflectionProperty->isStatic()
                || $reflectionProperty->getName() === 'listeners'
                // A virtual hooked property (PHP 8.4) has no value of its own, and may be write-only
                || (\PHP_VERSION_ID >= 80400 && $reflectionProperty->isVirtual()) // @phpstan-ignore method.notFound
                || $reflectionProperty->isInitialized($object) === false
            ) {
                continue;
            }

            $export[$name] = $this->export($reflectionProperty->getValue($object), $maxDepth - 1);
        }

        return $export;
    }

    /**
     * Properties of the object, including the private properties of its parent classes
     *
     * A private property of a parent class shadowed by a property of the same name is keyed "ParentClass::name".
     *
     * @return array<string, ReflectionProperty>
     */
    private function getProperties(ReflectionObject $reflectionObject): array
    {
        $properties = [];
        foreach ($reflectionObject->getProperties() as $reflectionProperty) {
            $properties[$reflectionProperty->getName()] = $reflectionProperty;
        }

        $class = $reflectionObject;
        while (($class = $class->getParentClass()) instanceof ReflectionClass) {
            foreach ($class->getProperties(ReflectionProperty::IS_PRIVATE) as $reflectionProperty) {
                if ($reflectionProperty->getDeclaringClass()->getName() !== $class->getName()) {
                    continue;
                }
                $name = $reflectionProperty->getName();
                $properties[isset($properties[$name]) ? $class->getName() . '::' . $name : $name] = $reflectionProperty;
            }
        }

        return $properties;
    }
}
