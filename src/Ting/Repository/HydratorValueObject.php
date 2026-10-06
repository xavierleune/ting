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
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\UnitOfWork;

/**
 * @template T of object
 * @phpstan-import-type Row from ResultInterface
 *
 * @template-implements HydratorInterface<T>
 */
class HydratorValueObject implements HydratorInterface
{
    /**
     * @var class-string<T>
     */
    protected string $objectToHydrate;
    /**
     * @var ResultInterface|null rows as formatted by the driver
     */
    protected ?ResultInterface $result = null;


    /**
     * @param class-string<T> $objectToHydrate
     */
    public function __construct(string $objectToHydrate)
    {
        $this->objectToHydrate = $objectToHydrate;
    }

    /**
     * @return \Generator<int, T>
     */
    public function getIterator(): \Generator
    {
        // As count(): without result, there is no row
        if ($this->result === null) {
            return;
        }

        $class = new \ReflectionClass($this->objectToHydrate);
        $constructor = $class->getConstructor();

        foreach ($this->result as $key => $row) {
            yield $key => $this->hydrate($class, $constructor, $row);
        }
    }

    /**
     * Same rules as the native fetch_object() functions: each column is written to the property named after the column
     * (or its alias) whatever its visibility, then the constructor is called without arguments
     *
     * @param \ReflectionClass<T> $class
     * @param Row $row columns as formatted by the driver
     * @return T
     */
    private function hydrate(\ReflectionClass $class, ?\ReflectionMethod $constructor, array $row): object
    {
        $object = $class->newInstanceWithoutConstructor();
        foreach ($row as $column) {
            if ($class->hasProperty($column['name'])) {
                $class->getProperty($column['name'])->setValue($object, $column['value']);
            } else {
                $object->{$column['name']} = $column['value'];
            }
        }
        $constructor?->invoke($object);

        return $object;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        if ($this->result === null) {
            return 0;
        }

        // mysqli reports the number of rows as a string beyond PHP_INT_MAX
        return (int) $this->result->getNumRows();
    }

    public function setMetadataRepository(MetadataRepository $metadataRepository): void
    {
        // Useless for this hydrator
    }

    public function setUnitOfWork(UnitOfWork $unitOfWork): void
    {
        // Useless for this hydrator
    }

    public function setResult(ResultInterface $result): static
    {
        $this->result = $result;
        return $this;
    }
}
