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

namespace CCMBenchmark\Ting\Driver;

/**
 * The named placeholders (:name) of the SQL, as the Mysqli, Pgsql and SphinxQL drivers find them, and as the queries
 * find the placeholders of a list parameter to expand (Query\ParameterConverter): one definition, so that they all see
 * the same placeholders
 *
 * @internal
 */
final class Placeholder
{
    /**
     * PCRE pattern without delimiters, group 1 is the name of the parameter
     *
     * Match : values (:name)
     * Don't match : values (\:name)
     * Don't match : HH:MI:SS
     * Don't match : ::string
     */
    public const PATTERN = '(?<!\b)(?<![:\\\\]):(#?[a-zA-Z0-9_-]+)';
}
