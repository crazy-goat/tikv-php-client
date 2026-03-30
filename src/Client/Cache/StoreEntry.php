<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Cache;

use CrazyGoat\Proto\Metapb\Store;

final readonly class StoreEntry
{
    public function __construct(
        public Store $store,
        public int $expiresAt,
    ) {}
}
