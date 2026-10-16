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
 * Typed setters and public properties, written by the property accessor: a value of another type is refused, a date
 * converted as the Symfony property accessor converts it
 */
class TypedMutatorsEntity
{
    public int $setAgeCalls = 0;
    public int $count = 0;
    public ?\DateTime $date = null;
    public ?\DateTime $createdAt = null;
    private int $age = 0;

    public function getAge(): int
    {
        return $this->age;
    }

    public function setAge(int $age): void
    {
        $this->setAgeCalls++;
        $this->age = $age;
    }

    public function setCreated(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /** Declared in lower case: PHP methods are case-insensitive, the accessor finds it as setUpdatedAt() */
    public function setupdatedat(\DateTime $updatedAt): void // phpcs:ignore
    {
        $this->createdAt = $updatedAt;
    }

    public function setFailing(string $value): void
    {
        $this->setAgeCalls++;
        throw new \TypeError('Refused by the setter itself');
    }

    public function setBadReturn(string $value): static
    {
        $this->setAgeCalls++;
        return $value; // @phpstan-ignore return.type
    }
}
