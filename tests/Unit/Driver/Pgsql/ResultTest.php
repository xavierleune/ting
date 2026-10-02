<?php

/***********************************************************************
 *
 * Ting - PHP Datamapper
 * ==========================================
 *
 * Copyright (C) 2014 CCM Benchmark Group. (http://www.ccmbenchmark.com)
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

namespace CCMBenchmark\Ting\Tests\Unit\Driver\Pgsql;

use CCMBenchmark\Ting\Driver\Pgsql\Result;
use CCMBenchmark\Ting\Driver\ResultInterface;
use CCMBenchmark\Ting\Tests\Support\NativeFunctionMock;
use CCMBenchmark\Ting\Tests\Support\TestCase;

class ResultTest extends TestCase
{
    public function testSetQueryShouldRaiseExceptionOnColumnAsterisk()
    {
        NativeFunctionMock::override('pg_num_fields', 1);
        NativeFunctionMock::override('pg_field_table', function ($result, $index) {
            if ($index === 1) {
                return 'table';
            }
            return false;
        });

        NativeFunctionMock::override('pg_field_name', fn ($result, $index) => match ($index) {
            0 => 't.*',
            default => false,
        });

        $result = new Result();
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult('result resource');

        $this->assertThrows(
            \Throwable::class,
            function () use ($result): void {
                $result->setQuery('select t.* from table as t');
            },
            'Query invalid: usage of asterisk in column definition is forbidden'
        );
    }

    public function testSetQueryShouldNotRaiseExceptionWhenAsteriskIsInACondition()
    {
        NativeFunctionMock::override('pg_num_fields', 1);
        NativeFunctionMock::override('pg_field_table', function ($result, $index) {
            if ($index === 1) {
                return 'table';
            }
            return false;
        });

        NativeFunctionMock::override('pg_field_name', fn ($result, $index) => match ($index) {
            0 => 't.*',
            default => false,
        });

        $result = new Result('result resource');

        $this->assertNull(
            $result->setQuery(
                'select t.tata CASE WHEN COALESCE(t_avis.note,0) > -5
                THEN (length(t_avis.en_bref) > 200)::integer*100 ELSE 0 END +
                COALESCE(t_avis.note,0) as my_note_avis from table as t'
            )
        );
    }

    public function testSetQueryShouldRaiseExceptionParseColumns()
    {
        NativeFunctionMock::override('pg_num_fields', 0);

        $result = new Result();
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult('result resource');

        $this->assertThrows(
            \Throwable::class,
            function () use ($result): void {
                $result->setQuery('selectcolumn from table');
            },
            'Query invalid: can\'t parse columns'
        );
    }

    public function testSetQueryShouldNotRaiseExceptionWhenThereIsNoFromInTheQuery()
    {
        NativeFunctionMock::override('pg_num_fields', 0);

        $result = new Result();
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult('result resource');

        $this->assertNull($result->setQuery('select NOW(1)'));
    }

    public function testIterator()
    {
        NativeFunctionMock::override('pg_result_seek', true);
        NativeFunctionMock::override('pg_fetch_array', []);

        // Spy: records calls while keeping the real implementation
        $result = new class () extends Result {
            /** @var array<string, int> */
            public array $calls = ['next' => 0, 'key' => 0, 'valid' => 0, 'current' => 0];

            public function next(): void
            {
                $this->calls['next']++;
                parent::next();
            }

            public function key(): mixed
            {
                $this->calls['key']++;
                return parent::key();
            }

            public function valid(): bool
            {
                $this->calls['valid']++;
                return parent::valid();
            }

            public function current(): mixed
            {
                $this->calls['current']++;
                return parent::current();
            }
        };
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult('result resource');

        $result->rewind();
        $this->assertSame(1, $result->calls['next']);
        $result->key();
        $this->assertSame(1, $result->calls['key']);
        $result->next();
        $this->assertSame(2, $result->calls['next']);
        $result->valid();
        $this->assertSame(1, $result->calls['valid']);
        $result->current();
        $this->assertSame(1, $result->calls['current']);
    }

    public function testIteratorValidShouldReturnFalse()
    {
        NativeFunctionMock::override('pg_result_seek', true);
        NativeFunctionMock::override('pg_fetch_array', false);

        $result = new Result();
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult('result resource');
        $result->rewind();
        $result->next();

        $this->assertFalse($result->valid());
    }

    public function testGetNumRows()
    {
        $mockPgsqlResult = $this->createStub(ResultInterface::class);

        NativeFunctionMock::override('pg_num_rows', 10);

        $result = new Result($mockPgsqlResult);
        $result->setResult($mockPgsqlResult);

        $this->assertEquals(10, $result->getNumRows());
    }

    public function testSetQueryTakesFullConditionAsColumn()
    {
        $mockPgsqlResult = $this->createStub(ResultInterface::class);

        NativeFunctionMock::override('pg_fetch_array', [1, 1, 2, 3, 6, 7, 8]);

        NativeFunctionMock::override('pg_field_table', fn ($result, $index) => 'table');

        $result = new Result($mockPgsqlResult);
        $result->setQuery(
            'SELECT a,
                    CASE WHEN a = 1 THEN 1 ELSE 0 END,
                    CASE WHEN a = 1 THEN 2 ELSE 0 END aliased,
                    CASE WHEN a = 1 THEN 3 ELSE 0 END as aliased2,
                    CASE WHEN a = 1 THEN 4 ELSE 0 END + 2 as END,
                    CASE WHEN a = 1 THEN 5 ELSE 0 END + 2 END,
                    CASE WHEN a = 1 THEN 6 ELSE 0 END + 2
                    FROM table'
        );
        $result->setResult($mockPgsqlResult);
        $result->next();

        $this->assertEquals(
            [
                [
                    'name' => 'a',
                    'orgName' => 'a',
                    'table' => 'table',
                    'orgTable' => 'table',
                    'schema' => '',
                    'value' => 1
                ],
                [
                    'name' => 'CASE WHEN a = 1 THEN 1 ELSE 0 END',
                    'orgName' => 'CASE WHEN a = 1 THEN 1 ELSE 0 END',
                    'table' => '',
                    'orgTable' => '',
                    'schema' => '',
                    'value' => 1
                ],
                [
                    'name' => 'aliased',
                    'orgName' => 'CASE WHEN a = 1 THEN 2 ELSE 0 END',
                    'table' => '',
                    'orgTable' => '',
                    'schema' => '',
                    'value' => 2
                ],
                [
                    'name' => 'aliased2',
                    'orgName' => 'CASE WHEN a = 1 THEN 3 ELSE 0 END',
                    'table' => '',
                    'orgTable' => '',
                    'schema' => '',
                    'value' => 3
                ],
                [
                    'name' => 'END',
                    'orgName' => 'CASE WHEN a = 1 THEN 4 ELSE 0 END + 2',
                    'table' => '',
                    'orgTable' => '',
                    'schema' => '',
                    'value' => 6
                ],
                [
                    'name' => 'END',
                    'orgName' => 'CASE WHEN a = 1 THEN 5 ELSE 0 END + 2',
                    'table' => '',
                    'orgTable' => '',
                    'schema' => '',
                    'value' => 7
                ],
                [
                    'name' => 'CASE WHEN a = 1 THEN 6 ELSE 0 END + 2',
                    'orgName' => 'CASE WHEN a = 1 THEN 6 ELSE 0 END + 2',
                    'table' => '',
                    'orgTable' => '',
                    'schema' => '',
                    'value' => 8
                ]
            ],
            $result->current()
        );
    }
}
