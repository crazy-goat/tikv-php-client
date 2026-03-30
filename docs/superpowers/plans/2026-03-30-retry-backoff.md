# Retry with Exponential Backoff Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace fixed retry count with budget-based exponential backoff matching Go/Java TiKV client error handling semantics.

**Architecture:** A `Backoff` calculator computes sleep durations using `min(cap, base * 2^attempt)` with optional equal jitter. A `BackoffType` enum maps error types to backoff configs. `RawKvClient.executeWithRetry` uses a 20s total backoff budget instead of fixed retry count, classifying each error to determine backoff strategy or fatal throw.

**Tech Stack:** PHP 8.2+, PHPUnit 11, no external dependencies.

---

### Task 1: Backoff calculator

**Files:**
- Create: `src/Client/Retry/Backoff.php`
- Create: `tests/Unit/Retry/BackoffTest.php`

- [ ] **Step 1: Write the test file**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Retry;

use CrazyGoat\TiKV\Client\Retry\Backoff;
use PHPUnit\Framework\TestCase;

class BackoffTest extends TestCase
{
    public function testExponentialAttemptZeroReturnsBase(): void
    {
        $this->assertSame(100, Backoff::exponential(100, 2000, 0));
    }

    public function testExponentialGrowsExponentially(): void
    {
        $this->assertSame(100, Backoff::exponential(100, 2000, 0));
        $this->assertSame(200, Backoff::exponential(100, 2000, 1));
        $this->assertSame(400, Backoff::exponential(100, 2000, 2));
        $this->assertSame(800, Backoff::exponential(100, 2000, 3));
        $this->assertSame(1600, Backoff::exponential(100, 2000, 4));
    }

    public function testExponentialCapsAtMax(): void
    {
        $this->assertSame(2000, Backoff::exponential(100, 2000, 5));
        $this->assertSame(2000, Backoff::exponential(100, 2000, 10));
        $this->assertSame(2000, Backoff::exponential(100, 2000, 100));
    }

    public function testExponentialWithEqualJitterInRange(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $result = Backoff::exponential(100, 2000, 0, true);
            $this->assertGreaterThanOrEqual(50, $result);
            $this->assertLessThanOrEqual(100, $result);
        }
    }

    public function testExponentialWithEqualJitterCapped(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $result = Backoff::exponential(100, 2000, 10, true);
            $this->assertGreaterThanOrEqual(1000, $result);
            $this->assertLessThanOrEqual(2000, $result);
        }
    }

    public function testExponentialSmallBaseAndCap(): void
    {
        $this->assertSame(2, Backoff::exponential(2, 500, 0));
        $this->assertSame(4, Backoff::exponential(2, 500, 1));
        $this->assertSame(256, Backoff::exponential(2, 500, 7));
        $this->assertSame(500, Backoff::exponential(2, 500, 8));
        $this->assertSame(500, Backoff::exponential(2, 500, 20));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Retry/BackoffTest.php`
Expected: FAIL — `Backoff` class not found

- [ ] **Step 3: Write the Backoff implementation**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Retry;

final class Backoff
{
    public static function exponential(int $baseMs, int $capMs, int $attempt, bool $equalJitter = false): int
    {
        $expo = (int) min($capMs, $baseMs * (2 ** $attempt));

        if (!$equalJitter) {
            return $expo;
        }

        $half = intdiv($expo, 2);
        return $half + random_int(0, $half);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Retry/BackoffTest.php --testdox`
Expected: PASS — all tests green

- [ ] **Step 5: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Client/Retry/Backoff.php tests/Unit/Retry/BackoffTest.php
git commit -m "feat: add Backoff exponential calculator"
```

---

### Task 2: BackoffType enum

**Files:**
- Create: `src/Client/Retry/BackoffType.php`

- [ ] **Step 1: Create the enum**

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

    public function baseMs(): int
    {
        return match ($this) {
            self::None => 0,
            self::ServerBusy => 2000,
            self::StaleCmd => 2,
            self::RegionMiss => 2,
            self::TiKvRpc => 100,
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

- [ ] **Step 2: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add src/Client/Retry/BackoffType.php
git commit -m "feat: add BackoffType enum with backoff configs"
```

---

### Task 3: Integrate into RawKvClient

**Files:**
- Modify: `src/Client/RawKv/RawKvClient.php`
- Modify: `tests/Unit/RawKv/RawKvClientTest.php`

- [ ] **Step 1: Update RawKvClient constructor**

In `src/Client/RawKv/RawKvClient.php`, add imports:

```php
use CrazyGoat\TiKV\Client\Retry\BackoffType;
```

Change constructor parameter `$maxRetries = 3` to `$maxBackoffMs = 20000`:

```php
    public function __construct(
        private readonly PdClientInterface $pdClient,
        private readonly GrpcClientInterface $grpc,
        private readonly RegionCacheInterface $regionCache = new RegionCache(),
        private readonly int $maxBackoffMs = 20000,
    ) {
    }
```

- [ ] **Step 2: Add classifyError method**

Add this private method to `RawKvClient`:

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

        if ($e instanceof GrpcException) {
            return BackoffType::TiKvRpc;
        }

        return null;
    }
