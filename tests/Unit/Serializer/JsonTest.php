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

use CCMBenchmark\Ting\Serializer\Json;
use CCMBenchmark\Ting\Serializer\RuntimeException;
use CCMBenchmark\Ting\Tests\Support\TestCase;

class JsonTest extends TestCase
{
    public function testSerializeShouldReturnJsonEncodedValue()
    {
        $jsonSerializer = new Json();

        $this->assertSame(json_encode(['Bouh']), $jsonSerializer->serialize(['Bouh']));
    }

    public function testSerializeShouldReturnJsonEncodedValueAndUsePassedOptions()
    {
        $jsonSerializer = new Json();

        $this->assertSame(
            json_encode(['"Bouh"'], JSON_HEX_QUOT),
            $jsonSerializer->serialize(['"Bouh"'], ['options' => JSON_HEX_QUOT])
        );
    }

    public function testSerializeShouldReturnJsonEncodedValueAndUsePassedDepthAndRaiseException()
    {
        $jsonSerializer = new Json();

        $this->assertThrows(RuntimeException::class, function () use ($jsonSerializer): void {
            $jsonSerializer->serialize(['Bouh' => ['subBouh' => ['subSubBouh']]], ['depth' => 2]);
        });
    }

    public function testUnserializeShouldReturnJsonDecodedValue()
    {
        $encodedValue = json_encode(['Bouh']);
        $jsonSerializer = new Json();

        $this->assertSame(json_decode($encodedValue), $jsonSerializer->unserialize($encodedValue));
    }

    public function testUnserializeShouldReturnJsonEncodedValueAndUsePassedOptions()
    {
        $encodedValue = json_encode(['"Bouh"'], JSON_HEX_QUOT);
        $jsonSerializer = new Json();

        $this->assertSame(
            json_decode($encodedValue, false, 512, JSON_HEX_QUOT),
            $jsonSerializer->unserialize($encodedValue, ['options' => JSON_HEX_QUOT])
        );
    }

    public function testUnserializeShouldReturnJsonEncodedValueAndUsePassedDepth()
    {
        $encodedValue = json_encode(['Bouh' => ['subBouh']]);
        $jsonSerializer = new Json();

        $this->assertSame(
            json_decode($encodedValue, true, 3),
            $jsonSerializer->unserialize($encodedValue, ['assoc' => true, 'depth' => 3])
        );
    }

    public function testUnserializeShouldRaiseExceptionOnInvalidJson()
    {
        $jsonSerializer = new Json();

        $this->assertThrows(RuntimeException::class, function () use ($jsonSerializer): void {
            $jsonSerializer->unserialize('bouh');
        });
    }

    public function testNullValueShouldReturnNull()
    {
        $serializer = new Json();

        $this->assertNull($serializer->serialize(null));
        $this->assertNull($serializer->unserialize(null));
    }

    public function testEmptyStringValueShouldReturnNull()
    {
        $serializer = new Json();

        $this->assertSame('""', $serializer->serialize(''));
        $this->assertNull($serializer->unserialize(''));
    }
}
