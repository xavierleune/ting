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

use CCMBenchmark\Ting\Serializer\Ip;
use CCMBenchmark\Ting\Serializer\RuntimeException;
use CCMBenchmark\Ting\Tests\Support\TestCase;

class IpTest extends TestCase
{
    public function testSerializeThenUnSerializeShouldReturnOriginalValue()
    {
        $value = '127.0.0.1';
        $serializer = new Ip();

        $this->assertEquals($value, $serializer->unserialize($serializer->serialize($value)));
    }

    public function testSerializeInvalidValueShouldRaiseException()
    {
        $serializer = new Ip();

        $this->assertThrows(RuntimeException::class, function () use ($serializer): void {
            $serializer->serialize('badip');
        });
    }

    public function testUnserializeShouldAcceptAnInteger()
    {
        $serializer = new Ip();

        $this->assertSame('10.0.0.1', $serializer->unserialize(167772161));
        $this->assertSame('10.0.0.1', $serializer->unserialize('167772161'));
        $this->assertSame('255.255.255.255', $serializer->unserialize('4294967295'));
        // A signed 32 bits column
        $this->assertSame('255.255.255.255', $serializer->unserialize('-1'));
        $this->assertSame('128.0.0.0', $serializer->unserialize(-2147483648));
    }

    public function testUnserializeShouldRejectWhatIsNotAnIPv4Integer()
    {
        $serializer = new Ip();

        foreach (['abc', '', '1.5', 1.5, 2.0, ' 1', "1\n", '4294967296', '-2147483649', true] as $value) {
            $thrown = null;
            $types = $this->collectErrorTypes(static fn () => $serializer->unserialize($value), $thrown);

            $this->assertInstanceOf(RuntimeException::class, $thrown, var_export($value, true));
            $this->assertSame([], $types, var_export($value, true));
        }
    }

    public function testNullValueShouldBeReturned()
    {
        $serializer = new Ip();

        $this->assertNull($serializer->serialize(null));
        $this->assertNull($serializer->unserialize(null));
    }
}
