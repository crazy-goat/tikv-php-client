# Region Cache Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace per-key region cache with interval-based sorted array + binary search, reducing PD round-trips from O(keys) to O(regions).

**Architecture:** A `RegionCache` class stores `RegionInfo` entries sorted by `startKey`. Binary search finds the region containing a given key by checking `startKey <= key < endKey`. TTL = 600s + 0-60s jitter (Go client default). Exposed via `RegionCacheInterface` for testability. Integrated into `RawKvClient` as a constructor dependency.

**Tech Stack:** PHP 8.2+, PHPUnit 11, no external dependencies.

---

### Task 1: RegionCacheInterface

**Files:**
- Create: `src/Client/Cache/RegionCacheInterface.php`

- [ ] **Step 1: Create the interface**

```php
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
```

- [ ] **Step 2: Run lint**

Run: `composer lint`
Expected: PASS (no errors)

- [ ] **Step 3: Commit**

```bash
git add src/Client/Cache/RegionCacheInterface.php
git commit -m "feat: add RegionCacheInterface"
```

---

### Task 2: RegionCache implementation

**Files:**
- Create: `src/Client/Cache/RegionCache.php`
- Create: `tests/Unit/Cache/RegionCacheTest.php`

- [ ] **Step 1: Write the test file**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Cache;

use CrazyGoat\TiKV\Client\Cache\RegionCache;
use CrazyGoat\TiKV\Client\Cache\RegionCacheInterface;
use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;
use PHPUnit\Framework\TestCase;

class TestableRegionCache extends RegionCache
{
    private int $fakeTime;

    public function __construct(int $fakeTime, int $ttlSeconds = 600, int $jitterSeconds = 0)
    {
        parent::__construct($ttlSeconds, $jitterSeconds);
        $this->fakeTime = $fakeTime;
    }

    protected function now(): int
    {
        return $this->fakeTime;
    }

    public function setTime(int $time): void
    {
        $this->fakeTime = $time;
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
        $this->assertInstanceOf(RegionCacheInterface::class, new RegionCache());
    }

    public function testGetByKeyReturnsNullOnEmptyCache(): void
    {
        $cache = new RegionCache();
        $this->assertNull($cache->getByKey('anything'));
    }

    public function testPutAndGetByKeyHit(): void
    {
        $cache = new RegionCache();
        $region = $this->makeRegion(1, 'a', 'z');
        $cache->put($region);

        $this->assertSame($region, $cache->getByKey('m'));
    }

    public function testGetByKeyMissOutsideRange(): void
    {
        $cache = new RegionCache();
        $cache->put($this->makeRegion(1, 'b', 'd'));

        $this->assertNull($cache->getByKey('a'));
        $this->assertNull($cache->getByKey('d'));
        $this->assertNull($cache->getByKey('z'));
    }

    public function testGetByKeyAtStartKeyBoundary(): void
    {
        $cache = new RegionCache();
        $cache->put($this->makeRegion(1, 'b', 'd'));

        $this->assertNotNull($cache->getByKey('b'));
    }

    public function testGetByKeyEmptyEndKeyMeansUnbounded(): void
    {
        $cache = new RegionCache();
        $cache->put($this->makeRegion(1, 'a', ''));

        $this->assertNotNull($cache->getByKey('a'));
        $this->assertNotNull($cache->getByKey('z'));
        $this->assertNotNull($cache->getByKey("\xff"));
    }

    public function testMultipleRegionsBinarySearch(): void
    {
        $cache = new RegionCache();
        $r1 = $this->makeRegion(1, 'a', 'd');
        $r2 = $this->makeRegion(2, 'd', 'h');
        $r3 = $this->makeRegion(3, 'h', '');

        $cache->put($r1);
        $cache->put($r2);
        $cache->put($r3);

        $this->assertSame($r1, $cache->getByKey('a'));
        $this->assertSame($r1, $cache->getByKey('c'));
        $this->assertSame($r2, $cache->getByKey('d'));
        $this->assertSame($r2, $cache->getByKey('f'));
        $this->assertSame($r3, $cache->getByKey('h'));
        $this->assertSame($r3, $cache->getByKey('z'));
    }

