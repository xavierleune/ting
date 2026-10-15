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
 * PHP 8.4+: public properties only the class can write
 */
class AsymmetricVisibilityEntity
{
    public private(set) ?string $privateSet = null;

    public protected(set) ?string $protectedSet = null;

    public protected(set) ?string $hookedProtectedSet = null {
        set(?string $value) {
            $this->hookedProtectedSet = $value . ' (hooked on set)';
        }
    }

    /** Virtual: its value is kept in another property */
    public private(set) ?string $virtualPrivateSet {
        get => $this->stored;
        set(?string $value) {
            $this->stored = $value . ' (hooked on set)';
        }
    }

    public private(set) ?string $withSetter = null;

    private ?string $stored = null;

    public function setWithSetter(?string $withSetter): void
    {
        $this->withSetter = $withSetter . ' (setter)';
    }
}
