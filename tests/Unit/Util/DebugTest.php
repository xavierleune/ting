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
use tests\fixtures\model\TrackedChild;

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

    #[RequiresPhp('>= 8.4.0')]
    public function testExportShouldSkipVirtualProperties()
    {
        $export = (new Debug())->export(new HookedPropertiesEntity());

        $this->assertArrayNotHasKey('virtualSetOnly', $export);
        $this->assertSame('default', $export['hookSetOnly']);
    }

    public function testExportShouldShowTheValueOfDates()
    {
        $export = (new Debug())->export([
            'mutable' => new \DateTime('2026-01-02 03:04:05.123456', new \DateTimeZone('Europe/Paris')),
            'immutable' => new \DateTimeImmutable('2026-01-02 03:04:05', new \DateTimeZone('UTC')),
        ]);

        $this->assertSame(
            ['__CLASS__' => \DateTime::class, 'date' => '2026-01-02T03:04:05.123456+01:00', 'timezone' => 'Europe/Paris'],
            $export['mutable']
        );
        $this->assertSame(
            ['__CLASS__' => \DateTimeImmutable::class, 'date' => '2026-01-02T03:04:05.000000+00:00', 'timezone' => 'UTC'],
            $export['immutable']
        );
    }

    public function testExportShouldShowTheStateOfOtherInternalObjects()
    {
        $export = (new Debug())->export(new \DateTimeZone('Europe/Paris'));

        $this->assertSame(\DateTimeZone::class, $export['__CLASS__']);
        $this->assertSame('Europe/Paris', $export['timezone']);
    }

    public function testExportShouldStopOnANegativeMaxDepth()
    {
        $object = new \stdClass();
        $object->self = $object;

        $this->assertSame(\stdClass::class, (new Debug())->export($object, -1));
        $this->assertSame('Array(1)', (new Debug())->export([$object], -1));
    }

    public function testExportShouldIncludeThePrivatePropertiesOfParentClasses()
    {
        $entity = new TrackedChild();
        $entity->setOwner('Xavier');
        $entity->setTitle('Title');

        $export = (new Debug())->export($entity);

        $this->assertSame('Xavier', $export['owner']);
        $this->assertSame('Title', $export['title']);
        $this->assertArrayNotHasKey('listeners', $export);
    }

    public function testExportShouldNotConsumeGenerators()
    {
        $generator = (static function () {
            yield 1;
            yield 2;
        })();

        $debug = new Debug();
        $this->assertSame(['__CLASS__' => \Generator::class], $debug->export($generator));
        $this->assertSame(['__CLASS__' => \Generator::class], $debug->export(['g' => $generator])['g']);

        $this->assertSame([1, 2], iterator_to_array($generator));
    }

    public function testExportShouldNotConsumeIteratorsOverAGenerator()
    {
        $debug = new Debug();
        foreach ([\IteratorIterator::class, \NoRewindIterator::class] as $class) {
            $generator = (static function () {
                yield 1;
                yield 2;
            })();
            $iterator = new $class($generator);

            $this->assertSame(['__CLASS__' => $class], $debug->export($iterator), $class);
            $this->assertSame(['__CLASS__' => $class], $debug->export(['i' => $iterator])['i'], $class);

            $this->assertSame([1, 2], iterator_to_array($generator), $class);
        }
    }

    public function testExportShouldExportTheObjectKeysOfAnIterableAsPairs()
    {
        $city = new City();
        $city->setName('Paris');
        $map = new \WeakMap();
        $map[$city] = 'capital';

        $export = (new Debug())->export($map);

        $this->assertCount(1, $export);
        $this->assertSame(['key', 'value'], array_keys($export[0]));
        $this->assertSame(City::class, $export[0]['key']['__CLASS__']);
        $this->assertSame('Paris', $export[0]['key']['name']);
        $this->assertSame('capital', $export[0]['value']);
    }

    public function testExportShouldExportAnObjectHoldingAWeakMap()
    {
        $holder = new \stdClass();
        $holder->map = new \WeakMap();
        $holder->map[$holder] = 1;

        $export = (new Debug())->export($holder);

        $this->assertSame(1, $export['map'][0]['value']);
        $this->assertSame(\stdClass::class, $export['map'][0]['key']['__CLASS__']);
    }
}
