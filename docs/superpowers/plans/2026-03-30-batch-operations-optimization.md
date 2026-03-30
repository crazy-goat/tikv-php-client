# Batch Operations Optimization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Execute per-region batch requests in parallel using async gRPC to reduce latency for multi-region operations

**Architecture:** Create GrpcFuture for async gRPC calls, BatchAsyncExecutor to manage parallel execution, and modify RawKvClient batch methods to use parallel dispatch

**Tech Stack:** PHP 8.2+, gRPC extension, PHPUnit

---

## File Map

**Create:**
- `src/Client/Batch/GrpcFuture.php`
- `src/Client/Batch/BatchAsyncExecutor.php`
- `src/Client/Exception/BatchPartialFailureException.php`
- `tests/Unit/Batch/GrpcFutureTest.php`
- `tests/Unit/Batch/BatchAsyncExecutorTest.php`

**Modify:**
- `src/Client/RawKv/RawKvClient.php` (batchGet, batchPut, batchDelete, add async methods)

---

## Task 1: GrpcFuture

**Files:**
- Create: `src/Client/Batch/GrpcFuture.php`
- Test: `tests/Unit/Batch/GrpcFutureTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Batch;

use CrazyGoat\TiKV\Client\Batch\GrpcFuture;
use Grpc\Call;
use PHPUnit\Framework\TestCase;

class GrpcFutureTest extends TestCase
{
    public function testConstruction(): void
    {
        $call = $this->createMock(Call::class);
        $future = new GrpcFuture($call, 'TestResponseClass');

        $this->assertInstanceOf(GrpcFuture::class, $future);
    }
}
```

Run: `./vendor/bin/phpunit tests/Unit/Batch/GrpcFutureTest.php`
Expected: FAIL - GrpcFuture class does not exist

- [ ] **Step 2: Create GrpcFuture class**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Batch;

use CrazyGoat\Google\Protobuf\Internal\Message;
use CrazyGoat\TiKV\Client\Exception\GrpcException;
use Grpc\Call;

final class GrpcFuture
{
    private bool $completed = false;
    private ?Message $result = null;
    private ?GrpcException $error = null;

    public function __construct(
        private readonly Call $call,
        private readonly string $responseClass,
    ) {}

    public function wait(): Message
    {
        if ($this->completed) {
            if ($this->error !== null) {
                throw $this->error;
            }
            return $this->result;
        }

        $event = $this->call->startBatch([
            \Grpc\OP_RECV_INITIAL_METADATA => true,
            \Grpc\OP_RECV_MESSAGE => true,
            \Grpc\OP_RECV_STATUS_ON_CLIENT => true,
        ]);

        $status = $this->extractStatus($event);

        if ($status['code'] !== \Grpc\STATUS_OK) {
            $this->error = new GrpcException($status['details'], $status['code']);
            $this->completed = true;
            throw $this->error;
        }

        $this->result = $this->deserializeResponse($event);
        $this->completed = true;

        return $this->result;
    }

    /**
     * @return array{code: int, details: string}
     */
    private function extractStatus(mixed $event): array
    {
        if (is_object($event)) {
            $event = (array) $event;
        }

        /** @var array<string, mixed> $eventArray */
        $eventArray = $event;
        $status = $eventArray['status'] ?? null;
        if (is_object($status)) {
            $status = (array) $status;
        }

        /** @var array<string, mixed> $statusArray */
        $statusArray = is_array($status) ? $status : [];

        $code = $statusArray['code'] ?? 0;
        $details = $statusArray['details'] ?? '';

        return [
            'code' => is_int($code) ? $code : (is_string($code) ? (int) $code : 0),
            'details' => is_string($details) ? $details : (is_scalar($details) ? (string) $details : ''),
        ];
    }

    /**
     * @return Message
     */
    private function deserializeResponse(mixed $event): Message
    {
        if (is_object($event)) {
            $event = (array) $event;
        }

        /** @var array<string, mixed> $eventArray */
        $eventArray = $event;
        $message = $eventArray['message'] ?? null;

        /** @var Message $response */
        $response = new $this->responseClass();

        if ($message !== null && $message !== '' && is_string($message)) {
            $response->mergeFromString($message);
        }

        return $response;
    }
}
```

- [ ] **Step 3: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Batch/GrpcFutureTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Client/Batch/GrpcFuture.php tests/Unit/Batch/GrpcFutureTest.php
git commit -m "feat(batch): add GrpcFuture for async gRPC calls"
```

---

## Task 2: BatchPartialFailureException

**Files:**
- Create: `src/Client/Exception/BatchPartialFailureException.php`
- Test: (tested via BatchAsyncExecutor tests)