```

Add import for `GrpcException` if not already present:

```php
use CrazyGoat\TiKV\Client\Exception\GrpcException;
```

- [ ] **Step 3: Replace executeWithRetry**

Replace the entire `executeWithRetry` method:

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
                $backoffType = $this->classifyError($e);

                if ($backoffType === null) {
                    throw $e;
                }

                $cached = $this->regionCache->getByKey($key);
                if ($cached instanceof RegionInfo) {
                    $this->regionCache->invalidate($cached->regionId);
                }

                $sleepMs = $backoffType->sleepMs($attempt);
                $totalBackoffMs += $sleepMs;

                if ($totalBackoffMs > $this->maxBackoffMs) {
                    throw $e;
                }

                if ($sleepMs > 0) {
                    usleep($sleepMs * 1000);
                }

                $attempt++;
            }
        }
    }
```

- [ ] **Step 4: Update RawKvClientTest setUp**

In `tests/Unit/RawKv/RawKvClientTest.php`, the constructor call in `setUp` currently passes 3 args. It still works because `$maxBackoffMs` has a default. No change needed to setUp.

- [ ] **Step 5: Add test for fatal error (no retry)**

Add to `RawKvClientTest`:

```php
    public function testRaftEntryTooLargeThrowsImmediately(): void
    {
        $this->regionCache->method('getByKey')->willReturn(null);
        $this->regionCache->method('put');

        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $this->grpc->expects($this->once())
            ->method('call')
            ->willThrowException(new TiKvException('RaftEntryTooLarge'));

        $this->expectException(TiKvException::class);
        $this->expectExceptionMessage('RaftEntryTooLarge');
        $this->client->get('key');
    }

    public function testKeyNotInRegionThrowsImmediately(): void
    {
        $this->regionCache->method('getByKey')->willReturn(null);
        $this->regionCache->method('put');

        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $this->grpc->expects($this->once())
            ->method('call')
            ->willThrowException(new TiKvException('KeyNotInRegion'));

        $this->expectException(TiKvException::class);
        $this->expectExceptionMessage('KeyNotInRegion');
        $this->client->get('key');
    }
```

Add import at top of test file:

```php
use CrazyGoat\TiKV\Client\Exception\TiKvException;
```

- [ ] **Step 6: Add test for EpochNotMatch (immediate retry, no sleep)**

```php
    public function testEpochNotMatchRetriesImmediately(): void
    {
        $this->regionCache->method('getByKey')->willReturn(null);
        $this->regionCache->method('put');
        $this->regionCache->method('invalidate');

        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $response = new RawGetResponse();
        $response->setValue('found');

        $this->grpc->expects($this->exactly(2))
            ->method('call')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new TiKvException('EpochNotMatch')),
                $response,
            );

        $result = $this->client->get('key');
        $this->assertSame('found', $result);
    }
```

- [ ] **Step 7: Add test for budget exceeded**

```php
    public function testBudgetExceededThrowsLastException(): void
    {
        $client = new RawKvClient($this->pdClient, $this->grpc, $this->regionCache, 0);

        $this->regionCache->method('getByKey')->willReturn(null);
        $this->regionCache->method('put');
        $this->regionCache->method('invalidate');

        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $this->grpc->method('call')
            ->willThrowException(new TiKvException('ServerIsBusy'));

        $this->expectException(TiKvException::class);
        $this->expectExceptionMessage('ServerIsBusy');
        $client->get('key');
    }
```

- [ ] **Step 8: Add test for GrpcException retry**

```php
    public function testGrpcExceptionTriggersRetry(): void
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
                $this->throwException(new GrpcException('connection reset', 14)),
                $response,
            );

        $result = $this->client->get('key');
        $this->assertSame('recovered', $result);
    }
```

Add import:

```php
use CrazyGoat\TiKV\Client\Exception\GrpcException;
```

- [ ] **Step 9: Run unit tests**

Run: `vendor/bin/phpunit tests/Unit/ --testdox`
Expected: ALL tests pass

- [ ] **Step 10: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 11: Commit**

```bash
git add src/Client/RawKv/RawKvClient.php tests/Unit/RawKv/RawKvClientTest.php
git commit -m "feat: integrate budget-based retry with exponential backoff into RawKvClient"
```

---

### Task 4: Final verification

**Files:** none (verification only)

- [ ] **Step 1: Run full unit test suite**

Run: `vendor/bin/phpunit tests/Unit/ --testdox`
Expected: All tests pass

- [ ] **Step 2: Run lint**

Run: `composer lint`
Expected: PASS

- [ ] **Step 3: Run E2E tests**

Run: `docker compose run --rm php-test vendor/bin/phpunit --testsuite E2E --testdox`
Expected: All 141 E2E tests pass

- [ ] **Step 4: Commit plan doc**

```bash
git add docs/superpowers/plans/2026-03-30-retry-backoff.md
git commit -m "docs: add retry/backoff implementation plan"
```