    public function testPutReplacesExistingRegionById(): void
    {
        $cache = new RegionCache();
        $cache->put($this->makeRegion(1, 'a', 'd'));

        $updated = $this->makeRegion(1, 'a', 'f');
        $cache->put($updated);

        $this->assertSame($updated, $cache->getByKey('e'));
        $this->assertNull($cache->getByKey('g'));
    }

    public function testInvalidateRemovesRegion(): void
    {
        $cache = new RegionCache();
        $cache->put($this->makeRegion(1, 'a', 'z'));

        $cache->invalidate(1);

        $this->assertNull($cache->getByKey('m'));
    }

    public function testInvalidateNonExistentIsNoop(): void
    {
        $cache = new RegionCache();
        $cache->invalidate(999);
        $this->assertNull($cache->getByKey('a'));
    }

    public function testClearRemovesAll(): void
    {
        $cache = new RegionCache();
        $cache->put($this->makeRegion(1, 'a', 'd'));
        $cache->put($this->makeRegion(2, 'd', ''));

        $cache->clear();

        $this->assertNull($cache->getByKey('b'));
        $this->assertNull($cache->getByKey('e'));
    }

    public function testTtlExpiresEntry(): void
    {
        $cache = new TestableRegionCache(1000, 600, 0);
        $cache->put($this->makeRegion(1, 'a', 'z'));

        $this->assertNotNull($cache->getByKey('m'));

        $cache->setTime(1601);
        $this->assertNull($cache->getByKey('m'));
    }

    public function testTtlNotExpiredWithinWindow(): void
    {
        $cache = new TestableRegionCache(1000, 600, 0);
        $cache->put($this->makeRegion(1, 'a', 'z'));

        $cache->setTime(1599);
        $this->assertNotNull($cache->getByKey('m'));
    }

    public function testPutResetsExistingTtl(): void
    {
        $cache = new TestableRegionCache(1000, 600, 0);
        $cache->put($this->makeRegion(1, 'a', 'z'));

        $cache->setTime(1500);
        $cache->put($this->makeRegion(1, 'a', 'z'));

        $cache->setTime(2099);
        $this->assertNotNull($cache->getByKey('m'));

        $cache->setTime(2101);
        $this->assertNull($cache->getByKey('m'));
    }

    public function testEmptyStartKeyRegion(): void
    {
        $cache = new RegionCache();
        $cache->put($this->makeRegion(1, '', 'd'));

        $this->assertNotNull($cache->getByKey(''));
        $this->assertNotNull($cache->getByKey('a'));
        $this->assertNull($cache->getByKey('d'));
    }

    public function testSingleUnboundedRegion(): void
    {
        $cache = new RegionCache();
        $cache->put($this->makeRegion(1, '', ''));

        $this->assertNotNull($cache->getByKey(''));
        $this->assertNotNull($cache->getByKey('anything'));
        $this->assertNotNull($cache->getByKey("\xff\xff"));
    }

