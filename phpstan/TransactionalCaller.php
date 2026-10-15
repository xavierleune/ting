<?php

/***********************************************************************
 *
 * Ting - PHP Datamapper
 * ==========================================
 *
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

namespace CCMBenchmark\Ting\PHPStan;

use CCMBenchmark\Ting\Repository\Repository;

/**
 * Catches an exception thrown by the callback given to Repository::transactional(), so that PHPStan checks the
 * callback is known to be called before transactional() returns: otherwise the catch is reported as dead. Not part
 * of the library.
 */
final class TransactionalCaller
{
    /**
     * @param Repository<object> $repository
     */
    public function run(Repository $repository): bool
    {
        try {
            return $repository->transactional(static function (): bool {
                throw new \DomainException('Business rule');
            });
        } catch (\DomainException) {
            return false;
        }
    }
}
