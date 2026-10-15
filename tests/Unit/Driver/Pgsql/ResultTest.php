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
        NativeFunctionMock::override('pg_num_fields', 1);
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
     * @param array<int, string|false> $tables pg_field_table() of each column, false when missing
     * @param int $numFields pg_num_fields(): the number of columns of the result, the number of columns expected
     * @return list<array{name: string, orgName: string, table: string, schema: string}>
     */
    private function parseColumns(string $query, array $tables, int $numFields): array
    {
        NativeFunctionMock::override('pg_field_table', fn ($result, $index) => $tables[$index] ?? false);
        NativeFunctionMock::override('pg_result_seek', true);
        NativeFunctionMock::override('pg_fetch_array', array_fill(0, 20, 'v'));
        NativeFunctionMock::override('pg_num_fields', $numFields);

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
            // The fields of a composite, of a whole row or of a function returning a row: several columns
            'the fields of a composite column' => ['SELECT p.id, (p.loc).*, p.name FROM people p'],
            'the fields of a whole row' => ['SELECT (p).* FROM people p'],
            'the fields of a function call' => ['SELECT (json_populate_record(null::people, j)).* FROM x'],
            'the fields of a nested function call' => ['SELECT (jsonb_each(x)).*, id FROM t'],
            'a schema-qualified table star' => ['SELECT public.people.* FROM public.people'],
            'a star of a subscript' => ['SELECT (p.locs)[1].*, p.id FROM people p'],
            'a star of a subscripted column' => ['SELECT p.locs[1].*, p.id FROM people p'],
            'a star of a quoted schema-qualified table' => ['SELECT "app"."People".* FROM app."People"'],
            'a parenthesized alias star' => ['SELECT (p.*), p.id FROM people p'],
            'a doubly parenthesized star' => ['SELECT ((p).*) FROM people p'],
            'an aliased alias star' => ['SELECT p.* AS x, p.id FROM people p'],
            'an alias star with a bare alias' => ['SELECT p.* x FROM people p'],
            'the fields of a composite with an alias' => ['SELECT (p.loc).* AS l FROM people p'],
            'a star after comments' => ['SELECT (p.loc) /* a */ . /* b */ * FROM people p'],
            'a star between form feeds' => ["SELECT\fp.*\fFROM people p"],
        ];
    }

    public static function oneColumnStarProvider(): array
    {
        return [
            'a cast of a whole row' => ['u.*::text', '?column?'],
            'a cast of the fields of a composite' => ['(u.loc).*::text', '?column?'],
            'a parenthesized whole row cast' => ['(u.*)::text', '?column?'],
            'a function of a whole row' => ['row_to_json(u.*)', 'row_to_json'],
            'a parenthesized function of a whole row' => ['(row_to_json(u.*))', '?column?'],
            'ROW() of a whole row' => ['ROW(u.*)', '?column?'],
            'an array of whole rows' => ['ARRAY[u.*]', '?column?'],
            'count(*)' => ['count(*)', 'count'],
            'a multiplication of a number' => ['1.* u.x', '?column?'],
            'a multiplication of a parenthesized number' => ['(1.*u.x)', '?column?'],
            'a field of a composite' => ['(u.loc).x', '?column?'],
            'a test of a whole row' => ['u.* IS NULL', '?column?'],
            'a postfix test of a whole row' => ['u.* NOTNULL', '?column?'],
            'a comparison of whole rows' => ['u.* = u.*', '?column?'],
            'a whole row compared with IS DISTINCT FROM' => ['u.* IS DISTINCT FROM u.*', '?column?'],
            'a ROW() compared with a whole row' => ['ROW(1, 2) = u.*', '?column?'],
            'a CASE ending with a whole row' => ['CASE WHEN u.ok THEN u.* END', '?column?'],
            'a CASE ending with the fields of a composite' => [
                'CASE WHEN u.ok THEN NULL ELSE (u.loc).* END',
                '?column?',
            ],
            'COALESCE() of whole rows' => ['COALESCE(u.*, u.*)', 'COALESCE'],
        ];
    }

    public function testSetQueryShouldAcceptAnAliasedExpressionEndingWithAWholeRow()
    {
        $this->assertSame(
            [['id', 'id'], ['changed', 'o.* IS DISTINCT FROM n.*'], ['r', 'CASE WHEN t.ok THEN t.* END']],
            array_map(
                fn (array $column) => [$column['name'], $column['orgName']],
                $this->parseColumns(
                    'SELECT o.id, o.* IS DISTINCT FROM n.* AS changed, CASE WHEN t.ok THEN t.* END r'
                    . ' FROM t o JOIN t n ON n.id = o.id',
                    ['t'],
                    3
                )
            )
        );
    }

    /**
     * A whole row or the fields of a composite inside an expression is one column
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('oneColumnStarProvider')]
    public function testSetQueryShouldAcceptAnAsteriskInsideAnExpression(string $expression, string $name)
    {
        $this->assertSame(
            [[$name, $expression, ''], ['id', 'id', 'u']],
            array_map(
                fn (array $column) => [$column['name'], $column['orgName'], $column['table']],
                $this->parseColumns('SELECT ' . $expression . ', u.id FROM users u', [false, 'users'], 2)
            )
        );
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
            $this->parseColumns('SELECT id, count(*) AS c FROM users GROUP BY id', ['users'], 2)
        );
    }

    public function testSetQueryShouldAcceptAMultiplication()
    {
        $this->assertSame(
            [['name' => 'd', 'orgName' => '2 * id', 'table' => '', 'schema' => '']],
            $this->parseColumns('SELECT 2 * id AS d FROM users', [], 1)
        );
    }

    public function testSetQueryShouldIgnoreFromInAStringLiteral()
    {
        $this->assertSame(
            [
                ['name' => 'kw', 'orgName' => "'from'", 'table' => '', 'schema' => ''],
                ['name' => 'id', 'orgName' => 'id', 'table' => 'users', 'schema' => ''],
            ],
            $this->parseColumns("SELECT 'from' AS kw, id FROM users", [1 => 'users'], 2)
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
            $this->parseColumns(
                "SELECT name || ', ' || id AS lbl, id, 'it''s, ok' AS q FROM users",
                [1 => 'users'],
                3
            )
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
                [3 => 'users'],
                4
            )
        );
    }

    public function testSetQueryShouldIgnoreDollarQuotesWithANonAsciiTag()
    {
        // A tag holds letters, non-ASCII ones too, as an unquoted name does
        $this->assertSame(
            [
                ['name' => 'v', 'orgName' => '$é$, FROM x$é$', 'table' => '', 'schema' => ''],
                ['name' => 'w', 'orgName' => '$_ü1$, ) FROM y$_ü1$', 'table' => '', 'schema' => ''],
                ['name' => 'id', 'orgName' => 'id', 'table' => 'u', 'schema' => ''],
            ],
            $this->parseColumns(
                'SELECT $é$, FROM x$é$ AS v, $_ü1$, ) FROM y$_ü1$ AS w, u.id FROM users u',
                [2 => 'users'],
                3
            )
        );
    }

    public static function largeLiteralProvider(): array
    {
        $big = str_repeat('x', 2_000_000);

        return [
            'a block comment' => ["SELECT u.id /* $big */, u.name FROM users u", 'name'],
            'a block comment of slashes' => [
                'SELECT u.id /* ' . str_repeat('a/b ', 250_000) . ' */, u.name FROM users u',
                'name',
            ],
            'a block comment closed after a run of asterisks' => [
                'SELECT u.id /* ' . str_repeat('*', 400_000) . '*/, u.name FROM users u',
                'name',
            ],
            'a nested block comment opened after a run of slashes' => [
                'SELECT u.id /* ' . str_repeat('/', 400_000) . '/* x ' . str_repeat('*', 400_000) . '*/ */, u.name'
                . ' FROM users u',
                'name',
            ],
            'a block comment of runs of slashes' => [
                'SELECT u.id /* ' . str_repeat('//// ', 200_000) . ' */, u.name FROM users u',
                'name',
            ],
            'a dollar-quoted string' => ["SELECT u.id, \$\$ $big \$\$ AS s FROM users u", "\$\$ $big \$\$"],
            'a tagged dollar-quoted string' => [
                "SELECT u.id, \$t\$ \$ $big \$t\$ AS s FROM users u",
                "\$t\$ \$ $big \$t\$",
            ],
            'a string' => ["SELECT u.id, '$big''' AS s FROM users u", "'$big'''"],
            'an escape string' => ["SELECT u.id, E'$big\\'' AS s FROM users u", "E'$big\\''"],
            'a quoted identifier' => ["SELECT u.id, u.\"$big\" FROM users u", $big],
            'a quoted identifier of doubled quotes' => [
                'SELECT u.id, u."' . str_repeat('a""', 100_000) . '" FROM users u',
                str_repeat('a"', 100_000),
            ],
            'a quoted alias of doubled quotes' => [
                'SELECT u.id, count(*) AS "' . str_repeat('a""', 100_000) . '" FROM users u',
                'count(*)',
            ],
        ];
    }

    /**
     * A long run of blanks or comments inside a column is read in linear time (in quadratic time before, the PCRE
     * limits not counting the attempts at each position: 13 s for 80,000 comments)
     */
    public function testSetQueryShouldParseALongRunOfBlanksInAColumnInLinearTime()
    {
        $start = hrtime(true);
        $columns = $this->parseColumns(
            'SELECT u.id, u.a ' . str_repeat('/* c */ ', 25_000) . str_repeat("-- c\n", 25_000) . '+ 1 AS b'
            . str_repeat(' ', 50_000) . ', u.x ' . str_repeat(' ', 50_000) . 'c FROM users u',
            ['users', false, 'users'],
            3
        );
        $seconds = (hrtime(true) - $start) / 1e9;

        $this->assertSame(['id', 'b', 'c'], array_column($columns, 'name'));
        $this->assertLessThan(2, $seconds);
    }

    /**
     * A long run of double quotes inside a column, in a quoted identifier or a string literal, is read in linear time
     * (in quadratic time before: 12 s for 128,000 doubled double quotes)
     */
    public function testSetQueryShouldParseALongRunOfDoubleQuotesInAColumnInLinearTime()
    {
        $start = hrtime(true);
        $columns = $this->parseColumns(
            'SELECT f("' . str_repeat('""', 100_000) . '"), g(\'' . str_repeat('"', 200_000) . '\') AS b, '
            . 'h("' . str_repeat('""', 100_000) . '") "' . str_repeat('""', 100_000) . '", u.id FROM users u',
            [3 => 'users'],
            4
        );
        $seconds = (hrtime(true) - $start) / 1e9;

        $this->assertSame(['f', 'b', str_repeat('"', 100_000), 'id'], array_column($columns, 'name'));
        $this->assertLessThan(2, $seconds);
    }

    /**
     * Comments, string literals and quoted identifiers of any size: no PCRE backtrack limit reached
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('largeLiteralProvider')]
    public function testSetQueryShouldParseLargeLiterals(string $query, string $secondColumn)
    {
        $columns = $this->parseColumns($query, ['users', 'users'], 2);

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
                ['users', 'users'],
                2
            )
        );
    }

    public static function lineCommentEndProvider(): array
    {
        return [
            'carriage return' => ["\r"],
            'carriage return and line feed' => ["\r\n"],
            'line feed' => ["\n"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('lineCommentEndProvider')]
    public function testSetQueryShouldEndALineCommentAtTheEndOfTheLine(string $newline)
    {
        // PostgreSQL ends a -- comment at a line feed or a carriage return, alone or not
        $this->assertSame(
            [
                ['name' => 'id', 'orgName' => 'id', 'table' => 'p', 'schema' => ''],
                ['name' => 'name', 'orgName' => 'name', 'table' => 'p', 'schema' => ''],
            ],
            $this->parseColumns(
                'SELECT p.id -- key' . $newline . ', p.name FROM people p -- the people' . $newline . 'WHERE true',
                ['people', 'people'],
                2
            )
        );
    }

    public function testSetQueryShouldIgnoreNestedComments()
    {
        // PostgreSQL nests block comments: the first */ does not end the outer one
        $this->assertSame(
            [
                ['name' => 'id', 'orgName' => 'id', 'table' => 'p', 'schema' => ''],
                ['name' => 'name', 'orgName' => 'name', 'table' => 'p', 'schema' => ''],
            ],
            $this->parseColumns(
                'SELECT p.id /* old: /* p.code */ , p.legacy /*/ x **/ */, p.name FROM people p /* /* */',
                ['people', 'people'],
                2
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
                ['users', 'users'],
                3
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
                ['users', 'books', 'users'],
                3
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
            'a table after the ON condition of a join' => [
                'SELECT total, qty FROM users u JOIN shop.orders o ON o.uid = u.id AND o.x IN (1, 2), app.carts c'
                . ' WHERE c.uid = u.id',
                ['orders', 'carts'],
                [$column('total', 'o', 'shop'), $column('qty', 'c', 'app')],
            ],
            'a table read only in a derived table: keyed by its name' => [
                'SELECT nm FROM (SELECT name AS nm FROM app.people p) s',
                ['people'],
                [['name' => 'nm', 'orgName' => 'nm', 'table' => 'people', 'schema' => 'app']],
            ],
            'a table read only in a joined derived table' => [
                'SELECT u.id, nm FROM users u LEFT JOIN (SELECT uid, name AS nm FROM people p) s ON s.uid = u.id',
                ['users', 'people'],
                [$column('id', 'u'), ['name' => 'nm', 'orgName' => 'nm', 'table' => 'people', 'schema' => '']],
            ],
            'a table read only in a derived table after a comma' => [
                'SELECT u.id, nm FROM users u, (SELECT name AS nm FROM (SELECT name FROM people p) q) s',
                ['users', 'people'],
                [$column('id', 'u'), ['name' => 'nm', 'orgName' => 'nm', 'table' => 'people', 'schema' => '']],
            ],
            'a derived table whose alias differs only by case from a table of the query' => [
                'SELECT u.id, nm FROM users u JOIN (SELECT name AS nm FROM people "U") s ON true',
                ['users', 'people'],
                [$column('id', 'u'), ['name' => 'nm', 'orgName' => 'nm', 'table' => 'people', 'schema' => '']],
            ],
            'a table read only in a LATERAL sub-query: keyed by its alias' => [
                'SELECT u.id, nm FROM users u'
                . ' JOIN LATERAL (SELECT name AS nm FROM people p WHERE p.uid = u.id) s ON true',
                ['users', 'people'],
                [$column('id', 'u'), ['name' => 'nm', 'orgName' => 'nm', 'table' => 'p', 'schema' => '']],
            ],
            'a table read only in a CTE: keyed by its name' => [
                'WITH c AS (SELECT name AS nm FROM people p) SELECT nm, u.id FROM users u, c',
                ['people', 'users'],
                [['name' => 'nm', 'orgName' => 'nm', 'table' => 'people', 'schema' => ''], $column('id', 'u')],
            ],
            'tables read only in WITH RECURSIVE and several CTEs' => [
                'WITH RECURSIVE c (nm) AS NOT MATERIALIZED (SELECT name FROM app.people p),'
                . ' d AS MATERIALIZED (SELECT 1 FROM orders o), e AS (VALUES (1))'
                . ' SELECT nm, (SELECT 1 FROM d) AS one FROM c',
                ['people', false],
                [
                    ['name' => 'nm', 'orgName' => 'nm', 'table' => 'people', 'schema' => 'app'],
                    ['name' => 'one', 'orgName' => '(SELECT 1 FROM d)', 'table' => '', 'schema' => ''],
                ],
            ],
            'a CTE whose alias differs only by case from a table of the query' => [
                'WITH c AS (SELECT name AS nm FROM people "U") SELECT nm, u.id FROM users u JOIN c ON true',
                ['people', 'users'],
                [['name' => 'nm', 'orgName' => 'nm', 'table' => 'people', 'schema' => ''], $column('id', 'u')],
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
            // A table of a CTE ranks after every table of the main statement, however deep
            'the same table in a CTE and in a derived table of a parenthesized main SELECT' => [
                'WITH c AS (SELECT q.id FROM archive.book q) (SELECT id, title FROM (SELECT id, title FROM book) s)',
                ['book', 'book'],
                [
                    ['name' => 'id', 'orgName' => 'id', 'table' => 'book', 'schema' => ''],
                    ['name' => 'title', 'orgName' => 'title', 'table' => 'book', 'schema' => ''],
                ],
            ],
            'the same table in a CTE and in nested derived tables of the main SELECT' => [
                'WITH c AS (SELECT q.id FROM archive.book q)'
                . ' SELECT id FROM (SELECT id FROM (SELECT id FROM book) s1) s2',
                ['book'],
                [['name' => 'id', 'orgName' => 'id', 'table' => 'book', 'schema' => '']],
            ],
            'tables of nested CTEs: the closest to the main SELECT' => [
                'WITH c AS (SELECT x.id FROM (SELECT y.id FROM archive.book y) x), d AS (SELECT z.id FROM book z)'
                . ' (SELECT id FROM c UNION SELECT id FROM d)',
                ['book'],
                [['name' => 'id', 'orgName' => 'id', 'table' => 'book', 'schema' => '']],
            ],
            'non-ASCII letters in unquoted names' => [
                'SELECT ü.id, ü.prénom, prénom, ü.id AS clé, count(*) AS total_é, ü.name nöm FROM app.usérs AS ü',
                ['usérs', 'usérs', 'usérs', 'usérs', false, false],
                [
                    $column('id', 'ü', 'app'),
                    $column('prénom', 'ü', 'app'),
                    $column('prénom', 'ü', 'app'),
                    ['name' => 'clé', 'orgName' => 'id', 'table' => 'ü', 'schema' => 'app'],
                    ['name' => 'total_é', 'orgName' => 'count(*)', 'table' => '', 'schema' => ''],
                    ['name' => 'nöm', 'orgName' => 'name', 'table' => 'ü', 'schema' => 'app'],
                ],
            ],
            'a parenthesized join starting with a derived table' => [
                'SELECT p.id, p.name FROM ((SELECT 1 AS one) s JOIN tenant_a.people p ON true)'
                . ' WHERE p.id NOT IN (SELECT p.id FROM tenant_b.people p)',
                ['people', 'people'],
                [$column('id', 'p', 'tenant_a'), $column('name', 'p', 'tenant_a')],
            ],
            'a parenthesized join starting with VALUES' => [
                'SELECT id, name FROM ((VALUES (1)) v (one) JOIN app.people p ON true)',
                ['people', 'people'],
                [$column('id', 'p', 'app'), $column('name', 'p', 'app')],
            ],
            'the first table of a parenthesized join' => [
                'SELECT p.id, o.total FROM (tenant_a.people p JOIN orders o ON true)'
                . ' WHERE p.id NOT IN (SELECT p.id FROM tenant_b.people p)',
                ['people', 'orders'],
                [$column('id', 'p', 'tenant_a'), $column('total', 'o')],
            ],
            'nested parenthesized joins' => [
                'SELECT name, total FROM (((SELECT 1) s JOIN app.people p ON true) LEFT JOIN (shop.orders o'
                . ' JOIN items i ON true) ON true) WHERE EXISTS (SELECT 1 FROM tenant_b.people p, x.orders o)',
                ['people', 'orders'],
                [$column('name', 'p', 'app'), $column('total', 'o', 'shop')],
            ],
            'a sub-query starting with a parenthesized SELECT' => [
                'SELECT nm FROM (((SELECT name AS nm FROM app.people p)) UNION SELECT 1) s',
                ['people'],
                [['name' => 'nm', 'orgName' => 'nm', 'table' => 'people', 'schema' => 'app']],
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
        $this->assertSame($expected, $this->parseColumns($query, $tables, \count($expected)));
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
                    ['id', 'id', 'users', 'Users', 'App'],
                    ['name', 'name', 'users', 'Users', 'App'],
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
            'a quoted alias, the key of the row in lowercase' => [
                'SELECT "U".id, name FROM "MyUsers" "U"',
                ['MyUsers', 'MyUsers'],
                [
                    ['id', 'id', 'u', 'MyUsers', ''],
                    ['name', 'name', 'u', 'MyUsers', ''],
                ],
            ],
        ];
    }

    public function testSetQueryShouldRaiseExceptionOnAliasesDifferingOnlyByCase()
    {
        $this->assertThrows(
            QueryException::class,
            fn () => $this->parseColumns(
                'SELECT "U".id, u.name FROM users "U" JOIN users u ON true',
                ['users', 'users'],
                2
            ),
            'Query invalid: the aliases U and u differ only by case, the key of their row: rename one of them'
        );
        // The alias of a LATERAL sub-query is the key of its columns
        $this->assertThrows(
            QueryException::class,
            fn () => $this->parseColumns(
                'SELECT u.id, name FROM users u, LATERAL (SELECT "U".name FROM people "U" WHERE "U".id = u.id) x',
                ['users', 'people'],
                2
            ),
            'Query invalid: the aliases u and U differ only by case, the key of their row: rename one of them'
        );
    }

    public static function derivedTableNameProvider(): array
    {
        $id = ['name' => 'id', 'orgName' => 'id', 'table' => 'u', 'schema' => ''];
        $nm = ['name' => 'nm', 'orgName' => 'nm', 'table' => '', 'schema' => ''];

        return [
            'a derived table' => [
                'SELECT u.id, nm FROM users u JOIN (SELECT name AS nm FROM "U") s ON true',
                ['users', 'U'],
                [$id, $nm],
            ],
            'a derived table read first' => [
                'SELECT nm, u.id FROM (SELECT name AS nm FROM app."U") s, users u',
                ['U', 'users'],
                [$nm, $id],
            ],
            'a CTE' => [
                'WITH c AS (SELECT name AS nm FROM "U") SELECT u.id, nm FROM users u, c',
                ['users', 'U'],
                [$id, $nm],
            ],
            'two derived tables' => [
                'SELECT nm, n2 FROM (SELECT name AS nm FROM "U") s, (SELECT name AS n2 FROM u) t',
                ['U', 'u'],
                [
                    ['name' => 'nm', 'orgName' => 'nm', 'table' => 'u', 'schema' => ''],
                    ['name' => 'n2', 'orgName' => 'n2', 'table' => '', 'schema' => ''],
                ],
            ],
        ];
    }

    /**
     * A table read only in a derived table or a CTE is keyed by its name, which the query cannot rename: when it
     * differs only by case from another key of the row, its columns are keyed by no table rather than throwing
     *
     * @param list<string> $tables
     * @param list<array<string, string>> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('derivedTableNameProvider')]
    public function testSetQueryShouldNotKeyADerivedTableNameDifferingOnlyByCase(
        string $query,
        array $tables,
        array $expected
    ) {
        $this->assertSame($expected, $this->parseColumns($query, $tables, \count($expected)));
    }

    public function testSetQueryShouldAcceptAliasesDifferingOnlyByCaseWithoutColumns()
    {
        // Only the aliases of the columns read are keys of the row
        $this->assertSame(
            [['name' => 'id', 'orgName' => 'id', 'table' => 'u', 'schema' => '']],
            $this->parseColumns('SELECT u.id FROM users u JOIN orders "U" ON true', ['users'], 1)
        );
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
        NativeFunctionMock::override('pg_num_fields', \count($expected));

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
                    ['name' => '?column?', 'orgName' => 'ts AT TIME ZONE tz', 'table' => '', 'schema' => ''],
                    $id,
                ],
            ],
            'casts, named after the column' => [
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
                    ['name' => '?column?', 'orgName' => "id::text || 'x'", 'table' => '', 'schema' => ''],
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
                    ['name' => 'c', 'orgName' => 'b', 'table' => 't', 'schema' => ''],
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
            'parenthesized WITH query' => [
                '(WITH c AS (SELECT 1 AS a, u.id AS b FROM users u) SELECT c.a, p.name FROM c JOIN app.people p ON true)',
                [false, 'people'],
                [
                    ['name' => 'a', 'orgName' => 'a', 'table' => 'c', 'schema' => ''],
                    ['name' => 'name', 'orgName' => 'name', 'table' => 'p', 'schema' => 'app'],
                ],
            ],
            'parenthesized WITH query in a UNION' => [
                '((WITH RECURSIVE c (a) AS (SELECT 1 UNION ALL SELECT a + 1 FROM c WHERE a < 3) SELECT c.a AS x FROM c))'
                . ' UNION ALL (SELECT 2)',
                [],
                [['name' => 'x', 'orgName' => 'a', 'table' => 'c', 'schema' => '']],
            ],
            'WITH query whose main SELECT is parenthesized' => [
                'WITH x AS (SELECT 1 AS a) (SELECT x.a FROM x)',
                [false],
                [['name' => 'a', 'orgName' => 'a', 'table' => 'x', 'schema' => '']],
            ],
            'WITH query whose parenthesized main SELECT starts a UNION' => [
                'WITH c AS (SELECT 1 q) (SELECT u.name AS label FROM app.users u LIMIT 1)'
                . ' UNION ALL SELECT o.title FROM app.other o',
                [],
                [['name' => 'label', 'orgName' => 'name', 'table' => 'u', 'schema' => 'app']],
            ],
            'WITH query whose parenthesized main SELECT without FROM starts a UNION' => [
                'WITH c AS (SELECT 1 q) (SELECT 1 AS x, 2 AS y) UNION SELECT 3, 4',
                [],
                [
                    ['name' => 'x', 'orgName' => '1', 'table' => '', 'schema' => ''],
                    ['name' => 'y', 'orgName' => '2', 'table' => '', 'schema' => ''],
                ],
            ],
            'parenthesized WITH query whose parenthesized main SELECT starts a UNION' => [
                '(WITH c AS (SELECT 1 q) (SELECT u.name AS label FROM app.users u)'
                . ' UNION ALL SELECT o.title FROM app.other o)',
                [],
                [['name' => 'label', 'orgName' => 'name', 'table' => 'u', 'schema' => 'app']],
            ],
            'WITH query whose main SELECT is in nested parentheses' => [
                'WITH c AS (SELECT 1 q) ((SELECT 1 AS x, c.q FROM c)) UNION ((SELECT 2, 3))',
                [],
                [
                    ['name' => 'x', 'orgName' => '1', 'table' => '', 'schema' => ''],
                    ['name' => 'q', 'orgName' => 'q', 'table' => 'c', 'schema' => ''],
                ],
            ],
            'WITH query with comments around the parenthesis of its main SELECT' => [
                "WITH c AS (SELECT 1 q)/* ( */( -- (\n SELECT 1 AS x) INTERSECT SELECT 1",
                [],
                [['name' => 'x', 'orgName' => '1', 'table' => '', 'schema' => '']],
            ],
            'WITH query with several CTEs before its parenthesized main SELECT' => [
                'WITH a (x) AS MATERIALIZED (SELECT 1), b AS NOT MATERIALIZED (SELECT 2 y)'
                . ' (SELECT a.x AS v, b.y FROM a, b) EXCEPT SELECT 1, 2',
                [],
                [
                    ['name' => 'v', 'orgName' => 'x', 'table' => 'a', 'schema' => ''],
                    ['name' => 'y', 'orgName' => 'y', 'table' => 'b', 'schema' => ''],
                ],
            ],
            'WITH RECURSIVE query with SEARCH before its parenthesized main SELECT' => [
                'WITH RECURSIVE t (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM t WHERE n < 3)'
                . ' SEARCH DEPTH FIRST BY n SET ord (SELECT t.n AS v FROM t ORDER BY ord) UNION ALL SELECT 0',
                [],
                [['name' => 'v', 'orgName' => 'n', 'table' => 't', 'schema' => '']],
            ],
            'WITH RECURSIVE query with CYCLE before its parenthesized main SELECT' => [
                'WITH RECURSIVE t (n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM t WHERE n < 3)'
                . ' CYCLE n SET is_cycle USING path (SELECT t.n AS v FROM t)',
                [],
                [['name' => 'v', 'orgName' => 'n', 'table' => 't', 'schema' => '']],
            ],
            'WITH query with a data-modifying CTE before its parenthesized main SELECT' => [
                'WITH d AS (DELETE FROM app.users u WHERE u.id = 1 RETURNING u.id)'
                . ' (SELECT d.id AS removed FROM d) EXCEPT SELECT 2',
                [],
                [['name' => 'removed', 'orgName' => 'id', 'table' => 'd', 'schema' => '']],
            ],
            'WITH query whose parenthesized main SELECT is a WITH query' => [
                'WITH c AS (SELECT 1 q) (WITH d AS (SELECT 2 r) SELECT d.r AS z FROM d) UNION SELECT 3',
                [],
                [['name' => 'z', 'orgName' => 'r', 'table' => 'd', 'schema' => '']],
            ],
            'aliases named normalized' => [
                'SELECT u.name normalized, f(x) normalized, x || y normalized, x IS NOT NFKD NORMALIZED FROM users u',
                ['users'],
                [
                    ['name' => 'normalized', 'orgName' => 'name', 'table' => 'u', 'schema' => ''],
                    ['name' => 'normalized', 'orgName' => 'f(x)', 'table' => '', 'schema' => ''],
                    ['name' => 'normalized', 'orgName' => 'x || y', 'table' => '', 'schema' => ''],
                    ['name' => '?column?', 'orgName' => 'x IS NOT NFKD NORMALIZED', 'table' => '', 'schema' => ''],
                ],
            ],
            'an alias after CASE, last of the query' => [
                'SELECT CASE WHEN a THEN 1 END total',
                [],
                [['name' => 'total', 'orgName' => 'CASE WHEN a THEN 1 END', 'table' => '', 'schema' => '']],
            ],
            'a quoted alias after CASE, last of the query' => [
                'SELECT CASE WHEN a THEN 1 END "Total"',
                [],
                [['name' => 'Total', 'orgName' => 'CASE WHEN a THEN 1 END', 'table' => '', 'schema' => '']],
            ],
            'an alias after an operation on CASE, last of the query' => [
                'SELECT 1 + CASE WHEN a THEN 1 END total',
                [],
                [['name' => 'total', 'orgName' => '1 + CASE WHEN a THEN 1 END', 'table' => '', 'schema' => '']],
            ],
            'an alias after CASE, last column of the query' => [
                'SELECT u.id, CASE WHEN a THEN 1 END total',
                ['users'],
                [
                    ['name' => 'id', 'orgName' => 'id', 'table' => 'u', 'schema' => ''],
                    ['name' => 'total', 'orgName' => 'CASE WHEN a THEN 1 END', 'table' => '', 'schema' => ''],
                ],
            ],
            'a clause without FROM' => [
                "SELECT 1 AS x, lower('A') WHERE true UNION SELECT 2, 'b' ORDER BY 1 LIMIT 1",
                [],
                [
                    ['name' => 'x', 'orgName' => '1', 'table' => '', 'schema' => ''],
                    ['name' => 'lower', 'orgName' => "lower('A')", 'table' => '', 'schema' => ''],
                ],
            ],
            'names of a keyword of a clause' => [
                'SELECT t.order, x AS limit, string_agg(y, \',\') WITHIN GROUP (ORDER BY y) FROM t',
                [],
                [
                    ['name' => 'order', 'orgName' => 'order', 'table' => 't', 'schema' => ''],
                    ['name' => 'limit', 'orgName' => 'x', 'table' => '', 'schema' => ''],
                    [
                        'name' => '?column?',
                        'orgName' => "string_agg(y, ',') WITHIN GROUP (ORDER BY y)",
                        'table' => '',
                        'schema' => '',
                    ],
                ],
            ],
        ];
    }

    public static function statementEndProvider(): array
    {
        return [
            'a semicolon then a line feed' => ["SELECT nextval('s') AS id;\n", [['id', "nextval('s')"]]],
            'a semicolon then blanks' => ['SELECT 1 AS one, 2 AS two;  ', [['one', '1'], ['two', '2']]],
            'a semicolon then a comment' => ["SELECT 1 AS one; -- done\n", [['one', '1']]],
            'a semicolon then a block comment' => ['SELECT 1 AS one; /* done */', [['one', '1']]],
            'a blank before the semicolon' => ["SELECT 1 AS one ;\n", [['one', '1']]],
            'without alias' => ["SELECT nextval('s');\n", [['nextval', "nextval('s')"]]],
            'a CASE with an alias' => [
                "SELECT CASE WHEN true THEN 1 END kind;\n",
                [['kind', 'CASE WHEN true THEN 1 END']],
            ],
            'a CASE without alias' => [
                "SELECT CASE WHEN true THEN 1 END;\n",
                [['?column?', 'CASE WHEN true THEN 1 END']],
            ],
        ];
    }

    /**
     * A semicolon ends the statement, whatever follows it, as the last column of a SELECT without FROM
     *
     * @param list<array{string, string}> $expected name, orgName
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('statementEndProvider')]
    public function testSetQueryShouldEndTheSelectListOnASemicolon(string $query, array $expected)
    {
        $this->assertSame(
            $expected,
            array_map(
                fn (array $column) => [$column['name'], $column['orgName']],
                $this->parseColumns($query, [], \count($expected))
            )
        );
    }

    public static function formFeedProvider(): array
    {
        $columns = [['id', 'id', 'p'], ['name', 'name', 'p']];

        return [
            'a semicolon then a form feed' => ["SELECT p.id, p.name FROM people p;\f", $columns],
            'a semicolon then a vertical tab' => ["SELECT p.id, p.name FROM people p;\v", $columns],
            'a form feed before DISTINCT' => ["SELECT\fDISTINCT p.id, p.name FROM people p", $columns],
            'a form feed around the table' => ["SELECT id, name FROM\fpeople\fp", $columns],
            'a form feed before a parenthesized main SELECT' => [
                "(\fSELECT p.id, p.name FROM people p) UNION (SELECT p.id, p.name FROM people p)",
                $columns,
            ],
            'a form feed before an alias named as a clause' => [
                "SELECT p.id AS\flimit, p.name FROM people p",
                [['limit', 'id', 'p'], ['name', 'name', 'p']],
            ],
            'form feeds in IS DISTINCT FROM' => [
                "SELECT p.a IS\fDISTINCT\fFROM p.b AS d, p.id FROM people p",
                [['d', 'p.a IS' . "\f" . 'DISTINCT' . "\f" . 'FROM p.b', ''], ['id', 'id', 'p']],
            ],
            'a form feed after a CASE' => [
                "SELECT CASE WHEN true THEN 1 END\f, p.id FROM people p",
                [['?column?', 'CASE WHEN true THEN 1 END', ''], ['id', 'id', 'p']],
            ],
        ];
    }

    /**
     * A form feed and a vertical tab are blanks, as PostgreSQL reads them (space, \t, \n, \r, \f, \v)
     *
     * @param list<array{string, string, string}> $expected name, orgName, table
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('formFeedProvider')]
    public function testSetQueryShouldReadAFormFeedAsABlank(string $query, array $expected)
    {
        $this->assertSame(
            $expected,
            array_map(
                fn (array $column) => [$column['name'], $column['orgName'], $column['table']],
                $this->parseColumns(
                    $query,
                    array_map(fn (array $column) => $column[2] === '' ? false : 'people', $expected),
                    \count($expected)
                )
            )
        );
    }

    public static function severalStatementsProvider(): array
    {
        return [
            'two SELECT without FROM' => ['SELECT 1 AS a; SELECT 2 AS b', [], [['b', '2', '']]],
            'two SELECT ended by a semicolon' => [
                "SELECT 1 AS a; SELECT 2 AS b, 3 AS c;\n",
                [],
                [['b', '2', ''], ['c', '3', '']],
            ],
            'a setting then a SELECT' => [
                "SELECT set_config('x', 'y', true); SELECT u.id, u.name FROM users u",
                ['users', 'users'],
                [['id', 'id', 'u'], ['name', 'name', 'u']],
            ],
            'two SELECT with FROM' => ['SELECT id FROM t; SELECT name FROM u', ['u'], [['name', 'name', 'u']]],
            'two semicolons' => ['SELECT 1 AS a;; ', [], [['a', '1', '']]],
            'a semicolon in a string' => ["SELECT 'a;b' AS x; ", [], [['x', "'a;b'", '']]],
            'a semicolon in an escape string' => ["SELECT E'\\';' AS x", [], [['x', "E'\\';'", '']]],
            'a semicolon in a dollar-quoted string' => ['SELECT $t$;$t$ AS x;', [], [['x', '$t$;$t$', '']]],
            'a semicolon in a quoted identifier' => ['SELECT "a;b" FROM t', ['t'], [['a;b', 'a;b', 't']]],
            'a semicolon in a block comment' => ['SELECT 1 /* ; SELECT 2 AS b */ AS a', [], [['a', '1', '']]],
            'a semicolon in a line comment' => ["SELECT 1 AS a -- ; SELECT 2 AS b\n", [], [['a', '1', '']]],
            'a statement after a comment' => ["SELECT 1 AS a; -- x\nSELECT 2 AS b", [], [['b', '2', '']]],
        ];
    }

    /**
     * A query of several statements gives the result of the last one, as pg_query() returns it
     *
     * @param array<int, string|false> $tables
     * @param list<array{string, string, string}> $expected name, orgName, table
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('severalStatementsProvider')]
    public function testSetQueryShouldReadTheColumnsOfTheLastStatement(string $query, array $tables, array $expected)
    {
        $this->assertSame(
            $expected,
            array_map(
                fn (array $column) => [$column['name'], $column['orgName'], $column['table']],
                $this->parseColumns($query, $tables, \count($expected))
            )
        );
    }

    public function testSetQueryShouldRaiseExceptionWhenTheLastStatementHasNoColumn()
    {
        $result = new Result();
        $result->setResult(new PgsqlResult());

        $this->assertThrows(
            QueryException::class,
            fn () => $result->setQuery('BEGIN; SELECT id FROM t; COMMIT'),
            'Query invalid: can\'t parse columns'
        );
    }

    public static function columnCountMismatchProvider(): array
    {
        return [
            'more columns in the result' => [
                'SELECT p.id, p.name FROM people p',
                3,
                'Query invalid: 2 columns read from the query, but its result has 3: the values cannot be matched to '
                . 'the columns',
            ],
            'fewer columns in the result' => [
                'SELECT p.id, p.name FROM people p',
                1,
                'Query invalid: 2 columns read from the query, but its result has 1: the values cannot be matched to '
                . 'the columns',
            ],
            'one column read' => [
                'SELECT p.id FROM people p',
                2,
                'Query invalid: 1 column read from the query, but its result has 2: the values cannot be matched to '
                . 'the columns',
            ],
        ];
    }

    /**
     * A query parsed into another number of columns than its result (a misread query, a composite expanded by the
     * server) would hydrate each column with the value of another one, or with none
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('columnCountMismatchProvider')]
    public function testSetQueryShouldRaiseExceptionWhenTheResultHasAnotherNumberOfColumns(
        string $query,
        int $numFields,
        string $message
    ) {
        NativeFunctionMock::override('pg_num_fields', $numFields);
        NativeFunctionMock::override('pg_field_table', 'people');

        $result = new Result();
        $result->setResult(new PgsqlResult());

        $this->assertThrows(QueryException::class, fn () => $result->setQuery($query), $message);
    }

    public static function resultWithoutColumnProvider(): array
    {
        return [
            'SELECT INTO TEMP' => ['SELECT a, b INTO TEMP x FROM t', 0, []],
            'SELECT INTO' => ['SELECT 1 AS a INTO x', 0, []],
            'SELECT without column' => ['SELECT FROM t', 2, [[], []]],
            'SELECT INTO ended by a semicolon' => ["SELECT a INTO x FROM t;\n", 0, []],
            'SELECT INTO after an empty statement' => ['; SELECT a INTO x FROM t', 0, []],
            // The SELECT after AS is the query of the statement, not an alias
            'CREATE TABLE AS' => ['CREATE TABLE z AS SELECT u.id, u.name FROM users u', 0, []],
            'CREATE TEMP TABLE AS' => ['CREATE TEMP TABLE z AS SELECT max(u.id) AS m FROM users u', 0, []],
            'CREATE MATERIALIZED VIEW' => ['CREATE MATERIALIZED VIEW v AS SELECT 1 AS x', 0, []],
            'PREPARE' => ['PREPARE p AS SELECT 1 AS x', 0, []],
            'CREATE TABLE AS then a form feed' => ["CREATE TABLE z AS\fSELECT 1 AS x", 0, []],
        ];
    }

    /**
     * A result without column (SELECT ... INTO creates a table and returns no row, SELECT FROM t returns rows without
     * column) has no value to shift and no field to read: its rows hold no column
     *
     * @param list<array{}> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('resultWithoutColumnProvider')]
    public function testSetQueryShouldReadNoColumnOfAResultWithoutColumn(string $query, int $rows, array $expected)
    {
        NativeFunctionMock::override('pg_num_fields', 0);
        // PHP throws a ValueError for a field the result does not have
        NativeFunctionMock::override('pg_field_table', static function (): never {
            throw new \ValueError('pg_field_table(): Argument #2 ($field) must be less than the number of fields');
        });
        NativeFunctionMock::override('pg_result_seek', true);
        NativeFunctionMock::override('pg_fetch_array', static function () use (&$rows): array|false {
            return $rows-- > 0 ? [] : false;
        });

        $result = new Result();
        $result->setResult(new PgsqlResult());

        $rowsRead = null;
        $types = $this->collectErrorTypes(function () use ($result, $query, &$rowsRead): void {
            $result->setQuery($query);
            $rowsRead = iterator_to_array($result);
        }, $thrown);

        $this->assertNull($thrown);
        $this->assertSame([], $types);
        $this->assertSame($expected, $rowsRead);
    }

    public static function lastStatementWithoutColumnProvider(): array
    {
        return [
            'INSERT ... SELECT' => ['SELECT id FROM people; INSERT INTO audit (at) SELECT now()'],
            'CREATE TABLE AS' => ['SELECT id FROM people; CREATE TABLE snap AS SELECT id FROM people'],
            'SELECT INTO' => ['SELECT id FROM people; SELECT id INTO snap FROM people'],
            'SELECT without column' => ['SELECT id FROM people; SELECT FROM people'],
        ];
    }

    /**
     * The last statement of several, holding a SELECT, returns no column: as one without SELECT (COMMIT), its result
     * is not read as a result without column
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('lastStatementWithoutColumnProvider')]
    public function testSetQueryShouldRaiseExceptionWhenTheLastOfSeveralStatementsReturnsNoColumn(string $query)
    {
        NativeFunctionMock::override('pg_num_fields', 0);

        $result = new Result();
        $result->setResult(new PgsqlResult());

        $this->assertThrows(
            QueryException::class,
            fn () => $result->setQuery($query),
            'Query invalid: can\'t parse columns'
        );
    }

    public function testSetQueryShouldRaiseExceptionWhenTheLastStatementHasNoColumnAndItsResultNone()
    {
        // The last statement returns no column, the query none: it is not read as a result without column
        NativeFunctionMock::override('pg_num_fields', 0);

        $result = new Result();
        $result->setResult(new PgsqlResult());

        $this->assertThrows(
            QueryException::class,
            fn () => $result->setQuery('SELECT id FROM t; COMMIT'),
            'Query invalid: can\'t parse columns'
        );
    }

    public static function keywordAliasProvider(): array
    {
        return [
            'FROM' => ['SELECT p.id AS from, p.name FROM people p', [['from', 'id', 'p'], ['name', 'name', 'p']]],
            'FROM after a comment' => [
                "SELECT p.id AS /* c */ from, p.name FROM people p",
                [['from', 'id', 'p'], ['name', 'name', 'p']],
            ],
            'FROM after a line break' => [
                "SELECT p.id AS\nFROM, p.name FROM people p",
                [['FROM', 'id', 'p'], ['name', 'name', 'p']],
            ],
            'FROM, last column' => [
                'SELECT p.name, p.id AS from FROM people p',
                [['name', 'name', 'p'], ['from', 'id', 'p']],
            ],
            'FROM, without FROM clause' => ['SELECT 1 AS from, 2 AS b', [['from', '1', ''], ['b', '2', '']]],
            'LIMIT and ORDER' => ['SELECT 1 AS limit, 2 AS order', [['limit', '1', ''], ['order', '2', '']]],
            'WHERE and GROUP' => [
                'SELECT p.id AS where, p.name AS group FROM people p',
                [['where', 'id', 'p'], ['group', 'name', 'p']],
            ],
            'UNION and INTO' => [
                'SELECT p.id AS union, p.name AS into FROM people p',
                [['union', 'id', 'p'], ['into', 'name', 'p']],
            ],
            'HAVING, WINDOW, OFFSET, FETCH, FOR' => [
                'SELECT 1 AS having, 2 AS window, 3 AS offset, 4 AS fetch, 5 AS for',
                [['having', '1', ''], ['window', '2', ''], ['offset', '3', ''], ['fetch', '4', ''], ['for', '5', '']],
            ],
            'SELECT' => ['SELECT 1 AS select, 2 AS b', [['select', '1', ''], ['b', '2', '']]],
            'DISTINCT before FROM' => [
                'SELECT p.name, p.id AS distinct FROM people p',
                [['name', 'name', 'p'], ['distinct', 'id', 'p']],
            ],
            'IS then DISTINCT' => [
                'SELECT p.ok AS is, p.id AS distinct FROM people p',
                [['is', 'ok', 'p'], ['distinct', 'id', 'p']],
            ],
            'CASE and END' => ['SELECT 1 AS case, 2 AS end', [['case', '1', ''], ['end', '2', '']]],
            'END inside a CASE' => [
                'SELECT CASE WHEN p.ok THEN (SELECT 1 AS end) END AS k, p.id FROM people p',
                [['k', 'CASE WHEN p.ok THEN (SELECT 1 AS end) END', ''], ['id', 'id', 'p']],
            ],
            'AS as' => ['SELECT u.id AS as FROM people u', [['as', 'id', 'u']]],
            'AS as twice' => [
                'SELECT u.id AS as, u.name AS as FROM people u',
                [['as', 'id', 'u'], ['as', 'name', 'u']],
            ],
            'AS case before a CASE' => [
                'SELECT u.id AS case, CASE WHEN u.a THEN u.b END AS x FROM people u',
                [['case', 'id', 'u'], ['x', 'CASE WHEN u.a THEN u.b END', '']],
            ],
            'the query of EXPLAIN CREATE TABLE AS' => ['EXPLAIN CREATE TABLE z AS SELECT 1 AS x', [['x', '1', '']]],
            'a FROM alias before a qualified column' => [
                'SELECT id AS from, p.name FROM people p',
                [['from', 'id', 'p'], ['name', 'name', 'p']],
            ],
        ];
    }

    /**
     * Any keyword, reserved included, is an alias after AS (a ColLabel for PostgreSQL), not a clause
     *
     * @param list<array{string, string, string}> $expected name, orgName, table
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('keywordAliasProvider')]
    public function testSetQueryShouldReadAKeywordAfterAsAsAnAlias(string $query, array $expected)
    {
        $this->assertSame(
            $expected,
            array_map(
                fn (array $column) => [$column['name'], $column['orgName'], $column['table']],
                $this->parseColumns(
                    $query,
                    array_map(fn (array $column) => $column[2] === '' ? false : 'people', $expected),
                    \count($expected)
                )
            )
        );
    }

    public static function keywordFieldProvider(): array
    {
        return [
            'FROM' => ['SELECT t.from FROM t', [['from', 'from', 't']]],
            'FROM then a column' => ['SELECT t.from, t.id FROM t', [['from', 'from', 't'], ['id', 'id', 't']]],
            'FROM of a quoted table' => ['SELECT "t".from, t.id FROM t', [['from', 'from', 't'], ['id', 'id', 't']]],
            'FROM with an alias' => ['SELECT t.from AS x, t.id FROM t', [['x', 'from', 't'], ['id', 'id', 't']]],
            'FROM of a composite' => [
                'SELECT (u).from, u.id FROM t u',
                [['?column?', '(u).from', ''], ['id', 'id', 'u']],
            ],
            'FROM in a sub-query' => [
                'SELECT u.id, (SELECT t.from FROM t) AS z FROM t u',
                [['id', 'id', 'u'], ['z', '(SELECT t.from FROM t)', '']],
            ],
            'FROM in a sub-query of WHERE' => [
                'SELECT u.id FROM t u WHERE u.x IN (SELECT t.from FROM t)',
                [['id', 'id', 'u']],
            ],
            'SELECT' => ['SELECT t.select FROM t', [['select', 'select', 't']]],
            'CASE' => ['SELECT t.case, t.id FROM t', [['case', 'case', 't'], ['id', 'id', 't']]],
            'CASE in an expression with an alias' => [
                'SELECT t.case + 1 x, t.id FROM t',
                [['x', 't.case + 1', ''], ['id', 'id', 't']],
            ],
            'CASE with an alias' => ['SELECT t.case AS x, t.id FROM t', [['x', 'case', 't'], ['id', 'id', 't']]],
            'END' => ['SELECT t.end, t.id FROM t', [['end', 'end', 't'], ['id', 'id', 't']]],
            'END inside a CASE' => [
                'SELECT CASE WHEN t.a THEN t.end END AS x, t.id FROM t',
                [['x', 'CASE WHEN t.a THEN t.end END', ''], ['id', 'id', 't']],
            ],
            'INTO' => ['SELECT t.into, t.id FROM t', [['into', 'into', 't'], ['id', 'id', 't']]],
            'LIMIT' => ['SELECT t.limit, t.id FROM t', [['limit', 'limit', 't'], ['id', 'id', 't']]],
            // The dot of a number is no field access: FROM ends the select list
            'a number ending with a dot' => ['SELECT 1. FROM t', [['?column?', '1.', '']]],
            'a number ending with a dot, then FROM' => ['SELECT 1.FROM t', [['?column?', '1.', '']]],
            'a number ending with a dot, then LIMIT' => ['SELECT 1. LIMIT 1', [['?column?', '1.', '']]],
            'a number ending with a dot, then UNION' => ['SELECT 1. UNION SELECT 2', [['?column?', '1.', '']]],
            'a number ending with a dot, then WHERE' => ['SELECT 1. WHERE true', [['?column?', '1.', '']]],
            'a decimal number, then LIMIT' => ['SELECT 1.5 LIMIT 1', [['?column?', '1.5', '']]],
            'a number ending with a dot, with an alias' => ['SELECT 1. AS x LIMIT 1', [['x', '1.', '']]],
            'clause keywords, then FOR UPDATE' => [
                'SELECT t.limit, t.where, t.into, t.for FROM t FOR UPDATE',
                [['limit', 'limit', 't'], ['where', 'where', 't'], ['into', 'into', 't'], ['for', 'for', 't']],
            ],
            'AS after a field' => ['SELECT t.as AS x, t.id FROM t', [['x', 'as', 't'], ['id', 'id', 't']]],
            'FROM after the field as' => ['SELECT t.as AS from FROM t', [['from', 'as', 't']]],
            'FROM after the field as, then a column' => [
                'SELECT t.as AS from, t.id FROM t',
                [['from', 'as', 't'], ['id', 'id', 't']],
            ],
            'SELECT after the field as' => [
                'SELECT t.as AS select, t.id FROM t',
                [['select', 'as', 't'], ['id', 'id', 't']],
            ],
            'CASE after the field as' => ['SELECT t.as AS case, t.id FROM t', [['case', 'as', 't'], ['id', 'id', 't']]],
            'the alias as, then the field as' => [
                'SELECT u.id AS as, t.as AS from FROM t, t u',
                [['as', 'id', 'u'], ['from', 'as', 't']],
            ],
            'LIMIT after the alias as' => ['SELECT 1 AS as LIMIT 1', [['as', '1', '']]],
            'WHERE after the alias as' => [
                'SELECT u.id AS as, u.name AS as WHERE true',
                [['as', 'id', 'u'], ['as', 'name', 'u']],
            ],
            'WITHIN GROUP' => [
                'SELECT count(*) WITHIN GROUP (ORDER BY a) AS x FROM t',
                [['x', 'count(*) WITHIN GROUP (ORDER BY a)', '']],
            ],
            'COLLATION FOR' => ['SELECT COLLATION FOR (x) AS c FROM t', [['c', 'COLLATION FOR (x)', '']]],
            'WITHIN GROUP, comments between, without FROM' => [
                "SELECT count(*) WITHIN /* c */ GROUP -- c\n (ORDER BY a) AS x LIMIT 1",
                [['x', "count(*) WITHIN   GROUP  \n (ORDER BY a)", '']],
            ],
            'COLLATION FOR, comments between, without FROM' => [
                "SELECT COLLATION /* c */ FOR -- c\n (x) AS c LIMIT 1",
                [['c', "COLLATION   FOR  \n (x)", '']],
            ],
            // WITHIN GROUP and COLLATION FOR are followed by a parenthesis: GROUP BY or FOR UPDATE after the alias
            // within or collation is a clause
            'GROUP BY after the alias within' => ['SELECT 1 AS within GROUP BY 1', [['within', '1', '']]],
            'FOR UPDATE after the alias collation' => ['SELECT 1 AS collation FOR UPDATE', [['collation', '1', '']]],
            'GROUP BY after the alias within, comments between' => [
                "SELECT 1 AS within /* c */ GROUP -- c\n BY 1",
                [['within', '1', '']],
            ],
            'FOR UPDATE after the alias collation, comments between' => [
                "SELECT 1 AS collation /* c */ FOR -- c\n UPDATE",
                [['collation', '1', '']],
            ],
        ];
    }

    /**
     * Any keyword, reserved included, is a name after a dot (t.from, a field of PostgreSQL), not a clause
     *
     * @param list<array{string, string, string}> $expected name, orgName, table
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('keywordFieldProvider')]
    public function testSetQueryShouldReadAKeywordAfterADotAsAField(string $query, array $expected)
    {
        $this->assertSame(
            $expected,
            array_map(
                fn (array $column) => [$column['name'], $column['orgName'], $column['table']],
                $this->parseColumns(
                    $query,
                    array_map(fn (array $column) => $column[2] === '' ? false : 't', $expected),
                    \count($expected)
                )
            )
        );
    }

    public function testSetQueryShouldNotEndTheSelectListOnTheForOfCollationFor()
    {
        // FOR ends a SELECT without FROM (FOR UPDATE), not COLLATION FOR (...)
        $this->assertSame(
            [['COLLATION FOR (u.name)', ''], ['id', 'u'], ['name', 'u']],
            array_map(
                fn (array $column) => [$column['orgName'], $column['table']],
                $this->parseColumns(
                    'SELECT COLLATION FOR (u.name), u.id, u.name FROM users u',
                    [false, 'users', 'users'],
                    3
                )
            )
        );
    }

    public static function expressionNameProvider(): array
    {
        return [
            // A cast of a column: the column
            'a cast of a column' => ['id::text', 'id'],
            'a cast of a qualified column' => ['u.id::text', 'id'],
            'a cast to a multi-word type' => ['x.y::double precision', 'y'],
            'a cast to a time zone type' => ['x::timestamp(3) with time zone', 'x'],
            'a cast to an array type' => ['t.tags::text[]', 'tags'],
            'a cast to a schema-qualified type' => ['x::public."MyType"', 'x'],
            'a cast to an interval with fields' => ['x::interval hour to minute', 'x'],
            'a chain of casts' => ['y::int::text', 'y'],
            'a cast to character varying(n)' => ['x::character varying(10)', 'x'],
            'CAST to character varying(n)' => ['CAST(x AS character varying(10))', 'x'],
            'a cast to bit varying(n)' => ['x::bit varying(5)', 'x'],
            'a cast to national character varying' => ['x::national character varying', 'x'],
            'a cast to nchar varying' => ['x::nchar varying', 'x'],
            'a cast to national char(n)' => ['x::national char(3)', 'x'],
            'a cast to an ARRAY type' => ['x::int ARRAY', 'x'],
            'a cast to an array[n] type' => ['x::int array[3]', 'x'],
            'CAST of a column' => ['CAST(u.code AS varchar(10))', 'code'],
            'a cast of a name of non-ASCII letters' => ['u.prénom::text', 'prénom'],
            'a column of schema.table.column' => ['app.city.id', 'id'],
            // A keyword without parentheses is read as a column
            'CURRENT_TIMESTAMP' => ['CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP'],
            'current_date' => ['current_date', 'current_date'],
            // A function call, cast or not: the function
            'a function call' => ['coalesce(u.a, u.b)', 'coalesce'],
            'an aggregate' => ['count(*)', 'count'],
            'a function call keeps its spelling' => ['COUNT(id)', 'COUNT'],
            'a schema-qualified function' => ['pg_catalog.lower(u.x)', 'lower'],
            'a quoted function name' => ['"MyFunc"(1)', 'MyFunc'],
            'a function with parentheses in a string' => ["concat(')', u.a, '(')", 'concat'],
            'a cast of a function call' => ['now()::date', 'now'],
            'a cast of an aggregate' => ['count(*)::int', 'count'],
            'TRIM' => ["TRIM(BOTH ' ' FROM name)", 'TRIM'],
            // Anything else: ?column?, which PostgreSQL may name otherwise
            'a cast of a parenthesized column' => ['(u.id)::text', '?column?'],
            'a cast of a literal' => ["'x'::text", '?column?'],
            'a cast of NULL' => ['NULL::int', '?column?'],
            'a cast of a parameter' => ['$1::int', '?column?'],
            'a cast of an operator expression' => ['(a + b)::numeric(10, 2)', '?column?'],
            'a cast of CASE' => ['CASE WHEN u.x THEN 1 END::text', '?column?'],
            'CAST of a function call' => ['CAST(max(u.x) AS int)', '?column?'],
            'a typed literal' => ["date '2026-01-01'", '?column?'],
            'an interval literal with a field' => ["interval '1' day", '?column?'],
            'an interval cast with a field' => ["'1'::interval day", '?column?'],
            'an interval literal with fields' => ["interval '1' day to second", '?column?'],
            'a national character literal' => ["N'abc'", '?column?'],
            'a window function' => ['count(*) OVER (PARTITION BY y)', '?column?'],
            'a window function over a named window' => ['row_number() OVER w', '?column?'],
            'an ordered-set aggregate' => ['percentile_cont(0.5) WITHIN GROUP (ORDER BY x)', '?column?'],
            'an aggregate with a filter' => ['count(*) FILTER (WHERE u.x)', '?column?'],
            'CASE' => ['CASE WHEN a THEN 1 ELSE 0 END', '?column?'],
            'nested CASE' => ['CASE WHEN a THEN CASE WHEN b THEN x ELSE y END END', '?column?'],
            'CASE in an operation' => ['CASE WHEN a THEN 1 ELSE 0 END + 2', '?column?'],
            'COLLATE' => ['u.name COLLATE "C"', '?column?'],
            'AT TIME ZONE' => ["now() AT TIME ZONE 'UTC'", '?column?'],
            'AT LOCAL' => ['u.ts AT LOCAL', '?column?'],
            'a subscript' => ['u.tags[1]', '?column?'],
            'a field of a composite' => ['(u.address).city', '?column?'],
            'ARRAY[]' => ['ARRAY[u.a, u.b]', '?column?'],
            'ARRAY()' => ['ARRAY(SELECT 1)', '?column?'],
            'ROW()' => ['ROW(u.a, u.b)', '?column?'],
            'EXISTS' => ['EXISTS (SELECT 1 FROM t)', '?column?'],
            'NOT' => ['NOT (u.flag)', '?column?'],
            'a scalar sub-query' => ['(SELECT max(x) FROM t)', '?column?'],
            'a parenthesized UNION' => ['((SELECT x FROM t) UNION (SELECT y FROM u))', '?column?'],
            'an operator' => ['u.price * u.qty', '?column?'],
            'an operator after a cast' => ["id::text || 'x'", '?column?'],
            'a JSON operator' => ["u.data->>'k'", '?column?'],
            'a unary minus' => ['-u.amount', '?column?'],
            'IS NULL' => ['u.id IS NULL', '?column?'],
            'NOTNULL' => ['x NOTNULL', '?column?'],
            'ISNULL' => ['x ISNULL', '?column?'],
            'IS NORMALIZED' => ['x IS NFC NORMALIZED', '?column?'],
            'a literal' => ["'x'", '?column?'],
            'a number' => ['1.5', '?column?'],
            'NULL' => ['NULL', '?column?'],
            'a parameter' => ['$1', '?column?'],
        ];
    }

    public static function deepExpressionProvider(): array
    {
        return [
            'nested CASE' => [
                str_repeat('CASE WHEN a THEN 1 ELSE ', 150) . 'u.x' . str_repeat(' END', 150),
                '?column?',
            ],
            'nested parentheses cast to a multi-word type' => [
                str_repeat('(', 150) . 'x' . str_repeat(')', 150) . '::double precision',
                '?column?',
            ],
            'nested parentheses before a window' => [
                str_repeat('(', 150) . 'x' . str_repeat(')', 150) . ' + count(*) OVER w',
                '?column?',
            ],
            'nested function calls' => [str_repeat('f(', 5000) . 'x' . str_repeat(')', 5000), 'f'],
        ];
    }

    /**
     * Deeply nested expressions are named by the same rule, without exception nor alias taken from their end
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('deepExpressionProvider')]
    public function testSetQueryShouldNameDeepExpressions(string $expression, string $name)
    {
        $this->assertSame(
            [[$name, ''], ['id', 'u']],
            array_map(
                fn (array $column) => [$column['name'], $column['table']],
                $this->parseColumns('SELECT ' . $expression . ', u.id FROM users u', [false, 'users'], 2)
            )
        );
    }

    /**
     * An expression without alias is named after the column of a cast of a column, or after the function of a call,
     * cast or not; any other expression is named ?column?, whatever PostgreSQL names it: alias it
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('expressionNameProvider')]
    public function testSetQueryShouldNameExpressionsWithoutAlias(string $expression, string $name)
    {
        $this->assertSame(
            [[$name, ''], ['id', 'u']],
            array_map(
                fn (array $column) => [$column['name'], $column['table']],
                $this->parseColumns('SELECT ' . $expression . ', u.id FROM users u', [false, 'users'], 2)
            )
        );
    }

    /**
     * @param array<int, string|false> $tables
     * @param list<array<string, string>> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('selectListProvider')]
    public function testSetQueryShouldParseTheSelectList(string $query, array $tables, array $expected)
    {
        $this->assertSame($expected, $this->parseColumns($query, $tables, \count($expected)));
    }

    public static function bareAliasOfAColumnProvider(): array
    {
        $column = fn (string $name, string $orgName, string $table = 'u') => [
            'name' => $name,
            'orgName' => $orgName,
            'table' => $table,
            'schema' => '',
        ];
        $id = $column('id', 'id');

        return [
            'table.column alias' => ['SELECT u.id, u.name n FROM users u', ['users', 'users'], [$id, $column('n', 'name')]],
            'a quoted alias' => ['SELECT u.name "N", u.id FROM users u', ['users', 'users'], [$column('N', 'name'), $id]],
            'a quoted column' => ['SELECT u."Name" n FROM users u', ['users'], [$column('n', 'Name')]],
            'a column without table' => ['SELECT name n, u.id FROM users u', ['users', 'users'], [$column('n', 'name'), $id]],
            'comments before the alias' => [
                "SELECT u.name/* c */n, u.id -- c\nid2 FROM users u",
                ['users', 'users'],
                [$column('n', 'name'), $column('id2', 'id')],
            ],
            'before a clause' => [
                'SELECT u.id, u.name n FROM users u WHERE u.id = 1',
                ['users', 'users'],
                [$id, $column('n', 'name')],
            ],
            'last of the query, without FROM' => ['SELECT u.id, u.name n', ['users', 'users'], [$id, $column('n', 'name')]],
            'before a clause, without FROM' => [
                'SELECT u.id, u.name n LIMIT 1',
                ['users', 'users'],
                [$id, $column('n', 'name')],
            ],
            // Not an alias: the keyword ends the select list, or a postfix operator the expression
            'FROM' => ['SELECT u.id, u.name FROM users u', ['users', 'users'], [$id, $column('name', 'name')]],
            'NOTNULL' => [
                'SELECT u.id, u.name NOTNULL FROM users u',
                ['users', false],
                [$id, $column('?column?', 'u.name NOTNULL', '')],
            ],
            // A constant is no column
            'TRUE' => ['SELECT true t, u.id FROM users u', [false, 'users'], [$column('t', 'true', ''), $id]],
        ];
    }

    /**
     * A column aliased without AS is a column, as aliased with AS: its values go to the entity of its table
     * @param array<int, string|false> $tables
     * @param list<array<string, string>> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('bareAliasOfAColumnProvider')]
    public function testSetQueryShouldReadAColumnAliasedWithoutAs(string $query, array $tables, array $expected)
    {
        $this->assertSame($expected, $this->parseColumns($query, $tables, \count($expected)));
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

        $this->assertSame(
            $expected,
            $this->parseColumns($query, array_fill(0, \count($expected), $table), \count($expected))
        );
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
        NativeFunctionMock::override('pg_num_fields', 7);

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
                    'name' => '?column?',
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
                    'name' => '?column?',
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
