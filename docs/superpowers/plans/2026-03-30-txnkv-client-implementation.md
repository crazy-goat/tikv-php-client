# TxnKV Client Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement TxnKV client with pessimistic/optimistic transaction modes, MVCC, lock resolver, and transactional backoff types

**Architecture:** Create TxnKvClient as main entry point, Transaction for state management, LockResolver for deadlock handling, extend BackoffType with TxnLock/TxnLockFast/TxnNotFound

**Tech Stack:** PHP 8.2+, gRPC extension, PHPUnit

---

## File Map

**Create:**
- `src/Client/TxnKv/TransactionStatus.php`
- `src/Client/TxnKv/Exception/TransactionConflictException.php`
- `src/Client/TxnKv/Exception/LockWaitTimeoutException.php`
- `src/Client/TxnKv/LockResolver.php`
- `src/Client/TxnKv/Transaction.php`
- `src/Client/TxnKv/TxnKvClient.php`
- `tests/Unit/TxnKv/TransactionStatusTest.php`
- `tests/Unit/TxnKv/LockResolverTest.php`
- `tests/Unit/TxnKv/TransactionTest.php`
- `tests/Unit/TxnKv/TxnKvClientTest.php`

**Modify:**
- `src/Client/Retry/BackoffType.php`

---

## Task 1: TransactionStatus Enum

**Files:**
- Create: `src/Client/TxnKv/TransactionStatus.php`
- Test: `tests/Unit/TxnKv/TransactionStatusTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\TxnKv;

use CrazyGoat\TiKV\Client\TxnKv\TransactionStatus;
use PHPUnit\Framework\TestCase;

class TransactionStatusTest extends TestCase
{
    public function testEnumCases(): void
    {
        $this->assertSame('Active', TransactionStatus::Active->name);
        $this->assertSame('Committed', TransactionStatus::Committed->name);
        $this->assertSame('RolledBack', TransactionStatus::RolledBack->name);
    }
}
```

Run: `./vendor/bin/phpunit tests/Unit/TxnKv/TransactionStatusTest.php`
Expected: FAIL - TransactionStatus class does not exist

- [ ] **Step 2: Create TransactionStatus enum**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\TxnKv;

enum TransactionStatus
{
    case Active;
    case Committed;
    case RolledBack;
}
```

- [ ] **Step 3: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/TxnKv/TransactionStatusTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Client/TxnKv/TransactionStatus.php tests/Unit/TxnKv/TransactionStatusTest.php
git commit -m "feat(txnkv): add TransactionStatus enum"
```

---

## Task 2: Transaction Exceptions

**Files:**
- Create: `src/Client/TxnKv/Exception/TransactionConflictException.php`
- Create: `src/Client/TxnKv/Exception/LockWaitTimeoutException.php`

- [ ] **Step 1: Create TransactionConflictException**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\TxnKv\Exception;

use CrazyGoat\TiKV\Client\Exception\TiKvException;

final class TransactionConflictException extends TiKvException
{
    /**
     * @param string[] $conflictingKeys
     */
    public function __construct(
        string $message = 'Transaction conflict detected',
        private readonly ?array $conflictingKeys = null,
    ) {
        parent::__construct($message);
    }

    /**
     * @return string[]|null
     */
    public function getConflictingKeys(): ?array
    {
        return $this->conflictingKeys;
    }
}
```

- [ ] **Step 2: Create LockWaitTimeoutException**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\TxnKv\Exception;

use CrazyGoat\TiKV\Client\Exception\TiKvException;

final class LockWaitTimeoutException extends TiKvException
{
    public function __construct(
        private readonly string $key,
        private readonly int $timeoutMs,
    ) {
        parent::__construct("Lock wait timeout for key: {$key} ({$timeoutMs}ms)");
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getTimeoutMs(): int
    {
        return $this->timeoutMs;
    }
}
```

- [ ] **Step 3: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Client/TxnKv/Exception/ --level=9`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Client/TxnKv/Exception/
git commit -m "feat(txnkv): add TransactionConflictException and LockWaitTimeoutException"
```

---

## Task 3: Extend BackoffType with Transactional Types

**Files:**
- Modify: `src/Client/Retry/BackoffType.php`

- [ ] **Step 1: Add new enum cases**

Add to enum:
```php
case TxnLock;
case TxnLockFast;
case TxnNotFound;
```

- [ ] **Step 2: Update baseMs()**

Add to match:
```php
self::TxnLock => 200,
self::TxnLockFast => 100,
self::TxnNotFound => 2,
```

- [ ] **Step 3: Update capMs()**

Add to match:
```php
self::TxnLock => 3000,
self::TxnLockFast => 3000,
self::TxnNotFound => 500,
```

- [ ] **Step 4: Update equalJitter()**

Add to match:
```php
self::TxnLock, self::TxnLockFast => true,
```

