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
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use tests\fixtures\model\CustomGetterEntity;
use tests\fixtures\model\HookedPropertiesEntity;
use tests\fixtures\model\PublicPropertiesEntity;
use tests\fixtures\model\TrackedChild;

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
}
