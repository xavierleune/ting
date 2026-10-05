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

namespace CCMBenchmark\Ting\Tests\Unit\Entity;

use CCMBenchmark\Ting\Entity\PropertyListenerInterface;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\model\Bouh;

class NotifyPropertyTest extends TestCase
{
    public function testPropertyChangedShouldNotCallPropertyChangedOnListeners()
    {
        $mockListener = $this->createMock(PropertyListenerInterface::class);
        $mockListener->expects($this->never())->method('propertyChanged');

        $notifyProperty = new Bouh();
        $notifyProperty->addPropertyListener($mockListener);
        $notifyProperty->propertyChanged('Bouh', 'value', 'value');
    }

    public function testPropertyChangedShouldCallPropertyChangedOnListeners()
    {
        $mockListener = $this->createMock(PropertyListenerInterface::class);
        $mockListener->expects($this->once())->method('propertyChanged');
        $mockListener2 = $this->createMock(PropertyListenerInterface::class);
        $mockListener2->expects($this->once())->method('propertyChanged');

        $notifyProperty = new Bouh();
        $notifyProperty->addPropertyListener($mockListener);
        $notifyProperty->addPropertyListener($mockListener2);
        $notifyProperty->propertyChanged('Bouh', 'value', 'newValue');
    }

    public function testSerializationWithoutListerners()
    {
        $mockListener = $this->createStub(PropertyListenerInterface::class);
        $mockListener2 = $this->createStub(PropertyListenerInterface::class);
        $entity = new Bouh();
        $entity->setId(20);
        $entity->setName('Xavier');
        $entity->addPropertyListener($mockListener);
        $entity->addPropertyListener($mockListener2);

        $expected = [
            'id' => 20,
            'firstname' => null,
            'name' => 'Xavier',
            'enabled' => null,
            'price' => null,
            'roles' => ['USER'],
            'city' => null,
            'retrievedTime' => null,
            'originalCity' => null,
            'cities' => [],
        ];
        $this->assertSame($expected, $entity->__serialize());
    }

    public function testDebugInfoShouldNotExposeListeners()
    {
        $entity = new Bouh();
        $entity->setName('Xavier');
        $entity->addPropertyListener($this->createStub(PropertyListenerInterface::class));

        $debugInfo = $entity->__debugInfo();

        $this->assertArrayNotHasKey('listeners', $debugInfo);
        $this->assertSame('Xavier', $debugInfo['name']);
    }
}
