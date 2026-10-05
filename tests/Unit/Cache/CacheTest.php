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
use Doctrine\Common\Cache\VoidCache;

class CacheTest extends TestCase
{
    public function testDeleteShouldCallLogger()
    {
        $mockLogger = $this->createMock(CacheLoggerInterface::class);
        $mockLogger
            ->expects($this->atLeastOnce())
            ->method('startOperation')
            ->with($this->identicalTo(CacheLoggerInterface::OPERATION_DELETE), $this->identicalTo('bouh'));
        $mockLogger->expects($this->once())->method('stopOperation');

        $cache = new Cache();
        $cache->setCache(new VoidCache());
        $cache->setLogger($mockLogger);
        $cache->delete('bouh');
    }

    public function testFetchShouldCallLogger()
    {
        $mockLogger = $this->createMock(CacheLoggerInterface::class);
        $mockLogger
            ->expects($this->atLeastOnce())
            ->method('startOperation')
            ->with($this->identicalTo(CacheLoggerInterface::OPERATION_GET), $this->identicalTo('bouh'));
        $mockLogger->expects($this->once())->method('stopOperation');

        $cache = new Cache();
        $cache->setCache(new VoidCache());
        $cache->setLogger($mockLogger);
        $cache->fetch('bouh');
    }

    public function testContainsShouldCallLogger()
    {
        $mockLogger = $this->createMock(CacheLoggerInterface::class);
        $mockLogger
            ->expects($this->atLeastOnce())
            ->method('startOperation')
            ->with($this->identicalTo(CacheLoggerInterface::OPERATION_EXIST), $this->identicalTo('bouh'));
        $mockLogger->expects($this->once())->method('stopOperation');

        $cache = new Cache();
        $cache->setCache(new VoidCache());
        $cache->setLogger($mockLogger);
        $cache->contains('bouh');
    }

    public function testSaveShouldCallLogger()
    {
        $mockLogger = $this->createMock(CacheLoggerInterface::class);
        $mockLogger
            ->expects($this->atLeastOnce())
            ->method('startOperation')
            // atoum never evaluated this check, which also expected the data and lifetime:
            // Cache::save() only logs the operation and the id
            ->with(
                $this->identicalTo(CacheLoggerInterface::OPERATION_STORE),
                $this->identicalTo('name')
            );
        $mockLogger->expects($this->once())->method('stopOperation');

        $cache = new Cache();
        $cache->setCache(new VoidCache());
        $cache->setLogger($mockLogger);
        $cache->save('name', 'Sylvain', 33);
    }
}
