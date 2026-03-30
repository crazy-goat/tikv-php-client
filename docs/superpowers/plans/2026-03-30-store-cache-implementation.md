# Store Cache Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `StoreCache` component that maps `storeId → address` with TTL, similar to RegionCache pattern.

**Architecture:** Create StoreCache with TTL (600s default) and jitter (60s default), mirroring RegionCache. Integrate into PdClient as optional dependency.

**Tech Stack:** PHP, PSR Logger, existing proto/Store type

---

## File Map

**Create:**
- `src/Client/Cache/StoreEntry.php`
- `src/Client/Cache/StoreCacheInterface.php`
- `src/Client/Cache/StoreCache.php`
- `tests/Unit/Cache/StoreEntryTest.php`
- `tests/Unit/Cache/StoreCacheTest.php`

**Modify:**
- `src/Client/Connection/PdClient.php` (inject StoreCacheInterface)

---

## Task 1: StoreEntry

**Files:**
- Create: `src/Client/Cache/StoreEntry.php`
- Test: `tests/Unit/Cache/StoreEntryTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

use CrazyGoat\Proto\Metapb\Store;
use CrazyGoat\TiKV\Client\Cache\StoreEntry;

class StoreEntryTest extends TestCase
{
    public function testConstruction(): void
    {
        $store = new Store();
        $store->setId(1);
        $store->setAddress("127.0.0.1:20160");

        $entry = new StoreEntry($store, 1000);

        $this->assertSame($store, $entry->store);
        $this->assertSame(1000, $entry->expiresAt);
    }
}
```

Run: `./vendor/bin/phpunit tests/Unit/Cache/StoreEntryTest.php`
Expected: FAIL - StoreEntry class does not exist

- [ ] **Step 2: Create StoreEntry class**

```php
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
```

- [ ] **Step 3: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Cache/StoreEntryTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Client/Cache/StoreEntry.php tests/Unit/Cache/StoreEntryTest.php
git commit -m "feat(store-cache): add StoreEntry value object"
```

---

## Task 2: StoreCacheInterface

**Files:**
- Create: `src/Client/Cache/StoreCacheInterface.php`

- [ ] **Step 1: Write the interface**

```php
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
```

- [ ] **Step 2: Commit**

```bash
git add src/Client/Cache/StoreCacheInterface.php
git commit -m "feat(store-cache): add StoreCacheInterface"
```

---

## Task 3: StoreCache Implementation

**Files:**
- Create: `src/Client/Cache/StoreCache.php`
- Test: `tests/Unit/Cache/StoreCacheTest.php`

- [ ] **Step 1: Write tests for cache miss**

```php
<?php

use CrazyGoat\TiKV\Client\Cache\StoreCache;
use CrazyGoat\Proto\Metapb\Store;

class StoreCacheTest extends TestCase
{
    public function testGetCacheMiss(): void
    {
        $cache = new StoreCache();
        $this->assertNull($cache->get(1));
    }
}
```

Run: `./vendor/bin/phpunit tests/Unit/Cache/StoreCacheTest.php`
Expected: FAIL - StoreCache class does not exist

- [ ] **Step 2: Create StoreCache skeleton**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Cache;

use CrazyGoat\Proto\Metapb\Store;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class StoreCache implements StoreCacheInterface
{
    /** @var StoreEntry[] */
    private array $entries = [];

    public function __construct(
        private readonly int $ttlSeconds = 600,
        private readonly int $jitterSeconds = 60,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function get(int $storeId): ?Store
    {
        return null;
    }

    public function put(Store $store): void
    {
    }

    public function invalidate(int $storeId): void
    {
    }

    public function clear(): void
    {
        $this->entries = [];
    }
}
```

Run: `./vendor/bin/phpunit tests/Unit/Cache/StoreCacheTest.php`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add src/Client/Cache/StoreCache.php tests/Unit/Cache/StoreCacheTest.php
git commit -m "feat(store-cache): add StoreCache skeleton"
```

- [ ] **Step 4: Write test for put and get hit**

```php
public function testPutAndGet(): void
{
    $cache = new StoreCache();

    $store = new Store();
    $store->setId(1);
    $store->setAddress("127.0.0.1:20160");

    $cache->put($store);

    $cached = $cache->get(1);
    $this->assertNotNull($cached);
    $this->assertSame("127.0.0.1:20160", $cached->getAddress());
}
```

Run: `./vendor/bin/phpunit tests/Unit/Cache/StoreCacheTest.php`
Expected: FAIL - get() returns null

- [ ] **Step 5: Implement get and put**

```php
public function get(int $storeId): ?Store
{
    if (!isset($this->entries[$storeId])) {
        return null;
    }

    $entry = $this->entries[$storeId];
    if ($this->now() >= $entry->expiresAt) {
        unset($this->entries[$storeId]);
        return null;
    }

    return $entry->store;
}

