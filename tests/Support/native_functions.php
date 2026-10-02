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

// Native functions that tests can override through NativeFunctionMock.
// Ting calls them unqualified, so PHP resolves these namespaced versions first.

namespace CCMBenchmark\Ting\Driver\Pgsql {

    use CCMBenchmark\Ting\Tests\Support\NativeFunctionMock;

    NativeFunctionMock::declare('pg_last_error');
    function pg_last_error(...$args)
    {
        return NativeFunctionMock::call('pg_last_error', $args);
    }

    NativeFunctionMock::declare('pg_execute');
    function pg_execute(...$args)
    {
        return NativeFunctionMock::call('pg_execute', $args);
    }

    NativeFunctionMock::declare('pg_query');
    function pg_query(...$args)
    {
        return NativeFunctionMock::call('pg_query', $args);
    }

    NativeFunctionMock::declare('pg_set_client_encoding');
    function pg_set_client_encoding(...$args)
    {
        return NativeFunctionMock::call('pg_set_client_encoding', $args);
    }

    NativeFunctionMock::declare('pg_connect');
    function pg_connect(...$args)
    {
        return NativeFunctionMock::call('pg_connect', $args);
    }

    NativeFunctionMock::declare('pg_close');
    function pg_close(...$args)
    {
        return NativeFunctionMock::call('pg_close', $args);
    }

    NativeFunctionMock::declare('pg_ping');
    function pg_ping(...$args)
    {
        return NativeFunctionMock::call('pg_ping', $args);
    }

    NativeFunctionMock::declare('pg_result_status');
    function pg_result_status(...$args)
    {
        return NativeFunctionMock::call('pg_result_status', $args);
    }

    NativeFunctionMock::declare('pg_result_seek');
    function pg_result_seek(...$args)
    {
        return NativeFunctionMock::call('pg_result_seek', $args);
    }

    NativeFunctionMock::declare('pg_field_table');
    function pg_field_table(...$args)
    {
        return NativeFunctionMock::call('pg_field_table', $args);
    }

    NativeFunctionMock::declare('pg_fetch_row');
    function pg_fetch_row(...$args)
    {
        return NativeFunctionMock::call('pg_fetch_row', $args);
    }

    NativeFunctionMock::declare('pg_fetch_assoc');
    function pg_fetch_assoc(...$args)
    {
        return NativeFunctionMock::call('pg_fetch_assoc', $args);
    }

    NativeFunctionMock::declare('pg_prepare');
    function pg_prepare(...$args)
    {
        return NativeFunctionMock::call('pg_prepare', $args);
    }

    NativeFunctionMock::declare('pg_num_rows');
    function pg_num_rows(...$args)
    {
        return NativeFunctionMock::call('pg_num_rows', $args);
    }

    NativeFunctionMock::declare('pg_query_params');
    function pg_query_params(...$args)
    {
        return NativeFunctionMock::call('pg_query_params', $args);
    }

    NativeFunctionMock::declare('pg_fetch_array');
    function pg_fetch_array(...$args)
    {
        return NativeFunctionMock::call('pg_fetch_array', $args);
    }

    NativeFunctionMock::declare('pg_num_fields');
    function pg_num_fields(...$args)
    {
        return NativeFunctionMock::call('pg_num_fields', $args);
    }

    NativeFunctionMock::declare('pg_errormessage');
    function pg_errormessage(...$args)
    {
        return NativeFunctionMock::call('pg_errormessage', $args);
    }

    NativeFunctionMock::declare('pg_affected_rows');
    function pg_affected_rows(...$args)
    {
        return NativeFunctionMock::call('pg_affected_rows', $args);
    }

    NativeFunctionMock::declare('pg_field_name');
    function pg_field_name(...$args)
    {
        return NativeFunctionMock::call('pg_field_name', $args);
    }

}

namespace CCMBenchmark\Ting\Driver\Mysqli {

    use CCMBenchmark\Ting\Tests\Support\NativeFunctionMock;

    NativeFunctionMock::declare('mysqli_init');
    function mysqli_init(...$args)
    {
        return NativeFunctionMock::call('mysqli_init', $args);
    }
}

namespace CCMBenchmark\Ting\Serializer {

    use CCMBenchmark\Ting\Tests\Support\NativeFunctionMock;

    NativeFunctionMock::declare('class_exists');
    function class_exists(...$args)
    {
        return NativeFunctionMock::call('class_exists', $args);
    }
}
