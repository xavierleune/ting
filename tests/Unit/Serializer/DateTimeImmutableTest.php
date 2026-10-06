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

use CCMBenchmark\Ting\Serializer\DateTimeImmutable;
use CCMBenchmark\Ting\Serializer\RuntimeException;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class DateTimeImmutableTest extends TestCase
{
    public function testSerializeThenUnSerializeShouldReturnOriginalValue()
    {
        $datetime = new \DateTimeImmutable('now');
        $serializer = new DateTimeImmutable();

        $this->assertEquals(
            $datetime->format(\DateTimeInterface::ATOM),
            $serializer->unserialize($serializer->serialize($datetime))->format(\DateTimeInterface::ATOM)
        );
    }

    public function testUnserializeInvalidValueShouldRaiseException()
    {
        $serializer = new DateTimeImmutable();

        $this->assertThrows(RuntimeException::class, function () use ($serializer): void {
            $serializer->unserialize('1345-67-89 bouh');
        });
    }

    public function testUnserializeAutoShouldWorkWithCommonFormat()
    {
        $serializer = new DateTimeImmutable();

        $this->assertIsObject($serializer->unserialize('2009-10-20 17:43:15', ['unSerializeUseFormat' => false]));
        $this->assertIsObject(
            $serializer->unserialize('2008-08-04 12:47:54.659698', ['unSerializeUseFormat' => false])
        );
        $this->assertIsObject($serializer->unserialize('2008-08-04 12:47', ['unSerializeUseFormat' => false]));
        $this->assertIsObject($serializer->unserialize('2008-08-04', ['unSerializeUseFormat' => false]));
    }

    public function testSerializeShouldUseTheDatetimeFormatByDefault()
    {
        $serializer = new DateTimeImmutable();

        $this->assertSame(
            '2024-01-31 10:00:00',
            $serializer->serialize(new \DateTimeImmutable('2024-01-31 10:00:00'))
        );
        $this->assertSame(
            '2024-01-31T10:00:00+01:00',
            $serializer->serialize(
                new \DateTimeImmutable('2024-01-31T10:00:00+01:00'),
                ['format' => \DateTimeInterface::ATOM]
            )
        );
    }

    public function testUnserializeShouldReadADatabaseDatetimeByDefault()
    {
        $serializer = new DateTimeImmutable();

        $this->assertSame(
            '2024-01-31 10:00:00',
            $serializer->unserialize('2024-01-31 10:00:00')->format('Y-m-d H:i:s')
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}> value, format of the check, expected
     */
    public static function provideDatabaseValues(): array
    {
        return [
            'MySQL DATETIME / PostgreSQL timestamp' => [
                '2024-01-31 10:00:00', 'Y-m-d H:i:s.u', '2024-01-31 10:00:00.000000',
            ],
            'microseconds' => ['2024-01-31 10:00:00.123456', 'Y-m-d H:i:s.u', '2024-01-31 10:00:00.123456'],
            'fewer fractional digits' => ['2024-01-31 10:00:00.5', 'Y-m-d H:i:s.u', '2024-01-31 10:00:00.500000'],
            'PostgreSQL timestamptz' => [
                '2024-01-31 10:00:00+01', 'Y-m-d H:i:s.u P', '2024-01-31 10:00:00.000000 +01:00',
            ],
            'timestamptz, half-hour offset' => [
                '2024-01-31 10:00:00-03:30', 'Y-m-d H:i:s.u P', '2024-01-31 10:00:00.000000 -03:30',
            ],
            'offset without colon' => [
                '2024-01-31 10:00:00+0100', 'Y-m-d H:i:s.u P', '2024-01-31 10:00:00.000000 +01:00',
            ],
            'timestamptz with microseconds' => [
                '2024-01-31 10:00:00.123456+01', 'Y-m-d H:i:s.u P', '2024-01-31 10:00:00.123456 +01:00',
            ],
            'UTC as Z' => ['2024-01-31 10:00:00Z', 'Y-m-d H:i:s.u P', '2024-01-31 10:00:00.000000 +00:00'],
            'DATE, at midnight' => ['2024-01-31', 'Y-m-d H:i:s.u', '2024-01-31 00:00:00.000000'],
            'ATOM' => ['2024-01-31T10:00:00+01:00', 'Y-m-d H:i:s.u P', '2024-01-31 10:00:00.000000 +01:00'],
            'RFC 3339 with a fraction' => [
                '2024-01-31T10:00:00.25Z', 'Y-m-d H:i:s.u P', '2024-01-31 10:00:00.250000 +00:00',
            ],
        ];
    }

    #[DataProvider('provideDatabaseValues')]
    public function testUnserializeShouldFallBackToTheDatabaseFormatsWhenTheFormatDoesNotMatch(
        string $value,
        string $checkFormat,
        string $expected
    ) {
        $serializer = new DateTimeImmutable();

        $this->assertSame($expected, $serializer->unserialize($value, ['format' => 'd/m/Y H:i'])->format($checkFormat));
    }

    public function testUnserializeShouldReadADatabaseDatetimeWithAnotherFormat()
    {
        $serializer = new DateTimeImmutable();

        $this->assertSame(
            '2024-01-31 10:00:00',
            $serializer->unserialize('2024-01-31 10:00:00', ['format' => \DateTimeInterface::ATOM])
                ->format('Y-m-d H:i:s')
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function provideNonDatabaseValues(): array
    {
        return [
            'now' => ['now'],
            'tomorrow' => ['tomorrow'],
            'relative' => ['+1 day'],
            'US-style date' => ['05/06/2024'],
            'empty string' => [''],
            'ISO without offset' => ['2024-01-31T10:00:00'],
            'time zone name' => ['2024-01-31 10:00:00 Europe/Paris'],
            'time zone abbreviation' => ['2024-01-31 10:00:00 CET'],
            'minutes only' => ['2024-01-31 10:00'],
            'trailing text' => ['2024-01-31 10:00:00 bouh'],
        ];
    }

    #[DataProvider('provideNonDatabaseValues')]
    public function testUnserializeShouldRejectWhatADatabaseDoesNotReturn(string $value)
    {
        $serializer = new DateTimeImmutable();

        $this->assertThrows(RuntimeException::class, function () use ($serializer, $value): void {
            $serializer->unserialize($value);
        });
    }

    public function testUnserializeWithoutFormatShouldUseThePhpParser()
    {
        $serializer = new DateTimeImmutable();

        $this->assertSame(
            (new \DateTimeImmutable('tomorrow'))->format('Y-m-d H:i:s'),
            $serializer->unserialize('tomorrow', ['unSerializeUseFormat' => false])->format('Y-m-d H:i:s')
        );
    }

    public function testUnserializeAnEmptyStringShouldRaiseException()
    {
        $serializer = new DateTimeImmutable();

        $this->assertThrows(RuntimeException::class, function () use ($serializer): void {
            $serializer->unserialize('');
        });
    }

    public function testUnserializeADateFormatShouldResetTheTime()
    {
        $serializer = new DateTimeImmutable();

        foreach (['Y-m-d', '!Y-m-d', 'Y-m-d|'] as $format) {
            $this->assertSame(
                '2024-01-31 00:00:00.000000',
                $serializer->unserialize('2024-01-31', ['format' => $format])->format('Y-m-d H:i:s.u'),
                $format
            );
        }
    }

    public function testSerializeInvalidValueShouldRaiseException()
    {
        $serializer = new DateTimeImmutable();

        $this->assertThrows(RuntimeException::class, function () use ($serializer): void {
            $serializer->serialize(new \StdClass());
        });
    }

    public function testNullValueShouldBeReturned()
    {
        $serializer = new DateTimeImmutable();

        $this->assertNull($serializer->serialize(null));
        $this->assertNull($serializer->unserialize(null));
    }
}
