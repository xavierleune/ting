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

namespace CCMBenchmark\Ting\Driver;

/**
 * RollbackListenerAwareDriverInterface: the subjects registered with the open transaction, notified when it ends
 * without being committed, forgotten when it is committed
 *
 * @internal shared by the drivers, which declare $transactionOpened
 */
trait RollbackListenersTrait
{
    /** @var \WeakMap<object, list<RollbackListenerInterface>>|null subject => listeners, null without subject */
    private ?\WeakMap $rollbackListeners = null;

    public function addRollbackListener(RollbackListenerInterface $listener, object $subject): bool
    {
        if ($this->transactionOpened === false) {
            return false;
        }

        $this->rollbackListeners ??= new \WeakMap();
        $listeners = $this->rollbackListeners[$subject] ?? [];
        if (\in_array($listener, $listeners, true) === false) {
            $listeners[] = $listener;
            $this->rollbackListeners[$subject] = $listeners;
        }

        return true;
    }

    /**
     * The transaction is committed: its subjects are forgotten
     */
    private function forgetRollbackListeners(): void
    {
        $this->rollbackListeners = null;
    }

    /**
     * The transaction ended without being committed: its subjects are notified, then forgotten
     */
    private function notifyRollbackListeners(): void
    {
        $rollbackListeners = $this->rollbackListeners;
        $this->rollbackListeners = null;
        foreach ($rollbackListeners ?? [] as $subject => $listeners) {
            foreach ($listeners as $listener) {
                $listener->rolledBack($subject);
            }
        }
    }
}
