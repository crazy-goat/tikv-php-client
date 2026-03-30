<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Exception;

final class StoreNotFoundException extends TiKvException
{
    public function __construct(public readonly int $storeId)
    {
        parent::__construct("Store {$storeId} not found in PD");
    }
}
