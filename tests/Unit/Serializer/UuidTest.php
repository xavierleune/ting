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

use CCMBenchmark\Ting\Serializer\RuntimeException;
use CCMBenchmark\Ting\Serializer\Uuid;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use Symfony\Component\Uid\UuidV4;

class UuidTest extends TestCase
{
    public function testSerializeThenUnSerializeShouldReturnOriginalValue(): void
    {
        $uuid = new UuidV4();
        $serializer = new Uuid();

        $this->assertInstanceOf(UuidV4::class, $serializer->unserialize($serializer->serialize($uuid)));
        $this->assertEquals(
            $uuid->toRfc4122(),
            $serializer->unserialize($serializer->serialize($uuid))->toRfc4122()
        );
    }

    public function testUnserializeInvalidValueShouldRaiseException(): void
    {
        $serializer = new Uuid();

        $this->assertThrows(RuntimeException::class, function () use ($serializer): void {
            $serializer->unserialize('Invalid uuid');
        });
    }

    public function testSerializeInvalidValueShouldRaiseException(): void
    {
        $serializer = new Uuid();

        $this->assertThrows(RuntimeException::class, function () use ($serializer): void {
            $serializer->serialize(new \StdClass());
        });
    }

    public function testNullValueShouldBeReturned(): void
    {
        $serializer = new Uuid();

        $this->assertNull($serializer->serialize(null));
        $this->assertNull($serializer->unserialize(null));
    }
}