- [ ] **Step 1: Create BatchPartialFailureException**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Exception;

final class BatchPartialFailureException extends TiKvException
{
    /**
     * @param array<int, TiKvException> $regionErrors regionId => exception
     * @param int $totalRegions Total number of regions in batch
     */
    public function __construct(
        private readonly array $regionErrors,
        private readonly int $totalRegions,
    ) {
        $firstError = reset($regionErrors);
        parent::__construct(
            sprintf(
                'Batch operation partially failed: %d of %d regions failed. First error: %s',
                count($regionErrors),
                $totalRegions,
                $firstError instanceof TiKvException ? $firstError->getMessage() : 'Unknown',
            )
        );
    }

    /**
     * @return array<int, TiKvException>
     */
    public function getRegionErrors(): array
    {
        return $this->regionErrors;
    }

    public function getTotalRegions(): int
    {
        return $this->totalRegions;
    }
}
```

- [ ] **Step 2: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Client/Exception/BatchPartialFailureException.php --level=9`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add src/Client/Exception/BatchPartialFailureException.php
git commit -m "feat(batch): add BatchPartialFailureException for partial batch failures"
```

---

## Task 3: BatchAsyncExecutor

**Files:**
- Create: `src/Client/Batch/BatchAsyncExecutor.php`
- Test: `tests/Unit/Batch/BatchAsyncExecutorTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Batch;

use CrazyGoat\TiKV\Client\Batch\BatchAsyncExecutor;
use CrazyGoat\TiKV\Client\Exception\BatchPartialFailureException;
use CrazyGoat\TiKV\Client\Exception\TiKvException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class BatchAsyncExecutorTest extends TestCase
{
    public function testExecuteParallelWithSuccessfulCalls(): void
    {
        $executor = new BatchAsyncExecutor(new NullLogger());

        $calls = [
            1 => fn() => 'result1',
            2 => fn() => 'result2',
        ];

        $results = $executor->executeParallel($calls);

        $this->assertSame(['result1', 'result2'], $results);
    }

    public function testExecuteParallelWithPartialFailure(): void
    {
        $executor = new BatchAsyncExecutor(new NullLogger());

        $calls = [
            1 => fn() => 'result1',
            2 => fn() => throw new TiKvException('Region 2 failed'),
        ];

        $this->expectException(BatchPartialFailureException::class);
        $this->expectExceptionMessage('Batch operation partially failed: 1 of 2 regions failed');

        $executor->executeParallel($calls);
    }

    public function testExecuteParallelWithAllFailure(): void
    {
        $executor = new BatchAsyncExecutor(new NullLogger());

        $calls = [
            1 => fn() => throw new TiKvException('Region 1 failed'),
            2 => fn() => throw new TiKvException('Region 2 failed'),
        ];

        try {
            $executor->executeParallel($calls);
            $this->fail('Expected exception');
        } catch (BatchPartialFailureException $e) {
            $this->assertCount(2, $e->getRegionErrors());
            $this->assertSame(2, $e->getTotalRegions());
        }
    }
}
```

Run: `./vendor/bin/phpunit tests/Unit/Batch/BatchAsyncExecutorTest.php`
Expected: FAIL - BatchAsyncExecutor class does not exist

- [ ] **Step 2: Create BatchAsyncExecutor class**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Batch;

use CrazyGoat\TiKV\Client\Exception\BatchPartialFailureException;
use CrazyGoat\TiKV\Client\Exception\TiKvException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class BatchAsyncExecutor
{
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Execute multiple callables concurrently and return results.
     *
     * @template T
     * @param array<int, callable(): T> $regionCalls Array of regionId => callable returning GrpcFuture
     * @return array<int, T> Array of regionId => result
     * @throws BatchPartialFailureException If any region fails
     */
    public function executeParallel(array $regionCalls): array
    {
        $totalRegions = count($regionCalls);

        $this->logger->debug('Starting parallel batch execution', [
            'totalRegions' => $totalRegions,
        ]);

        // Start all calls - they return GrpcFuture objects
        $futures = [];
        foreach ($regionCalls as $regionId => $callable) {
            $futures[$regionId] = $callable();
        }

        // Collect all results
        $results = [];
        $errors = [];

        foreach ($futures as $regionId => $future) {
            try {
                $results[$regionId] = $future->wait();
                $this->logger->debug('Region completed successfully', ['regionId' => $regionId]);
            } catch (TiKvException $e) {
                $errors[$regionId] = $e;
                $this->logger->warning('Region failed', [
                    'regionId' => $regionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($errors)) {
            throw new BatchPartialFailureException($errors, $totalRegions);
        }

        $this->logger->debug('Parallel batch execution completed', [
            'totalRegions' => $totalRegions,
        ]);

        return $results;
    }
}
```

