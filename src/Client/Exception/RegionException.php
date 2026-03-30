<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Exception;

final class RegionException extends TiKvException
{
    public function __construct(string $operation, string $error)
    {
        parent::__construct("{$operation} failed: {$error}");
    }
}
