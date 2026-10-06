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

namespace CCMBenchmark\Ting\Repository;

use CCMBenchmark\Ting\Exceptions\HydratorException;
use Generator;
use Throwable;

use function get_debug_type;
use function is_int;
use function is_string;
use function serialize;

/**
 * @template T type of the items, as built by the callable given to callableFinalizeAggregate(): the first row of
 *             each group with the "aggregate" key without one
 *
 * @template-extends Hydrator<T>
 */
class HydratorAggregator extends Hydrator
{
    /**
     * @var callable
     */
    protected $callableForId;

    /**
     * @var callable
     */
    protected $callableForData;

    /**
     * @var callable
     */
    protected $callableFinalizeAggregate;

    /**
     * @param callable $callableForId returns the group identifier of a row, never null: an int or a string, or any
     *                                serializable value (a Uuid object, an array for a composite identifier, a
     *                                float…), compared by value
     * @return $this
     */
    public function callableIdIs(callable $callableForId): static
    {
        $this->callableForId = $callableForId;
        return $this;
    }

    /**
     * @param callable $callableForData
     * @return $this
     */
    public function callableDataIs(callable $callableForData): static
    {
        $this->callableForData = $callableForData;
        return $this;
    }

    /**
     * @param callable $callableFinalizeAggregate
     * @return $this
     */
    public function callableFinalizeAggregate(callable $callableFinalizeAggregate): static
    {
        $this->callableFinalizeAggregate = $callableFinalizeAggregate;
        return $this;
    }

    /**
     * @return Generator<int, mixed> what the callable given to callableFinalizeAggregate() returns
     *
     * @throws HydratorException when the callable given to callableIdIs() returns null or a value which cannot be
     *                            serialized
     */
    public function getIterator(): Generator
    {
        $knownIdentifiers = [];
        $callableForId = $this->callableForId;
        $callableForData = $this->callableForData;
        // The group in progress: its identifier, then the key and the hydrated first row it is yielded with
        $group = null;
        $aggregate = [];

        foreach ($this->hydratedRows() as $key => $result) {
            $currentId = $callableForId($result);
            if ($currentId === null) {
                // null also marks "no group yet": every row would be lost
                throw new HydratorException(
                    'The callable given to HydratorAggregator::callableIdIs() returned null for row ' . $key
                    . ': a group identifier is required for each row'
                );
            }

            $currentId = $this->groupKey($currentId, $key);

            if (isset($knownIdentifiers[$currentId])) {
                continue;
            }

            if ($group === null) {
                $group = ['id' => $currentId, 'key' => $key, 'result' => $result];
            }

            if ($group['id'] === $currentId) {
                $aggregate[] = $callableForData($result);
            } else {
                $knownIdentifiers[$group['id']] = true;

                yield $group['key'] => $this->finalizeAggregate($group['result'], $aggregate);

                $aggregate = [$callableForData($result)];
                $group = ['id' => $currentId, 'key' => $key, 'result' => $result];
            }
        }

        // The pending group, built from its first row like the others (even when the last row was skipped)
        if ($group !== null) {
            yield $group['key'] => $this->finalizeAggregate($group['result'], $aggregate);
        }
    }

    /**
     * Returns the key of the group of $identifier: the identifier itself for an int or a string, its serialized form
     * otherwise, so that an object (a Uuid) or an array (a composite identifier) is compared by value and a float is
     * not truncated. A serialized form is prefixed with a NUL byte to set it apart from the string identifiers.
     *
     * @throws HydratorException when $identifier cannot be serialized
     */
    private function groupKey(mixed $identifier, int|string $rowKey): int|string
    {
        if (is_int($identifier) || is_string($identifier)) {
            return $identifier;
        }

        try {
            return "\0" . serialize($identifier);
        } catch (Throwable $exception) {
            throw new HydratorException(
                'The callable given to HydratorAggregator::callableIdIs() returned a ' . get_debug_type($identifier)
                . ' for row ' . $rowKey . ', which cannot be serialized: return an int, a string or a serializable'
                . ' value',
                0,
                $exception
            );
        }
    }

    /**
     * @param array<int|string, object|null> $result the first row of the group
     * @param mixed $aggregate
     *
     * @return mixed
     */
    private function finalizeAggregate(array $result, mixed $aggregate): mixed
    {
        if ($this->callableFinalizeAggregate === null) {
            $result['aggregate'] = $aggregate;
            return $result;
        }

        $callableFinalizeAggregate = $this->callableFinalizeAggregate;
        return $callableFinalizeAggregate($result, $aggregate);
    }
}
