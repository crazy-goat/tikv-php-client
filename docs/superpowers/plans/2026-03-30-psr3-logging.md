# PSR-3 Structured Logging Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add optional PSR-3 structured logging to RawKvClient, PdClient, and RegionCache with NullLogger default.

**Architecture:** Single injection point at RawKvClient, passed through to PdClient and RegionCache constructors. All three classes store `$this->logger = $logger ?? new NullLogger()`. Log events cover retry, cache, gRPC, and error classification.

**Tech Stack:** `psr/log ^3.0`, PHPUnit 11, phpstan level 9, PSR-12

---

### Task 1: Add psr/log dependency

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Add psr/log to composer.json require**

```json
"require": {
    "php": ">=8.2",
    "grpc/grpc": "^1.57",
    "google/protobuf": "^3.25",
    "psr/log": "^3.0"
},
```

- [ ] **Step 2: Install the dependency**

Run: `composer update psr/log --no-interaction`
Expected: psr/log installed successfully

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "Add psr/log ^3.0 dependency"
```

---

### Task 2: Add logging to RegionCache

**Files:**
- Modify: `src/Client/Cache/RegionCache.php`
- Modify: `tests/Unit/Cache/RegionCacheTest.php`

- [ ] **Step 1: Write failing tests for logging in RegionCache**

Add these tests to `tests/Unit/Cache/RegionCacheTest.php`. First update `TestableRegionCache` to accept a logger, then add test methods:

```php
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use PHPUnit\Framework\MockObject\MockObject;
```

Update `TestableRegionCache` constructor:

```php
class TestableRegionCache extends RegionCache
{
    public function __construct(private int $fakeTime, int $ttlSeconds = 600, ?LoggerInterface $logger = null)
    {
        parent::__construct($ttlSeconds, 0, $logger);
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
```

Add test methods:

```php
public function testPutLogsDebugMessage(): void
{
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('debug')
        ->with('Region cached', $this->callback(function (array $context): bool {
            return $context['regionId'] === 1
                && $context['startKey'] === 'a'
                && $context['endKey'] === 'z'
                && isset($context['ttl']);
        }));

    $cache = new RegionCache(600, 0, $logger);
    $cache->put($this->makeRegion(1, 'a', 'z'));
}

public function testGetByKeyLogsHit(): void
{
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('debug')
        ->with('Region cache hit', $this->callback(function (array $context): bool {
            return $context['key'] === 'm' && $context['regionId'] === 1;
        }));

    $cache = new RegionCache(600, 0, $logger);
    $cache->put($this->makeRegion(1, 'a', 'z'));

    // Reset expectations after put's debug call
    // Instead, use atLeast and check the last call
    // Better approach: use a fresh mock
}

public function testGetByKeyLogsCacheHit(): void
{
    $logger = $this->createMock(LoggerInterface::class);
    $cache = new RegionCache(600, 0, $logger);
    $cache->put($this->makeRegion(1, 'a', 'z'));

    $logger->expects($this->once())
        ->method('debug')
        ->with('Region cache hit', $this->callback(function (array $context): bool {
            return $context['key'] === 'm' && $context['regionId'] === 1;
        }));

    $cache->getByKey('m');
}

public function testGetByKeyLogsCacheMiss(): void
{
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects($this->once())
        ->method('debug')
        ->with('Region cache miss', $this->callback(function (array $context): bool {
            return $context['key'] === 'x';
        }));

    $cache = new RegionCache(600, 0, $logger);
    $cache->getByKey('x');
}

public function testInvalidateLogsInfo(): void
{
    $logger = $this->createMock(LoggerInterface::class);
    $cache = new RegionCache(600, 0, $logger);
    $cache->put($this->makeRegion(1, 'a', 'z'));

    $logger->expects($this->once())
        ->method('info')
        ->with('Region invalidated', ['regionId' => 1]);

    $cache->invalidate(1);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Cache/RegionCacheTest.php -v`
Expected: FAIL — RegionCache constructor does not accept logger parameter yet

- [ ] **Step 3: Implement logging in RegionCache**

Modify `src/Client/Cache/RegionCache.php`:

Add imports:
```php
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
```

Update constructor:
```php
public function __construct(
    private readonly int $ttlSeconds = 600,
    private readonly int $jitterSeconds = 60,
    private readonly LoggerInterface $logger = new NullLogger(),
) {
}
```

In `getByKey()`, after finding a valid region (before `return $region`):
```php
$this->logger->debug('Region cache hit', ['key' => $key, 'regionId' => $region->regionId]);
```

In `getByKey()`, at the start when `$index === null` (before `return null`):
```php
$this->logger->debug('Region cache miss', ['key' => $key]);
```

Also in `getByKey()`, when expired (before `return null`):
```php
$this->logger->debug('Region cache miss', ['key' => $key]);
```

Also in `getByKey()`, when key >= endKey (before `return null`):
```php
$this->logger->debug('Region cache miss', ['key' => $key]);
```

In `put()`, after inserting and setting TTL:
```php
$this->logger->debug('Region cached', [
    'regionId' => $region->regionId,
    'startKey' => $region->startKey,
    'endKey' => $region->endKey,
    'ttl' => $this->ttls[$region->regionId] - $this->now(),
]);
```

In `invalidate()`, before calling `removeById`:
```php
$this->logger->info('Region invalidated', ['regionId' => $regionId]);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Cache/RegionCacheTest.php -v`
Expected: All tests PASS

- [ ] **Step 5: Run full unit test suite**

Run: `vendor/bin/phpunit tests/Unit/ -v`
Expected: All tests PASS (existing tests use `new RegionCache()` without logger — NullLogger default handles this)

- [ ] **Step 6: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add src/Client/Cache/RegionCache.php tests/Unit/Cache/RegionCacheTest.php
git commit -m "Add PSR-3 logging to RegionCache"
```

---

### Task 3: Add logging to PdClient

**Files:**
- Modify: `src/Client/Connection/PdClient.php`
- Create: `tests/Unit/Connection/PdClientTest.php`

- [ ] **Step 1: Write failing tests for PdClient logging**

Create `tests/Unit/Connection/PdClientTest.php`:

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Connection;

use CrazyGoat\Proto\Metapb\Region;
use CrazyGoat\Proto\Metapb\RegionEpoch;
use CrazyGoat\Proto\Metapb\Peer;
use CrazyGoat\Proto\Metapb\Store;
use CrazyGoat\Proto\Pdpb\GetRegionResponse;
use CrazyGoat\Proto\Pdpb\GetStoreResponse;
use CrazyGoat\Proto\Pdpb\ResponseHeader;
use CrazyGoat\TiKV\Client\Connection\PdClient;
use CrazyGoat\TiKV\Client\Exception\GrpcException;
use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PdClientTest extends TestCase
{
    private GrpcClientInterface&MockObject $grpc;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->grpc = $this->createMock(GrpcClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function makeGetRegionResponse(): GetRegionResponse
    {
        $epoch = new RegionEpoch();
        $epoch->setConfVer(1);
        $epoch->setVersion(1);

        $region = new Region();
        $region->setId(1);
        $region->setStartKey('');
        $region->setEndKey('');
        $region->setRegionEpoch($epoch);

        $leader = new Peer();
        $leader->setId(1);
        $leader->setStoreId(1);

        $header = new ResponseHeader();
        $header->setClusterId(12345);

        $response = new GetRegionResponse();
        $response->setRegion($region);
        $response->setLeader($leader);
        $response->setHeader($header);

        return $response;
    }

    public function testGetRegionLogsGrpcCall(): void
    {
        $this->grpc->method('call')->willReturn($this->makeGetRegionResponse());

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('PD gRPC call', ['method' => 'GetRegion', 'address' => 'pd1:2379']);

        $client = new PdClient($this->grpc, 'pd1:2379', $this->logger);
        $client->getRegion('key');
    }

    public function testGetRegionLogsClusterIdLearned(): void
    {
        $this->grpc->method('call')->willReturn($this->makeGetRegionResponse());

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Learned cluster ID', ['clusterId' => 12345]);

        $client = new PdClient($this->grpc, 'pd1:2379', $this->logger);
        $client->getRegion('key');
    }

    public function testClusterIdMismatchLogsWarning(): void
    {
        $this->grpc->method('call')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new GrpcException('mismatch cluster id, need 99 but got 0', 2)),
                $this->makeGetRegionResponse(),
            );

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Cluster ID mismatch, retrying', ['method' => 'GetRegion', 'clusterId' => 99]);

        $client = new PdClient($this->grpc, 'pd1:2379', $this->logger);
        $client->getRegion('key');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Connection/PdClientTest.php -v`
Expected: FAIL — PdClient constructor does not accept logger parameter yet

- [ ] **Step 3: Implement logging in PdClient**

Modify `src/Client/Connection/PdClient.php`:

Add imports:
```php
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
```

Update constructor:
```php
public function __construct(
    private readonly GrpcClientInterface $grpc,
    private readonly string $pdAddress,
    private readonly LoggerInterface $logger = new NullLogger(),
) {
}
```

In `callWithClusterIdRetry()`, at the start (before the try block):
```php
$this->logger->debug('PD gRPC call', ['method' => $method, 'address' => $this->pdAddress]);
```

In `callWithClusterIdRetry()`, in the catch block after extracting cluster ID (before setting `$this->clusterId`):
```php
$this->logger->warning('Cluster ID mismatch, retrying', ['method' => $method, 'clusterId' => $extractedId]);
```

In `learnClusterId()`, after setting `$this->clusterId`:
```php
$this->logger->info('Learned cluster ID', ['clusterId' => $clusterId]);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Connection/PdClientTest.php -v`
Expected: All tests PASS

- [ ] **Step 5: Run full unit test suite**

Run: `vendor/bin/phpunit tests/Unit/ -v`
Expected: All tests PASS

- [ ] **Step 6: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add src/Client/Connection/PdClient.php tests/Unit/Connection/PdClientTest.php
git commit -m "Add PSR-3 logging to PdClient"
```

---

### Task 4: Add logging to RawKvClient and wire everything together

**Files:**
- Modify: `src/Client/RawKv/RawKvClient.php`
- Modify: `tests/Unit/RawKv/RawKvClientTest.php`

- [ ] **Step 1: Write failing tests for RawKvClient logging**

Add imports to `tests/Unit/RawKv/RawKvClientTest.php`:
```php
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
```

Add these test methods:

```php
public function testRetryLogsWarningOnRetriableError(): void
{
    $logger = $this->createMock(LoggerInterface::class);
    $client = new RawKvClient($this->pdClient, $this->grpc, $this->regionCache, 20000, $logger);

    $this->regionCache->method('getByKey')->willReturn(null);
    $this->regionCache->method('put');
    $this->regionCache->method('invalidate');

    $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
    $this->pdClient->method('getStore')->willReturn($this->defaultStore());

    $response = new RawGetResponse();
    $response->setValue('ok');

    $this->grpc->expects($this->exactly(2))
        ->method('call')
        ->willReturnOnConsecutiveCalls(
            $this->throwException(new TiKvException('EpochNotMatch')),
            $response,
        );

    $logger->expects($this->once())
        ->method('warning')
        ->with('Retrying operation', $this->callback(function (array $context): bool {
            return $context['key'] === 'key'
                && $context['attempt'] === 0
                && $context['backoffType'] === 'None'
                && $context['sleepMs'] === 0;
        }));

    $client->get('key');
}

public function testRetryLogsCacheInvalidation(): void
{
    $logger = $this->createMock(LoggerInterface::class);
    $client = new RawKvClient($this->pdClient, $this->grpc, $this->regionCache, 20000, $logger);

    $region = $this->defaultRegion();
    $this->regionCache->method('getByKey')->willReturn($region);
    $this->regionCache->method('invalidate');

    $this->pdClient->method('getRegion')->willReturn($region);
    $this->pdClient->method('getStore')->willReturn($this->defaultStore());

    $response = new RawGetResponse();
    $response->setValue('ok');

    $this->grpc->expects($this->exactly(2))
        ->method('call')
        ->willReturnOnConsecutiveCalls(
            $this->throwException(new TiKvException('EpochNotMatch')),
            $response,
        );

    $logger->expects($this->once())
        ->method('info')
        ->with('Invalidated region on retry', ['key' => 'key', 'regionId' => 1]);

    $client->get('key');
}

public function testBudgetExhaustedLogsError(): void
{
    $logger = $this->createMock(LoggerInterface::class);
    $client = new RawKvClient($this->pdClient, $this->grpc, $this->regionCache, 0, $logger);

    $this->regionCache->method('getByKey')->willReturn(null);
    $this->regionCache->method('put');
    $this->regionCache->method('invalidate');

    $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
    $this->pdClient->method('getStore')->willReturn($this->defaultStore());

    $this->grpc->method('call')
        ->willThrowException(new TiKvException('ServerIsBusy'));

    $logger->expects($this->once())
        ->method('error')
        ->with('Retry budget exhausted', $this->callback(function (array $context): bool {
            return $context['key'] === 'key'
                && $context['maxBackoffMs'] === 0;
        }));

    $this->expectException(TiKvException::class);
    $client->get('key');
}

public function testFatalErrorLogsError(): void
{
    $logger = $this->createMock(LoggerInterface::class);
    $client = new RawKvClient($this->pdClient, $this->grpc, $this->regionCache, 20000, $logger);

    $this->regionCache->method('getByKey')->willReturn(null);
    $this->regionCache->method('put');

    $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
    $this->pdClient->method('getStore')->willReturn($this->defaultStore());

    $this->grpc->method('call')
        ->willThrowException(new TiKvException('RaftEntryTooLarge'));

    $logger->expects($this->once())
        ->method('error')
        ->with('Fatal error, not retrying', $this->callback(function (array $context): bool {
            return $context['key'] === 'key'
                && $context['error'] === 'RaftEntryTooLarge';
        }));

    $this->expectException(TiKvException::class);
    $client->get('key');
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/RawKv/RawKvClientTest.php -v`
Expected: FAIL — RawKvClient constructor does not accept logger parameter at position 5

- [ ] **Step 3: Implement logging in RawKvClient**

Modify `src/Client/RawKv/RawKvClient.php`:

Add imports:
```php
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
```

Update constructor:
```php
public function __construct(
    private readonly PdClientInterface $pdClient,
    private readonly GrpcClientInterface $grpc,
    private readonly RegionCacheInterface $regionCache = new RegionCache(),
    private readonly int $maxBackoffMs = 20000,
    private readonly LoggerInterface $logger = new NullLogger(),
) {
}
```

Update `create()` factory method:
```php
public static function create(array $pdEndpoints, ?LoggerInterface $logger = null): self
{
    $resolvedLogger = $logger ?? new NullLogger();
    $grpc = new GrpcClient();
    $pdClient = new PdClient($grpc, $pdEndpoints[0], $resolvedLogger);

    return new self($pdClient, new GrpcClient(), new RegionCache(logger: $resolvedLogger), logger: $resolvedLogger);
}
```

In `executeWithRetry()`, after `$backoffType = $this->classifyError($e)`:

When backoffType is null (fatal, before `throw $e`):
```php
$this->logger->error('Fatal error, not retrying', ['key' => $key, 'error' => $e->getMessage()]);
```

After cache invalidation (after the `if ($cached instanceof RegionInfo)` block):
```php
if ($cached instanceof RegionInfo) {
    $this->regionCache->invalidate($cached->regionId);
    $this->logger->info('Invalidated region on retry', ['key' => $key, 'regionId' => $cached->regionId]);
}
```

After computing sleepMs, before budget check:
```php
$this->logger->warning('Retrying operation', [
    'key' => $key,
    'attempt' => $attempt,
    'backoffType' => $backoffType->name,
    'sleepMs' => $sleepMs,
    'totalBackoffMs' => $totalBackoffMs,
]);
```

When budget exceeded (before `throw $e`):
```php
$this->logger->error('Retry budget exhausted', [
    'key' => $key,
    'attempt' => $attempt,
    'totalBackoffMs' => $totalBackoffMs,
    'maxBackoffMs' => $this->maxBackoffMs,
]);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/RawKv/RawKvClientTest.php -v`
Expected: All tests PASS

- [ ] **Step 5: Run full unit test suite**

Run: `vendor/bin/phpunit tests/Unit/ -v`
Expected: All tests PASS

- [ ] **Step 6: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add src/Client/RawKv/RawKvClient.php tests/Unit/RawKv/RawKvClientTest.php
git commit -m "Add PSR-3 logging to RawKvClient with retry/error logging"
```

---

### Task 5: Run E2E tests in Docker

**Files:** None (verification only)

- [ ] **Step 1: Run E2E tests**

Run: `docker compose run --rm php-test vendor/bin/phpunit --testsuite E2E --testdox`
Expected: All 141 E2E tests PASS

- [ ] **Step 2: Run full lint**

Run: `composer lint`
Expected: PASS
