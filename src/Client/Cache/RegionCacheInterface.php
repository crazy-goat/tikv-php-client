<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Cache;

use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;

interface RegionCacheInterface
{
    public function getByKey(string $key): ?RegionInfo;

    public function put(RegionInfo $region): void;

    public function invalidate(int $regionId): void;

    public function clear(): void;
}
