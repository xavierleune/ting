<?php

/***********************************************************************
 *
 * Ting - PHP Datamapper
 * ==========================================
 *
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

namespace CCMBenchmark\Ting\Tests\Unit\Util;

use CCMBenchmark\Ting\Entity\PropertyListenerInterface;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use CCMBenchmark\Ting\Util\Debug;
use PHPUnit\Framework\Attributes\RequiresPhp;
use tests\fixtures\model\Bouh;
use tests\fixtures\model\City;
use tests\fixtures\model\HookedPropertiesEntity;
use tests\fixtures\model\PublicPropertiesEntity;

class DebugTest extends TestCase
{
    public function testExportShouldDescribeAnEntityWithoutItsListeners()
    {
        $bouh = new Bouh();
        $bouh->setName('Xavier');
        $bouh->addPropertyListener($this->createStub(PropertyListenerInterface::class));

        $export = (new Debug())->export($bouh);

        $this->assertSame(Bouh::class, $export['__CLASS__']);
        $this->assertSame('Xavier', $export['name']);
        $this->assertSame(['USER'], $export['roles']);
        $this->assertArrayNotHasKey('listeners', $export);
    }

    public function testExportShouldLeaveTheEntityUntouched()
    {
        $bouh = new Bouh();
        $listener = $this->createMock(PropertyListenerInterface::class);
        $listener->expects($this->once())->method('propertyChanged');
        $bouh->addPropertyListener($listener);

        (new Debug())->export($bouh);

        $bouh->setName('Xavier');
    }

    public function testExportShouldSkipUninitializedTypedProperties()
    {
        $export = (new Debug())->export(new PublicPropertiesEntity());

        $this->assertArrayNotHasKey('propertyWithSetter', $export);
        $this->assertSame('default', $export['propertyWithDefaultValue']);
        $this->assertSame('with getter', $export['propertyWithGetter']);
    }

    public function testExportShouldReplaceObjectsBeyondMaxDepthByTheirClass()
    {
        $city = new City();
        $city->setName('Paris');
        $bouh = new Bouh();
        $bouh->setCity($city);

        $this->assertSame('Paris', (new Debug())->export($bouh)['city']['name']);
        $this->assertSame(City::class, (new Debug())->export($bouh, 2)['city']);
    }

    public function testExportShouldExportEachEntityOfAnArray()
    {
        $bouh = new Bouh();
        $bouh->setName('Xavier');

        $export = (new Debug())->export(['b' => $bouh, 'count' => 1]);

        $this->assertSame('Xavier', $export['b']['name']);
        $this->assertSame(1, $export['count']);
    }

    #[RequiresPhp('>= 8.4.0')]
    public function testExportShouldReadHookedProperties()
    {
        $export = (new Debug())->export(new HookedPropertiesEntity());

        $this->assertSame('default (hooked on get)', $export['hookGetOnly']);
    }
}
