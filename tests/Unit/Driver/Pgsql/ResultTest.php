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

    public static function largeLiteralProvider(): array
    {
        $big = str_repeat('x', 2_000_000);

        return [
            'a block comment' => ["SELECT u.id /* $big */, u.name FROM users u", 'name'],
            'a dollar-quoted string' => ["SELECT u.id, \$\$ $big \$\$ AS s FROM users u", "\$\$ $big \$\$"],
            'a tagged dollar-quoted string' => [
                "SELECT u.id, \$t\$ \$ $big \$t\$ AS s FROM users u",
                "\$t\$ \$ $big \$t\$",
            ],
            'a string' => ["SELECT u.id, '$big''' AS s FROM users u", "'$big'''"],
            'an escape string' => ["SELECT u.id, E'$big\\'' AS s FROM users u", "E'$big\\''"],
            'a quoted identifier' => ["SELECT u.id, u.\"$big\" FROM users u", $big],
        ];
    }

    /**
     * Comments, string literals and quoted identifiers of any size: no PCRE backtrack limit reached
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('largeLiteralProvider')]
    public function testSetQueryShouldParseLargeLiterals(string $query, string $secondColumn)
    {
        $columns = $this->parseColumns($query, ['users', 'users']);

        $this->assertCount(2, $columns);
        $this->assertSame(['name' => 'id', 'orgName' => 'id', 'table' => 'u', 'schema' => ''], $columns[0]);
        $this->assertSame($secondColumn, $columns[1]['orgName']);
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

    public static function fromClauseProvider(): array
    {
        $column = fn (string $name, string $table, string $schema = '') => [
            'name' => $name,
            'orgName' => $name,
            'table' => $table,
            'schema' => $schema,
        ];

        return [
            'a semicolon after the alias of the last table' => [
                'SELECT b.boo_id, boo_name FROM app.t_bouh_boo b;',
                ['t_bouh_boo', 't_bouh_boo'],
                [$column('boo_id', 'b', 'app'), $column('boo_name', 'b', 'app')],
            ],
            'a semicolon after the alias of a joined table' => [
                'SELECT u.id, total, qty FROM users u JOIN shop.orders o ON o.uid = u.id JOIN app.carts c;',
                ['users', 'orders', 'carts'],
                [$column('id', 'u'), $column('total', 'o', 'shop'), $column('qty', 'c', 'app')],
            ],
            'a semicolon and spaces after the alias' => [
                "SELECT boo_id FROM app.t_bouh_boo AS b ;\n",
                ['t_bouh_boo'],
                [$column('boo_id', 'b', 'app')],
            ],
            'a long table name before a parenthesis' => [
                'SELECT x.n, b.boo_id, boo_name FROM (SELECT count(*) AS n FROM customer_order_line_items_archive_2024)'
                . ' x JOIN app.t_bouh_boo b ON true',
                [false, 't_bouh_boo', 't_bouh_boo'],
                [
                    ['name' => 'n', 'orgName' => 'n', 'table' => 'x', 'schema' => ''],
                    $column('boo_id', 'b', 'app'),
                    $column('boo_name', 'b', 'app'),
                ],
            ],
            'a long function name' => [
                'SELECT boo_id, boo_name FROM some_long_function_name_returning_rows(1) f'
                . ' JOIN app.t_bouh_boo b ON true',
                ['t_bouh_boo', 't_bouh_boo'],
                [$column('boo_id', 'b', 'app'), $column('boo_name', 'b', 'app')],
            ],
            'a long table name before a comma' => [
                'SELECT boo_id, total FROM app.t_bouh_boo b,customer_order_line_items_archive_2024 o',
                ['t_bouh_boo', 'customer_order_line_items_archive_2024'],
                [$column('boo_id', 'b', 'app'), $column('total', 'o')],
            ],
            'tables separated by a comma, without space' => [
                'SELECT o.id, total FROM users,orders o',
                ['orders', 'orders'],
                [$column('id', 'o'), $column('total', 'o')],
            ],
            'schema-qualified tables separated by commas' => [
                'SELECT u.id, name, label FROM public.users u, app.settings s , "app" . "tags" AS t WHERE true',
                ['users', 'settings', 'tags'],
                [$column('id', 'u', 'public'), $column('name', 's', 'app'), $column('label', 't', 'app')],
            ],
            'FROM in a function is no table' => [
                'SELECT id, extract(year from c.created_at) AS y, trim(both from name) AS t FROM created_at',
                ['created_at', false, false],
                [
                    $column('id', 'created_at'),
                    ['name' => 'y', 'orgName' => 'extract(year from c.created_at)', 'table' => '', 'schema' => ''],
                    ['name' => 't', 'orgName' => 'trim(both from name)', 'table' => '', 'schema' => ''],
                ],
            ],
            'a table named by one character' => [
                'SELECT id FROM t',
                ['t'],
                [$column('id', 't')],
            ],
            'the same table in a sub-query of another schema' => [
                'SELECT id, title FROM tenant_a.articles WHERE id IN (SELECT id FROM tenant_b.articles)',
                ['articles', 'articles'],
                [$column('id', 'articles', 'tenant_a'), $column('title', 'articles', 'tenant_a')],
            ],
            'the same alias in a sub-query of another schema' => [
                'SELECT a.id, title FROM tenant_a.articles a'
                . ' WHERE a.id IN (SELECT a.article_id FROM tenant_b.archive a)',
                ['articles', 'articles'],
                [$column('id', 'a', 'tenant_a'), $column('title', 'a', 'tenant_a')],
            ],
            'the same alias in a lateral sub-query' => [
                'SELECT a.id, title FROM tenant_a.articles a JOIN LATERAL (SELECT 1 FROM tenant_b.x a) l ON true',
                ['articles', 'articles'],
                [$column('id', 'a', 'tenant_a'), $column('title', 'a', 'tenant_a')],
            ],
            'the same table under another alias in EXISTS' => [
                'SELECT id FROM users u WHERE EXISTS (SELECT 1 FROM users v)',
                ['users'],
                [$column('id', 'u')],
            ],
            'the same table in a CTE' => [
                'WITH a AS (SELECT x.id FROM app.users x) SELECT id FROM users WHERE id IN (SELECT id FROM a)',
                ['users'],
                [$column('id', 'users')],
            ],
            'the same table in the second SELECT of a parenthesized UNION' => [
                '(SELECT id FROM city c) UNION ALL (SELECT 1) UNION ALL SELECT id FROM app.city d',
                ['city'],
                [$column('id', 'c')],
            ],
        ];
    }

    /**
     * The tables of the FROM clause, their aliases and their schemas: a column without table prefix is attributed to
     * the alias of the table pg_field_table() names
     *
     * @param array<int, string|false> $tables
     * @param list<array<string, string>> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('fromClauseProvider')]
    public function testSetQueryShouldReadTheTablesOfTheFromClause(string $query, array $tables, array $expected)
    {
        $this->assertSame($expected, $this->parseColumns($query, $tables));
    }

    public static function identifierCaseProvider(): array
    {
        return [
            'a quoted mixed-case table, with an alias' => [
                'SELECT u.id, u."firstName" FROM public."Users" u',
                ['Users', 'Users'],
                [
                    ['id', 'id', 'u', 'Users', 'public'],
                    ['firstName', 'firstName', 'u', 'Users', 'public'],
                ],
            ],
            'a quoted mixed-case table and schema, without alias' => [
                'SELECT id, "Users".name FROM "App"."Users"',
                ['Users', 'Users'],
                [
                    ['id', 'id', 'Users', 'Users', 'App'],
                    ['name', 'name', 'Users', 'Users', 'App'],
                ],
            ],
            'unquoted identifiers folded to lowercase' => [
                'SELECT U.ID, Name, U.Email AS Mail FROM App.USERS U',
                ['users', 'users', 'users'],
                [
                    ['ID', 'id', 'u', 'users', 'app'],
                    ['Name', 'name', 'u', 'users', 'app'],
                    ['Mail', 'email', 'u', 'users', 'app'],
                ],
            ],
            'a quoted alias keeps its case' => [
                'SELECT "U".id, u.name FROM users "U" JOIN users u ON true',
                ['users', 'users'],
                [
                    ['id', 'id', 'U', 'users', ''],
                    ['name', 'name', 'u', 'users', ''],
                ],
            ],
        ];
    }

    /**
     * PostgreSQL folds unquoted identifiers to lowercase and keeps the case of quoted ones: pg_field_table() gives
     * the name of the table as created ("Users" for a table created quoted)
     *
     * @param list<string> $tables
     * @param list<array{string, string, string, string, string}> $expected name, orgName, table, orgTable, schema
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('identifierCaseProvider')]
    public function testSetQueryShouldFoldUnquotedIdentifiersOnly(string $query, array $tables, array $expected)
    {
        NativeFunctionMock::override('pg_field_table', fn ($result, $index) => $tables[$index] ?? false);
        NativeFunctionMock::override('pg_result_seek', true);
        NativeFunctionMock::override('pg_fetch_array', array_fill(0, 20, 'v'));

        $result = new Result();
        $result->setResult(new PgsqlResult());
        $result->setQuery($query);
        $result->rewind();

        $this->assertSame(
            $expected,
            array_map(
                fn (array $column) => [
                    $column['name'],
                    $column['orgName'],
                    $column['table'],
                    $column['orgTable'],
                    $column['schema'],
                ],
                $result->current()
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
            'IS NOT DISTINCT FROM without alias' => [
                'SELECT a IS NOT DISTINCT FROM b, id, name FROM city',
                [1 => 'city', 2 => 'city'],
                [['name' => '?column?', 'orgName' => 'a IS NOT DISTINCT FROM b', 'table' => '', 'schema' => ''], $id, $name],
            ],
            'IS DISTINCT FROM with an alias without AS' => [
                'SELECT a IS DISTINCT FROM b d, id FROM city',
                [1 => 'city'],
                [['name' => 'd', 'orgName' => 'a IS DISTINCT FROM b', 'table' => '', 'schema' => ''], $id],
            ],
            'parenthesized UNION ALL' => [
                '(SELECT id, name FROM city) UNION ALL (SELECT id, name FROM city)',
                ['city', 'city'],
                [$id, $name],
            ],
            'doubly parenthesized UNION' => [
                '((SELECT id, name AS n FROM city WHERE id IN (SELECT 1))) UNION (SELECT id, name FROM city)',
                ['city', 'city'],
                [$id, ['name' => 'n', 'orgName' => 'name', 'table' => 'city', 'schema' => '']],
            ],
            'expressions ending in an identifier, without alias' => [
                'SELECT price * qty, NOT flag, -amount, a IS DISTINCT FROM t.b, b::text, ts AT TIME ZONE tz, id FROM city',
                [6 => 'city'],
                [
                    ['name' => '?column?', 'orgName' => 'price * qty', 'table' => '', 'schema' => ''],
                    ['name' => '?column?', 'orgName' => 'NOT flag', 'table' => '', 'schema' => ''],
                    ['name' => '?column?', 'orgName' => '-amount', 'table' => '', 'schema' => ''],
                    ['name' => '?column?', 'orgName' => 'a IS DISTINCT FROM t.b', 'table' => '', 'schema' => ''],
                    ['name' => 'b', 'orgName' => 'b::text', 'table' => '', 'schema' => ''],
                    ['name' => 'timezone', 'orgName' => 'ts AT TIME ZONE tz', 'table' => '', 'schema' => ''],
                    $id,
                ],
            ],
            'casts of a column, named after the column' => [
                'SELECT id::text, u.n::double precision, x::timestamp(0) with time zone, CAST(u.code AS varchar(10)),'
                . ' t.tags::text[], y::int::text, "Name"::text, (a + b)::text, id::text || \'x\', d::date c FROM city',
                [],
                [
                    ['name' => 'id', 'orgName' => 'id::text', 'table' => '', 'schema' => ''],
                    ['name' => 'n', 'orgName' => 'u.n::double precision', 'table' => '', 'schema' => ''],
                    ['name' => 'x', 'orgName' => 'x::timestamp(0) with time zone', 'table' => '', 'schema' => ''],
                    ['name' => 'code', 'orgName' => 'CAST(u.code AS varchar(10))', 'table' => '', 'schema' => ''],
                    ['name' => 'tags', 'orgName' => 't.tags::text[]', 'table' => '', 'schema' => ''],
                    ['name' => 'y', 'orgName' => 'y::int::text', 'table' => '', 'schema' => ''],
                    ['name' => 'Name', 'orgName' => 'Name"::text', 'table' => '', 'schema' => ''],
                    ['name' => '?column?', 'orgName' => '(a + b)::text', 'table' => '', 'schema' => ''],
                    ['name' => "id::text || 'x'", 'orgName' => "id::text || 'x'", 'table' => '', 'schema' => ''],
                    ['name' => 'c', 'orgName' => 'd::date', 'table' => '', 'schema' => ''],
                ],
            ],
            'expressions with an alias without AS' => [
                "SELECT price * qty total, lower(name) n, 'a' x, 2 y, (a + b) s, t.b c, CASE WHEN a THEN 1 END d FROM city",
                [],
                [
                    ['name' => 'total', 'orgName' => 'price * qty', 'table' => '', 'schema' => ''],
                    ['name' => 'n', 'orgName' => 'lower(name)', 'table' => '', 'schema' => ''],
                    ['name' => 'x', 'orgName' => "'a'", 'table' => '', 'schema' => ''],
                    ['name' => 'y', 'orgName' => '2', 'table' => '', 'schema' => ''],
                    ['name' => 's', 'orgName' => '(a + b)', 'table' => '', 'schema' => ''],
                    ['name' => 'c', 'orgName' => 't.b', 'table' => '', 'schema' => ''],
                    ['name' => 'd', 'orgName' => 'CASE WHEN a THEN 1 END', 'table' => '', 'schema' => ''],
                ],
            ],
            'a column of schema.table.column' => [
                'SELECT app.city.id FROM app.city',
                [],
                [['name' => 'id', 'orgName' => 'app.city.id', 'table' => '', 'schema' => '']],
            ],
            'UNION whose first SELECT alone is parenthesized' => [
                '(SELECT id, name AS n FROM city ORDER BY id LIMIT 3) UNION ALL SELECT o.code, o.label FROM other o',
                [],
                [
                    ['name' => 'id', 'orgName' => 'id', 'table' => '', 'schema' => ''],
                    ['name' => 'n', 'orgName' => 'name', 'table' => '', 'schema' => ''],
                ],
            ],
            'parenthesized UNION without FROM' => [
                '(SELECT 1 AS id, lower(name) AS name) UNION ALL (SELECT 2, 3)',
                [],
                [
                    ['name' => 'id', 'orgName' => '1', 'table' => '', 'schema' => ''],
                    ['name' => 'name', 'orgName' => 'lower(name)', 'table' => '', 'schema' => ''],
                ],
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
