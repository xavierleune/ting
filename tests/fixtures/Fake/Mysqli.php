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

namespace tests\fixtures\Fake;

/**
 * Stand-in for \mysqli in tests: declares what Ting's Mysqli driver uses, without connecting anywhere.
 * Methods do nothing and return null unless mocked.
 */
class Mysqli
{
    public $affected_rows = null;
    public $errno = null;
    public $error = null;
    public $insert_id = null;

    public function begin_transaction(...$args)
    {
        return null;
    }

    public function close(...$args)
    {
        return null;
    }

    public function commit(...$args)
    {
        return null;
    }

    public function options(...$args)
    {
        return null;
    }

    public function prepare(...$args)
    {
        return null;
    }

    public function query(...$args)
    {
        return null;
    }

    public function real_connect(...$args)
    {
        return null;
    }

    public function real_escape_string(...$args)
    {
        return null;
    }

    public function rollback(...$args)
    {
        return null;
    }

    public function select_db(...$args)
    {
        return null;
    }

    public function set_charset(...$args)
    {
        return null;
    }
}
