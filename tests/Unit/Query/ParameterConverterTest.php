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

namespace CCMBenchmark\Ting\Tests\Unit\Query;

use CCMBenchmark\Ting\Query\ParameterConverter;
use CCMBenchmark\Ting\Query\QueryException;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\fixtures\ColorsEnum;
use tests\fixtures\PriorityEnum;
use tests\fixtures\SuitEnum;

class ParameterConverterTest extends TestCase
{
    public function testScalarsNullAndStringableShouldBeKeptAsTheyAre()
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return '0190a3c4-8a6e-7d4e-9f7a-6b2c3d4e5f60';
            }
        };
        $params = ['i' => 1, 's' => 'a', 'f' => 1.5, 'b' => false, 'n' => null, 'u' => $stringable];

        [$sql, $converted] = ParameterConverter::convert('SELECT :i, :s, :f, :b, :n, :u', $params);

        $this->assertSame('SELECT :i, :s, :f, :b, :n, :u', $sql);
        $this->assertSame($params, $converted);
    }

    public function testADateShouldBeSentWithoutTimezoneConversion()
    {
        $params = [
            'mutable'   => new \DateTime('2026-03-04 05:06:07.891', new \DateTimeZone('Europe/Paris')),
            'immutable' => new \DateTimeImmutable('2026-12-31 23:59:59', new \DateTimeZone('America/New_York')),
        ];

        [$sql, $converted] = ParameterConverter::convert('SELECT :mutable, :immutable', $params);

        $this->assertSame('SELECT :mutable, :immutable', $sql);
        $this->assertSame(['mutable' => '2026-03-04 05:06:07', 'immutable' => '2026-12-31 23:59:59'], $converted);
    }

    /**
     * Carbon is Stringable: it keeps being sent through its __toString(), as before
     */
    public function testAStringableDateShouldBeSentAsItsString()
    {
        $date = new class ('2026-03-04 05:06:07') extends \DateTimeImmutable implements \Stringable {
            public function __toString(): string
            {
                return 'its own string';
            }
        };

        $this->assertSame(['d' => $date], ParameterConverter::convert('SELECT :d', ['d' => $date])[1]);
    }

    public function testABackedEnumShouldBeSentAsItsValue()
    {
        [, $converted] = ParameterConverter::convert(
            'SELECT :color, :priority',
            ['color' => ColorsEnum::GREEN, 'priority' => PriorityEnum::HIGH]
        );

        $this->assertSame(['color' => 'green', 'priority' => 3], $converted);
    }

    public function testAPureEnumShouldBeRefused()
    {
        $this->assertThrows(
            QueryException::class,
            fn () => ParameterConverter::convert('SELECT :suit', ['suit' => SuitEnum::HEARTS]),
            'The parameter "suit" is the case tests\fixtures\SuitEnum::HEARTS of a pure enum, which has no value to'
            . ' send: use a backed enum, or give a value'
        );
    }

    public function testAnObjectThatCannotBeSentShouldBeRefused()
    {
        $this->assertThrows(
            QueryException::class,
            fn () => ParameterConverter::convert('SELECT :o', ['o' => new \ArrayObject()]),
            'The parameter "o" is an object of class ArrayObject, which cannot be sent: give a scalar, null, a'
            . ' \DateTimeInterface, a \BackedEnum, a \Stringable object or an array (IN list)'
        );
    }

    public function testAnArrayShouldBeExpandedIntoAList()
    {
        [$sql, $converted] = ParameterConverter::convert(
            'SELECT * FROM t WHERE id IN (:ids) AND name = :name',
            ['ids' => [3, 5, 8], 'name' => 'a']
        );

        $this->assertSame('SELECT * FROM t WHERE id IN (:ids__1, :ids__2, :ids__3) AND name = :name', $sql);
        $this->assertSame(['ids__1' => 3, 'ids__2' => 5, 'ids__3' => 8, 'name' => 'a'], $converted);
    }

    public function testTheKeysOfAnArrayShouldBeIgnored()
    {
        [$sql, $converted] = ParameterConverter::convert('SELECT :ids', ['ids' => ['x' => 'a', 7 => 'b']]);

        $this->assertSame('SELECT :ids__1, :ids__2', $sql);
        $this->assertSame(['ids__1' => 'a', 'ids__2' => 'b'], $converted);
    }

    public function testTheElementsOfAListShouldBeConverted()
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'uuid';
            }
        };

        [, $converted] = ParameterConverter::convert(
            'SELECT :values',
            ['values' => [new \DateTimeImmutable('2026-01-02 03:04:05'), ColorsEnum::RED, $stringable, true, 1.5]]
        );

        $this->assertSame(
            [
                'values__1' => '2026-01-02 03:04:05',
                'values__2' => 'red',
                'values__3' => $stringable,
                'values__4' => true,
                'values__5' => 1.5,
            ],
            $converted
        );
    }

    public function testAListUsedSeveralTimesShouldBeExpandedAtEachPlaceholder()
    {
        [$sql, $converted] = ParameterConverter::convert(
            'SELECT * FROM t WHERE a IN (:ids) OR b IN (:ids) OR c = :id',
            ['ids' => [1, 2], 'id' => 0]
        );

        $this->assertSame(
            'SELECT * FROM t WHERE a IN (:ids__1, :ids__2) OR b IN (:ids__1, :ids__2) OR c = :id',
            $sql
        );
        $this->assertSame(['ids__1' => 1, 'ids__2' => 2, 'id' => 0], $converted);
    }

    /**
     * The placeholders are found as the drivers find them: a longer name, an escaped colon, a cast or a time are not
     * the placeholder of the list
     */
    public function testOnlyThePlaceholdersOfTheListShouldBeExpanded()
    {
        [$sql] = ParameterConverter::convert(
            "SELECT :ids_count, '\\:ids', x::ids, '12:ids' FROM t WHERE id IN (:ids)",
            ['ids' => [1, 2], 'ids_count' => 2]
        );

        $this->assertSame("SELECT :ids_count, '\\:ids', x::ids, '12:ids' FROM t WHERE id IN (:ids__1, :ids__2)", $sql);
    }

    public function testAnEmptyListShouldBeRefused()
    {
        $this->assertThrows(
            QueryException::class,
            fn () => ParameterConverter::convert('SELECT * FROM t WHERE id IN (:ids)', ['ids' => []]),
            'The parameter "ids" is an empty array: an empty IN list is not valid SQL, do not run the query (or write'
            . ' a condition that is always false) when there is no value'
        );
    }

    public function testANullElementShouldBeRefused()
    {
        $this->assertThrows(
            QueryException::class,
            fn () => ParameterConverter::convert('SELECT * FROM t WHERE id IN (:ids)', ['ids' => [1, null]]),
            'The element 2 of the parameter "ids" is null: an IN list never matches NULL, remove it and test IS NULL in'
            . ' its own condition'
        );
    }

    public function testANestedListShouldBeRefused()
    {
        $this->assertThrows(
            QueryException::class,
            fn () => ParameterConverter::convert('SELECT * FROM t WHERE id IN (:ids)', ['ids' => [[1, 2]]]),
            'The element 1 of the parameter "ids" is an array: an IN list holds values, not lists'
        );
    }

    /**
     * @return iterable<string, array{0: mixed, 1: string}>
     */
    public static function provideElementsThatCannotBeSent(): iterable
    {
        yield 'pure enum' => [
            SuitEnum::SPADES,
            'The element 2 of the parameter "ids" is the case tests\fixtures\SuitEnum::SPADES of a pure enum, which has'
            . ' no value to send: use a backed enum, or give a value',
        ];
        yield 'object' => [
            new \stdClass(),
            'The element 2 of the parameter "ids" is an object of class stdClass, which cannot be sent: give a scalar,'
            . ' null, a \DateTimeInterface, a \BackedEnum, a \Stringable object or an array (IN list)',
        ];
    }

    #[DataProvider('provideElementsThatCannotBeSent')]
    public function testAnElementThatCannotBeSentShouldBeRefused(mixed $element, string $message)
    {
        $this->assertThrows(
            QueryException::class,
            fn () => ParameterConverter::convert('SELECT :ids', ['ids' => [1, $element]]),
            $message
        );
    }

    public function testAnElementNamedLikeAnotherParameterShouldBeRefused()
    {
        $this->assertThrows(
            QueryException::class,
            fn () => ParameterConverter::convert('SELECT :ids, :ids__2', ['ids' => [1, 2], 'ids__2' => 'other']),
            'The parameter "ids" is a list whose elements are sent as ids__1, ...: "ids__2" is also another parameter or'
            . ' placeholder of the query, rename one of them'
        );
    }

    public function testAnElementNamedLikeAnotherPlaceholderShouldBeRefused()
    {
        // :ids__1 has no value of its own: it would silently receive the first element of the list
        $this->assertThrows(
            QueryException::class,
            fn () => ParameterConverter::convert('SELECT :ids, :ids__1', ['ids' => [1, 2]]),
            'The parameter "ids" is a list whose elements are sent as ids__1, ...: "ids__1" is also another parameter or'
            . ' placeholder of the query, rename one of them'
        );
    }

    public function testAListNamedLikeTheElementOfAnotherListShouldBeRefused()
    {
        $this->assertThrows(
            QueryException::class,
            fn () => ParameterConverter::convert('SELECT :a, :a__1', ['a' => [1], 'a__1' => [2, 3]]),
            'The parameter "a" is a list whose elements are sent as a__1, ...: "a__1" is also another parameter or'
            . ' placeholder of the query, rename one of them'
        );
    }

    public function testListsWhoseNamesShareAPrefixShouldNotCollide()
    {
        [$sql, $converted] = ParameterConverter::convert(
            'SELECT :a, :a_1, :a__x',
            ['a' => [1], 'a_1' => [2], 'a__x' => [3, 4]]
        );

        $this->assertSame('SELECT :a__1, :a_1__1, :a__x__1, :a__x__2', $sql);
        $this->assertSame(['a__1' => 1, 'a_1__1' => 2, 'a__x__1' => 3, 'a__x__2' => 4], $converted);
    }

    public function testAListOfTheSameLengthShouldGiveTheSameSql()
    {
        $sql = 'SELECT * FROM t WHERE id IN (:ids)';

        $this->assertSame(
            ParameterConverter::convert($sql, ['ids' => [1, 2]])[0],
            ParameterConverter::convert($sql, ['ids' => [3, 4]])[0]
        );
        $this->assertNotSame(
            ParameterConverter::convert($sql, ['ids' => [1, 2]])[0],
            ParameterConverter::convert($sql, ['ids' => [1, 2, 3]])[0]
        );
    }
}
