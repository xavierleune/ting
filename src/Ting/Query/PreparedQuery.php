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

namespace CCMBenchmark\Ting\Query;

use CCMBenchmark\Ting\Repository\CollectionInterface;

/**
 * @template T type of the items of the collections built by the collection factory of the query
 *
 * @template-extends Query<T>
 */
class PreparedQuery extends Query
{
    use PreparedStatementTrait;

    /**
     * Prepare then execute a reading query
     * @template U
     * @param CollectionInterface<U>|null $collection null for a collection of the collection factory of the query
     * @return ($collection is null ? CollectionInterface<T> : CollectionInterface<U>)
     * @throws QueryException
     */
    public function query(?CollectionInterface $collection = null): CollectionInterface
    {
        if (!$collection instanceof CollectionInterface) {
            $collection = $this->collectionFactory->get();
        }

        $this->prepareQuery();

        $this->statement->execute($this->params, $collection);

        return $collection;
    }

    /**
     * Prepare then execute a writing query
     * @return mixed
     * @throws QueryException
     */
    public function execute(): mixed
    {
        $this->prepareExecute();

        return $this->statement->execute($this->params);
    }

    public function getStatementName(): string
    {
        return sha1($this->sql);
    }
}
