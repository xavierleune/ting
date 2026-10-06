<?php

namespace CCMBenchmark\Ting\Serializer;

use BackedEnum as T;

class BackedEnum implements SerializerInterface
{
    /**
     * @inheritDoc
     */
    public function serialize($toSerialize, array $options = []): ?string
    {
        if ($toSerialize === null) {
            return null;
        }
        if (!is_object($toSerialize)) {
            throw new RuntimeException('BackedEnumSerializer can only serialize objects');
        }
        if (!enum_exists($toSerialize::class)) {
            throw new RuntimeException('BackedEnumSerializer can only serialize enums');
        }

        // A string, as in 3.x: an int-backed enum is stored like its other values
        /** @var T $toSerialize */
        return (string) $toSerialize->value;
    }

    /**
     * @template T of \BackedEnum
     * @param string|null $serialized
     * @param array{'enum'?: class-string<T>} $options
     * @return null|T
     */
    public function unserialize($serialized, array $options = []): ?\BackedEnum
    {
        if ($serialized === null) {
            return null;
        }
        if (!isset($options['enum'])) {
            throw new RuntimeException('BackedEnumSerializer requires an enum class name');
        }
        if (!enum_exists($options['enum'])) {
            throw new RuntimeException('Invalid enum class given to BackedEnumSerializer');
        }
        $enum = $options['enum'];
        if ((string) (new \ReflectionEnum($enum))->getBackingType() === 'int') {
            $serialized = $this->toInt($serialized);
        }
        try {
            return $enum::from($serialized);
        } catch (\ValueError | \TypeError) {
            throw new RuntimeException('Invalid enum value given to BackedEnumSerializer');
        }
    }

    /**
     * from() would cast '1.5' to 1 (with a deprecation) and throw a TypeError on 'abc': only an integer, or a
     * string holding one, is a value of an int-backed enum
     */
    private function toInt(mixed $serialized): int
    {
        if (is_int($serialized)) {
            return $serialized;
        }
        if (is_string($serialized) && preg_match('/^([+-]?)0*(\d+)$/', $serialized, $matches) === 1) {
            // false beyond PHP_INT_MAX / PHP_INT_MIN
            $value = filter_var(($matches[1] === '-' ? '-' : '') . $matches[2], FILTER_VALIDATE_INT);
            if ($value !== false) {
                return $value;
            }
        }

        throw new RuntimeException('Invalid enum value given to BackedEnumSerializer');
    }
}
