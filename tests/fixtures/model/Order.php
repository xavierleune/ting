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
 * An entity whose id getter refuses to return an id not generated yet
 */
class Order implements NotifyPropertyInterface
{
    use NotifyProperty;

    protected ?int $id = null;
    protected string $reference = '';

    public function getId(): int
    {
        return $this->id ?? throw new \LogicException('Order not inserted yet');
    }

    public function setId(int $id): void
    {
        $this->propertyChanged('id', $this->id, $id);
        $this->id = $id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): void
    {
        $this->propertyChanged('reference', $this->reference, $reference);
        $this->reference = $reference;
    }
}
