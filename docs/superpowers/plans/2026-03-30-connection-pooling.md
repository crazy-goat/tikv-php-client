# Connection Pooling Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Improve gRPC connection management with single shared client, per-address channel close, and channel health checking.

**Architecture:** Add `closeChannel()` to `GrpcClientInterface`, add health checking and logging to `GrpcClient`, merge PD/TiKV into single shared instance, close failed channels on GrpcException during retry.

**Tech Stack:** PHP 8.2+, gRPC extension, PSR-3 logging, PHPUnit 11

---

### Task 1: Add `closeChannel()` to GrpcClientInterface and GrpcClient

**Files:**
- Modify: `src/Client/Grpc/GrpcClientInterface.php`
- Modify: `src/Client/Grpc/GrpcClient.php`

- [ ] **Step 1: Add `closeChannel` to interface**

Add to `src/Client/Grpc/GrpcClientInterface.php` before the `close()` method:

```php
/**
 * Close a single channel by address, forcing reconnect on next call.
 */
public function closeChannel(string $address): void;
```

- [ ] **Step 2: Add logger and `closeChannel` to GrpcClient**

Update `src/Client/Grpc/GrpcClient.php`:

Add imports:
```php
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
```

Add constructor:
```php
public function __construct(
    private readonly LoggerInterface $logger = new NullLogger(),
) {
}
```

Add `closeChannel()` method after `close()`:
```php
public function closeChannel(string $address): void
{
    if (isset($this->channels[$address])) {
        $this->logger->debug('Channel closed', ['address' => $address]);
        $this->channels[$address]->close();
        unset($this->channels[$address]);
    }
}
```

Update `getChannel()` to add health checking and logging:
```php
private function getChannel(string $address): Channel
{
    if (isset($this->channels[$address])) {
        $state = $this->channels[$address]->getConnectivityState();
        if ($state === \Grpc\CHANNEL_FATAL_FAILURE) {
            $this->logger->warning('Channel in fatal failure, reconnecting', ['address' => $address]);
            $this->closeChannel($address);
        }
    }

    if (!isset($this->channels[$address])) {
        $this->logger->debug('Opening gRPC channel', ['address' => $address]);
        $this->channels[$address] = new Channel($address, [
            'credentials' => ChannelCredentials::createInsecure(),
        ]);
    }

    return $this->channels[$address];
}
```

- [ ] **Step 3: Update existing unit tests**

The mock for `GrpcClientInterface` in `tests/Unit/RawKv/RawKvClientTest.php` will automatically pick up the new `closeChannel` method since it's a mock. No changes needed to existing tests — PHPUnit mocks allow any method on the interface.

Verify existing tests still pass:

Run: `vendor/bin/phpunit tests/Unit/`
Expected: All tests PASS

- [ ] **Step 4: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Client/Grpc/GrpcClientInterface.php src/Client/Grpc/GrpcClient.php
git commit -m "Add closeChannel() and health checking to GrpcClient"
```

---

### Task 2: Single shared GrpcClient and channel close on GrpcException

**Files:**
- Modify: `src/Client/RawKv/RawKvClient.php`
- Modify: `tests/Unit/RawKv/RawKvClientTest.php`

- [ ] **Step 1: Write failing test for closeChannel on GrpcException**

Add this test to `tests/Unit/RawKv/RawKvClientTest.php`:

```php
public function testGrpcExceptionClosesChannel(): void
{
    $logger = $this->createMock(LoggerInterface::class);
    $client = new RawKvClient($this->pdClient, $this->grpc, $this->regionCache, 20000, $logger);

    $region = $this->defaultRegion();
    $this->regionCache->method('getByKey')->willReturn($region);
    $this->regionCache->method('invalidate');

    $this->pdClient->method('getRegion')->willReturn($region);
    $this->pdClient->method('getStore')->willReturn($this->defaultStore());

    $response = new RawGetResponse();
    $response->setValue('recovered');

    $this->grpc->expects($this->exactly(2))
        ->method('call')
        ->willReturnOnConsecutiveCalls(
            $this->throwException(new GrpcException('connection reset', 14)),
            $response,
        );

    $this->grpc->expects($this->once())
        ->method('closeChannel')
        ->with('tikv1:20160');

    $client->get('key');
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/RawKv/RawKvClientTest.php --filter=testGrpcExceptionClosesChannel`
Expected: FAIL — `closeChannel` is never called

- [ ] **Step 3: Implement channel close on GrpcException in executeWithRetry**

Update `executeWithRetry()` in `src/Client/RawKv/RawKvClient.php`. After the cache invalidation block (after line 632), add channel close logic for GrpcException:

```php
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
```

- [ ] **Step 4: Update `create()` to use single shared GrpcClient**

Update `create()` in `src/Client/RawKv/RawKvClient.php`:

```php
public static function create(array $pdEndpoints, ?LoggerInterface $logger = null): self
{
    $resolvedLogger = $logger ?? new NullLogger();
    $grpc = new GrpcClient($resolvedLogger);
    $pdClient = new PdClient($grpc, $pdEndpoints[0], $resolvedLogger);

    return new self($pdClient, $grpc, new RegionCache(logger: $resolvedLogger), logger: $resolvedLogger);
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/RawKv/RawKvClientTest.php`
Expected: All tests PASS

- [ ] **Step 6: Run full unit + integration test suite**

Run: `vendor/bin/phpunit tests/Unit/ tests/Integration/`
Expected: All tests PASS

- [ ] **Step 7: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add src/Client/RawKv/RawKvClient.php tests/Unit/RawKv/RawKvClientTest.php
git commit -m "Use single shared GrpcClient, close channel on GrpcException"
```

---

### Task 3: E2E verification

**Files:** None (verification only)

- [ ] **Step 1: Run E2E tests**

Run: `docker compose run --rm php-test vendor/bin/phpunit --testsuite E2E --testdox`
Expected: All 141 E2E tests PASS

- [ ] **Step 2: Run full lint**

Run: `composer lint`
Expected: PASS
