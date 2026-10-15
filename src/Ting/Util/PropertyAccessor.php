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
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyWriteInfo;

class PropertyAccessor
{
    /** Written by the Symfony property accessor (a setter, a public property, a virtual one through its set hook) */
    private const WRITE_ACCESSOR = 'accessor';
    /** Its own value written by reflection, without its set hook (PHP 8.4+) */
    private const WRITE_RAW = 'raw';
    /** Written by reflection, through its set hook if any: the Symfony property accessor is not allowed to */
    private const WRITE_REFLECTION = 'reflection';
    /** An iterable value written by the Symfony property accessor (its adder and remover), any other as WRITE_RAW */
    private const WRITE_ITERABLE_ACCESSOR_ELSE_RAW = 'iterable_accessor_else_raw';
    /** An iterable value written by the Symfony property accessor (its adder and remover), any other as WRITE_REFLECTION */
    private const WRITE_ITERABLE_ACCESSOR_ELSE_REFLECTION = 'iterable_accessor_else_reflection';
    private const ALL_WRITE_MODES = [
        self::WRITE_ACCESSOR,
        self::WRITE_RAW,
        self::WRITE_REFLECTION,
        self::WRITE_ITERABLE_ACCESSOR_ELSE_RAW,
        self::WRITE_ITERABLE_ACCESSOR_ELSE_REFLECTION,
    ];
    /**
     * The format of the items cached changed since Ting 3.x (whose prefix was write_property_), and what they hold
     * depends on the PHP version (property hooks, asymmetric visibility): a persistent pool filled by another version
     * is not read
     */
    private const CACHE_PREFIX_WRITE = 'write_property_v2_' . \PHP_MAJOR_VERSION . '.' . \PHP_MINOR_VERSION . '_';

    /** @var array<string, self::WRITE_*> by class and property name, how to write the property */
    private array $writeModes = [];
    /** @var array<string, ReflectionProperty> by class and property name */
    private array $reflectionProperties = [];
    private readonly PropertyAccessorInterface $propertyAccessor;
    private ?CacheItemPoolInterface $cacheItemPool = null;
    private ?ReflectionExtractor $writeInfoExtractor = null;

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

        $mode = $this->getWriteMode($object, (string) $propertyPath);
        if ($mode === self::WRITE_ITERABLE_ACCESSOR_ELSE_RAW || $mode === self::WRITE_ITERABLE_ACCESSOR_ELSE_REFLECTION) {
            // The Symfony property accessor only uses an adder and a remover for an iterable value
            if (is_iterable($value)) {
                $mode = self::WRITE_ACCESSOR;
            } else {
                $mode = $mode === self::WRITE_ITERABLE_ACCESSOR_ELSE_RAW ? self::WRITE_RAW : self::WRITE_REFLECTION;
            }
        }

