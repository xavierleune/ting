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

use CCMBenchmark\Ting\Exceptions\TransactionException;

/**
 * A transaction lives in the server session: when the connection is replaced or closed while a transaction is open,
 * the server rolls it back. The driver then forgets the transaction and remembers it was lost, so that the next
 * commit() reports it instead of "succeeding" on the new connection.
 *
 * @internal shared by the drivers, which declare $transactionOpened
 */
trait LostTransactionTrait
{
    /**
     * True when the connection was replaced or closed while a transaction was open, until the next commit(),
     * rollback() or startTransaction()
     */
    protected bool $transactionLost = false;

    /**
     * To call when the connection is replaced or closed
     */
    private function loseTransaction(): void
    {
        if ($this->transactionOpened === true) {
            $this->transactionOpened = false;
            $this->transactionLost = true;
        }
    }

    /**
     * For commit(): reports a lost transaction, once
     * @throws TransactionException
     */
    private function assertTransactionNotLost(): void
    {
        if ($this->transactionLost === true) {
            $this->transactionLost = false;
            throw new TransactionException(
                'The transaction was lost with the connection: the server rolled it back'
            );
        }
    }

    /**
     * For rollback(): a lost transaction was already rolled back by the server, nothing is sent to the new connection
     * @return bool true when the transaction was lost
     */
    private function forgetLostTransaction(): bool
    {
        $lost = $this->transactionLost;
        $this->transactionLost = false;

        return $lost;
    }
}
