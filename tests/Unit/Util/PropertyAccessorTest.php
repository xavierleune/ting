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

use CCMBenchmark\Ting\Tests\Support\TestCase;
use CCMBenchmark\Ting\Util\PropertyAccessor;
use PHPUnit\Framework\Attributes\RequiresPhp;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\PropertyAccess\Exception\InvalidTypeException;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use tests\fixtures\model\AsymmetricVisibilityChild;
use tests\fixtures\model\AsymmetricVisibilityEntity;
use tests\fixtures\model\AsymmetricVisibilityMutators;
use tests\fixtures\model\CustomGetterEntity;
use tests\fixtures\model\HookedPropertiesEntity;
use tests\fixtures\model\PublicPropertiesEntity;
use tests\fixtures\model\ReadonlyPropertiesChild;
use tests\fixtures\model\ReadonlyPropertiesEntity;
use tests\fixtures\model\TrackedChild;
use tests\fixtures\model\TypedMutatorsEntity;
use tests\fixtures\model\VirtualDateHookEntity;

class PropertyAccessorTest extends TestCase
{
    #[RequiresPhp('>= 8.4.0')]
    public function testSetPropertyShouldBypassPropertyHook()
    {
        $accessor = new PropertyAccessor();
        $entity = new HookedPropertiesEntity();
        $accessor->setValue($entity, 'hookBoth', 'value', null);

        $this->assertSame('value (hooked on get)', $entity->hookBoth);
    }

    /**
     * A virtual property has no value of its own to write: it is written through its set hook
     */
    #[RequiresPhp('>= 8.4.0')]
    public function testSetPropertyShouldWriteAVirtualPropertyThroughItsSetHook()
    {
        $accessor = new PropertyAccessor();
        $entity = new HookedPropertiesEntity();
        $accessor->setValue($entity, 'virtualSetOnly', 'value', null);

        $this->assertSame('value (hooked on set)', $entity->hookSetOnly);
    }

    /**
     * A public property only the class can write (private(set), protected(set)): its own value is written, without
     * its set hook, as for a public property
     */
    #[RequiresPhp('>= 8.4.0')]
    public function testSetPropertyShouldWriteAPropertyWithAsymmetricVisibility()
    {
        $accessor = new PropertyAccessor();
        $entity = new AsymmetricVisibilityEntity();
        $accessor->setValue($entity, 'privateSet', 'private', null);
        $accessor->setValue($entity, 'protectedSet', 'protected', null);
        $accessor->setValue($entity, 'hookedProtectedSet', 'hooked', null);

        $this->assertSame('private', $entity->privateSet);
        $this->assertSame('protected', $entity->protectedSet);
        $this->assertSame('hooked', $entity->hookedProtectedSet);
    }

    /**
     * Virtual, a property with asymmetric visibility has no value of its own: it is written through its set hook
     */
    #[RequiresPhp('>= 8.4.0')]
    public function testSetPropertyShouldWriteAVirtualPropertyWithAsymmetricVisibilityThroughItsSetHook()
    {
        $accessor = new PropertyAccessor();
        $entity = new AsymmetricVisibilityEntity();
        $accessor->setValue($entity, 'virtualPrivateSet', 'virtual', null);

        $this->assertSame('virtual (hooked on set)', $entity->virtualPrivateSet);
    }

    /**
     * Its conventional setter writes it, as before
     */
    #[RequiresPhp('>= 8.4.0')]
    public function testSetPropertyShouldWriteAPropertyWithAsymmetricVisibilityThroughItsSetter()
    {
        $accessor = new PropertyAccessor();
        $entity = new AsymmetricVisibilityEntity();
        $accessor->setValue($entity, 'withSetter', 'value', null);

        $this->assertSame('value (setter)', $entity->withSetter);
    }

