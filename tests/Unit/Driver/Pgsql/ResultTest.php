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
use CCMBenchmark\Ting\Driver\QueryException;
use CCMBenchmark\Ting\Driver\ResultInterface;
use CCMBenchmark\Ting\Tests\Support\NativeFunctionMock;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\Fake\PgsqlResult;

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
        $result->setResult(new PgsqlResult());

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

        $result = new Result();
        $result->setResult(new PgsqlResult());

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
        $result->setResult(new PgsqlResult());

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
        NativeFunctionMock::override('pg_field_table', 'table');

        $result = new Result();
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult(new PgsqlResult());

        $this->assertNull($result->setQuery('select NOW(1)'));
    }

    public function testSetQueryWithAColumnFromAnUnresolvedTableShouldHaveNoTable()
    {
        // A column from a function (or VALUES, a CTE...): pg_field_table() returns false
        NativeFunctionMock::override('pg_num_fields', 1);
        NativeFunctionMock::override('pg_field_table', false);
        NativeFunctionMock::override('pg_result_seek', true);
        NativeFunctionMock::override('pg_fetch_array', ['1']);

        $result = new Result();
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult(new PgsqlResult());
        $result->setQuery('SELECT x FROM generate_series(1, 3) AS x');
        $result->rewind();

        $this->assertSame(
            [['name' => 'x', 'orgName' => 'x', 'table' => '', 'orgTable' => '', 'schema' => '', 'value' => '1']],
            $result->current()
        );
    }

    /**
     * @param array<int, string> $tables pg_field_table() of each column, false when missing
     * @return list<array{name: string, orgName: string, table: string, schema: string}>
     */
    private function parseColumns(string $query, array $tables = []): array
    {
        NativeFunctionMock::override('pg_field_table', fn ($result, $index) => $tables[$index] ?? false);
        NativeFunctionMock::override('pg_result_seek', true);
        NativeFunctionMock::override('pg_fetch_array', array_fill(0, 20, 'v'));

        $result = new Result();
        $result->setConnectionName('connectionName');
        $result->setDatabase('database');
        $result->setResult(new PgsqlResult());
        $result->setQuery($query);
        $result->rewind();

        return array_map(
            fn (array $column) => [
                'name' => $column['name'],
                'orgName' => $column['orgName'],
                'table' => $column['table'],
                'schema' => $column['schema'],
            ],
            $result->current()
        );
    }

    public static function asteriskColumnProvider(): array
    {
        return [
            'star' => ['SELECT * FROM users'],
            'alias star' => ['SELECT id, u.* FROM users u'],
            'quoted alias star' => ['SELECT "u" . * FROM users u'],
            'star in a CTE query' => ['WITH x AS (SELECT 1 AS a) SELECT * FROM x'],
            'distinct star' => ['SELECT DISTINCT * FROM users'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('asteriskColumnProvider')]
    public function testSetQueryShouldRaiseExceptionOnAnAsteriskColumn(string $query)
    {
        $result = new Result();
        $result->setResult(new PgsqlResult());

        $this->assertThrows(
            QueryException::class,
            function () use ($result, $query): void {
                $result->setQuery($query);
            },
            'Query invalid: usage of asterisk in column definition is forbidden'
        );
    }

    public function testSetQueryShouldAcceptCountStar()
    {
        $this->assertSame(
            [
                ['name' => 'id', 'orgName' => 'id', 'table' => 'users', 'schema' => ''],
                ['name' => 'c', 'orgName' => 'count(*)', 'table' => '', 'schema' => ''],
            ],
            $this->parseColumns('SELECT id, count(*) AS c FROM users GROUP BY id', ['users'])
        );
    }

    public function testSetQueryShouldAcceptAMultiplication()
    {
        $this->assertSame(
            [['name' => 'd', 'orgName' => '2 * id', 'table' => '', 'schema' => '']],
            $this->parseColumns('SELECT 2 * id AS d FROM users')
        );
    }

    public function testSetQueryShouldIgnoreFromInAStringLiteral()
    {
        $this->assertSame(
            [
                ['name' => 'kw', 'orgName' => "'from'", 'table' => '', 'schema' => ''],
                ['name' => 'id', 'orgName' => 'id', 'table' => 'users', 'schema' => ''],
            ],
            $this->parseColumns("SELECT 'from' AS kw, id FROM users", [1 => 'users'])
        );
    }

    public function testSetQueryShouldIgnoreCommasInStringLiterals()
    {
        $this->assertSame(
            [
                ['name' => 'lbl', 'orgName' => "name || ', ' || id", 'table' => '', 'schema' => ''],
                ['name' => 'id', 'orgName' => 'id', 'table' => 'users', 'schema' => ''],
                ['name' => 'q', 'orgName' => "'it''s, ok'", 'table' => '', 'schema' => ''],
            ],
            $this->parseColumns("SELECT name || ', ' || id AS lbl, id, 'it''s, ok' AS q FROM users", [1 => 'users'])
        );
    }

    public function testSetQueryShouldIgnoreEscapeStringsAndDollarQuotes()
    {
        $this->assertSame(
            [
                ['name' => 'e', 'orgName' => "E'a\\', from b'", 'table' => '', 'schema' => ''],
                ['name' => 'd', 'orgName' => '$$x, from y$$', 'table' => '', 'schema' => ''],
                ['name' => 't', 'orgName' => "\$tag\$x, ' from\$tag\$", 'table' => '', 'schema' => ''],
                ['name' => 'id', 'orgName' => 'id', 'table' => 'users', 'schema' => ''],
            ],
            $this->parseColumns(
                "SELECT E'a\\', from b' AS e, \$\$x, from y\$\$ AS d, \$tag\$x, ' from\$tag\$ AS t, id FROM users",
                [3 => 'users']
            )
        );
    }

    public function testSetQueryShouldIgnoreComments()
    {
        $this->assertSame(
            [
                ['name' => 'id', 'orgName' => 'id', 'table' => 'users', 'schema' => ''],
                ['name' => 'name', 'orgName' => 'name', 'table' => 'users', 'schema' => ''],
            ],
            $this->parseColumns(
                "SELECT id, -- the id, then the name from users\n name /* a, b from c */ FROM users",
                ['users', 'users']
            )
        );
    }

    public function testSetQueryShouldParseTheMainSelectOfACte()
    {
        $this->assertSame(
            [
                ['name' => 'id', 'orgName' => 'id', 'table' => 'u', 'schema' => 'public'],
                ['name' => 'n', 'orgName' => 'name', 'table' => 'u', 'schema' => 'public'],
                ['name' => 'nb', 'orgName' => 'nb', 'table' => 'r', 'schema' => ''],
            ],
            $this->parseColumns(
                'WITH recent (user_id, nb) AS (SELECT user_id, count(id) FROM books GROUP BY user_id)
                SELECT u.id, u.name AS n, r.nb AS nb FROM public.users u INNER JOIN recent r ON r.user_id = u.id',
                ['users', 'users']
            )
        );
    }

    public function testSetQueryShouldReadSchemaQualifiedTables()
    {
        $this->assertSame(
            [
                ['name' => 'id', 'orgName' => 'id', 'table' => 'u', 'schema' => 'public'],
                ['name' => 'title', 'orgName' => 'title', 'table' => 'b', 'schema' => 'app'],
                ['name' => 'name', 'orgName' => 'name', 'table' => 'u', 'schema' => 'public'],
            ],
            $this->parseColumns(
                'SELECT u.id, b.title, name FROM public.users u JOIN "app"."books" AS b ON b.user_id = u.id',
                ['users', 'books', 'users']
            )
        );
    }

    public static function selectListProvider(): array
    {
        $id = ['name' => 'id', 'orgName' => 'id', 'table' => 'city', 'schema' => ''];
        $name = ['name' => 'name', 'orgName' => 'name', 'table' => 'city', 'schema' => ''];

        return [
            'DISTINCT' => ['SELECT DISTINCT id, name FROM city', ['city', 'city'], [$id, $name]],
            'ALL' => ['SELECT ALL id, name FROM city', ['city', 'city'], [$id, $name]],
            'DISTINCT ON' => [
                'SELECT DISTINCT ON (id, lower(name)) id, name FROM city',
                ['city', 'city'],
                [$id, $name],
            ],
            'DISTINCT in a sub-query' => [
                'SELECT id, (SELECT DISTINCT 1) AS one, name FROM city',
                ['city', false, 'city'],
                [$id, ['name' => 'one', 'orgName' => '(SELECT DISTINCT 1)', 'table' => '', 'schema' => ''], $name],
            ],
            'a column named distinct_id' => [
                'SELECT distinct_id, name FROM city',
                ['city', 'city'],
                [['name' => 'distinct_id', 'orgName' => 'distinct_id', 'table' => 'city', 'schema' => ''], $name],
            ],
            'ARRAY[] with a comma' => [
                'SELECT id, ARRAY[a, b] AS ab, name FROM city',
                ['city', false, 'city'],
                [$id, ['name' => 'ab', 'orgName' => 'ARRAY[a, b]', 'table' => '', 'schema' => ''], $name],
            ],
            'quoted alias with a space' => [
                'SELECT id AS "the id", name FROM city',
                ['city', 'city'],
                [['name' => 'the id', 'orgName' => 'id', 'table' => 'city', 'schema' => ''], $name],
            ],
            'quoted alias with a space on an expression' => [
                'SELECT count(id) AS "nb of ids", name FROM city GROUP BY name',
                [1 => 'city'],
                [['name' => 'nb of ids', 'orgName' => 'count(id)', 'table' => '', 'schema' => ''], $name],
            ],
            'IS DISTINCT FROM' => [
                'SELECT a IS DISTINCT FROM b AS d, id, name FROM city',
                [1 => 'city', 2 => 'city'],
                [['name' => 'd', 'orgName' => 'a IS DISTINCT FROM b', 'table' => '', 'schema' => ''], $id, $name],
            ],
            'IS NOT DISTINCT FROM, with a comment' => [
                'SELECT id, a IS NOT DISTINCT /* c */ FROM b AS d, name FROM city',
                [0 => 'city', 2 => 'city'],
                [$id, ['name' => 'd', 'orgName' => 'a IS NOT DISTINCT   FROM b', 'table' => '', 'schema' => ''], $name],
            ],
        ];
    }

    /**
     * @param array<int, string|false> $tables
     * @param list<array<string, string>> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('selectListProvider')]
    public function testSetQueryShouldParseTheSelectList(string $query, array $tables, array $expected)
    {
        $this->assertSame($expected, $this->parseColumns($query, $tables));
    }

    public static function quotedIdentifierProvider(): array
    {
        $id = ['name' => 'id', 'orgName' => 'id', 'table' => 'users', 'schema' => 'public'];
        $column = fn (string $name, string $table = 'users', string $schema = 'public') => [
            'name' => $name,
            'orgName' => $name,
            'table' => $table,
            'schema' => $schema,
        ];

        return [
            'a colon' => ['SELECT "id", "a:b" FROM "public"."users"', [$id, $column('a:b')]],
            'a space' => ['SELECT "id", "first name" FROM "public"."users"', [$id, $column('first name')]],
            'an accent' => ['SELECT "id", "prénom" FROM "public"."users"', [$id, $column('prénom')]],
            'a dash' => ['SELECT "id", "e-mail" FROM "public"."users"', [$id, $column('e-mail')]],
            'an escaped double quote' => ['SELECT "id", "a""b" FROM "public"."users"', [$id, $column('a"b')]],
            'a quoted table prefix' => [
                'SELECT "u:x"."id", "u:x"."a b" FROM "public"."users" AS "u:x"',
                [$column('id', 'u:x'), $column('a b', 'u:x')],
            ],
            'a table and a schema with a colon' => [
                'SELECT "id", "a :b" FROM "my schema"."ta:ble" WHERE "id" = $1',
                [$column('id', 'ta:ble', 'my schema'), $column('a :b', 'ta:ble', 'my schema')],
            ],
            'an alias with an escaped double quote' => [
                'SELECT "id" AS "the ""id""" FROM "public"."users"',
                [['name' => 'the "id"', 'orgName' => 'id', 'table' => 'users', 'schema' => 'public']],
            ],
        ];
    }

    /**
     * Quoted identifiers may hold any character: Ting generates them for every mapped column (getAll, getBy...)
     *
     * @param list<array<string, string>> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('quotedIdentifierProvider')]
    public function testSetQueryShouldParseQuotedIdentifiers(string $query, array $expected)
    {
        // pg_field_table() gives the name of the table, unquoted
        $table = str_contains($query, 'ta:ble') ? 'ta:ble' : 'users';

        $this->assertSame($expected, $this->parseColumns($query, array_fill(0, \count($expected), $table)));
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
        $result->setResult(new PgsqlResult());

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
        $result->setResult(new PgsqlResult());
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

        $result = new Result();
        $result->setResult($mockPgsqlResult);
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

    public function testWithoutResultShouldBeEmpty()
    {
        NativeFunctionMock::override('pg_num_rows', fn () => $this->fail('No result to count'));
        NativeFunctionMock::override('pg_fetch_array', fn () => $this->fail('No result to fetch'));
        $result = new Result();

        $this->assertSame(0, $result->getNumRows());
        $this->assertSame([], iterator_to_array($result));
    }

    public function testSetQueryBeforeSetResultShouldRaiseQueryException()
    {
        $result = new Result();

        $this->assertThrows(
            QueryException::class,
            fn () => $result->setQuery('SELECT id FROM t'),
            'Result::setQuery() called before setResult(): the tables of the columns are read from the result'
        );
    }
}
