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
use CCMBenchmark\Ting\Logger\CacheLoggerInterface;
use CCMBenchmark\Ting\Tests\Support\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class CacheTest extends TestCase
{
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
}
