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
     * The object the listeners were added to: this one, unless it is a clone, which copies the listeners and this
     * reference of its original. The unit of work tells a clone of an entity it manages by it.
     *
     * @var \WeakReference<object>|null
     */
    protected ?\WeakReference $listenersOwner = null;

    /**
     * Add an observer to the current object. A listener already added (by the original of a clone) is not added twice.
     */
    public function addPropertyListener(PropertyListenerInterface $listener): void
    {
        if (\in_array($listener, $this->listeners, true) === false) {
            $this->listeners[] = $listener;
        }
        $this->listenersOwner = \WeakReference::create($this);
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
     * Every property but the listeners (and their owner). Names are mangled, so that the private properties of the
     * parent classes are kept, and told apart from a property of the same name in a child class.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): ?array
    {
        $properties = get_mangled_object_vars($this);
        unset($properties["\0*\0listeners"], $properties["\0*\0listenersOwner"]);

        return $properties;
    }

    /**
     * Every property but the listeners (and their owner), with mangled names, as serialize() writes them without
     * __serialize(): unserialize() restores them all, private properties of the parent classes included.
     * An entity defining __sleep() keeps only the properties it lists, as serialize() does: a bare name stands for a
     * property visible from the class of the entity, a private property of a parent class needs its mangled name
     * ("\0Parent\0name"), an uninitialized typed property is left out, and a property which does not exist is reported
     * by the warning of serialize(), raised as an E_USER_WARNING (PHP raises an E_WARNING, which userland code cannot
     * trigger). The listeners and their owner are never kept: an unserialized entity is not a clone.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $properties = get_mangled_object_vars($this);
        unset($properties["\0*\0listeners"], $properties["\0*\0listenersOwner"]);

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

            if (\in_array($name, ['listeners', 'listenersOwner'], true) === false
                && $this->sleepPropertyExists($name) === false
            ) {
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

        // As serialize(): against the class of the entity, its own properties and those of its parents but the private
        // ones (property_exists() would see these from the scope of the class using this trait)
        return (new \ReflectionClass(static::class))->hasProperty($name);
    }
}
