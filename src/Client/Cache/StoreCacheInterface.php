<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Cache;

use CrazyGoat\Proto\Metapb\Store;

interface StoreCacheInterface
{
    public function get(int $storeId): ?Store;
    public function put(Store $store): void;
    public function invalidate(int $storeId): void;
    public function clear(): void;
}
