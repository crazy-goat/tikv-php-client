<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Cache;

use CrazyGoat\TiKV\Client\Cache\RegionCache;
use CrazyGoat\TiKV\Client\Cache\RegionCacheInterface;
use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;
use PHPUnit\Framework\TestCase;

class TestableRegionCache extends RegionCache
{
    public function __construct(private int $fakeTime, int $ttlSeconds = 600)
    {
        parent::__construct($ttlSeconds, 0);
    }

    public function setTime(int $time): void
    {
        $this->fakeTime = $time;
    }

    protected function now(): int
    {
        return $this->fakeTime;
    }
}

class RegionCacheTest extends TestCase
{
    private function makeRegion(int $id, string $startKey, string $endKey = ''): RegionInfo
    {
        return new RegionInfo(
            regionId: $id,
            leaderPeerId: 1,
            leaderStoreId: 1,
            epochConfVer: 1,
            epochVersion: 1,
            startKey: $startKey,
            endKey: $endKey,
        );
    }

    public function testImplementsInterface(): void
    {
        $cache = new RegionCache();
        $this->assertInstanceOf(RegionCacheInterface::class, $cache);
    }

    public function testGetByKeyReturnsNullOnEmptyCache(): void
    {
        $cache = new RegionCache();
        $this->assertNull($cache->getByKey('any_key'));
    }

    public function testPutAndGetByKeyHit(): void
    {
        $cache = new RegionCache();
        $region = $this->makeRegion(1, 'a', 'z');
        $cache->put($region);

        $result = $cache->getByKey('m');
        $this->assertSame($region, $result);
    }

    public function testGetByKeyMissOutsideRange(): void
    {
        $cache = new RegionCache();
        $region = $this->makeRegion(1, 'b', 'd');
        $cache->put($region);

        $this->assertNull($cache->getByKey('a'));
        $this->assertNull($cache->getByKey('d'));
        $this->assertNull($cache->getByKey('z'));
    }

    public function testGetByKeyAtStartKeyBoundary(): void
    {
        $cache = new RegionCache();
        $region = $this->makeRegion(1, 'b', 'd');
        $cache->put($region);

        $result = $cache->getByKey('b');
        $this->assertSame($region, $result);
    }

    public function testGetByKeyEmptyEndKeyMeansUnbounded(): void
    {
        $cache = new RegionCache();
        $region = $this->makeRegion(1, 'a', '');
        $cache->put($region);

        $this->assertSame($region, $cache->getByKey('a'));
        $this->assertSame($region, $cache->getByKey('m'));
        $this->assertSame($region, $cache->getByKey('z'));
        $this->assertSame($region, $cache->getByKey('zzz'));
    }

    public function testMultipleRegionsBinarySearch(): void
    {
        $cache = new RegionCache();
        $region1 = $this->makeRegion(1, 'a', 'd');
        $region2 = $this->makeRegion(2, 'd', 'h');
        $region3 = $this->makeRegion(3, 'h', '');

        $cache->put($region1);
        $cache->put($region2);
        $cache->put($region3);

        $this->assertSame($region1, $cache->getByKey('a'));
        $this->assertSame($region1, $cache->getByKey('c'));
        $this->assertSame($region2, $cache->getByKey('d'));
        $this->assertSame($region2, $cache->getByKey('f'));
        $this->assertSame($region3, $cache->getByKey('h'));
        $this->assertSame($region3, $cache->getByKey('z'));
    }

    public function testPutReplacesExistingRegionById(): void
    {
        $cache = new RegionCache();
        $region1 = $this->makeRegion(1, 'a', 'd');
        $cache->put($region1);

        $region2 = $this->makeRegion(1, 'a', 'f');
        $cache->put($region2);

        $this->assertSame($region2, $cache->getByKey('e'));
        $this->assertNull($cache->getByKey('g'));
    }

    public function testInvalidateRemovesRegion(): void
    {
        $cache = new RegionCache();
        $region = $this->makeRegion(1, 'a', 'z');
        $cache->put($region);

        $cache->invalidate(1);

        $this->assertNull($cache->getByKey('m'));
    }

    public function testInvalidateNonExistentIsNoop(): void
    {
        $cache = new RegionCache();
        $region = $this->makeRegion(1, 'a', 'z');
        $cache->put($region);

        $cache->invalidate(999);

        $this->assertSame($region, $cache->getByKey('m'));
    }

    public function testClearRemovesAll(): void
    {
        $cache = new RegionCache();
        $cache->put($this->makeRegion(1, 'a', 'd'));
        $cache->put($this->makeRegion(2, 'd', 'h'));

        $cache->clear();

        $this->assertNull($cache->getByKey('a'));
        $this->assertNull($cache->getByKey('d'));
    }

    public function testTtlExpiresEntry(): void
    {
        $cache = new TestableRegionCache(1000, 600);
        $region = $this->makeRegion(1, 'a', 'z');
        $cache->put($region);

        $cache->setTime(1601);

        $this->assertNull($cache->getByKey('m'));
    }

    public function testTtlNotExpiredWithinWindow(): void
    {
        $cache = new TestableRegionCache(1000, 600);
        $region = $this->makeRegion(1, 'a', 'z');
        $cache->put($region);

        $cache->setTime(1599);

        $this->assertSame($region, $cache->getByKey('m'));
    }

    public function testPutResetsExistingTtl(): void
    {
        $cache = new TestableRegionCache(1000, 600);
        $region = $this->makeRegion(1, 'a', 'z');
        $cache->put($region);

        $cache->setTime(1500);
        $cache->put($region);

        $cache->setTime(2099);
        $this->assertSame($region, $cache->getByKey('m'));

        $cache->setTime(2101);
        $this->assertNull($cache->getByKey('m'));
    }

    public function testEmptyStartKeyRegion(): void
    {
        $cache = new RegionCache();
        $region = $this->makeRegion(1, '', 'd');
        $cache->put($region);

        $this->assertSame($region, $cache->getByKey(''));
        $this->assertSame($region, $cache->getByKey('a'));
        $this->assertNull($cache->getByKey('d'));
    }

    public function testSingleUnboundedRegion(): void
    {
        $cache = new RegionCache();
        $region = $this->makeRegion(1, '', '');
        $cache->put($region);

        $this->assertSame($region, $cache->getByKey(''));
        $this->assertSame($region, $cache->getByKey('a'));
        $this->assertSame($region, $cache->getByKey('z'));
        $this->assertSame($region, $cache->getByKey('anything'));
    }

    public function testInsertOrderDoesNotMatter(): void
    {
        $cache = new RegionCache();
        $region1 = $this->makeRegion(1, 'a', 'd');
        $region2 = $this->makeRegion(2, 'd', 'h');
        $region3 = $this->makeRegion(3, 'h', '');

        $cache->put($region3);
        $cache->put($region1);
        $cache->put($region2);

        $this->assertSame($region1, $cache->getByKey('a'));
        $this->assertSame($region1, $cache->getByKey('c'));
        $this->assertSame($region2, $cache->getByKey('d'));
        $this->assertSame($region2, $cache->getByKey('f'));
        $this->assertSame($region3, $cache->getByKey('h'));
        $this->assertSame($region3, $cache->getByKey('z'));
    }
}
