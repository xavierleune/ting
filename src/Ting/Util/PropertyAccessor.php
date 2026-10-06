<?php

namespace CCMBenchmark\Ting\Util;

use ReflectionProperty;
use PropertyHookType;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

class PropertyAccessor
{
    /** @var array<string, array{public: bool, supportsHook: bool, hasSetHook: bool}> by class and property name */
    private array $reflectionData = [];
    /** @var array<string, ReflectionProperty> by class and property name */
    private array $reflectionProperties = [];
    private const CACHE_PREFIX_WRITE = 'write_property_';
    private readonly PropertyAccessorInterface $propertyAccessor;
    private ?CacheItemPoolInterface $cacheItemPool = null;

    public function __construct() {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function setCacheItemPool(?CacheItemPoolInterface $cacheItemPool): void
    {
        $this->cacheItemPool = $cacheItemPool;
    }
    
    public function setValue(object $object, PropertyPathInterface|string $propertyPath, mixed $value, ?string $setter = null): void
    {
        if ($setter !== null) {
            $object->$setter($value);
            return;
        }

        $reflectionData = $this->getReflectionData($object, $propertyPath);

        if ($reflectionData['public'] && $reflectionData['supportsHook'] && $reflectionData['hasSetHook']) {
            // SetRawValue is 8.4+ only.
            // We use it only if there is a hook we want to bypass
            // In any other case we let the property accessor
            $reflectionProperty = $this->getReflectionProperty($object, $propertyPath);
            $reflectionProperty->setRawValue($object, $value); // @phpstan-ignore method.notFound
            return;
        }
        $this->propertyAccessor->setValue($object, $propertyPath, $value);
    }

    public function getValue(object $object, PropertyPathInterface|string $propertyPath, ?string $getter = null): mixed
    {
        if ($getter !== null) {
            return $object->$getter();
        }
        return $this->propertyAccessor->getValue($object, $propertyPath);
    }

    /**
     * @param object|array<mixed> $objectOrArray
     */
    public function isWritable(object|array $objectOrArray, PropertyPathInterface|string $propertyPath, ?string $setter): bool
    {
        if ($setter !== null) {
            return \is_object($objectOrArray) && method_exists($objectOrArray, $setter);
        }
        return $this->propertyAccessor->isWritable($objectOrArray, $propertyPath);
    }

    /**
     * A custom getter is called: like a conventional getter, it is not readable when it reads a typed property not
     * initialized yet ("must not be accessed before initialization"), and readable when it handles that case itself
     * (`return $this->status ?? 'draft';`). Any other error of the getter is rethrown.
     *
     * @param object|array<mixed> $objectOrArray
     */
    public function isReadable(object|array $objectOrArray, PropertyPathInterface|string $propertyPath, ?string $getter): bool
    {
        if ($getter !== null) {
            if (\is_object($objectOrArray) === false || method_exists($objectOrArray, $getter) === false) {
                return false;
            }

            try {
                $objectOrArray->$getter();
            } catch (\Error $error) {
                if (str_contains($error->getMessage(), 'must not be accessed before initialization')) {
                    return false;
                }

                throw $error;
            }

            return true;
        }
        return $this->propertyAccessor->isReadable($objectOrArray, $propertyPath);
    }

    /**
     * @param object $object
     * @param string $propertyPath
     * @return array{'public': bool, 'supportsHook': bool, 'hasSetHook': bool}
     * @throws InvalidArgumentException
     */
    private function getReflectionData(object $object, string $propertyPath): array
    {
        $key = $object::class.'..'.$propertyPath;
        if (isset($this->reflectionData[$key])) {
            return $this->reflectionData[$key];
        }

        $cacheItemPool = $this->cacheItemPool;
        $item = null;
        if ($cacheItemPool instanceof CacheItemPoolInterface) {
            $item = $cacheItemPool->getItem(self::CACHE_PREFIX_WRITE.rawurlencode($key));
            if ($item->isHit()) {
                return $this->reflectionData[$key] = $item->get();
            }
        }

        try {
            $reflection = new ReflectionProperty($object, $propertyPath);
            $data = [
                'public' => $reflection->isPublic(),
                'supportsHook' => \PHP_VERSION_ID >= 80400,
                'hasSetHook' => \PHP_VERSION_ID >= 80400 && $reflection->getHook(PropertyHookType::Set) !== null, // @phpstan-ignore method.notFound, class.notFound
            ];
        } catch (ReflectionException) {
            // Private in a parent class, or only reachable through a setter: no hook to bypass, Symfony writes it
            $data = ['public' => false, 'supportsHook' => \PHP_VERSION_ID >= 80400, 'hasSetHook' => false];
        }

        if ($cacheItemPool instanceof CacheItemPoolInterface && $item !== null) {
            $cacheItemPool->save($item->set($data));
        }
        
        return $this->reflectionData[$key] = $data;
    }
    
    private function getReflectionProperty(object $object, string $propertyPath): ReflectionProperty
    {
        $key = $object::class.'..'.$propertyPath;
        
        return $this->reflectionProperties[$key] ?? $this->reflectionProperties[$key] = new ReflectionProperty($object, $propertyPath);
    }
}