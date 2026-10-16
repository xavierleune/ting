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
 * A virtual property written through its set hook (PHP 8.4): a value its hook refuses is converted or reported by
 * the Symfony property accessor, as a setter
 */
class VirtualDateHookEntity
{
    public ?\DateTimeImmutable $at = null;
    public int $calls = 0;

    public ?\DateTimeImmutable $createdAt {
        get => $this->at;
        set(?\DateTimeImmutable $value) {
            $this->calls++;
            $this->at = $value;
        }
    }
}
