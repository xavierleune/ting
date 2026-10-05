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
        try {
            return $enum::from($serialized);
        } catch (\ValueError) {
            throw new RuntimeException('Invalid enum value given to BackedEnumSerializer');
        }
    }
}
