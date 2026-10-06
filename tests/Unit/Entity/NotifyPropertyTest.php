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
use tests\fixtures\model\SleepingTrackedChild;
use tests\fixtures\model\TrackedBase;
use tests\fixtures\model\Bouh;
use tests\fixtures\model\TrackedChild;
use tests\fixtures\model\TrackedChildOfUntracked;
use tests\fixtures\model\UntrackedBase;

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

    public function testPropertyChangedShouldNotNotifyTheSameObjectAsOldAndNewValue()
    {
        $mockListener = $this->createMock(PropertyListenerInterface::class);
        $mockListener->expects($this->never())->method('propertyChanged');

        $notifyProperty = new Bouh();
        $notifyProperty->addPropertyListener($mockListener);
        $value = new \DateTime();
        $notifyProperty->propertyChanged('Bouh', $value, $value);
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

        // Mangled names, as serialize() writes them without __serialize()
        $expected = [
            "\0*\0id" => 20,
            "\0*\0firstname" => null,
            "\0*\0name" => 'Xavier',
            "\0*\0enabled" => null,
            "\0*\0price" => null,
            "\0*\0roles" => ['USER'],
            "\0*\0city" => null,
            "\0*\0retrievedTime" => null,
            "\0*\0originalCity" => null,
            "\0*\0cities" => [],
        ];
        $this->assertSame($expected, $entity->__serialize());
    }

    public function testUnserializeShouldRestoreThePrivatePropertiesOfTheParentAndChildClasses()
    {
        $entity = new TrackedChild();
        $entity->setOwner('owner');
        $entity->setTitle('title');
        $listener = $this->createMock(PropertyListenerInterface::class);
        $listener->expects($this->never())->method('propertyChanged');
        $entity->addPropertyListener($listener);

        $copy = unserialize(serialize($entity));

        $this->assertSame('owner', $copy->getOwner());
        $this->assertSame('title', $copy->getTitle());
        $copy->setTitle('other');
    }

    public function testUnserializeShouldRestoreThePrivatePropertiesOfAParentWithoutTheTrait()
    {
        $entity = new TrackedChildOfUntracked();
        $entity->setSecret('secret');
        $entity->setTitle('title');
        $entity->addPropertyListener($this->createStub(PropertyListenerInterface::class));

        $copy = unserialize(serialize($entity));

        $this->assertSame('secret', $copy->getSecret());
        $this->assertSame('title', $copy->getTitle());
    }

    public function testUnserializeShouldReadAnEntitySerializedWithUnmangledNames()
    {
        // As written before 4.0.0: get_object_vars() in the scope of the trait
        $serialized = 'O:25:"tests\\fixtures\\model\\Bouh":2:{s:2:"id";i:20;s:4:"name";s:6:"Xavier";}';

        $copy = unserialize($serialized);

        $this->assertSame(20, $copy->getId());
        $this->assertSame('Xavier', $copy->getName());
    }

    public function testSerializeShouldHonourSleep(): void
    {
        $entity = new SleepingTrackedChild();
        $entity->setOwner('owner');
        $entity->setTitle('title');
        $entity->setCache('cache');
        $entity->addPropertyListener($this->createStub(PropertyListenerInterface::class));

        // Bare names resolved as serialize() does, mangled names kept, an uninitialized typed property skipped
        $this->assertSame(
            ["\0" . SleepingTrackedChild::class . "\0title" => 'title', "\0" . TrackedBase::class . "\0owner" => 'owner'],
            $entity->__serialize()
        );

        SleepingTrackedChild::$wakeups = 0;
        $copy = unserialize(serialize($entity));

        $this->assertSame('title', $copy->getTitle());
        $this->assertSame('owner', $copy->getOwner());
        $this->assertNull($copy->getCache());
        $this->assertSame(1, SleepingTrackedChild::$wakeups);
    }

    public function testSerializeShouldNeverKeepTheListenersListedBySleep(): void
    {
        $entity = new SleepingTrackedChild();
        $entity->sleep = ['title', 'listeners'];
        $entity->addPropertyListener($this->createStub(PropertyListenerInterface::class));

        $this->assertSame(["\0" . SleepingTrackedChild::class . "\0title" => null], $entity->__serialize());
    }

    public function testSerializeShouldWarnAboutAPropertyListedBySleepWhichDoesNotExist(): void
    {
        $entity = new SleepingTrackedChild();
        $entity->sleep = ['title', 'missing'];

        $types = $this->collectErrorTypes(function () use ($entity, &$serialized): void {
            $serialized = $entity->__serialize();
        });

        $this->assertSame([E_USER_WARNING], $types);
        $this->assertSame(["\0" . SleepingTrackedChild::class . "\0title" => null], $serialized);
    }

    public function testDebugInfoShouldExposeThePrivatePropertiesOfTheParentAndChildClasses()
    {
        $entity = new TrackedChildOfUntracked();
        $entity->setSecret('secret');
        $entity->setTitle('title');

        $this->assertSame(
            ["\0" . UntrackedBase::class . "\0secret" => 'secret', "\0" . TrackedChildOfUntracked::class . "\0title" => 'title'],
            $entity->__debugInfo()
        );
    }

    public function testDebugInfoShouldNotExposeListeners()
    {
        $entity = new Bouh();
        $entity->setName('Xavier');
        $entity->addPropertyListener($this->createStub(PropertyListenerInterface::class));

        $debugInfo = $entity->__debugInfo();

        $this->assertArrayNotHasKey("\0*\0listeners", $debugInfo);
        $this->assertSame('Xavier', $debugInfo["\0*\0name"]);
    }
}
