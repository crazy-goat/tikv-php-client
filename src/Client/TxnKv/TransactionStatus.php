<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\TxnKv;

enum TransactionStatus
{
    case Active;
    case Committed;
    case RolledBack;

    /**
     * The commit outcome is unknown: the primary commit RPC failed at the
     * transport level, so the transaction may already be committed. The
     * transaction is closed and must never be rolled back (issue #216).
     */
    case Undetermined;
}
