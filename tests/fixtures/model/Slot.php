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
 * An entity whose primary key is mutable: a \DateTime
 */
class Slot implements NotifyPropertyInterface
{
    use NotifyProperty;

    protected ?\DateTime $day = null;
    protected ?string $label = null;

    public function getDay(): ?\DateTime
    {
        return $this->day;
    }

    public function setDay(?\DateTime $day): void
    {
        $this->propertyChanged('day', $this->day, $day);
        $this->day = $day;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->propertyChanged('label', $this->label, $label);
        $this->label = $label;
    }
}