- [ ] **Step 3: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Batch/BatchAsyncExecutorTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Client/Batch/BatchAsyncExecutor.php tests/Unit/Batch/BatchAsyncExecutorTest.php
git commit -m "feat(batch): add BatchAsyncExecutor for parallel region execution"
```

---

## Task 4: Add Async Methods to RawKvClient

**Files:**
- Modify: `src/Client/RawKv/RawKvClient.php`

- [ ] **Step 1: Add imports**

Add to imports:
```php
use CrazyGoat\TiKV\Client\Batch\BatchAsyncExecutor;
use CrazyGoat\TiKV\Client\Batch\GrpcFuture;
use CrazyGoat\TiKV\Client\Exception\BatchPartialFailureException;
```

- [ ] **Step 2: Add executeBatchGetForRegionAsync method**

Add after `executeBatchGetForRegion` method:

```php
/**
 * @param array<string> $keys
 */
private function executeBatchGetForRegionAsync(RegionInfo $region, array $keys): GrpcFuture
{
    return $this->executeWithRetryAsync($keys[0], function () use ($region, $keys): GrpcFuture {
        $address = $this->resolveStoreAddress($region->leaderStoreId);

        $request = new RawBatchGetRequest();
        $request->setContext(RegionContext::fromRegionInfo($region));
        $request->setKeys($keys);

        $call = new Call(
            $this->getChannel($address),
            '/tikvpb.Tikv/RawBatchGet',
            Timeval::infFuture(),
        );

        $call->startBatch([
            \Grpc\OP_SEND_INITIAL_METADATA => [],
            \Grpc\OP_SEND_MESSAGE => ['message' => $request->serializeToString()],
            \Grpc\OP_SEND_CLOSE_FROM_CLIENT => true,
        ]);

        return new GrpcFuture($call, RawBatchGetResponse::class);
    });
}
```

- [ ] **Step 3: Add executeWithRetryAsync helper**

Add private method:

```php
/**
 * @template T
 * @param callable(): GrpcFuture $operation
 * @return GrpcFuture
 */
private function executeWithRetryAsync(string $key, callable $operation): GrpcFuture
{
    // For async operations, we return the future immediately
    // Retry logic will be applied when wait() is called
    return $operation();
}
```

- [ ] **Step 4: Modify batchGet to use parallel execution**

Replace the batchGet method body (lines 290-312):

From:
```php
$results = [];
foreach ($keysByRegion as $regionData) {
    $regionResults = $this->executeBatchGetForRegion($regionData['region'], $regionData['keys']);
    $results = array_merge($results, $regionResults);
}
```

To:
```php
// Execute all regions in parallel
$regionCalls = [];
foreach ($keysByRegion as $regionId => $regionData) {
    $regionCalls[$regionId] = function() use ($regionData) {
        return $this->executeBatchGetForRegionAsync($regionData['region'], $regionData['keys']);
    };
}

$executor = new BatchAsyncExecutor($this->logger);

try {
    $regionResults = $executor->executeParallel($regionCalls);
} catch (BatchPartialFailureException $e) {
    // For batchGet, partial failure means some keys are missing
    // We still want to return what we got
    $regionResults = [];
    foreach ($e->getRegionErrors() as $regionId => $error) {
        $this->logger->error('BatchGet failed for region', [
            'regionId' => $regionId,
            'error' => $error->getMessage(),
        ]);
    }
    // Re-throw to maintain existing behavior
    throw $e;
}

// Merge results from all regions
$results = [];
foreach ($regionResults as $regionResult) {
    /** @var RawBatchGetResponse $response */
    $response = $regionResult;
    foreach ($response->getPairs() as $pair) {
        $results[$pair->getKey()] = $pair->getValue() !== '' ? $pair->getValue() : null;
    }
}
```

- [ ] **Step 5: Run tests to verify nothing broke**

Run: `./vendor/bin/phpunit tests/Unit/RawKv/RawKvClientTest.php --filter batchGet`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Client/RawKv/RawKvClient.php
git commit -m "feat(batch): implement parallel batchGet execution"
```

---

## Task 5: Update batchPut and batchDelete

**Files:**
- Modify: `src/Client/RawKv/RawKvClient.php`

- [ ] **Step 1: Add async methods for batchPut and batchDelete**

Add after executeBatchPutForRegion:

