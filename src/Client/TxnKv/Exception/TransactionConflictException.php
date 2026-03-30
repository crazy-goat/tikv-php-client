<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\TxnKv\Exception;

use CrazyGoat\TiKV\Client\Exception\TiKvException;

final class TransactionConflictException extends TiKvException
{
    /**
     * @param string[] $conflictingKeys
     */
    public function __construct(
        string $message = 'Transaction conflict detected',
        private readonly ?array $conflictingKeys = null,
    ) {
        parent::__construct($message);
    }

    /**
     * @return string[]|null
     */
    public function getConflictingKeys(): ?array
    {
        return $this->conflictingKeys;
    }
}
