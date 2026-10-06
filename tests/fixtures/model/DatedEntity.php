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
 * Date properties of every kind of declared type, for the "datetime" fields without serializer
 */
class DatedEntity extends DatedEntityParent
{
    public ?\DateTimeImmutable $immutable = null;
    public ?\DateTimeInterface $interface = null;
    public ?\DateTime $mutable = null;
    /** @var mixed */
    public $untyped;
    public \DateTimeInterface|string|null $union = null;
    private ?\DateTime $virtualValue = null;

    /**
     * A setter without property of the same name
     */
    public function setVirtual(?\DateTime $value): void
    {
        $this->virtualValue = $value;
    }

    public function getVirtual(): ?\DateTime
    {
        return $this->virtualValue;
    }
}