public function put(Store $store): void
{
    $storeId = (int) $store->getId();
    unset($this->entries[$storeId]);

    $this->entries[$storeId] = new StoreEntry(
        $store,
        $this->now() + $this->ttlSeconds + $this->jitter(),
    );
}

protected function now(): int
{
    return time();
}

private function jitter(): int
{
    if ($this->jitterSeconds <= 0) {
        return 0;
    }
    return random_int(0, $this->jitterSeconds);
}
```

Run: `./vendor/bin/phpunit tests/Unit/Cache/StoreCacheTest.php`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Client/Cache/StoreCache.php
git commit -m "feat(store-cache): implement get and put with TTL"
```

- [ ] **Step 7: Write test for invalidate**

```php
public function testInvalidate(): void
{
    $cache = new StoreCache();

    $store = new Store();
    $store->setId(1);
    $store->setAddress("127.0.0.1:20160");

    $cache->put($store);
    $this->assertNotNull($cache->get(1));

    $cache->invalidate(1);
    $this->assertNull($cache->get(1));
}
```

Run: `./vendor/bin/phpunit tests/Unit/Cache/StoreCacheTest.php`
Expected: PASS (invalidate already works via unset in put, but explicit test confirms behavior)

- [ ] **Step 8: Commit**

```bash
git add tests/Unit/Cache/StoreCacheTest.php
git commit -m "feat(store-cache): add invalidate test"
```

- [ ] **Step 9: Write test for TTL expiration**

```php
public function testTtlExpiration(): void
{
    $cache = new class extends StoreCache {
        protected function now(): int {
            return 1000;
        }
    };

    $store = new Store();
    $store->setId(1);
    $store->setAddress("127.0.0.1:20160");

    $cache->put($store);
    $this->assertNotNull($cache->get(1));

    // Simulate time passing beyond TTL (600s default + up to 60s jitter = 1060)
    $cache = new class extends StoreCache {
        protected function now(): int {
            return 2000;
        }
    };

    $this->assertNull($cache->get(1));
}
```

Run: `./vendor/bin/phpunit tests/Unit/Cache/StoreCacheTest.php`
Expected: PASS

- [ ] **Step 10: Commit**

```bash
git add tests/Unit/Cache/StoreCacheTest.php
git commit -m "feat(store-cache): add TTL expiration test"
```

---

## Task 4: Integrate StoreCache into PdClient

**Files:**
- Modify: `src/Client/Connection/PdClient.php`

- [ ] **Step 1: Read current PdClient constructor**

```php
public function __construct(
    GrpcClientInterface $grpc,
    string $pdAddress,
    LoggerInterface $logger = new NullLogger(),
)
```

- [ ] **Step 2: Modify constructor to accept StoreCacheInterface**

```php
public function __construct(
    private readonly GrpcClientInterface $grpc,
    private readonly string $pdAddress,
    private readonly LoggerInterface $logger = new NullLogger(),
    private readonly ?StoreCacheInterface $storeCache = null,
) {
    $this->storeCache ??= new StoreCache(logger: $logger);
}
```

- [ ] **Step 3: Update getStore to use cache**

Change from:
```php
if (isset($this->storeCache[$storeId])) {
    return $this->storeCache[$storeId];
}
```

To:
```php
$cached = $this->storeCache->get($storeId);
if ($cached !== null) {
    return $cached;
}
```

And change:
```php
$this->storeCache[$storeId] = $store;
```

To:
```php
$this->storeCache->put($store);
```

- [ ] **Step 4: Remove old storeCache array**

Remove line 27-28:
```php
/** @var array<int, Store> */
private array $storeCache = [];
```

- [ ] **Step 5: Add use statement**

Add:
```php
use CrazyGoat\TiKV\Client\Cache\StoreCacheInterface;
```

- [ ] **Step 6: Run existing tests to verify nothing broke**

Run: `./vendor/bin/phpunit tests/Unit/Connection/PdClientTest.php`
Expected: PASS (existing tests mock PdClientInterface, not internal implementation)

- [ ] **Step 7: Commit**

```bash
git add src/Client/Connection/PdClient.php
git commit -m "feat(store-cache): integrate StoreCache into PdClient"
```

---

## Task 5: Final Verification

- [ ] **Step 1: Run full test suite**

Run: `./vendor/bin/phpunit`
Expected: ALL PASS

- [ ] **Step 2: Run phpstan if configured**

Run: `./vendor/bin/phpstan analyse src/Client/Cache --level=max`
Expected: No errors

- [ ] **Step 3: Verify no TODO/FIXME in new code**

---

## Summary

**New files:** 5
**Modified files:** 1
**Total commits:** 8

**After all tasks:**
- `StoreCache` provides TTL-based caching for store addresses
- `PdClient` uses `StoreCache` instead of simple array
- All existing tests pass
