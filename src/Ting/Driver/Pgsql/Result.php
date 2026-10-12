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

namespace CCMBenchmark\Ting\Driver\Pgsql;

use stdClass;
use CCMBenchmark\Ting\Driver\QueryException;
use CCMBenchmark\Ting\Driver\ResultInterface;

/**
 * @phpstan-import-type Row from ResultInterface
 */
class Result implements ResultInterface
{
    public const SQL_TABLE_SEPARATOR = 'inner|join|left|right|full|cross|where|group|having|window|union|intersect|except|order|limit|offset|fetch|for|on|using|natural';
    /**
     * A column, table.column, with an optional alias: each part is a quoted identifier (any character, "" for a
     * double quote) or a name, unquoted
     */
    public const PARSE_RAW_COLUMN = '/^\s*(?:(?P<table>"(?:[^"]++|"")+"|[a-z_][a-z0-9_$]*)\.)?(?P<column>"(?:[^"]++|"")+"|[a-z_][a-z0-9_$]*)(?:\s+as\s+(?P<alias>"(?:[^"]++|"")+"|[a-z_][a-z0-9_$]*))?\s*$/i';
    public const PARSE_DYNAMIC_COLUMN = '/(?<prefix>\s+(as\s+))?(?P<alias>"(?:[^"]++|"")+"|(?<![\w$])[a-z_][a-z0-9_$]*+)?\s*$/i';

