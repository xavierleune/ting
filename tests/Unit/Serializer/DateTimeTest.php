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

use CCMBenchmark\Ting\Serializer\DateTime;
use CCMBenchmark\Ting\Serializer\RuntimeException;
use CCMBenchmark\Ting\Tests\Support\TestCase;

class DateTimeTest extends TestCase
{
    public function testSerializeThenUnSerializeShouldReturnOriginalValue()
    {
        $datetime = new \DateTime('now');
        $serializer = new DateTime();

        $this->assertEquals(
            $datetime->format('Y-m-d H:i:s'),
            $serializer->unserialize($serializer->serialize($datetime))->format('Y-m-d H:i:s')
        );
    }

    public function testUnserializeInvalidValueShouldRaiseException()
    {
        $serializer = new DateTime();

        $this->assertThrows(RuntimeException::class, function () use ($serializer): void {
            $serializer->unserialize('1345-67-89 bouh');
        });
    }

    public function testUnserializeAutoShouldWorkWithCommonFormat()
    {
        $serializer = new DateTime();

        $this->assertIsObject($serializer->unserialize('2009-10-20 17:43:15', ['unSerializeUseFormat' => false]));
        $this->assertIsObject(
            $serializer->unserialize('2008-08-04 12:47:54.659698', ['unSerializeUseFormat' => false])
        );
        $this->assertIsObject($serializer->unserialize('2008-08-04 12:47', ['unSerializeUseFormat' => false]));
        $this->assertIsObject($serializer->unserialize('2008-08-04', ['unSerializeUseFormat' => false]));
    }

    public function testUnserializeShouldFallBackToPhpParsingWhenTheFormatDoesNotMatch()
    {
        $serializer = new DateTime();

        $this->assertSame(
            '2024-01-31T10:00:00+01:00',
            $serializer->unserialize('2024-01-31T10:00:00+01:00')->format(\DateTimeInterface::ATOM)
        );
        // PostgreSQL timestamptz
        $this->assertSame(
            '2024-01-31T10:00:00+01:00',
            $serializer->unserialize('2024-01-31 10:00:00+01')->format(\DateTimeInterface::ATOM)
        );
    }

    public function testUnserializeAnEmptyStringShouldRaiseException()
    {
        $serializer = new DateTime();

        $this->assertThrows(RuntimeException::class, function () use ($serializer): void {
            $serializer->unserialize('');
        });
    }

    public function testSerializeInvalidValueShouldRaiseException()
    {
        $serializer = new DateTime();

        $this->assertThrows(RuntimeException::class, function () use ($serializer): void {
            $serializer->serialize(new \StdClass());
        });
    }

    public function testNullValueShouldBeReturned()
    {
        $serializer = new DateTime();

        $this->assertNull($serializer->serialize(null));
        $this->assertNull($serializer->unserialize(null));
    }
}
