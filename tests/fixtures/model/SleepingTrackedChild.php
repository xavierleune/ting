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
 * Keeps its own title and the owner of its parent, but not its cache (__sleep()), and counts __wakeup() calls
 */
class SleepingTrackedChild extends TrackedBase
{
    public static int $wakeups = 0;

    private ?string $title = null;

    protected ?string $cache = null;

    public ?int $uninitialized;

    /** @var list<string> */
    public array $sleep = ['title', "\0" . TrackedBase::class . "\0owner", 'uninitialized'];

    public function setTitle(?string $title): void
    {
        $this->propertyChanged('title', $this->title, $title);
        $this->title = $title;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setCache(?string $cache): void
    {
        $this->cache = $cache;
    }

    public function getCache(): ?string
    {
        return $this->cache;
    }

    /**
     * @return list<string>
     */
    public function __sleep(): array
    {
        return $this->sleep;
    }

    public function __wakeup(): void
    {
        self::$wakeups++;
    }
}
