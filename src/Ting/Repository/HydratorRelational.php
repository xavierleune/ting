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

use CCMBenchmark\Ting\Driver\ResultInterface;
use CCMBenchmark\Ting\Exception;
use CCMBenchmark\Ting\Exceptions\HydratorException;
use CCMBenchmark\Ting\Repository\Hydrator\Relation;
use CCMBenchmark\Ting\Repository\Hydrator\RelationMany;
use Generator;
use SplDoublyLinkedList;

use function array_column;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_diff;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function in_array;
use function iterator_to_array;
use function sprintf;

/**
 * @template T type of the items, as built by the callable given to callableFinalizeAggregate(): the rows without
 *             the aliases of the sources without one
 * @phpstan-import-type Row from ResultInterface
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

    /**
     * @var array<string, Metadata<object>> alias => metadata of the row hydrated last: the state of the result may be
     *                                      the one of another result once the row is yielded (a read nested in a
     *                                      setter)
     */
    private array $metadataOfRow = [];

    /**
     * @var array<string, array<string, mixed>> alias of a relation => primary column name => value in the row hydrated
     *                                          last (null when the column is not selected or null)
     */
    private array $primaryValuesOfRow = [];

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
     * @param Metadata<object> $metadata the metadata of $alias in the row
     * @param array<string, mixed> $primaryValues the primary values of $alias in the row
     *
     * @throws Exception
     */
    private function saveReference(string $alias, object $entity, Metadata $metadata, array $primaryValues): string
    {
        $key = $this->getIdentifiers($alias, $metadata, $primaryValues);

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

        // Read in a setter called while another result is hydrated (a lookup with this hydrator): the references and
        // the resources of the other one are given back once this result is read
        $previous = [$this->referencesRelation, $this->resources];
        $this->referencesRelation = [];
        $this->resources          = [];

        try {
            $results = $this->collect($relations, $sources, $roots);
            $this->assignResourcesToReferences($relations);
        } finally {
            [$this->referencesRelation, $this->resources] = $previous;
        }

        foreach ($results as $result) {
            yield $this->finalizeAggregate($result);
        }
    }

    /**
     * Hydrates every row, collecting the entities of the relations, and returns one row per distinct combination of
     * roots
     *
     * @param list<array{source: string, target: string, targetSetter: string, many: bool}> $relations
     * @param array<string> $sources
     * @param array<string> $roots
     * @return array<string, array<int|string, object|null>>
     * @throws Exception
     */
    private function collect(array $relations, array $sources, array $roots): array
    {
        $results = [];
        $checked = false;
        foreach ($this->hydratedRows() as $result) {
            $metadataOfRow = $this->metadataOfRow;
            $primaryValuesOfRow = $this->primaryValuesOfRow;
            if ($checked === false) {
                $this->assertAliasesInResult($relations, $result);
                $checked = true;
            }

            foreach ($relations as $index => $relation) {
                $target = $result[$relation['target']] ?? null;
                $source = $result[$relation['source']] ?? null;
                if ($target === null || $source === null) {
                    continue;
                }

                $this->saveResourceFor(
                    $index,
                    $relation,
                    $this->saveReference(
                        $relation['target'],
                        $target,
                        $metadataOfRow[$relation['target']],
                        $primaryValuesOfRow[$relation['target']]
                    ),
                    $this->saveReference(
                        $relation['source'],
                        $source,
                        $metadataOfRow[$relation['source']],
                        $primaryValuesOfRow[$relation['source']]
                    )
                );
            }

            $rootKeys = [];
            foreach ($roots as $root) {
                $entity = $result[$root] ?? null;
                $rootKeys[] = $entity !== null
                    ? $this->saveReference($root, $entity, $metadataOfRow[$root], $primaryValuesOfRow[$root])
                    : null;
            }

            if (array_filter($rootKeys, static fn (?string $rootKey): bool => $rootKey !== null) === []) {
                // No root entity (LEFT JOIN on the root side): nothing to yield, the entities of the row are given to
                // no root
                continue;
            }

            foreach ($sources as $source) {
                unset($result[$source]);
            }

            $results[serialize($rootKeys)] ??= $result;
        }

        return $results;
    }

    /**
     * @param list<array{source: string, target: string, targetSetter: string, many: bool}> $relations
     * @param array<int|string, mixed> $result the first row
     *
     * @throws HydratorException when an alias of a relation is not in the result (a typo would otherwise give a
     *                           single row)
     */
    private function assertAliasesInResult(array $relations, array $result): void
    {
        foreach ($relations as $relation) {
            foreach ([$relation['target'], $relation['source']] as $alias) {
                if (array_key_exists($alias, $result) === false) {
                    throw new HydratorException(sprintf(
                        'The alias "%s" of the relation %s -> %s is not in the result, which holds: %s',
                        $alias,
                        $relation['source'],
                        $relation['target'],
                        implode(', ', array_keys($result))
                    ));
                }
            }
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
     * @param Row $columns
     *
     * @return array<int|string, object|null>
     * @throws HydratorException
     */
    protected function hydrateColumns(string $connectionName, string $database, array $columns): array
    {
        $row = parent::hydrateColumns($connectionName, $database, $columns);
        $this->metadataOfRow = $this->metadataList;

        // Read from the columns, not from the entity: a primary property may be set though its column is not selected
        // (a default value, a constructor) or be a typed property left uninitialized
        $this->primaryValuesOfRow = [];
        foreach ($this->config as $relation) {
            foreach ([$relation['source'], $relation['target']] as $alias) {
                if (isset($row[$alias], $this->metadataList[$alias])) {
                    $this->primaryValuesOfRow[$alias] ??= $this->primaryValuesFromColumns($alias, $columns);
                }
            }
        }

        return $row;
    }

    /**
     * @param Metadata<object> $metadata
     * @param array<string, mixed> $primaryValues primary column name => value in the row, null when not selected
     *
     * @throws HydratorException when the metadata have no primary key, or when a primary value is missing: the entity
     *                           cannot be told apart from the other entities of $alias
     */
    private function getIdentifiers(string $alias, Metadata $metadata, array $primaryValues): string
    {
        if ($primaryValues === []) {
            throw new HydratorException(sprintf('No primary found for "%s"', $metadata->getEntity()));
        }

        $missing = array_keys(array_filter($primaryValues, static fn (mixed $value): bool => $value === null));
        if ($missing !== []) {
            // A primary key column is never null in a table: a null one is not selected, or comes from an expression,
            // a view or an outer join of a derived table. Part of a composite key cannot identify the entity either.
            throw new HydratorException(sprintf(
                'The entity of the alias "%s" (%s) has no value for its primary key %s %s: an entity of a relation is '
                . 'identified by its primary key, every primary key column of the alias must be selected and not null',
                $alias,
                $metadata->getEntity(),
                count($missing) > 1 ? 'columns' : 'column',
                implode(', ', $missing)
            ));
        }

        return $this->referenceKey($alias, array_values($primaryValues));
    }
}
