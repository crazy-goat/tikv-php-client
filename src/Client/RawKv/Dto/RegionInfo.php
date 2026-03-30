<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\RawKv\Dto;

final readonly class RegionInfo
{
    public function __construct(
        public int $regionId,
        public int $leaderPeerId,
        public int $leaderStoreId,
        public int $epochConfVer,
        public int $epochVersion,
        public string $startKey = '',
        public string $endKey = '',
    ) {
    }
}
