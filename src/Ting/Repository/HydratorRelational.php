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

use stdClass;
use CCMBenchmark\Ting\Exception;
use CCMBenchmark\Ting\Exceptions\HydratorException;
use CCMBenchmark\Ting\Repository\Hydrator\Relation;
use CCMBenchmark\Ting\Repository\Hydrator\RelationMany;
use Generator;
use SplDoublyLinkedList;

use function array_column;
use function array_diff;
use function array_map;
use function array_unique;
use function implode;
use function in_array;
use function iterator_to_array;
use function sprintf;

/**
 * @template T
 *
 * @template-extends Hydrator<T>
 */
final class HydratorRelational extends Hydrator
{
    /**
     * @var callable|null
     */
    private $callableFinalizeAggregate;

    private SplDoublyLinkedList $config;

    protected array $referencesRelation = [];

    private array $resources = [];

    protected bool $identityMap = true;

    public function __construct()
    {
        parent::__construct();
        $this->config = new SplDoublyLinkedList();
    }

    /**
     * @param bool $enable
     * @throws HydratorException
     * @return void
     */
    public function identityMap($enable): void
    {
        if ((bool) $enable === false) {
            throw new HydratorException('identityMap can\'t be disabled for this Hydrator');
        }
    }

    /**
     * @param callable $callableFinalizeAggregate
     * @return $this
     */
    public function callableFinalizeAggregate(callable $callableFinalizeAggregate): self
    {
        $this->callableFinalizeAggregate = $callableFinalizeAggregate;
        return $this;
    }

    public function addRelation(Relation $relation): void
    {
        $this->config->push([
            'source' => $relation->getSource(),
            'target' => $relation->getTarget(),
            'targetSetter' => $relation->getSetter(),
            'many' => $relation instanceof RelationMany
        ]);
    }

    /**
     * @throws Exception
     *
     * @return Generator<int, T|stdClass>
     */
    public function getIterator(): Generator
    {
        if ($this->config->isEmpty()) {
            return $this->hydrateNoAssociation();
        }

        return $this->hydrate();
    }

    /**
     * Orders the relations so that each one comes after the relations targeting its source: a setter receives
     * entities whose own relations are already set. Independent relations keep their insertion order.
     *
     * @throws HydratorException when the relations form a cycle
     *
     * @return list<array{source: string, target: string, targetSetter: string, many: bool}>
     */
    private function resolveDependencies(): array
    {
        /** @var list<array{source: string, target: string, targetSetter: string, many: bool}> $pending */
        $pending = iterator_to_array($this->config, false);
        $ordered = [];

        while ($pending !== []) {
            $targets = array_column($pending, 'target');
            foreach ($pending as $index => $relation) {
                if (in_array($relation['source'], $targets, true) === false) {
                    $ordered[] = $relation;
                    unset($pending[$index]);
                    continue 2;
                }
            }

            throw new HydratorException(sprintf(
                'Cannot order the relations %s: they contain a cycle. Every relation must lead to a root alias, which '
                . 'is the source of no relation: set the back reference in the setter instead.',
                implode(', ', array_map(
                    static fn (array $relation): string => $relation['source'] . ' -> ' . $relation['target'],
                    $pending
                ))
            ));
        }

        return $ordered;
    }

    /**
     * Stores the first instance met for the entity of $alias and returns its reference key
     *
     * @throws Exception
     */
    private function saveReference(string $alias, array $result): string
    {
        $key = $this->getIdentifiers($alias, $result[$alias]);

        if (isset($this->referencesRelation[$key]) === false) {
            $this->referencesRelation[$key] = $result[$alias];
        }

        return $key;
    }

    /**
     * @param array{source: string, target: string, targetSetter: string, many: bool} $relation
     */
    private function saveResourceFor(int $index, array $relation, string $keyTarget, string $keySource): void
    {
        if ($relation['many'] === true) {
            $this->resources[$index][$keyTarget][$keySource] ??= $this->referencesRelation[$keySource];
        } else {
            $this->resources[$index][$keyTarget] = $this->referencesRelation[$keySource];
        }
    }

    /**
     * @param list<array{source: string, target: string, targetSetter: string, many: bool}> $relations
     */
    private function assignResourcesToReferences(array $relations): void
    {
        foreach ($relations as $index => $relation) {
            foreach ($this->resources[$index] ?? [] as $keyTarget => $valuesToSet) {
                $this->referencesRelation[$keyTarget]->{$relation['targetSetter']}($valuesToSet);
            }
        }
    }

    private function hydrate(): Generator
    {
        $relations = $this->resolveDependencies();
        $sources   = array_unique(array_column($relations, 'source'));
        // The roots receive entities without being given to another one: one row per distinct combination of roots
        $roots     = array_unique(array_diff(array_column($relations, 'target'), $sources));

        $this->referencesRelation = [];
        $this->resources          = [];
        $results                  = [];

        foreach ($this->result as $columns) {
            $result = $this->hydrateColumns($this->result->getConnectionName(), $this->result->getDatabase(), $columns);

            foreach ($relations as $index => $relation) {
                if (isset($result[$relation['target']], $result[$relation['source']]) === false) {
                    continue;
                }

                $this->saveResourceFor(
                    $index,
                    $relation,
                    $this->saveReference($relation['target'], $result),
                    $this->saveReference($relation['source'], $result)
                );
            }

            $rootKeys = [];
            foreach ($roots as $root) {
                $rootKeys[] = isset($result[$root]) ? $this->saveReference($root, $result) : null;
            }

            foreach ($sources as $source) {
                unset($result[$source]);
            }

            $results[serialize($rootKeys)] ??= $result;
        }

        $this->assignResourcesToReferences($relations);

        foreach ($results as $result) {
            yield $this->finalizeAggregate($result);
        }
    }

    private function hydrateNoAssociation(): Generator
    {
        foreach ($this->result as $columns) {
            yield $this->finalizeAggregate(
                $this->hydrateColumns($this->result->getConnectionName(), $this->result->getDatabase(), $columns)
            );
        }
    }

    /**
     *
     * @return mixed
     */
    private function finalizeAggregate(array $result): mixed
    {
        if ($this->callableFinalizeAggregate === null) {
            return $result;
        }

        $callableFinalizeAggregate = $this->callableFinalizeAggregate;
        return $callableFinalizeAggregate($result);
    }

    /**
     * @param string $table
     * @param object $entity
     *
     * @throws HydratorException
     *
     * @return string
     */
    private function getIdentifiers($table, $entity): string
    {
        $values = [];
        foreach ($this->metadataList[$table]->getPrimaries() as $primary) {
            $values[] = $this->metadataList[$table]->getEntityPropertyByFieldName($entity, $primary['fieldName']);
        }

        if ($values === []) {
            throw new HydratorException(sprintf('No primary found for "%s"', $this->metadataList[$table]->getEntity()));
        }

        return $this->referenceKey($table, $values);
    }
}