    /**
     * A token of the query: a comment, a string literal ('...', E'...', $tag$...$tag$), a quoted identifier,
     * a word (an unquoted identifier holds non-ASCII letters, and $ after its first character), or any other character
     */
    private const SQL_TOKEN = <<<'REGEX'
        ~
            --[^\n]*
          | (?<comment>/\*(?:[^*/]++|\*(?!/)|/(?!\*)|(?&comment))*+(?:\*/|\z))
          | (?<![\w$])[eE]'(?:[^'\\]++|\\.|'')*+(?:'|\z)
          | '(?:[^']++|'')*+(?:'|\z)
          | "(?:[^"]++|"")*+(?:"|\z)
          | (?<![\w$])(?<tag>\$(?:[a-zA-Z_]\w*+)?\$)(?:[^$]++|(?!\k<tag>)\$)*+(?:\k<tag>|\z)
          | [\w\x80-\xff][\w$\x80-\xff]*
          | .
        ~sx
        REGEX;

    /**
     * The names PostgreSQL gives to the types of its grammar, by their unquoted words in lowercase: a cast of an
     * unnamed expression is named after its type. Any other type keeps its name (text, date, jsonb, public.my_type)
     */
    private const TYPE_NAMES = [
        'int' => 'int4', 'integer' => 'int4', 'smallint' => 'int2', 'bigint' => 'int8', 'real' => 'float4',
        'float' => 'float8', 'double precision' => 'float8', 'decimal' => 'numeric', 'dec' => 'numeric',
        'numeric' => 'numeric', 'boolean' => 'bool', 'char' => 'bpchar', 'character' => 'bpchar', 'nchar' => 'bpchar',
        'national char' => 'bpchar', 'national character' => 'bpchar', 'varchar' => 'varchar',
        'char varying' => 'varchar', 'character varying' => 'varchar', 'nchar varying' => 'varchar',
        'national char varying' => 'varchar', 'national character varying' => 'varchar', 'bit' => 'bit',
        'bit varying' => 'varbit', 'timestamp' => 'timestamp', 'time' => 'time', 'interval' => 'interval',
    ];

    /**
     * The names PostgreSQL gives to the expressions of its grammar named after a keyword, followed by a parenthesis or
     * a bracket
     */
    private const KEYWORD_NAMES = ['array' => 'array', 'row' => 'row', 'exists' => 'exists'];

    /**
     * The depth of parentheses and CASE beyond which an expression is not named (?column?)
     */
    private const MAX_NESTING = 100;

    /**
     * States of a depth of parentheses for the table search: no SELECT, the select list of a SELECT, its FROM clause
     */
    private const NO_SELECT = 0;
    private const SELECT_LIST = 1;
    private const FROM_CLAUSE = 2;

    /**
     * The keywords ending a FROM clause: its commas no longer separate tables after them
     */
    private const AFTER_FROM_CLAUSE = [
        'where', 'group', 'having', 'window', 'order', 'limit', 'offset', 'fetch', 'for', 'union', 'intersect',
        'except', 'returning',
    ];

    /**
     * The keywords of a clause that are part of an expression after another keyword, outside of parentheses:
     * WITHIN GROUP (ORDER BY ...), COLLATION FOR (...)
     */
    private const NOT_A_CLAUSE_AFTER = ['group' => ['within'], 'for' => ['collation']];

    /**
     * The tokens following a parenthesized SELECT in a sub-query: a set operation, a clause, or its end
     */
    private const AFTER_SUB_QUERY = [')', 'union', 'intersect', 'except', 'order', 'limit', 'offset', 'fetch', 'for'];

    /**
     * A whole column made of an asterisk: *, alias.* or DISTINCT *
     */
    private const ASTERISK_COLUMN = '/^(?:distinct\s+)?(?:(?:"(?:[^"]++|"")*+"|\w++)\s*\.\s*)?\*$/i';

    protected ?string $connectionName = null;
    protected ?string $database = null;
    /**
     * Natively typed object: PgSql\Result is final and only built by a server, tests stand in for it
     * @var \PgSql\Result|null
     */
    protected ?object $result = null;
    /** @var array<int, stdClass> $fields  */
    protected array $fields = [];
    protected int $iteratorOffset = 0;
    /** @var Row|null */
    protected ?array $iteratorCurrent = null;

    public function setConnectionName(string $connectionName): static
    {
        $this->connectionName = $connectionName;
        return $this;
    }

    public function setDatabase(string $database): static
    {
        $this->database = $database;
        return $this;
    }

    /**
     * @param \PgSql\Result $result
     * @return $this
     */
    public function setResult($result): static
    {
        $this->result = $result;
        return $this;
    }

    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    public function getDatabase(): ?string
    {
        return $this->database;
    }

    /**
     * Analyze the given query
     * @throws QueryException
     *
     * @internal
     */
    public function setQuery(string $query): void
    {
        $resultResource = $this->result;
        if ($resultResource === null) {
            throw new QueryException(
                'Result::setQuery() called before setResult(): the tables of the columns are read from the result'
            );
        }

        $fields = [];

        $tokensWithCase = self::tokenize($query);
        $tokens = array_map(strtolower(...), $tokensWithCase);
        $comparisonFroms = self::findComparisonFroms($tokens);
        $selectDepth = self::findMainSelectDepth($tokens);

        [$tableToAlias, $aliasToSchema, $derivedTables] = $this->findTables(
            $tokensWithCase,
            $tokens,
            $comparisonFroms,
            $selectDepth
        );

        $startCapture = false;
        // The tokens of the main SELECT before its first column: DISTINCT, ALL, DISTINCT ON (...)
        $firstColumn = 0;
        $columnsMatches = [];
        $column = '';
        $scope  = 'column';
        $brackets = 0;
        $totalTokens = count($tokens);
        $noAlias = false;

        foreach ($tokens as $index => $token) {
            if ($index < $firstColumn) {
                continue;
            }

            if ($token === 'case' && $scope === 'column') {
                $scope = 'condition';
                $noAlias = true;
            } elseif ($token === 'end' && $scope === 'condition') {
                $scope = 'column';
            }

            // Square brackets (ARRAY[a, b], subscripts) hold commas as parentheses do
            if ($token === '(' || $token === '[') {
                $brackets++;
            }

            if ($token === ')' || $token === ']') {
                $brackets--;
            }

            if ($startCapture) {
                // The FROM, or a clause of a SELECT without FROM (WHERE, UNION, LIMIT...), ends the select list; not
                // a name (t.order, AS order) nor WITHIN GROUP
                $isEnd = ($token === 'from' && !isset($comparisonFroms[$index]))
                    || (
                        \in_array($token, ['into', ...self::AFTER_FROM_CLAUSE], true)
                        && !\in_array(
                            $tokens[self::previousSignificant($tokens, $index)],
                            ['.', 'as', ...(self::NOT_A_CLAUSE_AFTER[$token] ?? [])],
                            true
                        )
                    );
                // The parenthesis closing a parenthesized main SELECT ends its last column
                $isClosed = $brackets < $selectDepth;
                if (
                    $isClosed
                    || ($brackets === $selectDepth && ($token === ',' || $isEnd || $index === $totalTokens - 1))
                ) {
                    $scope = 'column';

                    if (!$isClosed && $index === $totalTokens - 1 && $token !== ';') {
                        $column .= $tokensWithCase[$index];
                    }

                    // Only a whole column is forbidden: COUNT(*) or 2 * id are not
                    if (self::match(self::ASTERISK_COLUMN, trim($column))) {
                        throw new QueryException('Query invalid: usage of asterisk in column definition is forbidden');
                    }

                    /**
                     * Match column format table.column (as alias)
                     */
                    self::match(
                        self::PARSE_RAW_COLUMN,
                        $column,
                        $matches
                    );

                    // TRUE, FALSE and NULL are constants, not columns
                    if (
                        $matches !== []
                        && (
                            $matches['table'] !== ''
                            || !\in_array(strtolower($matches['column']), ['true', 'false', 'null'], true)
                        )
                    ) {
                        $columnComponent = [
                            'complex' => false,
                            'column' => $matches['column']
                        ];

                        // An unmatched named group is an empty string, never unset
                        $columnComponent['table'] = $matches['table'];

                        if (isset($matches['alias'])) {
                            $columnComponent['alias'] = $matches['alias'];
                        }
                    } else { // Match dynamic column, ie : max(table.column), table.column || table.id, ...
                        $column = trim($column);
                        self::match(self::PARSE_DYNAMIC_COLUMN, $column, $matches);
                        $prefix = $noAlias ? '' : ($matches['prefix'] ?? '');
                        $alias = $noAlias ? '' : ($matches['alias'] ?? '');
                        $expression = trim(substr($column, 0, \strlen($column) - \strlen($prefix . $alias)));

                        // Without AS, an identifier is an alias only after an operand: in price * qty, NOT flag,
                        // a IS DISTINCT FROM t.b, b::double precision or count(*) OVER w, it ends the expression
                        if (
                            $alias !== ''
                            && $prefix === ''
                            && (
                                $expression === ''
                                || !self::endsWithOperand($expression)
                                || self::figureExpression($column) !== null
                            )
                        ) {
                            $alias = '';
                            $expression = $column;
                        }

                        $columnComponent = [
                            'complex' => true,
                            'table' => '',
                            'column' => $expression,
                            // Without alias, the expression is named as PostgreSQL names it
                            'alias' => $alias !== '' ? $alias : (self::expressionName($expression) ?? '?column?'),
                        ];
                    }

                    $columnsMatches[] = $columnComponent;
                    $column = '';
                    if ($isEnd || $isClosed) {
                        break;
                    }
                    continue;
                }

                $column .= $tokensWithCase[$index];

                if ($scope === 'column' && $token !== 'end') {
                    $noAlias = false;
                }
            }

            // The columns of the main SELECT: not those of a sub-query, nor of the CTEs of a WITH query
            if ($token === 'select' && $brackets === $selectDepth) {
                $startCapture = true;
                $firstColumn = self::findFirstColumn($tokens, $index);
            }
        }

        if ($columnsMatches === []) {
            throw new QueryException('Query invalid: can\'t parse columns');
        }

        // Whether each column is keyed by the name of a table read only in a derived table
        $byDerivedTable = [];
        foreach ($columnsMatches as $match) {
            $stdClass = new stdClass();
            $stdClass->orgname = $match['column'];

            $stdClass->name = isset($match['alias']) === true ? $match['alias'] : $stdClass->orgname;

            $table = pg_field_table($resultResource, count($fields));
            if ($table === false) {
                $table = '';
            }
            // The name of the table as created: lowercase unless created quoted ("Users")
            $stdClass->orgtable = $match['complex'] === false ? (string) $table : '';

            if ($match['table'] !== '') {
                $stdClass->table = $this->identifierKey($match['table']);
            } elseif ($match['complex'] === false) {
                // No table for a column from a function, VALUES, a CTE...
                $stdClass->table = $tableToAlias[$stdClass->orgtable] ?? '';
            } else {
                $stdClass->table = $stdClass->orgtable;
            }

            $stdClass->schema = $aliasToSchema[$stdClass->table] ?? '';
            $byDerivedTable[] = $match['table'] === '' && isset($derivedTables[$stdClass->orgtable]);

            // The schema and orgtable (from pg_field_table()) are unquoted already. The name keeps the spelling of the
            // query; a column is folded to lowercase unless quoted, as PostgreSQL names it
            $stdClass->name     = $this->unescapeField($stdClass->name);
            $stdClass->orgname  = $match['complex'] === false
                ? $this->identifierKey($stdClass->orgname)
                : $this->unescapeField($stdClass->orgname);

            $fields[] = $stdClass;
        }

        // The alias is the key of the entity in the row, an application key rather than a database name: in
        // lowercase, as in 3.x, whether quoted or not ("U" and u are both u). Two aliases of the row differing only by
        // case would share that key and mix their columns in one entity: the query throws. The name of a table read
        // only in a derived table cannot be renamed: differing only by case from another key, it keys no column
        $aliasOfKey = [];
        foreach ([false, true] as $derived) {
            foreach ($fields as $index => $field) {
                if ($byDerivedTable[$index] !== $derived) {
                    continue;
                }
                $key = strtolower($field->table);
                $aliasOfKey[$key] ??= $field->table;
                if ($aliasOfKey[$key] === $field->table) {
                    $field->table = $key;
                } elseif ($derived) {
                    $field->table = '';
                    $field->schema = '';
                } else {
                    throw new QueryException(sprintf(
                        'Query invalid: the aliases %s and %s differ only by case, the key of their row: rename one '
                        . 'of them',
                        $aliasOfKey[$key],
                        $field->table
                    ));
                }
            }
        }

        $this->fields = $fields;
    }

    /**
     * The tables of the FROM clauses and of the joins, read from the tokens: the alias of each table, the schema of
     * each alias, and the tables read only in a derived table. A FROM belongs to a SELECT at the same depth of
     * parentheses (not to extract(year FROM x), nor to IS [NOT] DISTINCT FROM); a FROM clause lists its items
     * separated by commas. Neither a sub-query nor a function names a table.
     * A table or an alias read at several depths (a sub-query, a LATERAL join, IN (SELECT ...), EXISTS, a CTE) is
     * the one of the main SELECT: a table read deeper never replaces one read closer to the main SELECT
     *
     * @param list<string> $tokensWithCase
     * @param list<string> $tokens lowercase tokens
     * @param array<int, true> $comparisonFroms
     * @param int $selectDepth depth of the parentheses around the main SELECT
     * @return array{array<string, string>, array<string, string>, array<string, true>} table => alias,
     *         alias => schema, table read only in a derived table (keyed by its name) => true
     */
    private function findTables(array $tokensWithCase, array $tokens, array $comparisonFroms, int $selectDepth): array
    {
        $tableToAlias = [];
        $aliasToSchema = [];
        $derivedTables = [];
        // How far from the main SELECT each table and each alias was read: 0 at its depth, deeper further, and
        // outside of its parentheses (the next SELECT of a UNION) the furthest
        $tableDistance = [];
        $aliasDistance = [];
        // At each depth of parentheses: no SELECT, its select list, or its FROM clause (until the clause after it)
        $states = [self::NO_SELECT];
        // At each depth of parentheses, the depth of the SELECT it belongs to: the parentheses of a join, as
        // FROM (a JOIN b ON ...), hold items of the FROM clause around them
        $depths = [0];
        // The opening parentheses of the parenthesized joins read
        $joins = [];
        // The derived tables (sub-queries of the FROM clause but LATERAL ones, bodies of the CTEs): the aliases inside
        // are not visible outside of them. They nest: the end of the outermost one read so far is enough
        $derivedUntil = -1;
        [$closing, $subQueries] = self::matchParentheses($tokens);

        foreach ($tokens as $index => $token) {
            $isJoin = isset($joins[$index]);
            if ($token === '(' || $token === '[') {
                $states[] = $isJoin ? self::FROM_CLAUSE : self::NO_SELECT;
                $depths[] = $isJoin ? end($depths) : end($depths) + 1;
                // The first item of a parenthesized join follows its parenthesis
                if (!$isJoin) {
                    continue;
                }
            }
            if ($token === ')' || $token === ']') {
                if (\count($states) > 1) {
                    array_pop($states);
                    array_pop($depths);
                }
                continue;
            }
            // The body of a CTE: name [(columns)] AS [NOT] [MATERIALIZED] (SELECT ...)
            if ($token === 'as') {
                $body = self::nextSignificant($tokens, $index);
                if (($tokens[$body] ?? '') === 'not') {
                    $body = self::nextSignificant($tokens, $body);
                }
                if (($tokens[$body] ?? '') === 'materialized') {
                    $body = self::nextSignificant($tokens, $body);
                }
                if (isset($subQueries[$body])) {
                    $derivedUntil = max($derivedUntil, $closing[$body]);
                }
            }

            $top = array_key_last($states);
            if ($token === 'select') {
                $states[$top] = self::SELECT_LIST;
                continue;
            }
            if ($states[$top] === self::FROM_CLAUSE && \in_array($token, self::AFTER_FROM_CLAUSE, true)) {
                $states[$top] = self::SELECT_LIST;
                continue;
            }

            // An item follows a FROM, a JOIN, a comma of the FROM clause (FROM a, b; JOIN b ON ..., c) or the
            // parenthesis of a join
            $isFrom = $token === 'from' && !isset($comparisonFroms[$index]) && $states[$top] !== self::NO_SELECT;
            if (!$isJoin && $token !== 'join' && !$isFrom && ($token !== ',' || $states[$top] !== self::FROM_CLAUSE)) {
                continue;
            }
            if ($isFrom) {
                $states[$top] = self::FROM_CLAUSE;
            }

            $depth = $depths[$top];
            $distance = $depth >= $selectDepth ? $depth - $selectDepth : \PHP_INT_MAX - $depth;
            $item = self::readFromItem($tokensWithCase, $tokens, $closing, $subQueries, $index);
            if ($item['derived'] !== null) {
                $derivedUntil = max($derivedUntil, $item['derived']);
            }
            if ($item['join'] !== null) {
                $joins[$item['join']] = true;
            }
            if ($item['table'] !== null) {
                $table = $this->identifierKey($item['table']);
                // A table read only in a derived table: its columns are keyed by its name, as in 3.x
                $alias = $item['alias'] !== null && $index > $derivedUntil
                    ? $this->identifierKey($item['alias'])
                    : $table;
                if ($distance <= ($tableDistance[$table] ?? \PHP_INT_MAX)) {
                    $tableToAlias[$table] = $alias;
                    $tableDistance[$table] = $distance;
                    if ($index > $derivedUntil) {
                        unset($derivedTables[$table]);
                    } else {
                        $derivedTables[$table] = true;
                    }
                }
                if ($distance <= ($aliasDistance[$alias] ?? \PHP_INT_MAX)) {
                    $aliasToSchema[$alias] = $item['schema'] !== null ? $this->identifierKey($item['schema']) : '';
                    $aliasDistance[$alias] = $distance;
                }
            }
        }

        return [$tableToAlias, $aliasToSchema, $derivedTables];
    }

    /**
     * The item of a FROM clause or of a join following $position: [ONLY | LATERAL] [[database.]schema.]table, a
     * function call or a parenthesized sub-query / join, then its optional alias (AS optional, with its column
     * aliases)
     *
     * @param list<string> $tokensWithCase
     * @param list<string> $tokens lowercase tokens
     * @param array<int, int> $closing opening parenthesis => closing parenthesis
     * @param array<int, true> $subQueries opening parentheses of the sub-queries
     * @return array{table: ?string, schema: ?string, alias: ?string, end: int, derived: ?int, join: ?int} end: the
     *         first token after the item; derived: the closing parenthesis of a derived table (a sub-query, not
     *         LATERAL); join: the opening parenthesis of a parenthesized join
     */
    private static function readFromItem(
        array $tokensWithCase,
        array $tokens,
        array $closing,
        array $subQueries,
        int $position
    ): array {
        $item = [
            'table' => null,
            'schema' => null,
            'alias' => null,
            'end' => $position,
            'derived' => null,
            'join' => null,
        ];

        $index = self::nextSignificant($tokens, $position);
        $lateral = ($tokens[$index] ?? '') === 'lateral';
        if ($lateral || ($tokens[$index] ?? '') === 'only') {
            $index = self::nextSignificant($tokens, $index);
        }

        if (($tokens[$index] ?? '') === '(') {
            $isSubQuery = isset($subQueries[$index]);
            if (!$isSubQuery && !$lateral) {
                $item['join'] = $index;
            }
            $index = $closing[$index];
            if (!$lateral && $isSubQuery) {
                $item['derived'] = $index;
            }
        } elseif (self::isIdentifier($tokensWithCase[$index] ?? '')) {
            $names = [$tokensWithCase[$index]];
            $next = self::nextSignificant($tokens, $index);
            while (
                ($tokens[$next] ?? '') === '.'
                && self::isIdentifier($tokensWithCase[self::nextSignificant($tokens, $next)] ?? '')
            ) {
                $index = self::nextSignificant($tokens, $next);
                $names[] = $tokensWithCase[$index];
                $next = self::nextSignificant($tokens, $index);
            }

            if (($tokens[$next] ?? '') === '(') {
                // A function call
                $index = $closing[$next];
            } else {
                $item['table'] = array_pop($names);
                $item['schema'] = array_pop($names);
                // FROM table * (its descendant tables, the default)
                if (($tokens[$next] ?? '') === '*') {
                    $index = $next;
                }
            }
        } else {
            return $item;
        }

        $index = self::nextSignificant($tokens, $index);
        $hasAs = ($tokens[$index] ?? '') === 'as';
        if ($hasAs) {
            $index = self::nextSignificant($tokens, $index);
        }
        $alias = $tokensWithCase[$index] ?? '';
        if (
            self::isIdentifier($alias)
            && (
                $hasAs
                || $alias[0] === '"'
                || !self::match('/^(?:' . self::SQL_TABLE_SEPARATOR . '|tablesample)$/i', $alias)
            )
        ) {
            $item['alias'] = $alias;
            $index = self::nextSignificant($tokens, $index);
            // The column aliases: AS t (a, b)
            if (($tokens[$index] ?? '') === '(') {
                $index = self::nextSignificant($tokens, $closing[$index]);
            }
        }
        $item['end'] = $index;

        return $item;
    }

    /**
     * The parentheses and brackets of the query, matched once: the position closing each opening one (past the last
     * token when it is never closed), and the opening parentheses of the sub-queries: (SELECT ...),
     * ((SELECT ...) UNION ...), (VALUES ...), (WITH ...), (TABLE ...); not a parenthesized join, as
     * ((SELECT ...) s JOIN t ON ...), whose first parenthesized SELECT an alias or a join follows
     *
     * @param list<string> $tokens lowercase tokens
     * @return array{array<int, int>, array<int, true>} opening position => closing position, sub-query => true
     */
    private static function matchParentheses(array $tokens): array
    {
        $closing = [];
        $opened = [];
        foreach ($tokens as $index => $token) {
            if ($token === '(' || $token === '[') {
                $opened[] = $index;
            } elseif (($token === ')' || $token === ']') && $opened !== []) {
                $closing[array_pop($opened)] = $index;
            }
        }
        foreach ($opened as $index) {
            $closing[$index] = \count($tokens);
        }

        // From the end: whether a parenthesis opens a sub-query depends on the one it starts with
        $subQueries = [];
        $next = \count($tokens);
        for ($index = $next - 1; $index >= 0; $index--) {
            if (trim($tokens[$index]) === '') {
                continue;
            }
            $first = $tokens[$next] ?? '';
            if (
                $tokens[$index] === '('
                && (
                    \in_array($first, ['select', 'with', 'values', 'table'], true)
                    || (
                        isset($subQueries[$next])
                        && \in_array(
                            $tokens[self::nextSignificant($tokens, $closing[$next] ?? $next)] ?? '',
                            self::AFTER_SUB_QUERY,
                            true
                        )
                    )
                )
            ) {
                $subQueries[$index] = true;
            }
            $next = $index;
        }

        return [$closing, $subQueries];
    }

    /**
     * Whether the token is an identifier: a quoted identifier or a name, unquoted
     */
    private static function isIdentifier(string $token): bool
    {
        return self::match('/^(?:"(?:[^"]++|"")++"|[a-z_\x80-\xff][a-z0-9_$\x80-\xff]*+)$/i', $token);
    }

    /**
     * preg_match() that never fails silently: a PCRE failure (backtrack or JIT stack limit) throws
     *
     * @param array<int|string, string>|null $matches
     * @param-out array<int|string, string> $matches
     * @throws QueryException
     */
    private static function match(string $pattern, string $subject, ?array &$matches = null): bool
    {
        $result = preg_match($pattern, $subject, $matches);
        if ($result === false) {
            $excerpt = \strlen($subject) > 80 ? substr($subject, 0, 80) . '...' : $subject;
            throw self::pcreFailure('parse "' . $excerpt . '"');
        }

        return $result === 1;
    }

    private static function pcreFailure(string $action): QueryException
    {
        return new QueryException('Query invalid: can\'t ' . $action . ' (' . preg_last_error_msg() . ')');
    }

    /**
     * The name PostgreSQL gives to a column made of an expression without alias, as its FigureColnameInternal() does:
     * - a column, a field or a subscript of it: the name of the column or of the field;
     * - a function call: the name of the function (TRIM btrim, ltrim or rtrim, AT TIME ZONE timezone), ARRAY array,
     *   ROW row, EXISTS exists; a scalar sub-query: the name of its column;
     * - a cast, COLLATE: the name of the expression cast, else the name of the type ('x'::text text, 1::int int4);
     * - CASE: the name of its ELSE result, else case.
     * Null for any other expression (an operator, a literal), which PostgreSQL names ?column?. A name keeps the
     * spelling of the query, as the name of a column does
     */
    private static function expressionName(string $expression): ?string
    {
        $name = self::figureExpression($expression);

        return $name !== null && $name[1] > 0 ? $name[0] : null;
    }

    /**
     * The name and the strength (see figureName()) of an expression; null for an expression of an operator, which
     * an alias follows, or nested more than MAX_NESTING times
     *
     * @return array{string, int}|null
     */
    private static function figureExpression(string $expression): ?array
    {
        $tokens = array_values(array_filter(
            self::tokenize($expression),
            static fn (string $token): bool => trim($token) !== ''
        ));
        // The closing parenthesis, bracket or END of each opening one or CASE; each depth is figured by a recursive
        // call
        $closing = [];
        $opened = [];
        foreach ($tokens as $index => $token) {
            $token = strtolower($token);
            if ($token === '(' || $token === '[' || $token === 'case') {
                $opened[] = $index;
            } elseif (($token === ')' || $token === ']' || $token === 'end') && $opened !== []) {
                $closing[array_pop($opened)] = $index;
            }
            if (\count($opened) > self::MAX_NESTING) {
                return null;
            }
        }

        return self::figureName($tokens, $closing, 0, \count($tokens));
    }

    /**
     * The name of the expression of the tokens from $from to $to (excluded), and its strength: 2 for a name, 1 for the
     * name of a type or case, which a cast replaces, 0 for none. Null for an expression of an operator
     *
     * @param list<string> $tokens significant tokens
     * @param array<int, int> $closing
     * @return array{string, int}|null
     */
    private static function figureName(array $tokens, array $closing, int $from, int $to): ?array
    {
        $primary = self::figurePrimary($tokens, $closing, $from, $to);
        if ($primary === null) {
            return null;
        }
        [$name, $strength, $index] = $primary;

        while ($index < $to) {
            $token = strtolower($tokens[$index]);
            $next = $index + 1 < $to ? strtolower($tokens[$index + 1]) : '';
            if ($token === ':' && $next === ':') {
                $type = self::figureType($tokens, $closing, $index + 2, $to);
                if ($type === null) {
                    return null;
                }
                if ($strength < 2) {
                    [$name, $strength] = [$type[0], 1];
                }
                $index = $type[1];
            } elseif ($token === '[' && ($closing[$index] ?? $to) < $to) {
                $index = $closing[$index] + 1;
            } elseif ($token === '.' && $next !== '' && self::isIdentifier($tokens[$index + 1])) {
                [$name, $strength] = [$tokens[$index + 1], 2];
                $index += 2;
            } elseif ($token === 'collate' && $next !== '' && self::isIdentifier($tokens[$index + 1])) {
                $index += 2;
                while ($index + 1 < $to && $tokens[$index] === '.' && self::isIdentifier($tokens[$index + 1])) {
                    $index += 2;
                }
            } elseif ($token === 'at' && $next === 'time' && strtolower($tokens[$index + 2] ?? '') === 'zone') {
                // The function timezone(), whatever its operands
                $zone = self::figurePrimary($tokens, $closing, $index + 3, $to);
                if ($zone === null) {
                    return null;
                }
                [$name, $strength, $index] = ['timezone', 2, $zone[2]];
            } else {
                return null;
            }
        }

        return [$name, $strength];
    }

    /**
     * The name, the strength (see figureName()) and the end of the operand starting at $from: a literal, a column,
     * a function call, a parenthesized expression, CASE, CAST...; null when no operand starts there (an operator)
     *
     * @param list<string> $tokens significant tokens
     * @param array<int, int> $closing
     * @return array{string, int, int}|null
     */
    private static function figurePrimary(array $tokens, array $closing, int $from, int $to): ?array
    {
        $token = $tokens[$from] ?? '';
        $lower = strtolower($token);
        $next = $from + 1 < $to ? $tokens[$from + 1] : '';
        $close = $closing[$from + 1] ?? $to;
        $call = ($next === '(' || $next === '[') && $close < $to;

        if ($from >= $to) {
            return null;
        }
        if ($token === '(' && ($closing[$from] ?? $to) < $to) {
            return [...self::figureParenthesized($tokens, $closing, $from + 1, $closing[$from]), $closing[$from] + 1];
        }
        if (self::isLiteral($token) || \in_array($lower, ['true', 'false', 'null'], true)) {
            return ['', 0, $from + 1];
        }
        if (ctype_digit($token[0]) || ($token === '.' && ctype_digit($next[0] ?? ''))) {
            // 1, 1.5, .5, 1e3
            $index = $token === '.' ? $from + 2 : $from + 1;
            if ($token !== '.' && ($tokens[$index] ?? '') === '.' && $index < $to) {
                $index += ctype_digit($tokens[$index + 1][0] ?? '') && $index + 1 < $to ? 2 : 1;
            }
            return ['', 0, $index];
        }
        if (!self::isIdentifier($token)) {
            return null;
        }
        if ($lower === 'case') {
            return self::figureCase($tokens, $closing, $from, $to);
        }
        if ($lower === 'not') {
            return null;
        }
        if (isset(self::KEYWORD_NAMES[$lower]) && $call && ($next === '(' || $lower === 'array')) {
            return [self::KEYWORD_NAMES[$lower], 2, $close + 1];
        }
        if ($lower === 'cast' && $next === '(' && $close < $to) {
            return self::figureCast($tokens, $closing, $from + 2, $close);
        }

        // A typed literal: date '2026-01-01' (but the bit strings B'1' and X'1')
        $type = self::figureType($tokens, $closing, $from, $to);
        if (
            $type !== null
            && $type[1] < $to
            && self::isLiteral($tokens[$type[1]])
            && !\in_array($lower, ['b', 'x'], true)
        ) {
            return [$type[0], 1, $type[1] + 1];
        }

        // A column or a function: [schema.]name
        $name = $token;
        $index = $from + 1;
        while ($index + 1 < $to && $tokens[$index] === '.' && self::isIdentifier($tokens[$index + 1])) {
            $name = $tokens[$index + 1];
            $index += 2;
        }
        if (($tokens[$index] ?? '') !== '(' || $index >= $to) {
            return [$name, 2, $index];
        }
        if ($index === $from + 1 && $lower === 'trim') {
            $name = match (strtolower($tokens[$index + 1] ?? '')) {
                'leading' => 'ltrim',
                'trailing' => 'rtrim',
                default => 'btrim',
            };
        }
        // Its call, then WITHIN GROUP (...), FILTER (...), OVER (...) or OVER window
        $index = $closing[$index] ?? $to;
        while ($index < $to) {
            $index++;
            $keyword = $index < $to ? strtolower($tokens[$index]) : '';
            if ($keyword === 'within' && $index + 1 < $to && strtolower($tokens[$index + 1]) === 'group') {
                $index += 2;
            } elseif ($keyword === 'filter' || $keyword === 'over') {
                $index++;
            } else {
                return [$name, 2, $index];
            }
            if ($index >= $to) {
                return null;
            }
            // OVER window, or the parenthesis of the clause
            $index = $tokens[$index] === '(' ? $closing[$index] ?? $to : $index;
        }

        return null;
    }

    /**
     * The name and the strength of a parenthesized expression: a scalar sub-query is named after its column, a row
     * (a, b) row, any other expression as without parentheses (an operator: no name, but an operand all the same)
     *
     * @param list<string> $tokens significant tokens
     * @param array<int, int> $closing
     * @return array{string, int}
     */
    private static function figureParenthesized(array $tokens, array $closing, int $from, int $to): array
    {
        $keyword = strtolower($tokens[$from] ?? '');
        if ($keyword === 'values') {
            return ['column1', 2];
        }
        if ($keyword === 'select' || $keyword === 'with') {
            return [self::subQueryColumnName($tokens, $closing, $from, $to) ?? '?column?', 2];
        }
        for ($index = $from; $index < $to; $index++) {
            if ($tokens[$index] === ',') {
                return ['row', 2];
            }
            if (isset($closing[$index])) {
                $index = $closing[$index];
            }
        }

        return self::figureName($tokens, $closing, $from, $to) ?? ['', 0];
    }

    /**
     * The name of the first column of the scalar sub-query from $from to $to: its alias, or its name
     *
     * @param list<string> $tokens significant tokens
     * @param array<int, int> $closing
     */
    private static function subQueryColumnName(array $tokens, array $closing, int $from, int $to): ?string
    {
        // Its first SELECT, after the CTEs of WITH
        for ($select = $from; $select < $to && strtolower($tokens[$select]) !== 'select'; $select++) {
            if (isset($closing[$select])) {
                $select = $closing[$select];
            }
        }
        // After its DISTINCT, ALL or DISTINCT ON (...)
        $start = $select + 1;
        $keyword = strtolower($tokens[$start] ?? '');
        if ($keyword === 'all' || $keyword === 'distinct') {
            $start++;
        }
        if ($keyword === 'distinct' && strtolower($tokens[$start] ?? '') === 'on') {
            $start = ($closing[$start + 1] ?? $to) + 1;
        }

        // Its first column: up to a comma, FROM or a clause
        for ($end = $start; $end < $to; $end++) {
            $token = strtolower($tokens[$end]);
            if (\in_array($token, [',', 'from', 'into', ...self::AFTER_FROM_CLAUSE], true)) {
                break;
            }
            if (isset($closing[$end])) {
                $end = $closing[$end];
            }
        }
        if ($end - $start >= 2 && strtolower($tokens[$end - 2]) === 'as') {
            return $tokens[$end - 1];
        }
        $name = self::figureName($tokens, $closing, $start, $end);
        if ($name !== null) {
            return $name[1] > 0 ? $name[0] : null;
        }

        // An alias without AS after an operand
        return $end - $start >= 2
            && self::isIdentifier($tokens[$end - 1])
            && self::endsWithOperand(implode(' ', \array_slice($tokens, $start, $end - 1 - $start)))
            ? $tokens[$end - 1]
            : null;
    }

    /**
     * The name, the strength and the end of CASE ... END at $from: the name of its ELSE result, else case
     *
     * @param list<string> $tokens significant tokens
     * @param array<int, int> $closing
     * @return array{string, int, int}|null
     */
    private static function figureCase(array $tokens, array $closing, int $from, int $to): ?array
    {
        $end = $closing[$from] ?? $to;
        if ($end >= $to) {
            return null;
        }
        $else = null;
        for ($index = $from + 1; $index < $end; $index++) {
            if (strtolower($tokens[$index]) === 'else') {
                $else = $index;
            } elseif (isset($closing[$index])) {
                $index = $closing[$index];
            }
        }
        $result = $else !== null ? self::figureName($tokens, $closing, $else + 1, $end) : null;

        return $result !== null && $result[1] === 2 ? [$result[0], 2, $end + 1] : ['case', 1, $end + 1];
    }

    /**
     * The name, the strength and the end of CAST(expression AS type), whose expression starts at $from and whose
     * parenthesis closes at $to: the name of the expression, else the name of the type
     *
     * @param list<string> $tokens significant tokens
     * @param array<int, int> $closing
     * @return array{string, int, int}|null
     */
    private static function figureCast(array $tokens, array $closing, int $from, int $to): ?array
    {
        $as = null;
        for ($index = $from; $index < $to; $index++) {
            if (strtolower($tokens[$index]) === 'as') {
                $as = $index;
            } elseif (isset($closing[$index])) {
                $index = $closing[$index];
            }
        }
        $type = $as !== null ? self::figureType($tokens, $closing, $as + 1, $to) : null;
        if ($as === null || $type === null || $type[1] !== $to) {
            return null;
        }
        $name = self::figureName($tokens, $closing, $from, $as);

        return $name !== null && $name[1] === 2 ? [$name[0], 2, $to + 1] : [$type[0], 1, $to + 1];
    }

    /**
     * The name PostgreSQL gives to the type starting at $from, and its end: a type of the grammar (int4 for int,
     * float8 for double precision, timestamptz for timestamp with time zone...) or a [schema.]name, then its
     * modifiers and its array bounds
     *
     * @param list<string> $tokens significant tokens
     * @param array<int, int> $closing
     * @return array{string, int}|null
     */
    private static function figureType(array $tokens, array $closing, int $from, int $to): ?array
    {
        if ($from >= $to || !self::isIdentifier($tokens[$from])) {
            return null;
        }

        $name = null;
        $end = $from;
        for ($words = min(3, $to - $from); $words > 0 && $name === null; $words--) {
            $phrase = strtolower(implode(' ', \array_slice($tokens, $from, $words)));
            if (isset(self::TYPE_NAMES[$phrase])) {
                $name = self::TYPE_NAMES[$phrase];
                $end = $from + $words;
            }
        }
        if ($name === null) {
            $name = $tokens[$from];
            $end = $from + 1;
            while ($end + 1 < $to && $tokens[$end] === '.' && self::isIdentifier($tokens[$end + 1])) {
                $name = $tokens[$end + 1];
                $end += 2;
            }
        }

        // Its modifiers: varchar(10), numeric(10, 2), float(24) a float4
        if ($end < $to && $tokens[$end] === '(') {
            if (($closing[$end] ?? $to) >= $to) {
                return null;
            }
            if ($name === 'float8' && $closing[$end] === $end + 2 && (int) $tokens[$end + 1] <= 24) {
                $name = 'float4';
            }
            $end = $closing[$end] + 1;
        }
        // timestamp(3) with time zone
        $zone = strtolower(implode(' ', \array_slice($tokens, $end, max(0, min(3, $to - $end)))));
        if (($name === 'timestamp' || $name === 'time') && \in_array($zone, ['with time zone', 'without time zone'])) {
            $name .= $zone === 'with time zone' ? 'tz' : '';
            $end += 3;
        }
        // Its array bounds: int[], int[3][3], int ARRAY[3]
        while ($end < $to && ($tokens[$end] === '[' || strtolower($tokens[$end]) === 'array')) {
            if ($tokens[$end] === '[' && ($closing[$end] ?? $to) >= $to) {
                return null;
            }
            $end = $tokens[$end] === '[' ? $closing[$end] + 1 : $end + 1;
        }

        return [$name, $end];
    }

    /**
     * Whether the token is a string literal: '...', E'...' or $tag$...$tag$
     */
    private static function isLiteral(string $token): bool
    {
        return $token !== '' && ($token[0] === "'" || ($token[0] === '$' && \strlen($token) > 1)
            || (($token[0] === 'e' || $token[0] === 'E') && ($token[1] ?? '') === "'"));
    }

    /**
     * Whether the expression ends with an operand (a name, a quoted identifier, a literal, a parenthesis or a bracket),
     * which an alias may follow without AS, rather than with an operator, a "." or a keyword expecting an operand
     */
    private static function endsWithOperand(string $expression): bool
    {
        return self::match('/[\w$"\')\]]$/', $expression)
            && !self::match(
                '/(?:^|[^\w$."])(?:not|and|or|is|like|ilike|similar|to|between|symmetric|escape|collate|from|when|then'
                . '|else|case|any|some|all|distinct|overlaps|at\s+time\s+zone)$/i',
                $expression
            );
    }

    /**
     * Split the query into tokens, comments replaced by a space: concatenated, the tokens give the query back
     * @return list<string>
     */
    private static function tokenize(string $query): array
    {
        if (preg_match_all(self::SQL_TOKEN, $query, $matches) === false) {
            throw self::pcreFailure('split the query into tokens');
        }

        return array_map(
            static fn (string $token): string => str_starts_with($token, '--') || str_starts_with($token, '/*')
                ? ' '
                : $token,
            $matches[0]
        );
    }

    /**
     * Depth of the parentheses around the main SELECT: 0, unless the query starts with parentheses around a SELECT,
     * as (SELECT ...) UNION ALL (SELECT ...) or (SELECT ... LIMIT 1) UNION ALL SELECT ...: the columns are those of
     * that first SELECT, as PostgreSQL names them
     *
     * @param list<string> $tokens lowercase tokens
     */
    private static function findMainSelectDepth(array $tokens): int
    {
        $leading = 0;
        foreach ($tokens as $token) {
            if ($token === '(') {
                $leading++;
            } elseif (trim($token) !== '') {
                return $token === 'select' ? $leading : 0;
            }
        }

        return 0;
    }

    /**
     * Position of the first column of a select list: after its DISTINCT, ALL or DISTINCT ON (...)
     *
     * @param list<string> $tokens lowercase tokens
     * @param int $select position of the SELECT
     */
    private static function findFirstColumn(array $tokens, int $select): int
    {
        $index = self::nextSignificant($tokens, $select);
        if (($tokens[$index] ?? '') === 'all') {
            return self::nextSignificant($tokens, $index);
        }
        if (($tokens[$index] ?? '') !== 'distinct') {
            return $index;
        }

        $index = self::nextSignificant($tokens, $index);
        if (($tokens[$index] ?? '') !== 'on') {
            return $index;
        }

        // The expressions of DISTINCT ON, up to the closing parenthesis
        $depth = 0;
        for ($index = self::nextSignificant($tokens, $index); isset($tokens[$index]); $index++) {
            if ($tokens[$index] === '(') {
                $depth++;
            } elseif ($tokens[$index] === ')' && --$depth === 0) {
                return self::nextSignificant($tokens, $index);
            }
        }

        return $index;
    }

    /**
     * Position of the first token after $index that is not blank (a comment is a blank token)
     *
     * @param list<string> $tokens
     */
    private static function nextSignificant(array $tokens, int $index): int
    {
        do {
            $index++;
        } while (isset($tokens[$index]) && trim($tokens[$index]) === '');

        return $index;
    }

    /**
     * Position of the last token before $index that is not blank (0 when none)
     *
     * @param list<string> $tokens
     */
    private static function previousSignificant(array $tokens, int $index): int
    {
        do {
            $index--;
        } while ($index > 0 && trim($tokens[$index]) === '');

        return max($index, 0);
    }

    /**
     * Positions of the FROM tokens of IS [NOT] DISTINCT FROM: a comparison, not the FROM clause
     *
     * @param list<string> $tokens lowercase tokens
     * @return array<int, true>
     */
    private static function findComparisonFroms(array $tokens): array
    {
        $froms = [];
        $previous = [];
        foreach ($tokens as $index => $token) {
            if (trim($token) === '') {
                continue;
            }
            if ($token === 'from' && ($previous === ['is', 'distinct'] || $previous === ['not', 'distinct'])) {
                $froms[$index] = true;
            }
            $previous = [$previous[1] ?? '', $token];
        }

        return $froms;
    }

    /**
     * Move the internal pointer to an arbitrary row in the result set
     */
    protected function dataSeek(int $offset): bool
    {
        // As the iteration: without result, there is no row
        if ($this->result === null) {
            return false;
        }

        return pg_result_seek($this->result, $offset);
    }

    /**
     * Format data
     *
     * @param array<int|string, string|null>|false $data a row fetched as a numeric array
     * @return Row|null
     */
    protected function format(array|false $data): ?array
    {
        if ($data === false) {
            return null;
        }

        $columns = [];
        foreach ($this->fields as $i => $rawField) {
            $columns[] = [
                'name'     => $rawField->name,
                'orgName'  => $rawField->orgname,
                'table'    => $rawField->table,
                'orgTable' => $rawField->orgtable,
                'schema'   => $rawField->schema,
                'value'    => $data[$i]
            ];
        }

        return $columns;
    }

    public function getNumRows(): int
    {
        if ($this->result === null) {
            return 0;
        }

        return pg_num_rows($this->result);
    }

    /**
     * Unescape the given field name according to PGSQL Standards: a quoted identifier loses its quotes and its
     * doubled double quotes ("a""b" is a"b); the double quotes around anything else (an expression) are trimmed
     */
    protected function unescapeField(string $field): string
    {
        if (self::match('/^"((?:[^"]++|"")*+)"$/s', $field, $matches)) {
            return str_replace('""', '"', $matches[1]);
        }

        return trim($field, '"');
    }

    /**
     * An identifier of the query as PostgreSQL resolves it: a quoted identifier unquoted, keeping its case, any other
     * folded to lowercase
     */
    private function identifierKey(string $identifier): string
    {
        return str_starts_with($identifier, '"') ? $this->unescapeField($identifier) : strtolower($identifier);
    }

    /**
     * Iterator
     */
    public function rewind(): void
    {
        $this->dataSeek(0);
        $this->iteratorOffset = -1;
        $this->next();
    }

    /**
     * @return Row|null
     */
    public function current(): mixed
    {
        return $this->iteratorCurrent;
    }

    public function key(): mixed
    {
        return $this->iteratorOffset;
    }

    public function next(): void
    {
        $this->iteratorCurrent = $this->result === null
            ? null
            : $this->format(pg_fetch_array($this->result, null, \PGSQL_NUM));

        $this->iteratorOffset++;
    }

    public function valid(): bool
    {
        return $this->iteratorCurrent !== null;
    }
}
