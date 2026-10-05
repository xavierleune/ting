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

namespace CCMBenchmark\Ting\Cache;

use CCMBenchmark\Ting\Logger\CacheLoggerInterface;
use Symfony\Contracts\Cache\CacheInterface as SymfonyCacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Decorates a Symfony cache pool to log its operations
 */
class Cache implements CacheInterface
{
    private ?CacheLoggerInterface $logger = null;

    private SymfonyCacheInterface $cache;

    public function setCache(SymfonyCacheInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Add the ability to log operations
     *
     * @param CacheLoggerInterface $logger
     */
    public function setLogger(CacheLoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Logs an operation with $this->logger if provided
     */
    protected function log(string $type, array|string $operation): void
    {
        if ($this->logger !== null) {
            $this->logger->startOperation($type, $operation);
        }
    }

    /**
     * Flag the last operation logged as stopped
     *
     * @param $miss boolean optional : required if last operation was a read
     */
    protected function stopLog(bool $miss = false): void
    {
        if ($this->logger !== null) {
            $this->logger->stopOperation($miss);
        }
    }

    /**
     * {@inheritdoc}
     *
     * Logged as a read, flagged as a miss when $callback has to compute (and store) the value
     */
    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        $this->log(CacheLoggerInterface::OPERATION_GET, $key);
        $miss = false;
        try {
            return $this->cache->get(
                $key,
                function (ItemInterface $item, bool &$save) use ($callback, &$miss): mixed {
                    $miss = true;

                    return $callback($item, $save);
                },
                $beta,
                $metadata
            );
        } finally {
            $this->stopLog($miss);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        $this->log(CacheLoggerInterface::OPERATION_DELETE, $key);
        $result = $this->cache->delete($key);
        $this->stopLog();

        return $result;
    }
}
