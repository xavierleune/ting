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

require __DIR__ . '/../vendor/autoload.php';

// Namespaced overrides of native functions must be declared before any code calling them runs:
// PHP caches the resolved function per call site, so a late declaration would be silently ignored.
require __DIR__ . '/Support/native_functions.php';

// Xdebug's develop mode keeps references to the last thrown exceptions, and through their traces to the objects of
// past tests: their destructors (e.g. Pgsql\Statement calling pg_query) then run in later tests, outside of the
// overrides they relied on.
if (\function_exists('xdebug_info') && \in_array('develop', xdebug_info('mode'), true)) {
    fwrite(STDERR, "Warning: Xdebug develop mode can make tests fail randomly, run them with `composer test` "
        . "(or XDEBUG_MODE=off).\n\n");
}