- [ ] **Step 5: Run tests and PHPStan**

Run: `./vendor/bin/phpstan analyse src/Client/Retry/BackoffType.php --level=9`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Client/Retry/BackoffType.php
git commit -m "feat(txnkv): add TxnLock, TxnLockFast, TxnNotFound backoff types"
```

---

## Task 4: LockResolver

**Files:**
- Create: `src/Client/TxnKv/LockResolver.php`
- Test: `tests/Unit/TxnKv/LockResolverTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\TxnKv;

use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use CrazyGoat\TiKV\Client\TxnKv\LockResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class LockResolverTest extends TestCase
{
    public function testConstruction(): void
    {
        $grpc = $this->createMock(GrpcClientInterface::class);
        $resolver = new LockResolver($grpc, new NullLogger());

        $this->assertInstanceOf(LockResolver::class, $resolver);
    }
}
```

Run: `./vendor/bin/phpunit tests/Unit/TxnKv/LockResolverTest.php`
Expected: FAIL - LockResolver class does not exist

- [ ] **Step 2: Create LockResolver skeleton**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\TxnKv;

use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class LockResolver
{
    public function __construct(
        private readonly GrpcClientInterface $grpc,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Resolve lock for a key.
     * Called when encountering a lock during read/write.
     */
    public function resolveLock(string $key, int $lockTs): void
    {
        $this->logger->debug('Resolving lock', ['key' => $key, 'lockTs' => $lockTs]);
        // TODO: Implement lock resolution
    }

    /**
     * Check for deadlocks.
     * Called periodically in pessimistic mode.
     */
    public function checkDeadlock(string $txnId): bool
    {
        $this->logger->debug('Checking for deadlock', ['txnId' => $txnId]);
        // TODO: Implement deadlock detection
        return false;
    }
}
```

- [ ] **Step 3: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/TxnKv/LockResolverTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Client/TxnKv/LockResolver.php tests/Unit/TxnKv/LockResolverTest.php
git commit -m "feat(txnkv): add LockResolver skeleton"
```

---

## Task 5: Transaction

**Files:**
- Create: `src/Client/TxnKv/Transaction.php`
- Test: `tests/Unit/TxnKv/TransactionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\TxnKv;

use CrazyGoat\TiKV\Client\TxnKv\Transaction;
use CrazyGoat\TiKV\Client\TxnKv\TransactionStatus;
use PHPUnit\Framework\TestCase;

class TransactionTest extends TestCase
{
    public function testConstruction(): void
    {
        $txn = new Transaction(
            txnId: 'test-txn-1',
            startTs: 1000,
            pessimistic: true,
            priority: 0,
        );

        $this->assertSame('test-txn-1', $txn->getTxnId());
        $this->assertSame(1000, $txn->getStartTs());
        $this->assertTrue($txn->isPessimistic());
        $this->assertSame(0, $txn->getPriority());
        $this->assertSame(TransactionStatus::Active, $txn->getStatus());
    }
}
```

Run: `./vendor/bin/phpunit tests/Unit/TxnKv/TransactionTest.php`
Expected: FAIL - Transaction class does not exist

- [ ] **Step 2: Create Transaction skeleton**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\TxnKv;

use CrazyGoat\TiKV\Client\TxnKv\Exception\TransactionConflictException;

final class Transaction
{
    private ?int $commitTs = null;
    private TransactionStatus $status;
    /** @var array<string, string> */
    private array $writeSet = [];
    /** @var array<string, ?string> */
    private array $readSet = [];
    /** @var array<string, int> */
    private array $locks = [];

    public function __construct(
        private readonly string $txnId,
        private readonly int $startTs,
        private readonly bool $pessimistic,
        private readonly int $priority,
    ) {
        $this->status = TransactionStatus::Active;
    }

    public function getTxnId(): string
    {
        return $this->txnId;
    }

    public function getStartTs(): int
    {
        return $this->startTs;
    }

    public function getCommitTs(): ?int
    {
        return $this->commitTs;
    }

    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    public function isPessimistic(): bool
    {
        return $this->pessimistic;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function get(string $key): ?string
    {
        // Check writeSet first (own writes)
        if (isset($this->writeSet[$key])) {
            return $this->writeSet[$key];
        }

        // TODO: Read from TiKV with startTs
        $value = null; // Placeholder

        // Record in readSet
        $this->readSet[$key] = $value;

        return $value;
    }

    public function set(string $key, string $value): void
    {
        if ($this->pessimistic) {
            // TODO: Acquire lock immediately
        }

        $this->writeSet[$key] = $value;
    }

    public function delete(string $key): void
    {
        $this->set($key, ''); // Empty string means delete
    }

    /**
     * @return array<array{key: string, value: ?string}>
     */
    public function scan(string $startKey, string $endKey): array
    {
        // TODO: Implement scan
        return [];
    }

    public function commit(): void
    {
        if ($this->status !== TransactionStatus::Active) {
            throw new \RuntimeException('Transaction is not active');
        }

        // TODO: Implement commit
        $this->status = TransactionStatus::Committed;
    }

    public function rollback(): void
    {
        if ($this->status !== TransactionStatus::Active) {
            throw new \RuntimeException('Transaction is not active');
        }

        $this->writeSet = [];
        $this->readSet = [];
        $this->locks = [];
        $this->status = TransactionStatus::RolledBack;
    }
}
```

