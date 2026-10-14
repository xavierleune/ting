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

use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;

/**
 * PHP 8.4+: the autoincrement property is virtual, its value kept in another property. Its set hook names the entity
 * after the id once the id is stored, then throws ($throwing)
 */
class VirtualIdEntity implements NotifyPropertyInterface
{
    use NotifyProperty;

    public bool $throwing = true;
    public ?string $name = null;

    /** @var array<string, int|null> */
    private array $data = [];

    public ?int $id {
        get => $this->data['id'] ?? null;
        set {
            $this->data['id'] = $value;
            $this->propertyChanged('name', $this->name, 'item-' . $value);
            $this->name = 'item-' . $value;
            if ($this->throwing) {
                throw new \LogicException('Forced failure of setId()');
            }
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }
}