    /**
     * Written by reflection when the Symfony property accessor has no method to write it with (__set() is not one of
     * them), through its adder and remover or its getter-setter otherwise, whatever the Symfony version
     */
    #[RequiresPhp('>= 8.4.0')]
    public function testSetPropertyShouldWriteAPropertyWithAsymmetricVisibilityThroughTheMethodsOfTheSymfonyAccessor()
    {
        $accessor = new PropertyAccessor();
        $entity = new AsymmetricVisibilityMutators();
        $accessor->setValue($entity, 'magic', 'value', null);
        $accessor->setValue($entity, 'items', ['a', 'b'], null);
        $accessor->setValue($entity, 'name', 'value', null);

        $this->assertSame('value', $entity->magic);
        $this->assertSame(['a (adder)', 'b (adder)'], $entity->items);
        $this->assertSame('value (getter-setter)', $entity->name);
    }

    /**
     * The Symfony property accessor only uses an adder and a remover for an iterable value: any other is written by
     * reflection
     */
    #[RequiresPhp('>= 8.4.0')]
    public function testSetPropertyShouldWriteANonIterableValueOfAPropertyWithAsymmetricVisibilityAndAnAdder()
    {
        $accessor = new PropertyAccessor();
        $entity = new AsymmetricVisibilityMutators();
        $accessor->setValue($entity, 'tags', ['a'], null);
        $this->assertSame(['a (adder)'], $entity->tags);

        $accessor->setValue($entity, 'tags', null, null);
        $this->assertNull($entity->tags);
    }

    /**
     * Declared by the parent class: written in the scope of that class, not of the class of the object
     */
    #[RequiresPhp('>= 8.4.0')]
    public function testSetPropertyShouldWriteAPropertyWithAsymmetricVisibilityDeclaredByTheParentClass()
    {
        $accessor = new PropertyAccessor();
        $entity = new AsymmetricVisibilityChild();
        $accessor->setValue($entity, 'privateSet', 'private', null);
        $accessor->setValue($entity, 'protectedSet', 'protected', null);
        $accessor->setValue($entity, 'virtualPrivateSet', 'virtual', null);

        $this->assertSame('private', $entity->privateSet);
        $this->assertSame('protected', $entity->protectedSet);
        $this->assertSame('virtual (hooked on set)', $entity->virtualPrivateSet);
    }

    public function testSetPropertyShouldInitializeAPublicReadonlyPropertyDeclaredByTheParentClass()
    {
        $accessor = new PropertyAccessor();
        $entity = new ReadonlyPropertiesChild();
        $accessor->setValue($entity, 'id', 3, null);

        $this->assertSame(3, $entity->id);
    }

    public function testSetPropertyShouldInitializeAPublicReadonlyProperty()
    {
        $accessor = new PropertyAccessor();
        $entity = new ReadonlyPropertiesEntity();
        $accessor->setValue($entity, 'id', 3, null);
        $accessor->setValue($entity, 'name', null, null);

        $this->assertSame(3, $entity->id);
        $this->assertNull($entity->name);
    }

    /**
     * Once initialized, a readonly property cannot be modified, even by Ting
     */
    public function testSetPropertyShouldNotModifyAnInitializedReadonlyProperty()
    {
        $accessor = new PropertyAccessor();
        $entity = new ReadonlyPropertiesEntity();

        $this->assertThrows(\Error::class, function () use ($accessor, $entity): void {
            $accessor->setValue($entity, 'promoted', 'value', null);
        }, 'Cannot modify readonly property ' . ReadonlyPropertiesEntity::class . '::$promoted');
        $this->assertSame('constructor', $entity->promoted);
    }

    /**
     * Ting 3.13 cached what to do under another format (a virtual property taken for one with a set hook to bypass):
     * a persistent pool it filled is not read
     */
    #[RequiresPhp('>= 8.4.0')]
    public function testSetPropertyShouldNotReadWhatTing3CachedInAPersistentPool()
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem('write_property_' . rawurlencode(HookedPropertiesEntity::class . '..virtualSetOnly'));
        $cache->save($item->set(['public' => true, 'supportsHook' => true, 'hasSetHook' => true]));
        $accessor = new PropertyAccessor();
        $accessor->setCacheItemPool($cache);
        $entity = new HookedPropertiesEntity();
        $accessor->setValue($entity, 'virtualSetOnly', 'value', null);

