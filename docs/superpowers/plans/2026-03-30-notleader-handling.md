# NotLeader Handling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Handle NotLeader errors from TiKV by switching to the correct leader peer locally (using the leader hint), or invalidating the region cache when no hint is available.

**Architecture:** Store all peers per region in `RegionInfo`. Wrap `RegionInfo` in a mutable `RegionEntry` inside the cache for leader switching. Check `getRegionError()` on all TiKV responses to surface NotLeader errors. Handle NotLeader before `classifyError()` in the retry loop.

**Tech Stack:** PHP 8.2+, PHPUnit 11, protobuf (google/protobuf), gRPC, PSR-3 logging

**Spec:** `docs/superpowers/specs/2026-03-30-notleader-handling-design.md`

**Lint:** `composer lint` (phpcs + rector + phpstan level 9)

**Unit tests:** `vendor/bin/phpunit tests/Unit/`

---

### Task 1: PeerInfo DTO

**Files:**
- Create: `src/Client/RawKv/Dto/PeerInfo.php`
- Create: `tests/Unit/RawKv/Dto/PeerInfoTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\RawKv\Dto;

use CrazyGoat\TiKV\Client\RawKv\Dto\PeerInfo;
use PHPUnit\Framework\TestCase;

class PeerInfoTest extends TestCase
{
    public function testConstruction(): void
    {
        $peer = new PeerInfo(peerId: 7, storeId: 3);

        $this->assertSame(7, $peer->peerId);
        $this->assertSame(3, $peer->storeId);
    }

    public function testIsReadonly(): void
    {
        $reflection = new \ReflectionClass(PeerInfo::class);
        $this->assertTrue($reflection->isReadOnly());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/RawKv/Dto/PeerInfoTest.php -v`
Expected: FAIL — class PeerInfo not found

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\RawKv\Dto;

