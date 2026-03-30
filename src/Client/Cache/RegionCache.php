<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Cache;

use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;

class RegionCache implements RegionCacheInterface
{
    /** @var RegionInfo[] */
    private array $regions = [];

    /** @var array<int, int> */
    private array $ttls = [];

    public function __construct(
        private readonly int $ttlSeconds = 600,
        private readonly int $jitterSeconds = 60,
    ) {
    }

    public function getByKey(string $key): ?RegionInfo
    {
        $index = $this->binarySearch($key);
        if ($index === null) {
            return null;
        }

        $region = $this->regions[$index];

        if ($this->isExpired($region->regionId)) {
            $this->removeByIndex($index);
            return null;
        }

        if ($region->endKey !== '' && $key >= $region->endKey) {
            return null;
        }

        return $region;
    }

    public function put(RegionInfo $region): void
    {
        $this->removeById($region->regionId);

        $position = $this->findInsertPosition($region->startKey);
        array_splice($this->regions, $position, 0, [$region]);

        $this->ttls[$region->regionId] = $this->now() + $this->ttlSeconds + $this->jitter();
    }

    public function invalidate(int $regionId): void
    {
        $this->removeById($regionId);
    }

    public function clear(): void
    {
        $this->regions = [];
        $this->ttls = [];
    }

    protected function now(): int
    {
        return time();
    }

    private function binarySearch(string $key): ?int
    {
        $left = 0;
        $right = count($this->regions) - 1;
        $result = null;

        while ($left <= $right) {
            $mid = (int) (($left + $right) / 2);
            $region = $this->regions[$mid];

            if ($region->startKey <= $key) {
                $result = $mid;
                $left = $mid + 1;
            } else {
                $right = $mid - 1;
            }
        }

        return $result;
    }

    private function findInsertPosition(string $startKey): int
    {
        $left = 0;
        $right = count($this->regions);

        while ($left < $right) {
            $mid = (int) (($left + $right) / 2);
            if ($this->regions[$mid]->startKey < $startKey) {
                $left = $mid + 1;
            } else {
                $right = $mid;
            }
        }

        return $left;
    }

    private function removeById(int $regionId): void
    {
        foreach ($this->regions as $index => $region) {
            if ($region->regionId === $regionId) {
                $this->removeByIndex($index);
                return;
            }
        }
    }

    private function removeByIndex(int $index): void
    {
        if (isset($this->regions[$index])) {
            $regionId = $this->regions[$index]->regionId;
            unset($this->ttls[$regionId]);
            array_splice($this->regions, $index, 1);
        }
    }

    private function isExpired(int $regionId): bool
    {
        if (!isset($this->ttls[$regionId])) {
            return true;
        }

        return $this->now() >= $this->ttls[$regionId];
    }

    private function jitter(): int
    {
        if ($this->jitterSeconds <= 0) {
            return 0;
        }

        return random_int(0, $this->jitterSeconds);
    }
}
