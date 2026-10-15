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


namespace tests\fixtures\model;

/**
 * PHP 8.4+: public properties only the class can write, with the methods the Symfony property accessor may use
 */
class AsymmetricVisibilityMutators
{
    public private(set) ?string $magic = null;

    /** @var list<string> */
    public private(set) array $items = [];

    public private(set) ?string $name = null;

    /** @var list<string>|null */
    public private(set) ?array $tags = null;

    public function __set(string $property, mixed $value): void
    {
        throw new \LogicException('__set() should not be called');
    }

    public function addItem(string $item): void
    {
        $this->items[] = $item . ' (adder)';
    }

    public function removeItem(string $item): void
    {
        $this->items = array_values(array_diff($this->items, [$item]));
    }

    public function addTag(string $tag): void
    {
        $this->tags[] = $tag . ' (adder)';
    }

    public function removeTag(string $tag): void
    {
        $this->tags = array_values(array_diff($this->tags ?? [], [$tag]));
    }

    /** A getter-setter */
    public function name(?string $name): void
    {
        $this->name = $name . ' (getter-setter)';
    }
}
