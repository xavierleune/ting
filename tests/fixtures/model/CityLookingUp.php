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
 * A city whose setName() runs a lookup (a read nested in the hydration of its row)
 */
class CityLookingUp extends City
{
    /** @var (\Closure(string|null): void)|null run by setName(), once: cleared before it runs */
    public static ?\Closure $lookup = null;

    public function setName($name)
    {
        parent::setName($name);

        $lookup = self::$lookup;
        self::$lookup = null;
        if ($lookup !== null) {
            $lookup($name);
        }
    }
}
