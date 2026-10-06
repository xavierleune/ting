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
 * An entity with mutable fields: a JSON object (\stdClass) and a \DateTime
 */
class Document implements NotifyPropertyInterface
{
    use NotifyProperty;

    protected ?int $id = null;
    protected ?string $title = null;
    protected ?\stdClass $payload = null;
    protected ?\DateTime $publishedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->propertyChanged('id', $this->id, $id);
        $this->id = $id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->propertyChanged('title', $this->title, $title);
        $this->title = $title;
    }

    public function getPayload(): ?\stdClass
    {
        return $this->payload;
    }

    public function setPayload(?\stdClass $payload): void
    {
        $this->propertyChanged('payload', $this->payload, $payload);
        $this->payload = $payload;
    }

    public function getPublishedAt(): ?\DateTime
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTime $publishedAt): void
    {
        $this->propertyChanged('publishedAt', $this->publishedAt, $publishedAt);
        $this->publishedAt = $publishedAt;
    }
}
