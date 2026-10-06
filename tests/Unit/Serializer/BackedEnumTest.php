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

namespace CCMBenchmark\Ting\Tests\Unit\Serializer;

use CCMBenchmark\Ting\Serializer\BackedEnum;
use CCMBenchmark\Ting\Serializer\RuntimeException;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use tests\fixtures\ColorsEnum;
use tests\fixtures\PriorityEnum;

class BackedEnumTest extends TestCase
{
    public function testSerializeThenUnSerializeShouldReturnOriginalValue()
    {
        $color = ColorsEnum::BLUE;
        $serializer = new BackedEnum();

        $this->assertEquals(
            $color,
            $serializer->unserialize($serializer->serialize($color), ['enum' => ColorsEnum::class])
        );
    }

    public function testSerializeAnIntBackedEnumShouldReturnAString()
    {
        $serializer = new BackedEnum();

        $this->assertSame('3', $serializer->serialize(PriorityEnum::HIGH));
        $this->assertSame(
            PriorityEnum::HIGH,
            $serializer->unserialize($serializer->serialize(PriorityEnum::HIGH), ['enum' => PriorityEnum::class])
        );
    }

    public function testUnserializeInvalidValueShouldRaiseException()
    {
        $serializer = new BackedEnum();

        $this->assertThrows(RuntimeException::class, function () use ($serializer): void {
            $serializer->unserialize('1345-67-89 bouh', ['enum' => ColorsEnum::class]);
        });
    }

    public function testUnserializeAnIntBackedEnumShouldAcceptIntegers()
    {
        $serializer = new BackedEnum();

        $this->assertSame(PriorityEnum::HIGH, $serializer->unserialize(3, ['enum' => PriorityEnum::class]));
        $this->assertSame(PriorityEnum::HIGH, $serializer->unserialize('+3', ['enum' => PriorityEnum::class]));
        $this->assertSame(PriorityEnum::HIGH, $serializer->unserialize('003', ['enum' => PriorityEnum::class]));
    }

    public function testUnserializeAnIntBackedEnumShouldRejectWhatIsNotAnInteger()
    {
        $serializer = new BackedEnum();

        foreach (['abc', '', '1.5', ' 3', '3 ', '-', '1e0', '9223372036854775808', 1.0, true] as $value) {
            $types = $this->collectErrorTypes(
                static fn () => $serializer->unserialize($value, ['enum' => PriorityEnum::class]),
                $thrown
            );

            $this->assertInstanceOf(RuntimeException::class, $thrown, var_export($value, true));
            $this->assertSame([], $types, var_export($value, true));
        }
        $this->assertThrows(RuntimeException::class, function () use ($serializer): void {
            $serializer->unserialize('-1', ['enum' => PriorityEnum::class]);
        });
    }

    public function testSerializeInvalidValueShouldRaiseException()
    {
        $serializer = new BackedEnum();

        $this->assertThrows(RuntimeException::class, function () use ($serializer): void {
            $serializer->serialize(new \StdClass());
        });
    }

    public function testNullValueShouldBeReturned()
    {
        $serializer = new BackedEnum();

        $this->assertNull($serializer->serialize(null));
        $this->assertNull($serializer->unserialize(null, ['enum' => ColorsEnum::class]));
    }
}
