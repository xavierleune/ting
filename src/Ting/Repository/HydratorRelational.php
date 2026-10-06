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
 * @template T type of the items, as built by the callable given to callableFinalizeAggregate(): the rows without
 *             the aliases of the sources without one
 *
 * @template-extends Hydrator<T>
 */
final class HydratorRelational extends Hydrator
{
    /**
     * @var callable|null
     */
    private $callableFinalizeAggregate;

    /** @var SplDoublyLinkedList<array{source: string, target: string, targetSetter: string, many: bool}> */
    private SplDoublyLinkedList $config;

    /** @var array<string, object> reference key => first instance met of the entity */
    protected array $referencesRelation = [];

    /**
     * @var array<int, array<string, mixed>> index of the relation => reference key of the target => entity of the
     *      source, or entities of the sources by reference key (array<string, object>) for a RelationMany
     */
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
     * @return Generator<int, mixed> the rows, or what the callable given to callableFinalizeAggregate() returns
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
     * @param object $entity the entity of $alias in the row
     *
     * @throws Exception
     */
    private function saveReference(string $alias, object $entity): string
    {
        $key = $this->getIdentifiers($alias, $entity);

        if (isset($this->referencesRelation[$key]) === false) {
            $this->referencesRelation[$key] = $entity;
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

        foreach ($this->hydratedRows() as $result) {
            foreach ($relations as $index => $relation) {
                $target = $result[$relation['target']] ?? null;
                $source = $result[$relation['source']] ?? null;
                if ($target === null || $source === null) {
                    continue;
                }

                $this->saveResourceFor(
                    $index,
                    $relation,
                    $this->saveReference($relation['target'], $target),
                    $this->saveReference($relation['source'], $source)
                );
            }

            $rootKeys = [];
            foreach ($roots as $root) {
                $entity = $result[$root] ?? null;
                $rootKeys[] = $entity !== null ? $this->saveReference($root, $entity) : null;
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
        foreach ($this->hydratedRows() as $result) {
            yield $this->finalizeAggregate($result);
        }
    }

    /**
     * @param array<int|string, object|null> $result
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
    private function getIdentifiers(string $table, object $entity): string
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
