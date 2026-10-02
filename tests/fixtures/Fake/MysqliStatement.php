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
 * Stand-in for \mysqli_stmt in tests: declares what Ting's Mysqli statement uses.
 * Methods do nothing and return null unless mocked.
 */
class MysqliStatement
{
    public $errno = null;
    public $error = null;

    public function bind_param(...$args)
    {
        return null;
    }

    public function close(...$args)
    {
        return null;
    }

    public function execute(...$args)
    {
        return null;
    }

    public function get_result(...$args)
    {
        return null;
    }
}