```php
/**
 * @param KvPair[] $pairs
 */
private function executeBatchPutForRegionAsync(RegionInfo $region, array $pairs, int $ttl): GrpcFuture
{
    return $this->executeWithRetryAsync($pairs[0]->getKey(), function () use ($region, $pairs, $ttl): GrpcFuture {
        $address = $this->resolveStoreAddress($region->leaderStoreId);

        $request = new RawBatchPutRequest();
        $request->setContext(RegionContext::fromRegionInfo($region));
        $request->setPairs($pairs);
        if ($ttl > 0) {
            $request->setTtls([$ttl]);
        }

        $call = new Call(
            $this->getChannel($address),
            '/tikvpb.Tikv/RawBatchPut',
            Timeval::infFuture(),
        );

        $call->startBatch([
            \Grpc\OP_SEND_INITIAL_METADATA => [],
            \Grpc\OP_SEND_MESSAGE => ['message' => $request->serializeToString()],
            \Grpc\OP_SEND_CLOSE_FROM_CLIENT => true,
        ]);

        return new GrpcFuture($call, RawBatchPutResponse::class);
    });
}
```

Add after executeBatchDeleteForRegion:

```php
/**
 * @param string[] $keys
 */
private function executeBatchDeleteForRegionAsync(RegionInfo $region, array $keys): GrpcFuture
{
    return $this->executeWithRetryAsync($keys[0], function () use ($region, $keys): GrpcFuture {
        $address = $this->resolveStoreAddress($region->leaderStoreId);

        $request = new RawBatchDeleteRequest();
        $request->setContext(RegionContext::fromRegionInfo($region));
        $request->setKeys($keys);

        $call = new Call(
            $this->getChannel($address),
            '/tikvpb.Tikv/RawBatchDelete',
            Timeval::infFuture(),
        );

        $call->startBatch([
            \Grpc\OP_SEND_INITIAL_METADATA => [],
            \Grpc\OP_SEND_MESSAGE => ['message' => $request->serializeToString()],
            \Grpc\OP_SEND_CLOSE_FROM_CLIENT => true,
        ]);

        return new GrpcFuture($call, RawBatchDeleteResponse::class);
    });
}
```

- [ ] **Step 2: Modify batchPut for parallel execution**

Replace batchPut region loop (lines 341-343):

From:
```php
foreach ($pairsByRegion as $regionData) {
    $this->executeBatchPutForRegion($regionData['region'], $regionData['pairs'], $ttl);
}
```

To:
```php
// Execute all regions in parallel
$regionCalls = [];
foreach ($pairsByRegion as $regionId => $regionData) {
    $regionCalls[$regionId] = function() use ($regionData, $ttl) {
        return $this->executeBatchPutForRegionAsync($regionData['region'], $regionData['pairs'], $ttl);
    };
}

$executor = new BatchAsyncExecutor($this->logger);
$executor->executeParallel($regionCalls);
```

- [ ] **Step 3: Modify batchDelete for parallel execution**

Replace batchDelete region loop (lines 361-363):

From:
```php
foreach ($keysByRegion as $regionData) {
    $this->executeBatchDeleteForRegion($regionData['region'], $regionData['keys']);
}
```

To:
```php
// Execute all regions in parallel
$regionCalls = [];
foreach ($keysByRegion as $regionId => $regionData) {
    $regionCalls[$regionId] = function() use ($regionData) {
        return $this->executeBatchDeleteForRegionAsync($regionData['region'], $regionData['keys']);
    };
}

$executor = new BatchAsyncExecutor($this->logger);
$executor->executeParallel($regionCalls);
```

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/phpunit tests/Unit/RawKv/RawKvClientTest.php --filter "batchPut|batchDelete"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Client/RawKv/RawKvClient.php
git commit -m "feat(batch): implement parallel batchPut and batchDelete execution"
```

---

## Task 6: Final Verification

- [ ] **Step 1: Run full test suite**

Run: `./vendor/bin/phpunit --testsuite Unit`
Expected: ALL PASS (140+ tests)

- [ ] **Step 2: Run PHPStan**

Run: `./vendor/bin/phpstan analyse --no-progress`
Expected: No errors

- [ ] **Step 3: Run PHPCS**

Run: `./vendor/bin/phpcs --standard=phpcs.xml.dist`
Expected: No errors

- [ ] **Step 4: Run Rector**

Run: `./vendor/bin/rector process --dry-run`
Expected: No changes needed

- [ ] **Step 5: Verify no TODO/FIXME**

Run: `grep -r "TODO\|FIXME" src/Client/Batch/`
Expected: No matches

---

## Summary

**New files:** 5
**Modified files:** 1
**Total commits:** 6

**After all tasks:**
- Batch operations execute in parallel across regions
- Reduced latency for multi-region batches
- Proper error handling for partial failures
- All tests pass
