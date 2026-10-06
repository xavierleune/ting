<?php

namespace CCMBenchmark\Ting\Serializer;

use InvalidArgumentException;

/**
 * @phpstan-import-type SerializerOptions from SerializeInterface
 */
class Uuid implements SerializerInterface
{
    public function serialize(mixed $toSerialize, array $options = []): ?string
    {
        if ($toSerialize === null) {
            return null;
        }
        if (!($toSerialize instanceof \Symfony\Component\Uid\Uuid)) {
            throw new RuntimeException('UUID has to be an instance of \Symfony\Component\Uid\Uuid . ' . gettype($toSerialize) . ' given.');
        }

        return $toSerialize->toRfc4122();
    }

    /**
     * @param string|null $serialized
     * @param SerializerOptions $options unused
     * @return mixed
     */
    public function unserialize($serialized, array $options = []): mixed
    {
        if ($serialized === null) {
            return null;
        }
        try {
            return \Symfony\Component\Uid\Uuid::fromString($serialized);
        } catch (InvalidArgumentException) {
            throw new RuntimeException('Cannot convert ' . $serialized . ' to UUID.');
        }
    }
}