        switch ($mode) {
            case self::WRITE_RAW:
                $this->getReflectionProperty($object, (string) $propertyPath)->setRawValue($object, $value); // @phpstan-ignore method.notFound
                return;
            case self::WRITE_REFLECTION:
                $this->getReflectionProperty($object, (string) $propertyPath)->setValue($object, $value);
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
     * How to write the property, without setter option:
     * - a public property with a set hook and a value of its own (PHP 8.4+): its value is written without the hook;
     * - a public property only the class can write (readonly, private(set) or protected(set)): by the Symfony property
     *   accessor through its setter or getter-setter, through its adder and remover for an iterable value; otherwise by
     *   reflection, its own value without its set hook (PHP 8.4+), a virtual one through its set hook;
     * - any other (a setter, a public property, a virtual one through its set hook): by the Symfony property accessor
     *
     * @return self::WRITE_*
     * @throws InvalidArgumentException
     */
    private function getWriteMode(object $object, string $propertyPath): string
    {
        $key = $object::class.'..'.$propertyPath;
        if (isset($this->writeModes[$key])) {
            return $this->writeModes[$key];
        }

        $cacheItemPool = $this->cacheItemPool;
        $item = null;
        if ($cacheItemPool instanceof CacheItemPoolInterface) {
            $item = $cacheItemPool->getItem(self::CACHE_PREFIX_WRITE.rawurlencode($key));
            $mode = $item->get();
            if ($item->isHit() && \in_array($mode, self::ALL_WRITE_MODES, true)) {
                return $this->writeModes[$key] = $mode;
            }
        }

        $mode = self::WRITE_ACCESSOR;
        try {
            $reflection = new ReflectionProperty($object, $propertyPath);
            if ($reflection->isPublic()) {
                $supportsHooks = \PHP_VERSION_ID >= 80400;
                $virtual = $supportsHooks && $reflection->isVirtual(); // @phpstan-ignore method.notFound
                if ($supportsHooks && $virtual === false
                    && $reflection->getHook(PropertyHookType::Set) !== null // @phpstan-ignore method.notFound, class.notFound
                ) {
                    // A set hook to bypass: not that of a virtual property, which has no value of its own
                    $mode = self::WRITE_RAW;
                } elseif (($reflection->isReadOnly()
                        || ($supportsHooks && ($reflection->isPrivateSet() || $reflection->isProtectedSet()))) // @phpstan-ignore method.notFound, method.notFound
                ) {
                    // Only the class can write it: by reflection, unless the Symfony property accessor has a method to
                    $byReflection = $supportsHooks && $virtual === false ? self::WRITE_RAW : self::WRITE_REFLECTION;
                    $mode = match ($this->getSymfonyMutator($object, $propertyPath)) {
                        PropertyWriteInfo::TYPE_METHOD => self::WRITE_ACCESSOR,
                        PropertyWriteInfo::TYPE_ADDER_AND_REMOVER => $byReflection === self::WRITE_RAW
                            ? self::WRITE_ITERABLE_ACCESSOR_ELSE_RAW
                            : self::WRITE_ITERABLE_ACCESSOR_ELSE_REFLECTION,
                        default => $byReflection,
                    };
                }
            }
        } catch (ReflectionException) {
            // Private in a parent class, or only reachable through a setter: no hook to bypass, Symfony writes it
        }

        if ($cacheItemPool instanceof CacheItemPoolInterface && $item !== null) {
            $cacheItemPool->save($item->set($mode));
        }

        return $this->writeModes[$key] = $mode;
    }

    /**
     * The method the Symfony property accessor writes the property through, found as it finds them: a setter or a
     * getter-setter (PropertyWriteInfo::TYPE_METHOD), else an adder and a remover, used for an iterable value only
     * (PropertyWriteInfo::TYPE_ADDER_AND_REMOVER); null without such public method. The accessor itself is not asked:
     * some versions (7.0) report writable a public property only its class can write, then fail to write it
     */
    private function getSymfonyMutator(object $object, string $propertyPath): ?string
    {
        $this->writeInfoExtractor ??= new ReflectionExtractor(['set'], null, null, false);
        foreach ([false, true] as $adderAndRemover) {
            $writeInfo = $this->writeInfoExtractor->getWriteInfo($object::class, $propertyPath, [
                'enable_getter_setter_extraction' => true,
                'enable_magic_methods_extraction' => 0,
                'enable_constructor_extraction' => false,
                'enable_adder_remover_extraction' => $adderAndRemover,
            ]);
            $public = match ($writeInfo?->getType()) {
                PropertyWriteInfo::TYPE_METHOD => $writeInfo->getVisibility() === PropertyWriteInfo::VISIBILITY_PUBLIC
                    && $writeInfo->isStatic() === false,
                PropertyWriteInfo::TYPE_ADDER_AND_REMOVER =>
                    $writeInfo->getAdderInfo()->getVisibility() === PropertyWriteInfo::VISIBILITY_PUBLIC
                    && $writeInfo->getRemoverInfo()->getVisibility() === PropertyWriteInfo::VISIBILITY_PUBLIC,
                default => false,
            };
            if ($public && $writeInfo !== null) {
                return $writeInfo->getType();
            }
        }

        return null;
    }

    /**
     * The property of the class declaring it: written in the scope of that class (a property private(set), or readonly
     * before PHP 8.4, declared by a parent class cannot be written from the scope of the class of the object)
     */
    private function getReflectionProperty(object $object, string $propertyPath): ReflectionProperty
    {
        $key = $object::class.'..'.$propertyPath;
        if (isset($this->reflectionProperties[$key]) === false) {
            $declaringClass = (new ReflectionProperty($object, $propertyPath))->getDeclaringClass()->getName();
            $this->reflectionProperties[$key] = new ReflectionProperty($declaringClass, $propertyPath);
        }

        return $this->reflectionProperties[$key];
    }
}