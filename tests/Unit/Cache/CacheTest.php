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

namespace CCMBenchmark\Ting\Tests\Unit\Cache;

use CCMBenchmark\Ting\Cache\Cache;
use CCMBenchmark\Ting\Exceptions\ConfigException;
use CCMBenchmark\Ting\Logger\CacheLoggerInterface;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface as SymfonyCacheInterface;

class CacheTest extends TestCase
{
    public function testGetWithoutPoolShouldRaiseConfigException()
    {
        $cache = new Cache();

        $this->assertThrows(ConfigException::class, function () use ($cache): void {
            $cache->get('bouh', fn () => 'value');
        }, 'No cache pool: call Cache::setCache() first');
    }

    public function testDeleteWithoutPoolShouldRaiseConfigException()
    {
        $cache = new Cache();

        $this->assertThrows(ConfigException::class, function () use ($cache): void {
            $cache->delete('bouh');
        }, 'No cache pool: call Cache::setCache() first');
    }

    public function testDeleteShouldCallLogger()
    {
        $mockLogger = $this->createMock(CacheLoggerInterface::class);
        $mockLogger
            ->expects($this->once())
            ->method('startOperation')
            ->with($this->identicalTo(CacheLoggerInterface::OPERATION_DELETE), $this->identicalTo('bouh'));
        $mockLogger->expects($this->once())->method('stopOperation');

        $cache = new Cache();
        $cache->setCache(new ArrayAdapter());
        $cache->setLogger($mockLogger);

        $this->assertTrue($cache->delete('bouh'));
    }

    public function testGetShouldComputeTheValueAndLogAMissWhenNotInCache()
    {
        $mockLogger = $this->createMock(CacheLoggerInterface::class);
        $mockLogger
            ->expects($this->once())
            ->method('startOperation')
            ->with($this->identicalTo(CacheLoggerInterface::OPERATION_GET), $this->identicalTo('bouh'));
        $mockLogger->expects($this->once())->method('stopOperation')->with($this->identicalTo(true));

        $cache = new Cache();
        $cache->setCache(new ArrayAdapter());
        $cache->setLogger($mockLogger);

        $this->assertSame('computed', $cache->get('bouh', fn () => 'computed'));
    }

    public function testGetShouldReturnTheCachedValueAndLogAHit()
    {
        $pool = new ArrayAdapter();
        $pool->get('bouh', fn () => 'cached');
        $mockLogger = $this->createMock(CacheLoggerInterface::class);
        $mockLogger->expects($this->once())->method('startOperation');
        $mockLogger->expects($this->once())->method('stopOperation')->with($this->identicalTo(false));

        $cache = new Cache();
        $cache->setCache($pool);
        $cache->setLogger($mockLogger);

        $this->assertSame('cached', $cache->get('bouh', function (): never {
            $this->fail('The callback must not be called on a cache hit');
        }));
    }

    public function testGetShouldStopTheLogWhenTheCallbackThrows()
    {
        $mockLogger = $this->createMock(CacheLoggerInterface::class);
        $mockLogger->expects($this->once())->method('startOperation');
        $mockLogger->expects($this->once())->method('stopOperation')->with($this->identicalTo(true));

        $cache = new Cache();
        $cache->setCache(new ArrayAdapter());
        $cache->setLogger($mockLogger);

        $this->assertThrows(\RuntimeException::class, function () use ($cache): void {
            $cache->get('bouh', function (): never {
                throw new \RuntimeException('Query failed');
            });
        }, 'Query failed');
    }

    /**
     * A pool rejecting the key (a reserved character): with Symfony pools the check depends on the version and on
     * zend.assertions, so the pool throws here whatever the key
     */
    private function rejectingPool(): SymfonyCacheInterface
    {
        $exception = new class ('Cache key "bou{h}" contains reserved characters') extends \InvalidArgumentException
            implements InvalidArgumentException {
        };
        $pool = $this->createStub(SymfonyCacheInterface::class);
        $pool->method('get')->willThrowException($exception);
        $pool->method('delete')->willThrowException($exception);

        return $pool;
    }

    /**
     * A read failing before its callback (a reserved character in the key) served no cached value: not a hit
     */
    public function testGetShouldLogAMissWhenThePoolThrowsBeforeTheCallback()
    {
        $mockLogger = $this->createMock(CacheLoggerInterface::class);
        $mockLogger->expects($this->once())->method('startOperation');
        $mockLogger->expects($this->once())->method('stopOperation')->with($this->identicalTo(true));

        $cache = new Cache();
        $cache->setCache($this->rejectingPool());
        $cache->setLogger($mockLogger);

        $this->assertThrows(InvalidArgumentException::class, function () use ($cache): void {
            $cache->get('bou{h}', fn () => $this->fail('The callback must not be called'));
        });
    }

    public function testDeleteShouldStopTheLogWhenThePoolThrows()
    {
        $mockLogger = $this->createMock(CacheLoggerInterface::class);
        $mockLogger->expects($this->once())->method('startOperation');
        $mockLogger->expects($this->once())->method('stopOperation');

        $cache = new Cache();
        $cache->setCache($this->rejectingPool());
        $cache->setLogger($mockLogger);

        $this->assertThrows(InvalidArgumentException::class, function () use ($cache): void {
            $cache->delete('bou{h}');
        });
    }
}
