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
use tests\fixtures\Fake\SphinxQL;

class DriverTest extends TestCase
{
    public function testEscapeFieldShouldEscapeField()
    {
        $mockDriver = new SphinxQL();

        $driver = new Driver($mockDriver);

        $this->assertSame('Bouh', $driver->escapeField('Bouh'));
    }
}