    public function testInsertOrderDoesNotMatter(): void
    {
        $cache = new RegionCache();
        $r3 = $this->makeRegion(3, 'h', '');
        $r1 = $this->makeRegion(1, 'a', 'd');
        $r2 = $this->makeRegion(2, 'd', 'h');

        $cache->put($r3);
        $cache->put($r1);
        $cache->put($r2);

        $this->assertSame($r1, $cache->getByKey('b'));
        $this->assertSame($r2, $cache->getByKey('e'));
        $this->assertSame($r3, $cache->getByKey('z'));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Cache/RegionCacheTest.php`
Expected: FAIL — `RegionCache` class not found

- [ ] **Step 3: Write the RegionCache implementation**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Cache;

use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;

class RegionCache implements RegionCacheInterface
{
    /** @var RegionInfo[] sorted by startKey */
    private array $regions = [];

    /** @var array<int, int> regionId => expiration timestamp */
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

        return $region;
    }

    public function put(RegionInfo $region): void
    {
        $this->removeById($region->regionId);

        $pos = $this->findInsertPosition($region->startKey);
        array_splice($this->regions, $pos, 0, [$region]);

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
        $regions = $this->regions;
        $count = count($regions);
        if ($count === 0) {
            return null;
        }

        $low = 0;
        $high = $count - 1;
        $result = -1;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            if (strcmp($regions[$mid]->startKey, $key) <= 0) {
                $result = $mid;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        if ($result === -1) {
            return null;
        }

        $region = $regions[$result];
        if ($region->endKey !== '' && strcmp($key, $region->endKey) >= 0) {
            return null;
        }

        return $result;
    }

    private function findInsertPosition(string $startKey): int
    {
        $count = count($this->regions);
        $low = 0;
        $high = $count;

        while ($low < $high) {
            $mid = intdiv($low + $high, 2);
            if (strcmp($this->regions[$mid]->startKey, $startKey) < 0) {
                $low = $mid + 1;
            } else {
                $high = $mid;
            }
        }

        return $low;
    }

    private function removeById(int $regionId): void
    {
        foreach ($this->regions as $i => $region) {
            if ($region->regionId === $regionId) {
                $this->removeByIndex($i);
                return;
            }
        }
    }

    private function removeByIndex(int $index): void
    {
        $regionId = $this->regions[$index]->regionId;
        array_splice($this->regions, $index, 1);
        unset($this->ttls[$regionId]);
    }

    private function isExpired(int $regionId): bool
    {
        if (!isset($this->ttls[$regionId])) {
            return true;
        }

        return $this->now() > $this->ttls[$regionId];
    }

    private function jitter(): int
    {
        if ($this->jitterSeconds <= 0) {
            return 0;
        }

        return random_int(0, $this->jitterSeconds);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Cache/RegionCacheTest.php --testdox`
Expected: PASS — all tests green

- [ ] **Step 5: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Client/Cache/RegionCache.php tests/Unit/Cache/RegionCacheTest.php
git commit -m "feat: add RegionCache with sorted array and binary search"
```

---

### Task 3: Integrate RegionCache into RawKvClient

**Files:**
- Modify: `src/Client/RawKv/RawKvClient.php`
- Modify: `tests/Unit/RawKv/RawKvClientTest.php`

- [ ] **Step 1: Update RawKvClient constructor and fields**

In `src/Client/RawKv/RawKvClient.php`:

Replace the old cache property and constructor:

```php
    /** @var array<string, RegionInfo> */
    private array $regionCache = [];
```

with nothing (remove it).

Update the `create` factory method to pass a `RegionCache`:

```php
    public static function create(array $pdEndpoints): self
    {
        $grpc = new GrpcClient();
        $pdClient = new PdClient($grpc, $pdEndpoints[0]);

        return new self($pdClient, new GrpcClient(), new RegionCache());
    }
```

Update the constructor to accept `RegionCacheInterface`:

```php
    public function __construct(
        private readonly PdClientInterface $pdClient,
        private readonly GrpcClientInterface $grpc,
        private readonly RegionCacheInterface $regionCache = new RegionCache(),
        private readonly int $maxRetries = 3,
    ) {
    }
```

Add imports at the top of the file:

```php
use CrazyGoat\TiKV\Client\Cache\RegionCache;
use CrazyGoat\TiKV\Client\Cache\RegionCacheInterface;
```

- [ ] **Step 2: Replace getRegionInfo and clearRegionCache**

Replace the old `getRegionInfo` method:

```php
    private function getRegionInfo(string $key): RegionInfo
    {
        if (!isset($this->regionCache[$key])) {
            $this->regionCache[$key] = $this->pdClient->getRegion($key);
        }

        return $this->regionCache[$key];
    }
```

with:

```php
    private function getRegionInfo(string $key): RegionInfo
    {
        $region = $this->regionCache->getByKey($key);
        if ($region !== null) {
            return $region;
        }

        $region = $this->pdClient->getRegion($key);
        $this->regionCache->put($region);

        return $region;
    }
```

Remove the old `clearRegionCache` method entirely:

```php
    private function clearRegionCache(string $key): void
    {
        unset($this->regionCache[$key]);
    }
```

- [ ] **Step 3: Update executeWithRetry to invalidate by region ID**

The current `executeWithRetry` takes a `string $key` and calls `clearRegionCache($key)`. Change it to accept a `RegionInfo` and invalidate by region ID.

Replace:

```php
    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function executeWithRetry(string $key, callable $operation): mixed
    {
        $lastException = null;

        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (TiKvException $e) {
                $lastException = $e;

                if (str_contains($e->getMessage(), 'EpochNotMatch')) {
                    $this->clearRegionCache($key);
                    continue;
                }

                throw $e;
            }
        }

        throw $lastException ?? new TiKvException('Max retries exceeded');
    }
```

with:

```php
    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function executeWithRetry(int $regionId, callable $operation): mixed
    {
        $lastException = null;

        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (TiKvException $e) {
                $lastException = $e;

                if (str_contains($e->getMessage(), 'EpochNotMatch')) {
                    $this->regionCache->invalidate($regionId);
                    continue;
                }

                throw $e;
            }
        }

