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

namespace CCMBenchmark\Ting\Entity;

trait NotifyProperty
{
    /** @var list<PropertyListenerInterface> */
    protected array $listeners = [];

    /**
     * Add an observer to the current object
     */
    public function addPropertyListener(PropertyListenerInterface $listener): void
    {
        $this->listeners[] = $listener;
    }

    /**
     * Notify all observers with old and new values.
     * The same value given as old and new value, objects included, is not a change: nothing is notified.
     */
    public function propertyChanged(string $propertyName, mixed $oldValue, mixed $newValue): void
    {
        if ($oldValue === $newValue) {
            return;
        }

        foreach ($this->listeners as $listener) {
            $listener->propertyChanged($this, $propertyName, $oldValue, $newValue);
        }
    }

    /**
     * Every property but the listeners. Names are mangled, so that the private properties of the parent classes are
     * kept, and told apart from a property of the same name in a child class.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): ?array
    {
        $properties = get_mangled_object_vars($this);
        unset($properties["\0*\0listeners"]);

        return $properties;
    }

    /**
     * Every property but the listeners, with mangled names, as serialize() writes them without __serialize():
     * unserialize() restores them all, private properties of the parent classes included.
     * An entity defining __sleep() keeps only the properties it lists, as serialize() does: a bare name stands for a
     * property visible from the class of the entity, a private property of a parent class needs its mangled name
     * ("\0Parent\0name"), an uninitialized typed property is left out, and a property which does not exist is reported
     * by a warning. The listeners are never kept.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $properties = get_mangled_object_vars($this);
        unset($properties["\0*\0listeners"]);

        // A magic method, not declared by any interface: method_exists() is the way PHP itself looks for it
        if (method_exists($this, '__sleep') === false) {
            return $properties;
        }

        $kept = [];
        foreach ($this->__sleep() as $name) {
            foreach ([$name, "\0" . static::class . "\0" . $name, "\0*\0" . $name] as $key) {
                if (array_key_exists($key, $properties)) {
                    $kept[$key] = $properties[$key];
                    continue 2;
                }
            }

            if ($name !== 'listeners' && $this->sleepPropertyExists($name) === false) {
                trigger_error(
                    'serialize(): "' . $name . '" returned as member variable from __sleep() but does not exist',
                    E_USER_WARNING
                );
            }
        }

        return $kept;
    }

    /**
     * @param string $name a name returned by __sleep(): bare, or mangled ("\0Class\0name", "\0*\0name")
     */
    private function sleepPropertyExists(string $name): bool
    {
        if (str_starts_with($name, "\0")) {
            $parts = explode("\0", $name);
            if (count($parts) !== 3) {
                return false;
            }

            return property_exists($parts[1] === '*' ? $this : $parts[1], $parts[2]);
        }

        return property_exists($this, $name);
    }
}
