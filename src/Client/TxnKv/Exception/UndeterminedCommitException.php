<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\TxnKv\Exception;

use CrazyGoat\TiKV\Client\Exception\TiKvException;

/**
 * The outcome of the commit is unknown (client-go's ErrResultUndetermined).
 *
 * Raised when the primary-key commit RPC fails at the transport level: the
 * commit may or may not have been applied, so the transaction must NOT be
 * rolled back. The caller must resolve the ambiguity out of band (e.g. by
 * re-checking the primary key's status with CheckTxnStatus).
 */
final class UndeterminedCommitException extends TiKvException
{
    public function __construct(
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