- [ ] **Step 3: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/TxnKv/TransactionTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Client/TxnKv/Transaction.php tests/Unit/TxnKv/TransactionTest.php
git commit -m "feat(txnkv): add Transaction skeleton"
```

---

## Task 6: TxnKvClient

**Files:**
- Create: `src/Client/TxnKv/TxnKvClient.php`
- Test: `tests/Unit/TxnKv/TxnKvClientTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\TxnKv;

use CrazyGoat\TiKV\Client\Cache\RegionCacheInterface;
use CrazyGoat\TiKV\Client\Connection\PdClientInterface;
use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use CrazyGoat\TiKV\Client\TxnKv\TxnKvClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class TxnKvClientTest extends TestCase
{
    public function testConstruction(): void
    {
        $pdClient = $this->createMock(PdClientInterface::class);
        $grpc = $this->createMock(GrpcClientInterface::class);
        $regionCache = $this->createMock(RegionCacheInterface::class);

        $client = new TxnKvClient(
            pdClient: $pdClient,
            grpc: $grpc,
            regionCache: $regionCache,
            logger: new NullLogger(),
        );

        $this->assertInstanceOf(TxnKvClient::class, $client);
    }
}
```

Run: `./vendor/bin/phpunit tests/Unit/TxnKv/TxnKvClientTest.php`
Expected: FAIL - TxnKvClient class does not exist

- [ ] **Step 2: Create TxnKvClient skeleton**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\TxnKv;

use CrazyGoat\TiKV\Client\Cache\RegionCache;
use CrazyGoat\TiKV\Client\Cache\RegionCacheInterface;
use CrazyGoat\TiKV\Client\Connection\PdClientInterface;
use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class TxnKvClient
{
    public function __construct(
        private readonly PdClientInterface $pdClient,
        private readonly GrpcClientInterface $grpc,
        private readonly RegionCacheInterface $regionCache = new RegionCache(),
        private readonly int $maxBackoffMs = 20000,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Begin a new transaction.
     *
     * @param array{
     *   pessimistic?: bool,
     *   priority?: int,
     * } $options
     */
    public function begin(array $options = []): Transaction
    {
        $pessimistic = $options['pessimistic'] ?? true;
        $priority = $options['priority'] ?? 0;

        // TODO: Get timestamp from PD
        $startTs = time() * 1000; // Placeholder

        $txnId = uniqid('txn-', true);

        $this->logger->info('Transaction started', [
            'txnId' => $txnId,
            'startTs' => $startTs,
            'pessimistic' => $pessimistic,
        ]);

        return new Transaction(
            txnId: $txnId,
            startTs: $startTs,
            pessimistic: $pessimistic,
            priority: $priority,
        );
    }
}
```

- [ ] **Step 3: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/TxnKv/TxnKvClientTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Client/TxnKv/TxnKvClient.php tests/Unit/TxnKv/TxnKvClientTest.php
git commit -m "feat(txnkv): add TxnKvClient skeleton"
```

---

## Task 7: Final Verification

- [ ] **Step 1: Run full test suite**

Run: `./vendor/bin/phpunit --testsuite Unit --no-coverage`
Expected: ALL PASS

- [ ] **Step 2: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Client/TxnKv/ --level=9`
Expected: No errors

- [ ] **Step 3: Run PHPCS**

Run: `./vendor/bin/phpcs src/Client/TxnKv/ --standard=phpcs.xml.dist`
Expected: No errors

- [ ] **Step 4: Run Rector**

Run: `./vendor/bin/rector process src/Client/TxnKv/ --dry-run`
Expected: No changes needed

- [ ] **Step 5: Verify no TODO/FIXME**

Run: `grep -r "TODO\|FIXME" src/Client/TxnKv/`
Expected: Only in skeleton methods (expected for now)

---

## Summary

**New files:** 10
**Modified files:** 1
**Total commits:** 7

**After all tasks:**
- TxnKvClient with begin() method
- Transaction with get/set/delete/scan/commit/rollback
- LockResolver skeleton
- Transactional backoff types (TxnLock, TxnLockFast, TxnNotFound)
- All tests passing
- PHPStan level 9 clean

**Note:** This is a skeleton implementation. Full MVCC, lock resolution, and TiKV integration would require additional work with actual gRPC calls to TiKV transaction API.
