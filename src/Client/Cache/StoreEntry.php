<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Cache;

use CrazyGoat\Proto\Metapb\Store;

final class StoreEntry
{
    public function __construct(
        public readonly Store $store,
        public readonly int $expiresAt,
    ) {}
}
