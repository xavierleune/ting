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

namespace CCMBenchmark\Ting\Tests\Unit\Driver\SphinxQL;

use CCMBenchmark\Ting\Driver\SphinxQL\Driver;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\Fake\Mysqli;
use tests\fixtures\Fake\SphinxQL;

class DriverTest extends TestCase
{
    public function testEscapeFieldShouldEscapeField()
    {
        $mockDriver = new SphinxQL();

        $driver = new Driver($mockDriver);

        $this->assertSame('Bouh', $driver->escapeField('Bouh'));
    }

    public function testEscapeFieldShouldAcceptNoFieldAndScalars(): void
    {
        $driver = new Driver(new SphinxQL());

        // The interface makes the field optional: the default null gives an empty name, as (string) null
        $this->assertSame('', $driver->escapeField());
        $this->assertSame('42', $driver->escapeField(42));
    }

    public function testExecuteShouldQuoteValuesAsMysqli()
    {
        $queries = [];
        $connection = $this->createStub(Mysqli::class);
        $connection->method('real_escape_string')->willReturnCallback(fn (string $value) => addslashes($value));
        $connection->method('query')->willReturnCallback(function (string $sql) use (&$queries): bool {
            $queries[] = $sql;

            return true;
        });

        $driver = new Driver($connection);
        $driver->execute(
            'SELECT id FROM idx WHERE MATCH(:match) AND deleted = :deleted AND visible = :visible'
                . ' AND parent = :parent AND weight > :weight AND score > :score',
            ['match' => "l'été", 'deleted' => false, 'visible' => true, 'parent' => null, 'weight' => 3, 'score' => 1.5]
        );

        // null became '' (with a deprecation) and false became ''
        $this->assertSame(
            ["SELECT id FROM idx WHERE MATCH('l\\'été') AND deleted = 0 AND visible = 1"
                . " AND parent = null AND weight > 3 AND score > 1.5"],
            $queries
        );
    }
}
