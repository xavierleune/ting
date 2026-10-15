<?php

namespace CCMBenchmark\Ting\Util;

use ReflectionProperty;
use PropertyHookType;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\PropertyAccess\Exception\AccessException;
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
            // We use it only if there is a hook we want to bypass (a virtual property has no value of its own: it is
            // written through its set hook, by the property accessor)
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
     * getValue() of a property that isReadable(), reading it once instead of twice
     *
     * @return array{0: bool, 1: mixed} [false, null] when the property is not readable (see isReadable()), [true, its
     *                                  value] otherwise
     */
    public function getValueIfReadable(object $object, string $propertyPath, ?string $getter): array
    {
        try {
            if ($getter !== null) {
                if (method_exists($object, $getter) === false) {
                    return [false, null];
                }

                return [true, $object->$getter()];
            }

            return [true, $this->propertyAccessor->getValue($object, $propertyPath)];
        } catch (AccessException) {
            // No such property, or a typed property not initialized
            return [false, null];
        } catch (\Error $error) {
            if (str_contains($error->getMessage(), 'must not be accessed before initialization')) {
                return [false, null];
            }

            throw $error;
        }
    }

    /**
     * The value held by the property itself, whatever its visibility: neither its getter nor its get hook is called
     *
     * @return array{0: bool, 1: mixed} [false, null] when the property does not exist, is not initialized or is
     *                                  virtual (hooks without a value of its own), [true, its value] otherwise
     */
    public function getRawValue(object $object, string $property): array
    {
        // Mangled names, as serialize() writes them: the hooks are bypassed, an uninitialized property is left out
        $properties = get_mangled_object_vars($object);
        foreach ([$property, "\0*\0" . $property] as $key) {
            if (\array_key_exists($key, $properties)) {
                return [true, $properties[$key]];
            }
        }

        // Private, in the class or one of its parents (the closest one first)
        for ($class = $object::class; $class !== false; $class = get_parent_class($class)) {
            $key = "\0" . $class . "\0" . $property;
            if (\array_key_exists($key, $properties)) {
                return [true, $properties[$key]];
            }
        }

        return [false, null];
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
                // A set hook to bypass: not that of a virtual property, which has no value of its own
                'hasSetHook' => \PHP_VERSION_ID >= 80400
                    && $reflection->getHook(PropertyHookType::Set) !== null // @phpstan-ignore method.notFound, class.notFound
                    && $reflection->isVirtual() === false, // @phpstan-ignore method.notFound
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