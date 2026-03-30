<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\TxnKv;

enum TransactionStatus
{
    case Active;
    case Committed;
    case RolledBack;
}
