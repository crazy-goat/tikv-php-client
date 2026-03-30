<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Exception;

use CrazyGoat\Proto\Errorpb\Error;
use CrazyGoat\Proto\Errorpb\NotLeader;

final class RegionException extends TiKvException
{
    public function __construct(
        string $operation,
        string $message,
        public readonly ?NotLeader $notLeader = null,
    ) {
        parent::__construct("{$operation} failed: {$message}");
    }

    public static function fromRegionError(Error $error): self
    {
        return new self(
            operation: 'RegionError',
            message: $error->getMessage(),
            notLeader: $error->getNotLeader(),
        );
    }
}
