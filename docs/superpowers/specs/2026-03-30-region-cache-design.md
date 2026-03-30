# Region Cache — Interval-based with Binary Search

## Problem

Current region cache in `RawKvClient` uses raw key as cache key (`$regionCache[$key] = RegionInfo`). Each unique key triggers a PD round-trip even when keys belong to the same region. For `batchGet` with 1000 keys in one region, this means 1000 PD lookups instead of 1.

## Solution

Replace the per-key cache with a sorted array of `RegionInfo` entries indexed by `startKey`. Use binary search to find the region containing a given key by checking `startKey <= key < endKey`.

## Interface

```php
interface RegionCacheInterface
{
    public function getByKey(string $key): ?RegionInfo;
    public function put(RegionInfo $region): void;
    public function invalidate(int $regionId): void;
    public function clear(): void;
}
```

## RegionCache Implementation

- Sorted array `RegionInfo[]` ordered by `startKey`
- Separate `int[] $ttls` array keyed by `regionId` with expiration timestamps
- TTL = 600s + random jitter 0-60s (matches Go client `regionCacheTTLSec = 600`, `regionCacheTTLJitterSec = 60`)
- Configurable via constructor: `__construct(int $ttlSeconds = 600, int $jitterSeconds = 60)`
- Binary search: find last region where `startKey <= $key`, then verify `$key < endKey` (or `endKey === ''` for last region)
- `put()`: replace existing region with same ID or insert at correct position to maintain sort order
- `invalidate()`: remove by `regionId`
- Expired entries removed lazily on `getByKey()` (no background GC needed in PHP)
- Time source: `protected function now(): int` returning `time()`, overridable in tests

## RawKvClient Integration

- Replace `private array $regionCache` with `private RegionCacheInterface $regionCache`
- Constructor accepts optional `?RegionCacheInterface $regionCache = null`, defaults to `new RegionCache()`
- `getRegionInfo(string $key)`: check cache first, PD lookup on miss, `put()` result into cache
- `clearRegionCache(string $key)` becomes `invalidateRegion(int $regionId)` — invalidate by region ID, not key
- `executeWithRetry`: on `EpochNotMatch`, call `$this->regionCache->invalidate($region->regionId)`
- `groupKeysByRegion`: first key cache hit serves all keys in same region automatically

## File Structure

New files:
- `src/Client/Cache/RegionCacheInterface.php`
- `src/Client/Cache/RegionCache.php`
- `tests/Unit/Cache/RegionCacheTest.php`

Modified:
- `src/Client/RawKv/RawKvClient.php`
- `tests/Unit/RawKv/RawKvClientTest.php`

## Testing

- **RegionCacheTest**: getByKey hit/miss, put/invalidate, TTL expiry (mockable time via `now()`), binary search edge cases (key at region boundary, empty endKey, multiple regions), clear
- **RawKvClientTest**: updated — mock `RegionCacheInterface`, verify cache hit skips PD, verify invalidation on EpochNotMatch
- **E2E tests**: no changes expected