final readonly class PeerInfo
{
    public function __construct(
        public int $peerId,
        public int $storeId,
    ) {
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/RawKv/Dto/PeerInfoTest.php -v`
Expected: 2 tests, 3 assertions, PASS

- [ ] **Step 5: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Client/RawKv/Dto/PeerInfo.php tests/Unit/RawKv/Dto/PeerInfoTest.php
git commit -m "feat: add PeerInfo readonly DTO"
```

---

### Task 2: Add peers field to RegionInfo

**Files:**
- Modify: `src/Client/RawKv/Dto/RegionInfo.php`

- [ ] **Step 1: Add the peers parameter**

In `src/Client/RawKv/Dto/RegionInfo.php`, add `peers` as the last constructor parameter with a default of `[]`:

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\RawKv\Dto;

final readonly class RegionInfo
{
    /**
     * @param list<PeerInfo> $peers
     */
    public function __construct(
        public int $regionId,
        public int $leaderPeerId,
        public int $leaderStoreId,
        public int $epochConfVer,
        public int $epochVersion,
        public string $startKey = '',
        public string $endKey = '',
        public array $peers = [],
    ) {
    }
}
```

- [ ] **Step 2: Run all existing tests to verify backward compatibility**

Run: `vendor/bin/phpunit tests/Unit/ -v`
Expected: All existing tests PASS (the `peers` field defaults to `[]`)

- [ ] **Step 3: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Client/RawKv/Dto/RegionInfo.php
git commit -m "feat: add peers field to RegionInfo DTO"
```

---

### Task 3: RegionEntry mutable cache wrapper

**Files:**
- Create: `src/Client/Cache/RegionEntry.php`
- Create: `tests/Unit/Cache/RegionEntryTest.php`

- [ ] **Step 1: Write the tests**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Cache;

use CrazyGoat\TiKV\Client\Cache\RegionEntry;
use CrazyGoat\TiKV\Client\RawKv\Dto\PeerInfo;
use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;
use PHPUnit\Framework\TestCase;

class RegionEntryTest extends TestCase
{
    private function makeRegionWithPeers(): RegionInfo
    {
        return new RegionInfo(
            regionId: 1,
            leaderPeerId: 10,
            leaderStoreId: 1,
            epochConfVer: 1,
            epochVersion: 1,
            startKey: 'a',
            endKey: 'z',
            peers: [
                new PeerInfo(peerId: 10, storeId: 1),
                new PeerInfo(peerId: 20, storeId: 2),
                new PeerInfo(peerId: 30, storeId: 3),
            ],
        );
    }

    public function testConstructionSetsLeaderFromRegionInfo(): void
    {
        $region = $this->makeRegionWithPeers();
        $entry = new RegionEntry($region, 1600);

        $this->assertSame(1, $entry->getLeaderStoreId());
        $this->assertSame(10, $entry->getLeaderPeerId());
        $this->assertSame(1600, $entry->expiresAt);
    }

    public function testSwitchLeaderWithKnownStoreIdReturnsTrue(): void
    {
        $entry = new RegionEntry($this->makeRegionWithPeers(), 1600);

        $result = $entry->switchLeader(3);

        $this->assertTrue($result);
        $this->assertSame(3, $entry->getLeaderStoreId());
        $this->assertSame(30, $entry->getLeaderPeerId());
    }

    public function testSwitchLeaderWithUnknownStoreIdReturnsFalse(): void
    {
        $entry = new RegionEntry($this->makeRegionWithPeers(), 1600);

        $result = $entry->switchLeader(99);

        $this->assertFalse($result);
        $this->assertSame(1, $entry->getLeaderStoreId());
        $this->assertSame(10, $entry->getLeaderPeerId());
    }

    public function testSwitchLeaderToSecondPeer(): void
    {
        $entry = new RegionEntry($this->makeRegionWithPeers(), 1600);

        $entry->switchLeader(2);

        $this->assertSame(2, $entry->getLeaderStoreId());
        $this->assertSame(20, $entry->getLeaderPeerId());
    }

    public function testSwitchLeaderWithNoPeersReturnsFalse(): void
    {
        $region = new RegionInfo(
            regionId: 1,
            leaderPeerId: 10,
            leaderStoreId: 1,
            epochConfVer: 1,
            epochVersion: 1,
        );
        $entry = new RegionEntry($region, 1600);

        $result = $entry->switchLeader(2);

        $this->assertFalse($result);
        $this->assertSame(1, $entry->getLeaderStoreId());
    }

    public function testSwitchLeaderToSameStoreIdReturnsTrue(): void
    {
        $entry = new RegionEntry($this->makeRegionWithPeers(), 1600);

        $result = $entry->switchLeader(1);

        $this->assertTrue($result);
        $this->assertSame(1, $entry->getLeaderStoreId());
        $this->assertSame(10, $entry->getLeaderPeerId());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Cache/RegionEntryTest.php -v`
Expected: FAIL — class RegionEntry not found

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Cache;

use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;

final class RegionEntry
{
    private int $leaderStoreId;
    private int $leaderPeerId;

    public function __construct(
        public readonly RegionInfo $region,
        public readonly int $expiresAt,
    ) {
        $this->leaderStoreId = $region->leaderStoreId;
        $this->leaderPeerId = $region->leaderPeerId;
    }

    public function getLeaderStoreId(): int
    {
        return $this->leaderStoreId;
    }

    public function getLeaderPeerId(): int
    {
        return $this->leaderPeerId;
    }

    public function switchLeader(int $leaderStoreId): bool
    {
        foreach ($this->region->peers as $peer) {
            if ($peer->storeId === $leaderStoreId) {
                $this->leaderStoreId = $peer->storeId;
                $this->leaderPeerId = $peer->peerId;
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/Cache/RegionEntryTest.php -v`
Expected: 6 tests, 14 assertions, PASS

- [ ] **Step 5: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Client/Cache/RegionEntry.php tests/Unit/Cache/RegionEntryTest.php
git commit -m "feat: add RegionEntry mutable cache wrapper"
```

---

### Task 4: Refactor RegionCache to use RegionEntry internally

**Files:**
- Modify: `src/Client/Cache/RegionCacheInterface.php`
- Modify: `src/Client/Cache/RegionCache.php`
- Modify: `tests/Unit/Cache/RegionCacheTest.php`

- [ ] **Step 1: Add switchLeader to RegionCacheInterface**

Replace the entire content of `src/Client/Cache/RegionCacheInterface.php`:

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Cache;

use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;

interface RegionCacheInterface
{
    /**
     * Look up the region that contains the given key.
     */
    public function getByKey(string $key): ?RegionInfo;

    /**
     * Store a region in the cache.
     */
    public function put(RegionInfo $region): void;

    /**
     * Remove a region from the cache by its ID.
     */
    public function invalidate(int $regionId): void;

    /**
     * Switch the leader of a cached region to the peer with the given store ID.
     *
     * Returns true if the peer was found and the leader was switched.
     * Returns false if the region is not cached or the store ID is not among known peers.
     */
    public function switchLeader(int $regionId, int $leaderStoreId): bool;

    /**
     * Remove all regions from the cache.
     */
    public function clear(): void;
}
```

- [ ] **Step 2: Refactor RegionCache to use RegionEntry internally**

Replace the entire content of `src/Client/Cache/RegionCache.php`:

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Cache;

use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class RegionCache implements RegionCacheInterface
{
    /** @var RegionEntry[] */
    private array $entries = [];

    public function __construct(
        private readonly int $ttlSeconds = 600,
        private readonly int $jitterSeconds = 60,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function getByKey(string $key): ?RegionInfo
    {
        $index = $this->binarySearch($key);
        if ($index === null) {
            $this->logger->debug('Region cache miss', ['key' => $key]);
            return null;
        }

        $entry = $this->entries[$index];

        if ($this->isExpired($entry)) {
            $this->removeByIndex($index);
            $this->logger->debug('Region cache miss', ['key' => $key]);
            return null;
        }

        if ($entry->region->endKey !== '' && $key >= $entry->region->endKey) {
            $this->logger->debug('Region cache miss', ['key' => $key]);
            return null;
        }

        $this->logger->debug('Region cache hit', ['key' => $key, 'regionId' => $entry->region->regionId]);

        return $this->resolveRegionInfo($entry);
    }

    public function put(RegionInfo $region): void
    {
        $this->removeById($region->regionId);

        $position = $this->findInsertPosition($region->startKey);
        $entry = new RegionEntry($region, $this->now() + $this->ttlSeconds + $this->jitter());
        array_splice($this->entries, $position, 0, [$entry]);

        $this->logger->debug('Region cached', [
            'regionId' => $region->regionId,
            'startKey' => $region->startKey,
            'endKey' => $region->endKey,
            'ttl' => $entry->expiresAt - $this->now(),
        ]);
    }

    public function invalidate(int $regionId): void
    {
        $this->logger->info('Region invalidated', ['regionId' => $regionId]);
        $this->removeById($regionId);
    }

    public function switchLeader(int $regionId, int $leaderStoreId): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry->region->regionId === $regionId) {
                $result = $entry->switchLeader($leaderStoreId);
                if ($result) {
                    $this->logger->info('Region leader switched', [
                        'regionId' => $regionId,
                        'newLeaderStoreId' => $leaderStoreId,
                    ]);
                }
                return $result;
            }
        }

        return false;
    }

    public function clear(): void
    {
        $this->entries = [];
    }

    protected function now(): int
    {
        return time();
    }

    private function binarySearch(string $key): ?int
    {
        $left = 0;
        $right = count($this->entries) - 1;
        $result = null;

        while ($left <= $right) {
            $mid = (int) (($left + $right) / 2);
            $entry = $this->entries[$mid];

            if ($entry->region->startKey <= $key) {
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
        $right = count($this->entries);

        while ($left < $right) {
            $mid = (int) (($left + $right) / 2);
            if ($this->entries[$mid]->region->startKey < $startKey) {
                $left = $mid + 1;
            } else {
                $right = $mid;
            }
        }

        return $left;
    }

    private function removeById(int $regionId): void
    {
        foreach ($this->entries as $index => $entry) {
            if ($entry->region->regionId === $regionId) {
                $this->removeByIndex($index);
                return;
            }
        }
    }

    private function removeByIndex(int $index): void
    {
        if (isset($this->entries[$index])) {
            array_splice($this->entries, $index, 1);
        }
    }

    private function isExpired(RegionEntry $entry): bool
    {
        return $this->now() >= $entry->expiresAt;
    }

    private function jitter(): int
    {
        if ($this->jitterSeconds <= 0) {
            return 0;
        }

        return random_int(0, $this->jitterSeconds);
    }

    private function resolveRegionInfo(RegionEntry $entry): RegionInfo
    {
        if ($entry->getLeaderStoreId() === $entry->region->leaderStoreId
            && $entry->getLeaderPeerId() === $entry->region->leaderPeerId) {
            return $entry->region;
        }

        return new RegionInfo(
            regionId: $entry->region->regionId,
            leaderPeerId: $entry->getLeaderPeerId(),
            leaderStoreId: $entry->getLeaderStoreId(),
            epochConfVer: $entry->region->epochConfVer,
            epochVersion: $entry->region->epochVersion,
            startKey: $entry->region->startKey,
            endKey: $entry->region->endKey,
            peers: $entry->region->peers,
        );
    }
}
```

- [ ] **Step 3: Add switchLeader tests to RegionCacheTest**

Add the following tests at the end of the `RegionCacheTest` class in `tests/Unit/Cache/RegionCacheTest.php`. Also update the `makeRegion` helper to accept optional peers, and add a new helper `makeRegionWithPeers`:

Add these two helper methods to the test class (after the existing `makeRegion` method):

```php
private function makeRegionWithPeers(int $id, string $startKey, string $endKey = ''): RegionInfo
{
    return new RegionInfo(
        regionId: $id,
        leaderPeerId: 10,
        leaderStoreId: 1,
        epochConfVer: 1,
        epochVersion: 1,
        startKey: $startKey,
        endKey: $endKey,
        peers: [
            new PeerInfo(peerId: 10, storeId: 1),
            new PeerInfo(peerId: 20, storeId: 2),
            new PeerInfo(peerId: 30, storeId: 3),
        ],
    );
}
```

Add `use CrazyGoat\TiKV\Client\RawKv\Dto\PeerInfo;` to the imports.

Add these test methods:

```php
public function testSwitchLeaderSucceedsAndGetByKeyReflectsNewLeader(): void
{
    $cache = new RegionCache();
    $region = $this->makeRegionWithPeers(1, 'a', 'z');
    $cache->put($region);

    $result = $cache->switchLeader(1, 3);

    $this->assertTrue($result);

    $resolved = $cache->getByKey('m');
    $this->assertNotNull($resolved);
    $this->assertSame(3, $resolved->leaderStoreId);
    $this->assertSame(30, $resolved->leaderPeerId);
    $this->assertSame(1, $resolved->regionId);
}

public function testSwitchLeaderWithUnknownStoreIdReturnsFalse(): void
{
    $cache = new RegionCache();
    $region = $this->makeRegionWithPeers(1, 'a', 'z');
    $cache->put($region);

    $result = $cache->switchLeader(1, 99);

    $this->assertFalse($result);

    $resolved = $cache->getByKey('m');
    $this->assertNotNull($resolved);
    $this->assertSame(1, $resolved->leaderStoreId);
}

public function testSwitchLeaderWithUnknownRegionIdReturnsFalse(): void
{
    $cache = new RegionCache();
    $region = $this->makeRegionWithPeers(1, 'a', 'z');
    $cache->put($region);

    $result = $cache->switchLeader(999, 2);

    $this->assertFalse($result);
}

public function testSwitchLeaderPreservesAllRegionFields(): void
{
    $cache = new RegionCache();
    $region = $this->makeRegionWithPeers(1, 'a', 'z');
    $cache->put($region);

    $cache->switchLeader(1, 2);

    $resolved = $cache->getByKey('m');
    $this->assertNotNull($resolved);
    $this->assertSame(1, $resolved->regionId);
    $this->assertSame(1, $resolved->epochConfVer);
    $this->assertSame(1, $resolved->epochVersion);
    $this->assertSame('a', $resolved->startKey);
    $this->assertSame('z', $resolved->endKey);
    $this->assertCount(3, $resolved->peers);
}

public function testGetByKeyReturnsOriginalRegionWhenLeaderNotSwitched(): void
{
    $cache = new RegionCache();
    $region = $this->makeRegionWithPeers(1, 'a', 'z');
    $cache->put($region);

    $resolved = $cache->getByKey('m');
    $this->assertSame($region, $resolved);
}

public function testSwitchLeaderLogsInfo(): void
{
    $logger = $this->createMock(LoggerInterface::class);
    $cache = new RegionCache(logger: $logger);
    $region = $this->makeRegionWithPeers(1, 'a', 'z');
    $cache->put($region);

    $logger->expects($this->once())
        ->method('info')
        ->with('Region leader switched', ['regionId' => 1, 'newLeaderStoreId' => 2]);

    $cache->switchLeader(1, 2);
}

public function testSwitchLeaderFailureDoesNotLog(): void
{
    $logger = $this->createMock(LoggerInterface::class);
    $cache = new RegionCache(logger: $logger);
    $region = $this->makeRegionWithPeers(1, 'a', 'z');
    $cache->put($region);

    $logger->expects($this->never())
        ->method('info')
        ->with('Region leader switched', $this->anything());

    $cache->switchLeader(1, 99);
}

public function testSwitchLeaderOnEmptyCacheReturnsFalse(): void
{
    $cache = new RegionCache();

    $this->assertFalse($cache->switchLeader(1, 2));
}
```

- [ ] **Step 4: Run all cache tests**

Run: `vendor/bin/phpunit tests/Unit/Cache/ -v`
Expected: All existing + new tests PASS

- [ ] **Step 5: Run full unit test suite to verify no regressions**

Run: `vendor/bin/phpunit tests/Unit/ -v`
Expected: All tests PASS

- [ ] **Step 6: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add src/Client/Cache/RegionCacheInterface.php src/Client/Cache/RegionCache.php tests/Unit/Cache/RegionCacheTest.php
git commit -m "feat: refactor RegionCache to use RegionEntry, add switchLeader"
```

---

### Task 5: Update RegionException with NotLeader support

**Files:**
- Modify: `src/Client/Exception/RegionException.php`

- [ ] **Step 1: Update RegionException**

Replace the entire content of `src/Client/Exception/RegionException.php`:

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Exception;

use CrazyGoat\Proto\Errorpb\Error;
use CrazyGoat\Proto\Errorpb\NotLeader;

final class RegionException extends TiKvException
{
    public function __construct(
        string $operation,
        string $message,
        public readonly ?NotLeader $notLeader = null,
    ) {
        parent::__construct("{$operation} failed: {$message}");
    }

    public static function fromRegionError(Error $error): self
    {
        return new self(
            operation: 'RegionError',
            message: $error->getMessage(),
            notLeader: $error->getNotLeader(),
        );
    }
}
```

- [ ] **Step 2: Run existing tests to verify backward compatibility**

Run: `vendor/bin/phpunit tests/Unit/ -v`
Expected: All tests PASS (existing call sites use positional args: `new RegionException('op', 'msg')`)

- [ ] **Step 3: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Client/Exception/RegionException.php
git commit -m "feat: add NotLeader support to RegionException"
```

---

### Task 6: RegionErrorHandler

**Files:**
- Create: `src/Client/RawKv/RegionErrorHandler.php`
- Create: `tests/Unit/RawKv/RegionErrorHandlerTest.php`

- [ ] **Step 1: Write the tests**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\RawKv;

use CrazyGoat\Proto\Errorpb\EpochNotMatch;
use CrazyGoat\Proto\Errorpb\Error;
use CrazyGoat\Proto\Errorpb\NotLeader;
use CrazyGoat\Proto\Kvrpcpb\RawGetResponse;
use CrazyGoat\Proto\Kvrpcpb\RawPutResponse;
use CrazyGoat\Proto\Metapb\Peer;
use CrazyGoat\TiKV\Client\Exception\RegionException;
use CrazyGoat\TiKV\Client\RawKv\RegionErrorHandler;
use PHPUnit\Framework\TestCase;

class RegionErrorHandlerTest extends TestCase
{
    public function testNoExceptionWhenResponseHasNoRegionError(): void
    {
        $response = new RawPutResponse();

        RegionErrorHandler::check($response);

        $this->expectNotToPerformAssertions();
    }

    public function testNoExceptionWhenRegionErrorIsNull(): void
    {
        $response = new RawGetResponse();

        RegionErrorHandler::check($response);

        $this->expectNotToPerformAssertions();
    }

    public function testThrowsRegionExceptionOnNotLeaderWithHint(): void
    {
        $leader = new Peer();
        $leader->setId(20);
        $leader->setStoreId(3);

        $notLeader = new NotLeader();
        $notLeader->setRegionId(42);
        $notLeader->setLeader($leader);

        $error = new Error();
        $error->setMessage('not leader');
        $error->setNotLeader($notLeader);

        $response = new RawGetResponse();
        $response->setRegionError($error);

        try {
            RegionErrorHandler::check($response);
            $this->fail('Expected RegionException');
        } catch (RegionException $e) {
            $this->assertNotNull($e->notLeader);
            $this->assertNotNull($e->notLeader->getLeader());
            $this->assertSame(3, (int) $e->notLeader->getLeader()->getStoreId());
            $this->assertSame(42, (int) $e->notLeader->getRegionId());
        }
    }

    public function testThrowsRegionExceptionOnNotLeaderWithoutHint(): void
    {
        $notLeader = new NotLeader();
        $notLeader->setRegionId(42);

        $error = new Error();
        $error->setMessage('not leader');
        $error->setNotLeader($notLeader);

        $response = new RawGetResponse();
        $response->setRegionError($error);

        try {
            RegionErrorHandler::check($response);
            $this->fail('Expected RegionException');
        } catch (RegionException $e) {
            $this->assertNotNull($e->notLeader);
            $this->assertNull($e->notLeader->getLeader());
        }
    }

    public function testThrowsRegionExceptionOnOtherRegionError(): void
    {
        $error = new Error();
        $error->setMessage('epoch not match');
        $error->setEpochNotMatch(new EpochNotMatch());

        $response = new RawGetResponse();
        $response->setRegionError($error);

        $this->expectException(RegionException::class);
        $this->expectExceptionMessage('RegionError failed: epoch not match');

        RegionErrorHandler::check($response);
    }

    public function testNoExceptionForObjectWithoutGetRegionErrorMethod(): void
    {
        $response = new \stdClass();

        RegionErrorHandler::check($response);

        $this->expectNotToPerformAssertions();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/RawKv/RegionErrorHandlerTest.php -v`
Expected: FAIL — class RegionErrorHandler not found

- [ ] **Step 3: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\RawKv;

use CrazyGoat\TiKV\Client\Exception\RegionException;

final class RegionErrorHandler
{
    public static function check(object $response): void
    {
        if (!method_exists($response, 'getRegionError')) {
            return;
        }

        $regionError = $response->getRegionError();
        if ($regionError === null) {
            return;
        }

        throw RegionException::fromRegionError($regionError);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/RawKv/RegionErrorHandlerTest.php -v`
Expected: 6 tests, PASS

- [ ] **Step 5: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Client/RawKv/RegionErrorHandler.php tests/Unit/RawKv/RegionErrorHandlerTest.php
git commit -m "feat: add RegionErrorHandler to check TiKV responses for region errors"
```

---

### Task 7: Add BackoffType::NotLeader

**Files:**
- Modify: `src/Client/Retry/BackoffType.php`

- [ ] **Step 1: Add the NotLeader case**

In `src/Client/Retry/BackoffType.php`, add the `NotLeader` case and update all match expressions:

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Retry;

enum BackoffType
{
    case None;
    case ServerBusy;
    case StaleCmd;
    case RegionMiss;
    case TiKvRpc;
    case NotLeader;

    public function baseMs(): int
    {
        return match ($this) {
            self::None => 0,
            self::ServerBusy => 2000,
            self::StaleCmd => 2,
            self::RegionMiss => 2,
            self::TiKvRpc => 100,
            self::NotLeader => 2,
        };
    }

    public function capMs(): int
    {
        return match ($this) {
            self::None => 0,
            self::ServerBusy => 10000,
            self::StaleCmd => 1000,
            self::RegionMiss => 500,
            self::TiKvRpc => 2000,
            self::NotLeader => 500,
        };
    }

    public function equalJitter(): bool
    {
        return match ($this) {
            self::ServerBusy, self::TiKvRpc => true,
            default => false,
        };
    }

    public function sleepMs(int $attempt): int
    {
        if ($this === self::None) {
            return 0;
        }

        return Backoff::exponential($this->baseMs(), $this->capMs(), $attempt, $this->equalJitter());
    }
}
```

- [ ] **Step 2: Run existing backoff tests**

Run: `vendor/bin/phpunit tests/Unit/Retry/ -v`
Expected: All existing tests PASS

- [ ] **Step 3: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Client/Retry/BackoffType.php
git commit -m "feat: add BackoffType::NotLeader (2ms base, 500ms cap)"
```

---

### Task 8: PdClient — extract peers from PD responses

**Files:**
- Modify: `src/Client/Connection/PdClient.php`
- Modify: `tests/Unit/Connection/PdClientTest.php`

- [ ] **Step 1: Write the tests**

Add these tests to `tests/Unit/Connection/PdClientTest.php`. Add `use CrazyGoat\TiKV\Client\RawKv\Dto\PeerInfo;` to the imports.

```php
public function testGetRegionPopulatesPeers(): void
{
    $epoch = new RegionEpoch();
    $epoch->setConfVer(1);
    $epoch->setVersion(1);

    $peer1 = new Peer();
    $peer1->setId(10);
    $peer1->setStoreId(1);

    $peer2 = new Peer();
    $peer2->setId(20);
    $peer2->setStoreId(2);

    $peer3 = new Peer();
    $peer3->setId(30);
    $peer3->setStoreId(3);

    $region = new Region();
    $region->setId(42);
    $region->setRegionEpoch($epoch);
    $region->setPeers([$peer1, $peer2, $peer3]);

    $leader = new Peer();
    $leader->setId(10);
    $leader->setStoreId(1);

    $header = new ResponseHeader();
    $header->setClusterId(100);

    $response = new GetRegionResponse();
    $response->setHeader($header);
    $response->setRegion($region);
    $response->setLeader($leader);

    $grpc = $this->createMock(GrpcClientInterface::class);
    $grpc->method('call')->willReturn($response);

    $client = new PdClient($grpc, 'pd:2379');
    $result = $client->getRegion('key');

    $this->assertCount(3, $result->peers);
    $this->assertInstanceOf(PeerInfo::class, $result->peers[0]);
    $this->assertSame(10, $result->peers[0]->peerId);
    $this->assertSame(1, $result->peers[0]->storeId);
    $this->assertSame(20, $result->peers[1]->peerId);
    $this->assertSame(2, $result->peers[1]->storeId);
    $this->assertSame(30, $result->peers[2]->peerId);
    $this->assertSame(3, $result->peers[2]->storeId);
}

public function testGetRegionReturnsEmptyPeersWhenNoPeersInResponse(): void
{
    $response = $this->makeGetRegionResponse();

    $grpc = $this->createMock(GrpcClientInterface::class);
    $grpc->method('call')->willReturn($response);

    $client = new PdClient($grpc, 'pd:2379');
    $result = $client->getRegion('key');

    $this->assertIsArray($result->peers);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/Connection/PdClientTest.php --filter testGetRegionPopulatesPeers -v`
Expected: FAIL — peers array is empty (not populated yet)

- [ ] **Step 3: Update PdClient.getRegion() to extract peers**

In `src/Client/Connection/PdClient.php`, add `use CrazyGoat\TiKV\Client\RawKv\Dto\PeerInfo;` to the imports.

Replace the `getRegion` method body:

```php
public function getRegion(string $key): RegionInfo
{
    $request = new GetRegionRequest();
    $request->setHeader($this->createHeader());
    $request->setRegionKey($key);

    /** @var GetRegionResponse $response */
    $response = $this->callWithClusterIdRetry(
        'GetRegion',
        $request,
        GetRegionResponse::class,
    );

    $region = $response->getRegion();
    $leader = $response->getLeader();
    $regionEpoch = $region?->getRegionEpoch();

    $peers = [];
    if ($region !== null) {
        foreach ($region->getPeers() as $peer) {
            $peers[] = new PeerInfo(
                peerId: (int) $peer->getId(),
                storeId: (int) $peer->getStoreId(),
            );
        }
    }

    return new RegionInfo(
        regionId: $region ? (int) $region->getId() : 0,
        leaderPeerId: $leader ? (int) $leader->getId() : 0,
        leaderStoreId: $leader ? (int) $leader->getStoreId() : 1,
        epochConfVer: $regionEpoch ? (int) $regionEpoch->getConfVer() : 0,
        epochVersion: $regionEpoch ? (int) $regionEpoch->getVersion() : 0,
        startKey: $region ? $region->getStartKey() : '',
        endKey: $region ? $region->getEndKey() : '',
        peers: $peers,
    );
}
```

- [ ] **Step 4: Update PdClient.scanRegions() to extract peers**

In the `scanRegions` method, update the loop to extract peers from each region meta. Replace the foreach body:

```php
foreach ($regionMetas as $index => $region) {
    /** @var \CrazyGoat\Proto\Metapb\Peer|null $leader */
    $leader = $leaders[$index] ?? null;
    $regionEpoch = $region->getRegionEpoch();

    $peers = [];
    foreach ($region->getPeers() as $peer) {
        $peers[] = new PeerInfo(
            peerId: (int) $peer->getId(),
            storeId: (int) $peer->getStoreId(),
        );
    }

    $regions[] = new RegionInfo(
        regionId: (int) $region->getId(),
        leaderPeerId: $leader instanceof \CrazyGoat\Proto\Metapb\Peer ? (int) $leader->getId() : 0,
        leaderStoreId: $leader instanceof \CrazyGoat\Proto\Metapb\Peer ? (int) $leader->getStoreId() : 1,
        epochConfVer: $regionEpoch ? (int) $regionEpoch->getConfVer() : 0,
        epochVersion: $regionEpoch ? (int) $regionEpoch->getVersion() : 0,
        startKey: $region->getStartKey(),
        endKey: $region->getEndKey(),
        peers: $peers,
    );
}
```

- [ ] **Step 5: Run PdClient tests**

Run: `vendor/bin/phpunit tests/Unit/Connection/PdClientTest.php -v`
Expected: All tests PASS

- [ ] **Step 6: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add src/Client/Connection/PdClient.php tests/Unit/Connection/PdClientTest.php
git commit -m "feat: extract peers from PD responses into RegionInfo"
```

---

### Task 9: RawKvClient — region error checking + NotLeader handling

**Files:**
- Modify: `src/Client/RawKv/RawKvClient.php`
- Modify: `tests/Unit/RawKv/RawKvClientTest.php`

- [ ] **Step 1: Write the NotLeader tests**

Add these imports to `tests/Unit/RawKv/RawKvClientTest.php`:

```php
use CrazyGoat\Proto\Errorpb\Error;
use CrazyGoat\Proto\Errorpb\NotLeader;
use CrazyGoat\Proto\Metapb\Peer;
use CrazyGoat\TiKV\Client\Exception\RegionException;
use CrazyGoat\TiKV\Client\RawKv\Dto\PeerInfo;
```

Add a helper method to the test class:

```php
private function regionWithPeers(): RegionInfo
{
    return new RegionInfo(
        regionId: 1,
        leaderPeerId: 10,
        leaderStoreId: 1,
        epochConfVer: 1,
        epochVersion: 1,
        startKey: '',
        endKey: '',
        peers: [
            new PeerInfo(peerId: 10, storeId: 1),
            new PeerInfo(peerId: 20, storeId: 2),
            new PeerInfo(peerId: 30, storeId: 3),
        ],
    );
}
```

Add these test methods:

```php
public function testNotLeaderWithHintSwitchesLeaderAndRetries(): void
{
    $region = $this->regionWithPeers();
    $this->regionCache->method('getByKey')->willReturn($region);

    $this->pdClient->method('getStore')->willReturn($this->defaultStore());

    $this->regionCache->expects($this->once())
        ->method('switchLeader')
        ->with(1, 3)
        ->willReturn(true);

    $leader = new Peer();
    $leader->setId(30);
    $leader->setStoreId(3);

    $notLeader = new NotLeader();
    $notLeader->setRegionId(1);
    $notLeader->setLeader($leader);

    $error = new Error();
    $error->setMessage('not leader');
    $error->setNotLeader($notLeader);

    $errorResponse = new RawGetResponse();
    $errorResponse->setRegionError($error);

    $successResponse = new RawGetResponse();
    $successResponse->setValue('found');

    $this->grpc->expects($this->exactly(2))
        ->method('call')
        ->willReturnOnConsecutiveCalls($errorResponse, $successResponse);

    $result = $this->client->get('key');
    $this->assertSame('found', $result);
}

public function testNotLeaderWithoutHintInvalidatesRegion(): void
{
    $region = $this->regionWithPeers();
    $this->regionCache->method('getByKey')->willReturn($region);

    $this->pdClient->method('getRegion')->willReturn($region);
    $this->pdClient->method('getStore')->willReturn($this->defaultStore());

    $this->regionCache->expects($this->once())
        ->method('invalidate')
        ->with(1);

    $this->regionCache->expects($this->never())
        ->method('switchLeader');

    $notLeader = new NotLeader();
    $notLeader->setRegionId(1);

    $error = new Error();
    $error->setMessage('not leader');
    $error->setNotLeader($notLeader);

    $errorResponse = new RawGetResponse();
    $errorResponse->setRegionError($error);

    $successResponse = new RawGetResponse();
    $successResponse->setValue('found');

    $this->grpc->expects($this->exactly(2))
        ->method('call')
        ->willReturnOnConsecutiveCalls($errorResponse, $successResponse);

    $result = $this->client->get('key');
    $this->assertSame('found', $result);
}

public function testNotLeaderWithUnknownPeerInvalidatesRegion(): void
{
    $region = $this->regionWithPeers();
    $this->regionCache->method('getByKey')->willReturn($region);

    $this->pdClient->method('getRegion')->willReturn($region);
    $this->pdClient->method('getStore')->willReturn($this->defaultStore());

    $this->regionCache->expects($this->once())
        ->method('switchLeader')
        ->with(1, 99)
        ->willReturn(false);

    $this->regionCache->expects($this->once())
        ->method('invalidate')
        ->with(1);

    $leader = new Peer();
    $leader->setId(99);
    $leader->setStoreId(99);

    $notLeader = new NotLeader();
    $notLeader->setRegionId(1);
    $notLeader->setLeader($leader);

    $error = new Error();
    $error->setMessage('not leader');
    $error->setNotLeader($notLeader);

    $errorResponse = new RawGetResponse();
    $errorResponse->setRegionError($error);

    $successResponse = new RawGetResponse();
    $successResponse->setValue('found');

    $this->grpc->expects($this->exactly(2))
        ->method('call')
        ->willReturnOnConsecutiveCalls($errorResponse, $successResponse);

    $result = $this->client->get('key');
    $this->assertSame('found', $result);
}

public function testRegionErrorSurfacesEpochNotMatch(): void
{
    $this->regionCache->method('getByKey')->willReturn(null);
    $this->regionCache->method('put');
    $this->regionCache->method('invalidate');

    $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
    $this->pdClient->method('getStore')->willReturn($this->defaultStore());

    $error = new Error();
    $error->setMessage('epoch not match');
    $error->setEpochNotMatch(new \CrazyGoat\Proto\Errorpb\EpochNotMatch());

    $errorResponse = new RawGetResponse();
    $errorResponse->setRegionError($error);

    $successResponse = new RawGetResponse();
    $successResponse->setValue('ok');

    $this->grpc->expects($this->exactly(2))
        ->method('call')
        ->willReturnOnConsecutiveCalls($errorResponse, $successResponse);

    $result = $this->client->get('key');
    $this->assertSame('ok', $result);
}

public function testNotLeaderStringFallbackInClassifyError(): void
{
    $this->regionCache->method('getByKey')->willReturn(null);
    $this->regionCache->method('put');
    $this->regionCache->method('invalidate');

    $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
    $this->pdClient->method('getStore')->willReturn($this->defaultStore());

    $response = new RawGetResponse();
    $response->setValue('recovered');

    $this->grpc->expects($this->exactly(2))
        ->method('call')
        ->willReturnOnConsecutiveCalls(
            $this->throwException(new RegionException('test', 'NotLeader')),
            $response,
        );

    $result = $this->client->get('key');
    $this->assertSame('recovered', $result);
}

public function testBackoffTypeNotLeaderSleepValues(): void
{
    $this->assertSame(2, \CrazyGoat\TiKV\Client\Retry\BackoffType::NotLeader->baseMs());
    $this->assertSame(500, \CrazyGoat\TiKV\Client\Retry\BackoffType::NotLeader->capMs());
    $this->assertFalse(\CrazyGoat\TiKV\Client\Retry\BackoffType::NotLeader->equalJitter());
    $this->assertSame(2, \CrazyGoat\TiKV\Client\Retry\BackoffType::NotLeader->sleepMs(0));
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/RawKv/RawKvClientTest.php --filter testNotLeader -v`
Expected: FAIL — NotLeader handling not implemented yet

- [ ] **Step 3: Add RegionErrorHandler::check() calls to all RPC methods**

In `src/Client/RawKv/RawKvClient.php`, add `use CrazyGoat\TiKV\Client\RawKv\RegionErrorHandler;` to the imports (it's not needed as a use statement since it's in the same namespace, but add it for clarity — actually since it's in the same namespace `CrazyGoat\TiKV\Client\RawKv`, no import needed).

Add `RegionErrorHandler::check($response);` after each `$this->grpc->call(...)` in every RPC method. Here are all the locations:

In `get()` — after line 93:
```php
$response = $this->grpc->call($address, 'tikvpb.Tikv', 'RawGet', $request, RawGetResponse::class);
RegionErrorHandler::check($response);
```

In `put()` — after line 121:
```php
$this->grpc->call($address, 'tikvpb.Tikv', 'RawPut', $request, RawPutResponse::class);
```
Change to:
```php
$response = $this->grpc->call($address, 'tikvpb.Tikv', 'RawPut', $request, RawPutResponse::class);
RegionErrorHandler::check($response);
```

In `delete()` — after line 138:
```php
$this->grpc->call($address, 'tikvpb.Tikv', 'RawDelete', $request, RawDeleteResponse::class);
```
Change to:
```php
$response = $this->grpc->call($address, 'tikvpb.Tikv', 'RawDelete', $request, RawDeleteResponse::class);
RegionErrorHandler::check($response);
```

In `getKeyTTL()` — after line 167:
```php
$response = $this->grpc->call(...);
```
Add after the call:
```php
RegionErrorHandler::check($response);
```

In `compareAndSwap()` — after line 225:
```php
$response = $this->grpc->call(...);
```
Add after the call:
```php
RegionErrorHandler::check($response);
```

In `executeBatchGetForRegion()` — after line 760:
```php
$response = $this->grpc->call(...);
```
Add after the call:
```php
RegionErrorHandler::check($response);
```

In `executeBatchPutForRegion()` — after line 786:
```php
$this->grpc->call(...);
```
Change to:
```php
$response = $this->grpc->call($address, 'tikvpb.Tikv', 'RawBatchPut', $request, RawBatchPutResponse::class);
RegionErrorHandler::check($response);
```

In `executeBatchDeleteForRegion()` — after line 803:
```php
$this->grpc->call(...);
```
Change to:
```php
$response = $this->grpc->call($address, 'tikvpb.Tikv', 'RawBatchDelete', $request, RawBatchDeleteResponse::class);
RegionErrorHandler::check($response);
```

In `executeScanForRegion()` — after line 835:
```php
$response = $this->grpc->call(...);
```
Add after the call:
```php
RegionErrorHandler::check($response);
```

In `executeDeleteRangeForRegion()` — after line 862:
```php
$response = $this->grpc->call(...);
```
Add after the call:
```php
RegionErrorHandler::check($response);
```

In `executeChecksumForRegion()` — after line 896:
```php
$response = $this->grpc->call(...);
```
Add after the call:
```php
RegionErrorHandler::check($response);
```

- [ ] **Step 4: Add NotLeader handling to executeWithRetry()**

In `src/Client/RawKv/RawKvClient.php`, add these imports:

```php
use CrazyGoat\Proto\Errorpb\NotLeader;
```

Replace the `executeWithRetry` method:

```php
/**
 * @template T
 * @param callable(): T $operation
 * @return T
 */
private function executeWithRetry(string $key, callable $operation): mixed
{
    $totalBackoffMs = 0;
    $attempt = 0;

    while (true) {
        try {
            return $operation();
        } catch (TiKvException $e) {
            $backoffType = $this->handleNotLeader($e, $key);

            if (!$backoffType instanceof BackoffType) {
                $backoffType = $this->classifyError($e);

                if (!$backoffType instanceof BackoffType) {
                    $this->logger->error('Fatal error, not retrying', ['key' => $key, 'error' => $e->getMessage()]);
                    throw $e;
                }

                $cached = $this->regionCache->getByKey($key);
                if ($cached instanceof RegionInfo) {
                    $this->regionCache->invalidate($cached->regionId);
                    $this->logger->info('Invalidated region on retry', [
                        'key' => $key,
                        'regionId' => $cached->regionId,
                    ]);

                    if ($e instanceof GrpcException) {
                        try {
                            $address = $this->resolveStoreAddress($cached->leaderStoreId);
                            $this->grpc->closeChannel($address);
                        } catch (StoreNotFoundException) {
                        }
                    }
                }
            }

            $sleepMs = $backoffType->sleepMs($attempt);
            $totalBackoffMs += $sleepMs;

            if ($totalBackoffMs > $this->maxBackoffMs) {
                $this->logger->error('Retry budget exhausted', [
                    'key' => $key,
                    'attempt' => $attempt,
                    'totalBackoffMs' => $totalBackoffMs,
                    'maxBackoffMs' => $this->maxBackoffMs,
                ]);
                throw $e;
            }

            $this->logger->warning('Retrying operation', [
                'key' => $key,
                'attempt' => $attempt,
                'backoffType' => $backoffType->name,
                'sleepMs' => $sleepMs,
                'totalBackoffMs' => $totalBackoffMs,
            ]);

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }

            $attempt++;
        }
    }
}
```

Add the `handleNotLeader` private method:

```php
private function handleNotLeader(TiKvException $e, string $key): ?BackoffType
{
    if (!$e instanceof RegionException || !$e->notLeader instanceof NotLeader) {
        return null;
    }

    $regionId = (int) $e->notLeader->getRegionId();
    $leader = $e->notLeader->getLeader();

    if ($leader !== null) {
        $leaderStoreId = (int) $leader->getStoreId();
        $switched = $this->regionCache->switchLeader($regionId, $leaderStoreId);
        if (!$switched) {
            $this->regionCache->invalidate($regionId);
            $this->logger->info('NotLeader hint peer unknown, invalidated region', [
                'key' => $key,
                'regionId' => $regionId,
                'hintStoreId' => $leaderStoreId,
            ]);
        }
    } else {
        $this->regionCache->invalidate($regionId);
        $this->logger->info('NotLeader without hint, invalidated region', [
            'key' => $key,
            'regionId' => $regionId,
        ]);
    }

    return BackoffType::NotLeader;
}
```

- [ ] **Step 5: Add NotLeader string fallback to classifyError()**

In the `classifyError` method, add this check before the `GrpcException` check:

```php
if (str_contains($message, 'NotLeader')) {
    return BackoffType::NotLeader;
}
```

The full `classifyError` method should be:

```php
private function classifyError(TiKvException $e): ?BackoffType
{
    $message = $e->getMessage();

    if (str_contains($message, 'RaftEntryTooLarge')) {
        return null;
    }
    if (str_contains($message, 'KeyNotInRegion')) {
        return null;
    }

    if (str_contains($message, 'EpochNotMatch')) {
        return BackoffType::None;
    }
    if (str_contains($message, 'ServerIsBusy')) {
        return BackoffType::ServerBusy;
    }
    if (str_contains($message, 'StaleCommand')) {
        return BackoffType::StaleCmd;
    }
    if (str_contains($message, 'RegionNotFound')) {
        return BackoffType::RegionMiss;
    }
    if (str_contains($message, 'NotLeader')) {
        return BackoffType::NotLeader;
    }

    if ($e instanceof GrpcException) {
        return BackoffType::TiKvRpc;
    }

    return null;
}
```

- [ ] **Step 6: Run all RawKvClient tests**

Run: `vendor/bin/phpunit tests/Unit/RawKv/RawKvClientTest.php -v`
Expected: All existing + new tests PASS

- [ ] **Step 7: Run full unit test suite**

Run: `vendor/bin/phpunit tests/Unit/ -v`
Expected: All tests PASS

- [ ] **Step 8: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add src/Client/RawKv/RawKvClient.php tests/Unit/RawKv/RawKvClientTest.php
git commit -m "feat: add region error checking and NotLeader handling to RawKvClient"
```

---

### Task 10: Final verification

- [ ] **Step 1: Run full unit + integration test suite**

Run: `vendor/bin/phpunit tests/Unit/ tests/Integration/ -v`
Expected: All tests PASS

- [ ] **Step 2: Run lint**

Run: `composer lint`
Expected: PASS (phpcs + rector + phpstan level 9)

- [ ] **Step 3: Verify test count**

Expected: ~130 tests (102 existing + ~28 new)

- [ ] **Step 4: Final commit (if any lint fixes needed)**

```bash
git add -A
git commit -m "chore: lint fixes for NotLeader handling"
```
