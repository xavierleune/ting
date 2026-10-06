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
     * @param string $propertyName
     * @param mixed $oldValue
     * @param mixed $newValue
     */
    public function propertyChanged($propertyName, $oldValue, $newValue): void
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
     */
    public function __serialize(): array
    {
        $properties = get_mangled_object_vars($this);
        unset($properties["\0*\0listeners"]);

        return $properties;
    }
}
