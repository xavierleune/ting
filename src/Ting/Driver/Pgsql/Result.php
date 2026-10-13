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
    public const PARSE_RAW_COLUMN = '/^\s*(?:(?P<table>"(?:[^"]++|"")+"|[a-z_\x80-\xff][a-z0-9_$\x80-\xff]*)\.)?(?P<column>"(?:[^"]++|"")+"|[a-z_\x80-\xff][a-z0-9_$\x80-\xff]*)(?:\s+as\s+(?P<alias>"(?:[^"]++|"")+"|[a-z_\x80-\xff][a-z0-9_$\x80-\xff]*))?\s*$/i';
    public const PARSE_DYNAMIC_COLUMN = '/(?<prefix>\s+(as\s+))?(?P<alias>"(?:[^"]++|"")+"|(?<![\w$\x80-\xff])[a-z_\x80-\xff][a-z0-9_$\x80-\xff]*+)?\s*$/i';

    /**
     * A token of the query: a comment, a string literal ('...', E'...', $tag$...$tag$), a quoted identifier,
     * a word (an unquoted identifier holds non-ASCII letters, and $ after its first character), or any other character
     */
    private const SQL_TOKEN = <<<'REGEX'
        ~
            --[^\n]*
          | (?<comment>/\*[^*/]*+(?:(?:/++(?!\*)|/+(?=/\*)|\*++(?!/)|\*+(?=\*/)|(?&comment))[^*/]*+)*+(?:\*/|\z))
          | (?<![\w$\x80-\xff])[eE]'(?:[^'\\]++|\\.|'')*+(?:'|\z)
          | '(?:[^']++|'')*+(?:'|\z)
          | "(?:[^"]++|"")*+(?:"|\z)
          | (?<![\w$\x80-\xff])(?<tag>\$(?:[a-zA-Z_]\w*+)?\$)(?:[^$]++|(?!\k<tag>)\$)*+(?:\k<tag>|\z)
          | [\w\x80-\xff][\w$\x80-\xff]*
          | .
        ~sx
        REGEX;

    /**
     * An unquoted name or a quoted identifier
     */
    private const NAME = '(?:"(?:[^"]++|"")++"|[a-z_\x80-\xff][a-z0-9_$\x80-\xff]*+)';

    /**
     * The type of a cast: a time zone type; an interval with its fields; a type of several words or a
     * [schema.]name, with its modifiers; then its array bounds ([], [3], ARRAY, ARRAY[3]). The longest types come
     * first: the casts are possessive
     */
    private const TYPE_NAME = '(?:(?:timestamp|time)(?:\s*\(\s*\d+\s*\))?\s+with(?:out)?\s+time\s+zone'
        . '|interval(?:\s*\(\s*\d+\s*\))?\s+(?:year|month|day|hour|minute|second)'
        . '(?:\s+to\s+(?:month|hour|minute|second))?(?:\s*\(\s*\d+\s*\))?'
        . '|(?:double\s+precision|(?:national\s+)?(?:character|char)\s+varying'
        . '|national\s+(?:character|char)|nchar\s+varying|bit\s+varying'
        . '|' . self::NAME . '(?:\s*\.\s*' . self::NAME . ')?)(?:\s*\([\d\s,]*+\))?'
        . ')(?:\s*\[\s*\d*+\s*\])*+(?:\s+array(?:\s*\[\s*\d*+\s*\])?)?';

    /**
     * Casts, ending an expression
     */
    private const CASTS = '(?:\s*::\s*' . self::TYPE_NAME . ')*+';

    /**
     * A column of [schema.]table.column (but TRUE, FALSE and NULL) or CAST(column AS type), then its casts
     */
    private const CAST_OF_COLUMN = '/^(?:'
        . '(?!(?:true|false|null)(?![a-z0-9_$\x80-\xff]))(?:' . self::NAME . '\s*\.\s*)*+(?<column>' . self::NAME . ')'
        . '|cast\s*\(\s*(?:' . self::NAME . '\s*\.\s*)*+(?<cast>' . self::NAME . ')\s+as\s+' . self::TYPE_NAME . '\s*\)'
        . ')' . self::CASTS . '$/i';

    /**
     * The start of a function call: [schema.]function( (but NOT, EXISTS, ARRAY, ROW... followed by a parenthesis)
     */
    private const FUNCTION_CALL = '/^(?!(?:not|exists|array|row|cast|any|all|some)\s*\()'
        . '(?:' . self::NAME . '\s*\.\s*)*+'
        . '(?<function>' . self::NAME . ')\s*\(/i';

    /**
     * The end of an expression whose last word belongs to it, not an alias: a cast (x::double precision,
     * x::interval day) or an interval literal (interval '1' day)
     */
    private const NO_ALIAS_END = '/(?:::\s*' . self::TYPE_NAME . '|\binterval\s*(?:\(\s*\d+\s*\)\s*)?'
        . '\'(?:[^\']++|\'\')*+\'\s*(?:year|month|day|hour|minute|second))\s*$/i';

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
    private const ASTERISK_COLUMN = '/^(?:distinct\s+)?(?:(?:"(?:[^"]++|"")*+"|[\w\x80-\xff]++)\s*\.\s*)?\*$/i';

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
        $cases = 0;
        $brackets = 0;
        $totalTokens = count($tokens);
        $noAlias = false;

        foreach ($tokens as $index => $token) {
            if ($index < $firstColumn) {
                continue;
            }

            // The END of a CASE is no alias: CASE nests
            if ($token === 'case') {
                $noAlias = true;
                $cases++;
            } elseif ($token === 'end' && $cases > 0) {
                $cases--;
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
                    if (!$isClosed && $index === $totalTokens - 1 && $token !== ';') {
                        $column .= $tokensWithCase[$index];
                        // The last token of the query, as any other one of the column, ends a CASE before an alias
                        if ($cases === 0 && $token !== 'end' && trim($token) !== '') {
                            $noAlias = false;
                        }
                    }
                    $cases = 0;

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
                        // a IS DISTINCT FROM t.b, b::double precision or count(*) OVER w, it ends the expression,
                        // as a postfix operator does (x NOTNULL, x ISNULL, x IS NFC NORMALIZED)
                        if (
                            $alias !== ''
                            && $prefix === ''
                            && (
                                $expression === ''
                                || !self::endsWithOperand($expression)
                                || self::match(self::NO_ALIAS_END, $column)
                                || \in_array(strtolower($alias), ['notnull', 'isnull'], true)
                                || (
                                    strtolower($alias) === 'normalized'
                                    && self::match('/\bis\s+(?:not\s+)?nfk?[cd]$/i', $expression)
                                )
                            )
                        ) {
                            $alias = '';
                            $expression = $column;
                        }

                        $columnComponent = [
                            'complex' => true,
                            'table' => '',
                            'column' => $expression,
                            // Without alias, named after its column or its function, else ?column?
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

                if ($cases === 0 && $token !== 'end' && trim($token) !== '') {
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
     * The name of a column made of an expression without alias: the column of a cast of a column (id::text,
     * CAST(u.id AS text), app.city.id), the function of a call, cast or not (count(*), now()::date); null for any
     * other expression, named ?column?. PostgreSQL names more expressions ('x'::text text, CASE case...): alias them
     */
    private static function expressionName(string $expression): ?string
    {
        if (self::match(self::CAST_OF_COLUMN, $expression, $matches)) {
            return ($matches['column'] ?? '') !== '' ? $matches['column'] : $matches['cast'];
        }
        if (!self::match(self::FUNCTION_CALL, $expression, $matches)) {
            return null;
        }

        // The parenthesis of the call, ending its match, closes before its casts only
        $tokens = self::tokenize($expression);
        [$closing] = self::matchParentheses($tokens);
        $open = 0;
        for ($length = \strlen($tokens[0]); $length < \strlen($matches[0]); $length += \strlen($tokens[$open])) {
            $open++;
        }
        $close = $closing[$open] ?? \count($tokens);
        if ($close >= \count($tokens)) {
            return null;
        }

        return self::match('/^' . self::CASTS . '\s*$/i', implode('', \array_slice($tokens, $close + 1)))
            ? $matches['function']
            : null;
    }
    /**
     * Whether the expression ends with an operand (a name, a quoted identifier, a literal, a parenthesis or a bracket),
     * which an alias may follow without AS, rather than with an operator, a "." or a keyword expecting an operand
     */
    private static function endsWithOperand(string $expression): bool
    {
        return self::match('/[\w$\x80-\xff"\')\]]$/', $expression)
            && !self::match(
                '/(?:^|[^\w$."\x80-\xff])(?:not|and|or|is|like|ilike|similar|to|between|symmetric|escape|collate'
                . '|from|when|then'
                . '|else|case|any|some|all|distinct|overlaps|at|over|at\s+time\s+zone)$/i',
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
