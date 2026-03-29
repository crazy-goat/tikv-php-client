# Feature: Exponential Backoff Retry

## Overview
Replace the current simple 3-retry mechanism with exponential backoff, handling more error types and providing configurable timeouts. The current retry logic only handles `EpochNotMatch` errors with fixed retries.

## Reference Implementation

### Go Client
- `Backoffer` with configurable max backoff time (default 20000ms = 20s)
- Handles: `EpochNotMatch`, `ServerIsBusy`, `StaleCommand`, `NotLeader`, `RegionNotFound`, `KeyNotInRegion`
- Exponential backoff with jitter
- Each error type has its own base sleep time

### Java Client
- `ConcreteBackOffer` with per-operation timeouts:
  - Read: `getRawKVReadTimeoutInMS()`
  - Write: `getRawKVWriteTimeoutInMS()`
  - Batch read: `getRawKVBatchReadTimeoutInMS()`
  - Batch write: `getRawKVBatchWriteTimeoutInMS()`
  - Scan: `getRawKVScanTimeoutInMS()`
  - DeleteRange: `getRawKVCleanTimeoutInMS()`
- Handles: region errors, server busy, stale epoch, not leader, store not match

## Current Behavior
```php
private function executeWithRetry(string $key, callable $operation, int $maxRetries = 3): mixed
{
    // Catches RuntimeException with 'EpochNotMatch'
    // Invalidates region cache and retries
    // Fixed 3 retries, no backoff
}
```

## API Design

### Backoff Configuration
```php
$client = RawKvClient::create(
    pdAddresses: ['127.0.0.1:2379'],
    backoffConfig: new BackoffConfig(
        maxRetries: 10,
        maxBackoffMs: 20000,
        baseBackoffMs: 100,
    ),
);
```

### BackoffConfig Value Object
```php
final readonly class BackoffConfig
{
    public function __construct(
        public int $maxRetries = 10,
        public int $maxBackoffMs = 20000,  // 20 seconds total max
        public int $baseBackoffMs = 100,   // initial sleep
        public float $multiplier = 2.0,    // exponential factor
        public float $jitter = 0.1,        // ±10% randomization
    ) {}
}
```

## Implementation Details

### Error Types to Handle
1. **EpochNotMatch** — region epoch changed (already handled)
2. **NotLeader** — request sent to non-leader replica → refresh leader, retry
3. **ServerIsBusy** — TiKV overloaded → backoff and retry
4. **StaleCommand** — command arrived after region split/merge → refresh region, retry
5. **RegionNotFound** — region no longer exists → refresh region cache, retry
6. **KeyNotInRegion** — key doesn't belong to this region → refresh region, retry
7. **StoreNotMatch** — store ID mismatch → refresh store info, retry

### Backoff Algorithm
```php
private function executeWithRetry(string $key, callable $operation): mixed
{
    $attempt = 0;
    $totalSleepMs = 0;
    
    while (true) {
        try {
            return $operation();
        } catch (RegionException $e) {
            $attempt++;
            if ($attempt >= $this->backoffConfig->maxRetries) throw $e;
            if ($totalSleepMs >= $this->backoffConfig->maxBackoffMs) throw $e;
            
            $this->invalidateRegionCache($key);
            
            $sleepMs = min(
                $this->backoffConfig->baseBackoffMs * ($this->backoffConfig->multiplier ** ($attempt - 1)),
                $this->backoffConfig->maxBackoffMs - $totalSleepMs,
            );
            $sleepMs *= (1 + (mt_rand(-100, 100) / 1000) * $this->backoffConfig->jitter);
            
            usleep((int)($sleepMs * 1000));
            $totalSleepMs += $sleepMs;
        }
    }
}
```

## Testing Strategy
1. EpochNotMatch still triggers retry (backward compatible)
2. NotLeader triggers leader refresh + retry
3. ServerIsBusy triggers backoff + retry
4. Max retries exceeded throws exception
5. Max backoff time exceeded throws exception
6. Backoff times increase exponentially
7. Default config works without explicit configuration

## Priority: MEDIUM
Improves reliability under load and during region splits/merges. The current 3-retry is fragile for production use.