        throw $lastException ?? new TiKvException('Max retries exceeded');
    }
```

- [ ] **Step 4: Update all executeWithRetry call sites in single-key operations**

Each single-key operation currently calls `$this->executeWithRetry($key, function () use ($key) { ... })`. Change them to get the region first, then pass `$region->regionId`.

For `get()` — replace:

```php
        return $this->executeWithRetry($key, function () use ($key): ?string {
            $region = $this->getRegionInfo($key);
```

with:

```php
        $region = $this->getRegionInfo($key);
        return $this->executeWithRetry($region->regionId, function () use ($key, $region): ?string {
```

For `put()` — replace:

```php
        $this->executeWithRetry($key, function () use ($key, $value, $ttl): null {
            $region = $this->getRegionInfo($key);
```

with:

```php
        $region = $this->getRegionInfo($key);
        $this->executeWithRetry($region->regionId, function () use ($key, $value, $ttl, $region): null {
```

For `delete()` — replace:

```php
        $this->executeWithRetry($key, function () use ($key): null {
            $region = $this->getRegionInfo($key);
```

with:

```php
        $region = $this->getRegionInfo($key);
        $this->executeWithRetry($region->regionId, function () use ($key, $region): null {
```

For `getKeyTTL()` — replace:

```php
        return $this->executeWithRetry($key, function () use ($key): ?int {
            $region = $this->getRegionInfo($key);
```

with:

```php
        $region = $this->getRegionInfo($key);
        return $this->executeWithRetry($region->regionId, function () use ($key, $region): ?int {
```

For `compareAndSwap()` — replace:

```php
        return $this->executeWithRetry($key, function () use ($key, $expectedValue, $newValue, $ttl): CasResult {
            $region = $this->getRegionInfo($key);
```

with:

```php
        $region = $this->getRegionInfo($key);
        return $this->executeWithRetry($region->regionId, function () use ($key, $expectedValue, $newValue, $ttl, $region): CasResult {
```

- [ ] **Step 5: Update executeWithRetry call sites in region-level executors**

For `executeBatchGetForRegion()` — replace:

```php
        return $this->executeWithRetry($keys[0], function () use ($region, $keys): array {
```

with:

```php
        return $this->executeWithRetry($region->regionId, function () use ($region, $keys): array {
```

For `executeBatchPutForRegion()` — replace:

```php
        $this->executeWithRetry($pairs[0]->getKey(), function () use ($region, $pairs, $ttl): null {
```

with:

```php
        $this->executeWithRetry($region->regionId, function () use ($region, $pairs, $ttl): null {
```

For `executeBatchDeleteForRegion()` — replace:

```php
        $this->executeWithRetry($keys[0], function () use ($region, $keys): null {
```

with:

```php
        $this->executeWithRetry($region->regionId, function () use ($region, $keys): null {
```

For `executeScanForRegion()` — the callback variable pattern. Replace:

```php
        return $this->executeWithRetry($startKey, $callback);
```

with:

```php
        return $this->executeWithRetry($region->regionId, $callback);
```

For `executeDeleteRangeForRegion()` — replace:

```php
        $this->executeWithRetry($startKey, function () use ($region, $startKey, $endKey): null {
```

with:

```php
        $this->executeWithRetry($region->regionId, function () use ($region, $startKey, $endKey): null {
```

For `executeChecksumForRegion()` — replace:

```php
        return $this->executeWithRetry($startKey, function () use ($region, $startKey, $endKey): ChecksumResult {
```

with:

```php
        return $this->executeWithRetry($region->regionId, function () use ($region, $startKey, $endKey): ChecksumResult {
```

- [ ] **Step 6: Update RawKvClientTest setUp to inject mock cache**

In `tests/Unit/RawKv/RawKvClientTest.php`, add the cache mock and update setUp:

Add import:

```php
use CrazyGoat\TiKV\Client\Cache\RegionCacheInterface;
```

Add property:

```php
    private RegionCacheInterface&MockObject $regionCache;
```

Update setUp:

```php
    protected function setUp(): void
    {
        $this->pdClient = $this->createMock(PdClientInterface::class);
        $this->grpc = $this->createMock(GrpcClientInterface::class);
        $this->regionCache = $this->createMock(RegionCacheInterface::class);
        $this->client = new RawKvClient($this->pdClient, $this->grpc, $this->regionCache);
    }
```

- [ ] **Step 7: Update test helper and test methods that set up region expectations**

The `defaultRegion()` helper stays the same. But tests that mock `$this->pdClient->method('getRegion')` need to also handle the cache mock.

For tests where the cache should miss (forcing PD lookup), add cache mock setup. Find all tests that call `$this->pdClient->method('getRegion')` and add before them:

```php
        $this->regionCache->method('getByKey')->willReturn(null);
        $this->regionCache->method('put');
```

For tests that don't need region lookup (closed state tests, empty batch tests), no change needed.

Specifically update these test methods:
- `testGetReturnsValue` — add cache miss mock
- `testGetReturnsNullForMissingKey` — add cache miss mock
- `testGetThrowsStoreNotFoundWhenStoreIsNull` — add cache miss mock
- `testPutCallsGrpc` — add cache miss mock
- `testDeleteCallsGrpc` — add cache miss mock
- `testBatchGetReturnsOrderedResults` — add cache miss mock
- `testBatchGetReturnsNullForMissingKeys` — add cache miss mock
- `testScanReturnsResults` — add cache miss mock (via `scanRegions`)
- `testBatchScanThrowsOnInvalidRangeFormat` — add cache miss mock (via `scanRegions`)
- `testCompareAndSwapSuccess` — add cache miss mock
- `testCompareAndSwapFailure` — add cache miss mock
- `testPutIfAbsentReturnsNullOnSuccess` — add cache miss mock
- `testPutIfAbsentReturnsExistingValue` — add cache miss mock
- `testChecksumReturnsResult` — add cache miss mock
- `testGetKeyTTLReturnsValue` — add cache miss mock
- `testGetKeyTTLReturnsNullWhenNotFound` — add cache miss mock
- `testGetKeyTTLReturnsNullWhenZero` — add cache miss mock

- [ ] **Step 8: Run unit tests**

Run: `vendor/bin/phpunit tests/Unit/ --testdox`
Expected: PASS — all tests green

- [ ] **Step 9: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 10: Commit**

```bash
git add src/Client/RawKv/RawKvClient.php tests/Unit/RawKv/RawKvClientTest.php
git commit -m "feat: integrate RegionCache into RawKvClient"
```

---

### Task 4: Final verification

**Files:** none (verification only)

- [ ] **Step 1: Run full unit test suite**

Run: `vendor/bin/phpunit tests/Unit/ --testdox`
Expected: All tests pass

- [ ] **Step 2: Run lint**

Run: `composer lint`
Expected: PASS — phpcs, rector, phpstan all clean

- [ ] **Step 3: Run E2E tests**

Run: `docker compose run --rm php-test vendor/bin/phpunit --testsuite E2E --testdox`
Expected: All 141 E2E tests pass

- [ ] **Step 4: Commit plan doc**

```bash
git add docs/superpowers/plans/2026-03-30-region-cache.md
git commit -m "docs: add region cache implementation plan"
```
