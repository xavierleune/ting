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

namespace tests\fixtures\ValueObject;

/**
 * Value object for HydratorValueObject: its properties are set from the columns, then the constructor is called
 * without arguments
 */
class Person
{
    private ?string $firstname = null;

    private ?string $name = null;

    private string $fullName = '';

    public function __construct()
    {
        $this->fullName = trim($this->firstname . ' ' . $this->name);
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }
}