        $this->assertSame('value (hooked on set)', $entity->hookSetOnly);
    }

    public function testSetPropertyShouldRespectSetter()
    {
        $accessor = new PropertyAccessor();
        $entity = $this->createEntitySpy();
        $accessor->setValue($entity, 'propertyWithSetter', 'value', 'setPropertyWithSetter');

        $this->assertEquals([['value']], $entity->calls['setPropertyWithSetter']);
    }

    public function testSetPropertyShouldFindSetter()
    {
        $accessor = new PropertyAccessor();
        $entity = $this->createEntitySpy();
        $accessor->setValue($entity, 'propertyWithSetter', 'value', null);

        $this->assertEquals([['value']], $entity->calls['setPropertyWithSetter']);
    }

    public function testGetPropertyShouldRespectGetter()
    {
        $accessor = new PropertyAccessor();
        $entity = $this->createEntitySpy();
        $accessor->getValue($entity, 'propertyWithGetter', 'getPropertyWithGetter');

        $this->assertSame([[]], $entity->calls['getPropertyWithGetter']);
    }

    public function testGetPropertyShouldFindGetter()
    {
        $accessor = new PropertyAccessor();
        $entity = $this->createEntitySpy();
        $accessor->getValue($entity, 'propertyWithGetter', null);

        $this->assertSame([[]], $entity->calls['getPropertyWithGetter']);
    }

    public function testGetPropertyShouldThrowOnUnitializedProperty()
    {
        $accessor = new PropertyAccessor();
        $entity = new PublicPropertiesEntity();

        $this->assertThrows(UninitializedPropertyException::class, function () use ($accessor, $entity): void {
            $accessor->getValue($entity, 'propertyWithSetter', null);
        });
    }

    public function testIsReadableShouldReturnTrueOnInitializedProperty()
    {
        $accessor = new PropertyAccessor();
        $entity = new PublicPropertiesEntity();

        $this->assertTrue($accessor->isReadable($entity, 'propertyWithDefaultValue', null));
    }

    public function testIsReadableShouldReturnFalseOnUnitializedProperty()
    {
        $accessor = new PropertyAccessor();
        $entity = new PublicPropertiesEntity();

        $this->assertFalse($accessor->isReadable($entity, 'propertyWithoutSetter', null));
    }

    public function testIsReadableShouldReturnTrueWhenAGetterIsDefined()
    {
        $accessor = new PropertyAccessor();
        $entity = new PublicPropertiesEntity();

        $this->assertTrue($accessor->isReadable($entity, 'propertyWithGetter', 'getPropertyWithGetter'));
    }

    public function testIsReadableShouldReturnFalseWhenGetterDoesNotExists()
    {
        $accessor = new PropertyAccessor();
        $entity = new PublicPropertiesEntity();

        $this->assertFalse($accessor->isReadable($entity, 'propertyWithGetter', 'wrongGetterName'));
    }

    public function testIsReadableShouldReturnFalseOnUninitializedPropertyWithACustomGetter()
    {
        $accessor = new PropertyAccessor();
        $entity = new CustomGetterEntity();

        $this->assertFalse($accessor->isReadable($entity, 'label', 'label'));

        $entity->setLabel('initialized');
        $this->assertTrue($accessor->isReadable($entity, 'label', 'label'));
    }

    public function testIsReadableShouldReturnFalseOnUninitializedPrivatePropertyOfTheParentClassWithACustomGetter()
    {
        $accessor = new PropertyAccessor();
        $entity = new class () extends CustomGetterEntity {
        };

        $this->assertFalse($accessor->isReadable($entity, 'label', 'label'));

        $entity->setLabel('initialized');
        $this->assertTrue($accessor->isReadable($entity, 'label', 'label'));
    }

    public function testIsReadableShouldReturnTrueOnUninitializedPropertyWithACustomGetterHandlingIt()
    {
        $accessor = new PropertyAccessor();
        $entity = new CustomGetterEntity();

        $this->assertTrue($accessor->isReadable($entity, 'label', 'labelOrDefault'));
        $this->assertSame('draft', $accessor->getValue($entity, 'label', 'labelOrDefault'));
    }

    public function testIsReadableShouldRethrowAnotherErrorOfACustomGetter()
    {
        $accessor = new PropertyAccessor();
        $entity = new class () {
            public function broken(): string
            {
                throw new \LogicException('Broken getter');
            }
        };

        $this->assertThrows(\LogicException::class, function () use ($accessor, $entity): void {
            $accessor->isReadable($entity, 'broken', 'broken');
        }, 'Broken getter');
    }

    public function testIsReadableShouldReturnTrueWithACustomGetterWithoutProperty()
    {
        $accessor = new PropertyAccessor();
        $entity = new class () {
            public function computed(): string
            {
                return 'computed';
            }
        };

        $this->assertTrue($accessor->isReadable($entity, 'computed', 'computed'));
    }

    public function testGetValueIfReadableShouldReadAsIsReadableAndGetValue()
    {
        $accessor = new PropertyAccessor();
        $entity = new PublicPropertiesEntity();
        $customGetter = new CustomGetterEntity();

        $this->assertSame([false, null], $accessor->getValueIfReadable($entity, 'propertyWithoutSetter', null));
        $this->assertSame([false, null], $accessor->getValueIfReadable($entity, 'missing', null));
        $this->assertSame([false, null], $accessor->getValueIfReadable($entity, 'propertyWithGetter', 'wrongGetterName'));
        $this->assertSame([false, null], $accessor->getValueIfReadable($customGetter, 'label', 'label'));
        $this->assertSame([true, 'draft'], $accessor->getValueIfReadable($customGetter, 'label', 'labelOrDefault'));
        $customGetter->setLabel('initialized');
        $this->assertSame([true, 'initialized'], $accessor->getValueIfReadable($customGetter, 'label', 'label'));
        $this->assertSame(
            [true, $accessor->getValue($entity, 'propertyWithDefaultValue')],
            $accessor->getValueIfReadable($entity, 'propertyWithDefaultValue', null)
        );
    }

    public function testGetValueIfReadableShouldRethrowAnotherErrorOfACustomGetter()
    {
        $accessor = new PropertyAccessor();
        $entity = new class () {
            public function broken(): string
            {
                throw new \LogicException('Broken getter');
            }
        };

        $this->assertThrows(\LogicException::class, function () use ($accessor, $entity): void {
            $accessor->getValueIfReadable($entity, 'broken', 'broken');
        }, 'Broken getter');
    }

    public function testIsWritableShouldReturnTrueWhenASetterIsDefined()
    {
        $accessor = new PropertyAccessor();
        $entity = new PublicPropertiesEntity();

        $this->assertTrue($accessor->isWritable($entity, 'propertyWithSetter', 'setPropertyWithDefaultValue'));
    }

    public function testIsWritableShouldReturnFalseWhenSetterDoesNotExists()
    {
        $accessor = new PropertyAccessor();
        $entity = new PublicPropertiesEntity();

        $this->assertFalse($accessor->isWritable($entity, 'propertyWithSetter', 'wrongSetterName'));
    }

    public function testAnArrayShouldBeNeitherReadableNorWritableThroughAMethod()
    {
        $accessor = new PropertyAccessor();

        $this->assertFalse($accessor->isWritable(['name' => 'Xavier'], '[name]', 'setName'));
        $this->assertFalse($accessor->isReadable(['name' => 'Xavier'], '[name]', 'getName'));
    }

    public function testPropertyAccessorCanLeverageInternalCache()
    {
        $cache = $this->createArrayAdapterSpy();
        $accessor = new PropertyAccessor();
        $accessor->setCacheItemPool($cache);
        $entity = new PublicPropertiesEntity();
        $accessor->setValue($entity, 'propertyWithDefaultValue', 'value1', null);
        $accessor->setValue($entity, 'propertyWithDefaultValue', 'value2', null);

        $this->assertSame(1, $cache->getItemCalls);
    }

    public function testPropertyAccessorCanLeverageExternalCache()
    {
        $cache = $this->createArrayAdapterSpy();
        $accessorFirst = new PropertyAccessor();
        $accessorFirst->setCacheItemPool($cache);
        $accessorSecond = new PropertyAccessor();
        $accessorSecond->setCacheItemPool($cache);
        $entity = new PublicPropertiesEntity();
        $accessorFirst->setValue($entity, 'propertyWithDefaultValue', 'value1', null);
        $accessorSecond->setValue($entity, 'propertyWithDefaultValue', 'value2', null);

        $this->assertSame(2, $cache->getItemCalls);
    }

    public function testPropertyAccessorCanLeverageExternalCacheOncePerProperty()
    {
        $cache = $this->createArrayAdapterSpy();
        $accessorFirst = new PropertyAccessor();
        $accessorFirst->setCacheItemPool($cache);
        $accessorFirst->setValue(new PublicPropertiesEntity(), 'propertyWithDefaultValue', 'value', null);

        // A cache hit is kept in memory, as a miss is
        $accessorSecond = new PropertyAccessor();
        $accessorSecond->setCacheItemPool($cache);
        for ($i = 0; $i < 3; $i++) {
            $accessorSecond->setValue(new PublicPropertiesEntity(), 'propertyWithDefaultValue', 'value', null);
        }

        $this->assertSame(2, $cache->getItemCalls);
    }

    public function testGetRawValueShouldReadThePropertyItselfWhateverItsVisibility()
    {
        $accessor = new PropertyAccessor();
        $entity = new TrackedChild();
        $entity->setOwner('owner');
        $entity->setTitle('title');
        $public = new PublicPropertiesEntity();
        $public->propertyWithoutSetter = 'value';

        // Private in the parent class, private in the class, public, private with a getter
        $this->assertSame([true, 'owner'], $accessor->getRawValue($entity, 'owner'));
        $this->assertSame([true, 'title'], $accessor->getRawValue($entity, 'title'));
        $this->assertSame([true, 'value'], $accessor->getRawValue($public, 'propertyWithoutSetter'));
        $this->assertSame([true, 'with getter'], $accessor->getRawValue($public, 'propertyWithGetter'));
        // Protected
        $this->assertSame([true, []], $accessor->getRawValue($entity, 'listeners'));
    }

    public function testGetRawValueShouldNotCallTheGetter()
    {
        $accessor = new PropertyAccessor();
        $entity = new class () {
            protected ?int $id = null;

            public function getId(): int
            {
                return $this->id ?? throw new \LogicException('Not generated yet');
            }
        };

        $this->assertSame([true, null], $accessor->getRawValue($entity, 'id'));
    }

    public function testGetRawValueShouldTellAPropertyNotInitializedOrMissing()
    {
        $accessor = new PropertyAccessor();
        $entity = new PublicPropertiesEntity();

        $this->assertSame([false, null], $accessor->getRawValue($entity, 'propertyWithSetter'));
        $this->assertSame([false, null], $accessor->getRawValue($entity, 'missing'));
    }

    #[RequiresPhp('>= 8.4.0')]
    public function testGetRawValueShouldBypassAGetHook()
    {
        $accessor = new PropertyAccessor();
        $entity = new HookedPropertiesEntity();

        $this->assertSame([true, 'default'], $accessor->getRawValue($entity, 'hookGetOnly'));
        // Virtual: no value
        $this->assertSame([false, null], $accessor->getRawValue($entity, 'virtualSetOnly'));
    }

    public function testSetPropertyShouldWriteAPrivatePropertyOfTheParentClassThroughItsSetter()
    {
        $accessor = new PropertyAccessor();
        $entity = new TrackedChild();

        $accessor->setValue($entity, 'owner', 'owner', null);

        $this->assertSame('owner', $entity->getOwner());
    }

    public function testSetPropertyShouldWriteAPropertyThatOnlyExistsThroughItsSetter()
    {
        $accessor = new PropertyAccessor();
        $accessor->setCacheItemPool(new ArrayAdapter());
        $entity = new class () {
            private array $data = [];

            public function setNickname(string $nickname): void
            {
                $this->data['nickname'] = $nickname;
            }

            public function getNickname(): ?string
            {
                return $this->data['nickname'] ?? null;
            }
        };

        $accessor->setValue($entity, 'nickname', 'nick', null);
        $accessor->setValue($entity, 'nickname', 'nick 2', null);

        $this->assertSame('nick 2', $entity->getNickname());
    }

    /**
     * Spy: records accessor calls while keeping the real implementation
     */
    private function createEntitySpy(): PublicPropertiesEntity
    {
        return new class () extends PublicPropertiesEntity {
            /** @var array<string, list<array<mixed>>> */
            public array $calls = ['setPropertyWithSetter' => [], 'getPropertyWithGetter' => []];

            public function setPropertyWithSetter(string $propertyWithSetter)
            {
                $this->calls['setPropertyWithSetter'][] = func_get_args();
                return parent::setPropertyWithSetter($propertyWithSetter);
            }

            public function getPropertyWithGetter(): string
            {
                $this->calls['getPropertyWithGetter'][] = func_get_args();
                return parent::getPropertyWithGetter();
            }
        };
    }

    /**
     * Spy: counts getItem calls while keeping the real implementation
     */
    private function createArrayAdapterSpy(): ArrayAdapter
    {
        return new class () extends ArrayAdapter {
            public int $getItemCalls = 0;

            public function getItem(mixed $key): CacheItem
            {
                $this->getItemCalls++;
                return parent::getItem($key);
            }
        };
    }

    /**
     * The setter is called directly: a value it refuses is reported as the Symfony property accessor reports it,
     * without running the setter
     */
    public function testSetPropertyShouldReportAValueTheSetterRefusesAsTheSymfonyAccessor()
    {
        $accessor = new PropertyAccessor();
        $entity = new TypedMutatorsEntity();

        $accessor->setValue($entity, 'age', 7, null);
        $this->assertThrows(
            InvalidTypeException::class,
            fn () => $accessor->setValue($entity, 'age', 'seven', null),
            'Expected argument of type "int", "string" given at property path "age".'
        );

        $this->assertSame(7, $entity->getAge());
        $this->assertSame(1, $entity->setAgeCalls);
    }

    /**
     * As the Symfony property accessor, a DateTimeImmutable is given as a DateTime to a setter of a DateTime (and the
     * other way round)
     */
    public function testSetPropertyShouldConvertADateForTheSetterAsTheSymfonyAccessor()
    {
        $accessor = new PropertyAccessor();
        $entity = new TypedMutatorsEntity();

        $accessor->setValue($entity, 'created', new \DateTimeImmutable('2026-10-16 12:00:00'), null);

        $this->assertInstanceOf(\DateTime::class, $entity->createdAt);
        $this->assertSame('2026-10-16 12:00:00', $entity->createdAt->format('Y-m-d H:i:s'));
    }

    /**
     * A TypeError thrown by the code of the setter is not a value refused: thrown as it is, the setter run once
     */
    public function testSetPropertyShouldRethrowATypeErrorOfTheSetterWithoutRunningItAgain()
    {
        $accessor = new PropertyAccessor();
        $entity = new class () {
            public int $calls = 0;

            public function setLabel(string $label): void
            {
                $this->calls++;
                strlen([]); // @phpstan-ignore argument.type
            }
        };

        $this->assertThrows(
            \TypeError::class,
            fn () => $accessor->setValue($entity, 'label', 'value', null),
            'strlen(): Argument #1 ($string) must be of type string, array given'
        );
        $this->assertSame(1, $entity->calls);
    }

    /**
     * A public property is assigned directly: a value of another type is reported as the Symfony property accessor
     * reports it, a date converted as it converts it
     */
    public function testSetPropertyShouldWriteAPublicPropertyAsTheSymfonyAccessor()
    {
        $accessor = new PropertyAccessor();
        $entity = new TypedMutatorsEntity();

        $accessor->setValue($entity, 'count', 3, null);
        $this->assertSame(3, $entity->count);

        $this->assertThrows(
            InvalidTypeException::class,
            fn () => $accessor->setValue($entity, 'count', 'three', null),
            'Expected argument of type "int", "string" given at property path "count".'
        );
        $this->assertSame(3, $entity->count);

        $accessor->setValue($entity, 'date', new \DateTimeImmutable('2026-10-16 12:00:00'), null);
        $this->assertInstanceOf(\DateTime::class, $entity->date);
    }

    /**
     * An iterable value goes through the adder and the remover, as the Symfony property accessor writes it; another
     * value of the same property through the setter
     */
    public function testSetPropertyShouldWriteAnIterableValueThroughTheAdderAndRemover()
    {
        $accessor = new PropertyAccessor();
        $entity = new class () {
            /** @var list<string> */
            public array $calls = [];
            /** @var list<string> */
            private array $tags = [];

            public function getTags(): array
            {
                return $this->tags;
            }

            public function setTags(array|string $tags): void
            {
                $this->calls[] = 'setTags';
                $this->tags = (array) $tags;
            }

            public function addTag(string $tag): void
            {
                $this->calls[] = 'addTag';
                $this->tags[] = $tag;
            }

            public function removeTag(string $tag): void
            {
                $this->calls[] = 'removeTag';
            }
        };

        $accessor->setValue($entity, 'tags', 'one', null);
        $accessor->setValue($entity, 'tags', ['one', 'two'], null);

        $this->assertSame(['setTags', 'addTag'], $entity->calls);
        $this->assertSame(['one', 'two'], $entity->getTags());
    }

    /**
     * A property written through __set(): the Symfony property accessor writes it, as before
     */
    public function testSetPropertyShouldWriteThroughTheMagicSetter()
    {
        $accessor = new PropertyAccessor();
        $entity = new class () {
            /** @var array<string, mixed> */
            private array $data = [];

            public function __set(string $name, mixed $value): void
            {
                $this->data[$name] = 'magic:' . $value;
            }

            public function __get(string $name): mixed
            {
                return $this->data[$name] ?? null;
            }
        };

        $accessor->setValue($entity, 'name', 'bob', null);

        $this->assertSame('magic:bob', $entity->name);
    }

    /**
     * A private property without setter cannot be written: reported by the Symfony property accessor, as before
     */
    public function testSetPropertyShouldReportAPropertyItCannotWrite()
    {
        $accessor = new PropertyAccessor();
        $entity = new class () {
            private string $secret = '';
        };

        $this->assertThrows(
            NoSuchPropertyException::class,
            fn () => $accessor->setValue($entity, 'secret', 'value', null)
        );
    }

    #[RequiresPhp('>= 8.4.0')]
    public function testSetPropertyShouldConvertADateForTheSetHookOfAVirtualProperty()
    {
        $accessor = new PropertyAccessor();
        $entity = new VirtualDateHookEntity();

        $accessor->setValue($entity, 'createdAt', new \DateTime('2026-10-16 12:00:00'), null);

        $this->assertInstanceOf(\DateTimeImmutable::class, $entity->createdAt);
        $this->assertSame(1, $entity->calls);
    }

    public function testSetPropertyShouldConvertADateForASetterDeclaredInAnotherCase()
    {
        $accessor = new PropertyAccessor();
        $entity = new TypedMutatorsEntity();

        $accessor->setValue($entity, 'updatedAt', new \DateTimeImmutable('2026-10-16 12:00:00'), null);

        $this->assertInstanceOf(\DateTime::class, $entity->createdAt);
    }

    /**
     * A TypeError thrown by the setter itself, or by its return type, ran its code: rethrown without running it again
     */
    public function testSetPropertyShouldNotRunASetterAgainAfterItsOwnTypeError()
    {
        $accessor = new PropertyAccessor();
        $entity = new TypedMutatorsEntity();

        $this->assertThrows(
            \TypeError::class,
            fn () => $accessor->setValue($entity, 'failing', 'value', null),
            'Refused by the setter itself'
        );
        $this->assertSame(1, $entity->setAgeCalls);

        $this->assertThrows(\TypeError::class, fn () => $accessor->setValue($entity, 'badReturn', 'value', null));
        $this->assertSame(2, $entity->setAgeCalls);
    }
}
