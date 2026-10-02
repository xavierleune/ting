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

use Brick\Geo\IO\WKBReader;
use CCMBenchmark\Ting\Serializer\Geometry;
use CCMBenchmark\Ting\Serializer\RuntimeException;
use CCMBenchmark\Ting\Tests\Support\NativeFunctionMock;
use CCMBenchmark\Ting\Tests\Support\TestCase;

class GeometryTest extends TestCase
{
    public function testUnserializeShouldReturnGeometryObject()
    {
        $geometrySerializer = new Geometry();

        $this->assertInstanceOf(
            \Brick\Geo\Geometry::class,
            $geometrySerializer->unserialize(hex2bin("00000000010100000000000000000024400000000000003440"))
        );
    }

    public function testUnserializeWithNullValueShouldReturnNull()
    {
        $geometrySerializer = new Geometry();

        $this->assertNull($geometrySerializer->unserialize(null));
    }

    public function testSerializeShouldReturnStringValue()
    {
        $geometrySerializer = new Geometry();

        $this->assertSame(
            hex2bin("00000000010100000000000000000024400000000000003440"),
            $geometrySerializer->serialize(
                (new WKBReader())->read(hex2bin('010100000000000000000024400000000000003440'))
            )
        );
    }

    public function testSerializeWithNullValueShouldReturnNull()
    {
        $geometrySerializer = new Geometry();

        $this->assertNull($geometrySerializer->serialize(null));
    }

    public function testRuntimeExceptionWhenPackageNotPresent()
    {
        $geometrySerializer = new Geometry();
        // Built before overriding class_exists: brick/geo itself is still installed
        $geometry = (new WKBReader())->read(hex2bin('010100000000000000000024400000000000003440'));
        NativeFunctionMock::override('class_exists', false);

        $this->assertThrows(RuntimeException::class, function () use ($geometrySerializer): void {
            $geometrySerializer->unserialize(
                hex2bin("00000000010100000000000000000024400000000000003440")
            );
        });
        $this->assertThrows(RuntimeException::class, function () use ($geometrySerializer, $geometry): void {
            $geometrySerializer->serialize($geometry);
        });
    }

    public function testUnserializeThrowExceptionOnIncorrectData()
    {
        $geometrySerializer = new Geometry();

        $this->assertThrows(\UnexpectedValueException::class, function () use ($geometrySerializer): void {
            $geometrySerializer->unserialize("Incorrect data");
        });
    }

    public function testSerializeThrowExceptionOnIncorrectData()
    {
        $geometrySerializer = new Geometry();

        $this->assertThrows(\UnexpectedValueException::class, function () use ($geometrySerializer): void {
            $geometrySerializer->serialize((new \StdClass()));
        });
    }
}
