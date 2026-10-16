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

namespace CCMBenchmark\Ting\Driver\Mysqli;

use CCMBenchmark\Ting\Driver\ResultInterface;

/**
 * @phpstan-import-type Row from ResultInterface
 * @phpstan-import-type Column from ResultInterface
 */
class Result implements ResultInterface
{
    protected ?string $connectionName = null;
    protected ?string $database = null;
    /**
     * Natively typed object: tests stand in for mysqli_result, whose properties cannot be read without a server
     * @var \mysqli_result|null
     */
    protected ?object $result = null;
    /** @var array<int, object{name: string, orgname: string, table: string, orgtable: string, def: string, db: string, catalog: string, max_length: int, length: int, charsetnr: string, flags: int, type: int, decimals: int}> $fields  */
    protected array $fields = [];
    private const CAST_NONE = 0;
    private const CAST_INT = 1;
    private const CAST_FLOAT = 2;
    private const CAST_NULL = 3;
    protected int $iteratorOffset = 0;
    /** @var array<int, Column>|null the columns of $fields without their value, see prepareColumnTemplates() */
    private ?array $columnTemplates = null;
    /** @var array<int, self::CAST_*> how to convert the value of each column */
    private array $columnCasts = [];
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
     * @param \mysqli_result $result
     * Typehinting $result would need rewriting all related unit tests
     */
    public function setResult($result): static
    {
        $this->result = $result;
        $this->fields = $this->result->fetch_fields();
        $this->columnTemplates = null;
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
     * Move the internal result pointer to an arbitrary row
     */
    protected function dataSeek(int $offset): bool|null
    {
        if ($this->result !== null) {
            return $this->result->data_seek($offset);
        }
        return null;
    }

    /**
     * Format output
     * @param array<mixed>|null|false $data a row fetched as a numeric array
     * @return Row|null
     */
    protected function format(array|null|false $data): ?array
    {
        if ($data === null || $data === false) {
            return null;
        }

        if (array_is_list($data) === false) {
            $data = array_values($data);
        }

        $columns = [];
        foreach ($this->columnTemplates ??= $this->prepareColumnTemplates() as $i => $column) {
            $value = $data[$i];
            if (\is_string($value)) {
                switch ($this->columnCasts[$i]) {
                    case self::CAST_FLOAT:
                        $value = (float) $value;
                        break;
                    case self::CAST_INT:
                        $value = (int) $value;
                        break;
                    case self::CAST_NULL:
                        $value = null;
                        break;
                }
            }
            $column['value'] = $value;
            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * The columns of a row without their value, and how to convert the value of each: read once per result instead of
     * once per row
     *
     * @return array<int, Column>
     */
    private function prepareColumnTemplates(): array
    {
        $templates = [];
        $this->columnCasts = [];
        foreach ($this->fields as $i => $rawField) {
            $templates[$i] = [
                'name'     => $rawField->name,
                'orgName'  => $rawField->orgname,
                'table'    => $rawField->table,
                'orgTable' => $rawField->orgtable,
                'value'    => null,
            ];
            $this->columnCasts[$i] = match ($rawField->type) {
                // Decimal, double, decimal or numeric (MySQL 5.0.3+), float: all mapped to float
                MYSQLI_TYPE_DECIMAL, MYSQLI_TYPE_DOUBLE, MYSQLI_TYPE_NEWDECIMAL, MYSQLI_TYPE_FLOAT => self::CAST_FLOAT,
                // Tinyint, mediumint, int, smallint
                MYSQLI_TYPE_TINY, MYSQLI_TYPE_INT24, MYSQLI_TYPE_LONG, MYSQLI_TYPE_SHORT => self::CAST_INT,
                MYSQLI_TYPE_NULL => self::CAST_NULL,
                // Bigint (may be bigger than PHP_INT_MAX) and any other type: the string as it is
                default => self::CAST_NONE,
            };
        }

        return $templates;
    }

    /**
     * @return int<0, max>|string the number of rows in the result set
     */
    public function getNumRows(): int|string
    {
        // As the iteration: without result, there is no row
        if ($this->result === null) {
            return 0;
        }

        return $this->result->num_rows;
    }

    /**
     * Iterator
     *
     * @internal Don't use mysqli_result iterator system
     *  When you do a jointure like that :
     *  select * from city c inner join country co on (c.cou_code = co.cou_code)
     *  the second column "cou_code" is missing, cause current() use an associative array
     */
    public function rewind(): void
    {
        if ($this->result !== null) {
            $this->result->data_seek(0);
            $this->iteratorOffset = -1;
            $this->next();
        }
    }

    /**
     * Return current row
     *
     * @return Row|null
     */
    public function current(): mixed
    {
        return $this->iteratorCurrent;
    }

    /**
     * Return the key of the actual row
     */
    public function key(): mixed
    {
        return $this->iteratorOffset;
    }

    /**
     * Move to the next row in result set
     */
    public function next(): void
    {
        if ($this->result !== null) {
            $this->iteratorCurrent = $this->format($this->result->fetch_array(MYSQLI_NUM));

            $this->iteratorOffset++;
        }
    }

    /**
     * Is the actual row valid
     * @return bool
     */
    public function valid(): bool
    {
        return $this->iteratorCurrent !== null;
    }
}
