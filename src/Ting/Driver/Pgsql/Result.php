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
     * a word (an unquoted identifier holds $ after its first character), or any other character
     */
    private const SQL_TOKEN = <<<'REGEX'
        ~
            --[^\n]*
          | /\*(?:[^*]++|\*(?!/))*+(?:\*/|\z)
          | (?<![\w$])[eE]'(?:[^'\\]++|\\.|'')*+(?:'|\z)
          | '(?:[^']++|'')*+(?:'|\z)
          | "(?:[^"]++|"")*+(?:"|\z)
          | (?<![\w$])(?<tag>\$(?:[a-zA-Z_]\w*+)?\$)(?:[^$]++|(?!\k<tag>)\$)*+(?:\k<tag>|\z)
          | \w[\w$]*
          | .
        ~sx
        REGEX;

    /**
     * A column, table.column or [database.]schema.table.column: its last part is named <name>
     */
    private const COLUMN_REFERENCE = '(?:(?:"(?:[^"]++|"")*+"|[a-z_][a-z0-9_$]*+)\s*\.\s*)*+'
        . '(?<name>"(?:[^"]++|"")*+"|[a-z_][a-z0-9_$]*+)';

    /**
     * A type name: a multi-word type, or a (schema-qualified) name with its modifiers, then array brackets
     */
    private const TYPE_NAME = '(?:double\s+precision|(?:character|bit)\s+varying'
        . '|(?:timestamp|time)(?:\s*\(\s*\d+\s*\))?\s+with(?:out)?\s+time\s+zone'
        . '|[a-z_][a-z0-9_$]*+(?:\s*\.\s*[a-z_][a-z0-9_$]*+)?(?:\s*\([\d\s,]*+\))?)(?:\s*\[\s*\d*+\s*\])*+';

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
     * A column of [database.]schema.table.column
     */
    private const QUALIFIED_NAME = '/^(?:(?:"(?:[^"]++|"")*+"|[a-z_][a-z0-9_$]*+)\s*\.\s*)++'
        . '(?:"(?:[^"]++|"")*+"|[a-z_][a-z0-9_$]*+)$/i';

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

        [$tableToAlias, $aliasToSchema] = $this->findTables($tokensWithCase, $tokens, $comparisonFroms, $selectDepth);

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
                $isMainFrom = $token === 'from' && !isset($comparisonFroms[$index]);
                // The parenthesis closing a parenthesized main SELECT ends its last column
                $isClosed = $brackets < $selectDepth;
                if (
                    $isClosed
                    || ($brackets === $selectDepth && ($token === ',' || $isMainFrom || $index === $totalTokens - 1))
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

                    if ($matches !== []) {
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

                        $cut = 0;

                        if ($noAlias === false) {
                            if (isset($matches['prefix'])) {
                                $cut += \strlen($matches['prefix']);
                            }
                            if (isset($matches['alias'])) {
                                $cut += \strlen($matches['alias']);
                            }
                        }

                        $matches['column'] = $cut > 0 ? trim(substr($column, 0, - $cut)) : $column;

                        // Without AS, an identifier is an alias only after an operand: in price * qty, NOT flag,
                        // a IS DISTINCT FROM t.b or b::text, it ends the expression, which PostgreSQL names ?column?.
                        // A column of [database.]schema.table.column is named after the column, as PostgreSQL does
                        if (
                            $noAlias === false
                            && ($matches['prefix'] ?? '') === ''
                            && ($matches['alias'] ?? '') !== ''
                            && $matches['column'] !== ''
                            && !self::endsWithOperand($matches['column'])
                        ) {
                            $qualified = self::match(self::QUALIFIED_NAME, $column);
                            $matches['column'] = $column;
                            $matches['alias'] = $qualified ? $matches['alias'] : '?column?';
                        }

                        // A whole column made of a cast of a column, or of AT TIME ZONE: named as PostgreSQL does
                        $expressionName = $noAlias === false ? self::expressionName($column) : null;
                        if ($expressionName !== null) {
                            $matches['column'] = $column;
                            $matches['alias'] = $expressionName;
                        }

                        $columnComponent = [
                            'complex' => true,
                            'table' => '',
                            'column' => $matches['column']
                        ];
                        if ($noAlias === false && isset($matches['alias'])) {
                            $columnComponent['alias'] = $matches['alias'];
                        }
                    }

                    $columnsMatches[] = $columnComponent;
                    $column = '';
                    if ($isMainFrom || $isClosed) {
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

        // Lowercase key of the row => the alias read
        $aliasOfKey = [];
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

            // The alias is the key of the entity in the row, an application key rather than a database name: in
            // lowercase, as in 3.x, whether quoted or not ("U" and u are both u). Two aliases of the row differing
            // only by case would share that key and mix their columns in one entity
            $key = strtolower($stdClass->table);
            $aliasOfKey[$key] ??= $stdClass->table;
            if ($aliasOfKey[$key] !== $stdClass->table) {
                throw new QueryException(sprintf(
                    'Query invalid: the aliases %s and %s differ only by case, the key of their row: rename one of them',
                    $aliasOfKey[$key],
                    $stdClass->table
                ));
            }
            $stdClass->table = $key;

            // The schema and orgtable (from pg_field_table()) are unquoted already. The name keeps the spelling of the
            // query; a column is folded to lowercase unless quoted, as PostgreSQL names it
            $stdClass->name     = $this->unescapeField($stdClass->name);
            $stdClass->orgname  = $match['complex'] === false
                ? $this->identifierKey($stdClass->orgname)
                : $this->unescapeField($stdClass->orgname);

            $fields[] = $stdClass;
        }

        $this->fields = $fields;
    }

    /**
     * The tables of the FROM clauses and of the joins, read from the tokens: the alias of each table, and the schema
     * of each alias. A FROM belongs to a SELECT at the same depth of parentheses (not to extract(year FROM x), nor to
     * IS [NOT] DISTINCT FROM); a FROM clause lists its items separated by commas. Neither a sub-query nor a function
     * names a table.
     * A table or an alias read at several depths (a sub-query, a LATERAL join, IN (SELECT ...), EXISTS, a CTE) is
     * the one of the main SELECT: a table read deeper never replaces one read closer to the main SELECT
     *
     * @param list<string> $tokensWithCase
     * @param list<string> $tokens lowercase tokens
     * @param array<int, true> $comparisonFroms
     * @param int $selectDepth depth of the parentheses around the main SELECT
     * @return array{array<string, string>, array<string, string>} table => alias, alias => schema
     */
    private function findTables(array $tokensWithCase, array $tokens, array $comparisonFroms, int $selectDepth): array
    {
        $tableToAlias = [];
        $aliasToSchema = [];
        // How far from the main SELECT each table and each alias was read: 0 at its depth, deeper further, and
        // outside of its parentheses (the next SELECT of a UNION) the furthest
        $tableDistance = [];
        $aliasDistance = [];
        // At each depth of parentheses: no SELECT, its select list, or its FROM clause (until the clause after it)
        $states = [self::NO_SELECT];
        // The derived tables (sub-queries of the FROM clause but LATERAL ones, bodies of the CTEs): the aliases inside
        // are not visible outside of them. They nest: the end of the outermost one read so far is enough
        $derivedUntil = -1;

        foreach ($tokens as $index => $token) {
            if ($token === '(' || $token === '[') {
                $states[] = self::NO_SELECT;
                continue;
            }
            if ($token === ')' || $token === ']') {
                if (\count($states) > 1) {
                    array_pop($states);
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
                if (self::isSubQuery($tokens, $body)) {
                    $derivedUntil = max($derivedUntil, self::closingParenthesis($tokens, $body));
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

            // An item follows a FROM, a JOIN, or a comma of the FROM clause (FROM a, b; JOIN b ON ..., c)
            $isFrom = $token === 'from' && !isset($comparisonFroms[$index]) && $states[$top] !== self::NO_SELECT;
            if ($token !== 'join' && !$isFrom && ($token !== ',' || $states[$top] !== self::FROM_CLAUSE)) {
                continue;
            }
            if ($isFrom) {
                $states[$top] = self::FROM_CLAUSE;
            }

            $depth = \count($states) - 1;
            $distance = $depth >= $selectDepth ? $depth - $selectDepth : \PHP_INT_MAX - $depth;
            $item = self::readFromItem($tokensWithCase, $tokens, $index);
            if ($item['derived'] !== null) {
                $derivedUntil = max($derivedUntil, $item['derived']);
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
                }
                if ($distance <= ($aliasDistance[$alias] ?? \PHP_INT_MAX)) {
                    $aliasToSchema[$alias] = $item['schema'] !== null ? $this->identifierKey($item['schema']) : '';
                    $aliasDistance[$alias] = $distance;
                }
            }
        }

        return [$tableToAlias, $aliasToSchema];
    }

    /**
     * The item of a FROM clause or of a join following $position: [ONLY | LATERAL] [[database.]schema.]table, a
     * function call or a parenthesized sub-query / join, then its optional alias (AS optional, with its column
     * aliases)
     *
     * @param list<string> $tokensWithCase
     * @param list<string> $tokens lowercase tokens
     * @return array{table: ?string, schema: ?string, alias: ?string, end: int, derived: ?int} end: the first token
     *         after the item; derived: the closing parenthesis of a derived table (a sub-query, not LATERAL)
     */
    private static function readFromItem(array $tokensWithCase, array $tokens, int $position): array
    {
        $item = ['table' => null, 'schema' => null, 'alias' => null, 'end' => $position, 'derived' => null];

        $index = self::nextSignificant($tokens, $position);
        $lateral = ($tokens[$index] ?? '') === 'lateral';
        if ($lateral || ($tokens[$index] ?? '') === 'only') {
            $index = self::nextSignificant($tokens, $index);
        }

        if (($tokens[$index] ?? '') === '(') {
            $isSubQuery = self::isSubQuery($tokens, $index);
            $index = self::closingParenthesis($tokens, $index);
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
                $index = self::closingParenthesis($tokens, $next);
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
                $index = self::nextSignificant($tokens, self::closingParenthesis($tokens, $index));
            }
        }
        $item['end'] = $index;

        return $item;
    }

    /**
     * Whether the parenthesis at $index opens a sub-query, not a parenthesized join: (SELECT ...),
     * ((SELECT ...) UNION ...), (VALUES ...), (WITH ...), (TABLE ...)
     *
     * @param list<string> $tokens lowercase tokens
     */
    private static function isSubQuery(array $tokens, int $index): bool
    {
        if (($tokens[$index] ?? '') !== '(') {
            return false;
        }
        do {
            $index = self::nextSignificant($tokens, $index);
        } while (($tokens[$index] ?? '') === '(');

        return \in_array($tokens[$index] ?? '', ['select', 'with', 'values', 'table'], true);
    }

    /**
     * Position of the parenthesis closing the one at $index (the last token when it is never closed)
     *
     * @param list<string> $tokens
     */
    private static function closingParenthesis(array $tokens, int $index): int
    {
        $depth = 0;
        for ($count = \count($tokens); $index < $count; $index++) {
            if ($tokens[$index] === '(' || $tokens[$index] === '[') {
                $depth++;
            } elseif (($tokens[$index] === ')' || $tokens[$index] === ']') && --$depth === 0) {
                return $index;
            }
        }

        return $index;
    }

    /**
     * Whether the token is an identifier: a quoted identifier or a name, unquoted
     */
    private static function isIdentifier(string $token): bool
    {
        return self::match('/^(?:"(?:[^"]++|"")++"|[a-z_][a-z0-9_$]*+)$/i', $token);
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
     * The name PostgreSQL gives to a column made of a cast of a column (id::text, CAST(u.id AS text): the name of the
     * column) or of AT TIME ZONE (timezone, the function it calls); null for any other expression
     */
    private static function expressionName(string $column): ?string
    {
        if (
            self::match('/^' . self::COLUMN_REFERENCE . '(?:\s*::\s*' . self::TYPE_NAME . ')++$/i', $column, $matches)
            || self::match(
                '/^cast\s*\(\s*' . self::COLUMN_REFERENCE . '\s+as\s+' . self::TYPE_NAME . '\s*\)$/i',
                $column,
                $matches
            )
        ) {
            return $matches['name'];
        }

        $operand = '(?:' . self::COLUMN_REFERENCE . "|'(?:[^']++|'')*+')";
        if (self::match('/(?J)^' . $operand . '\s+at\s+time\s+zone\s+' . $operand . '$/i', $column)) {
            return 'timezone';
        }

        return null;
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
