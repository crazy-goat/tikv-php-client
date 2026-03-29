# Feature: Slow Query Logging

## Overview
Log operations that exceed a configurable time threshold. Helps identify performance bottlenecks and slow TiKV nodes in production.

## Reference Implementation
- **Java**: Per-operation slow log thresholds:
  - `getRawKVReadSlowLogInMS()` — threshold for reads
  - `getRawKVWriteSlowLogInMS()` — threshold for writes
  - `getRawKVBatchReadSlowLogInMS()` — threshold for batch reads
  - `getRawKVBatchWriteSlowLogInMS()` — threshold for batch writes
  - `getRawKVScanSlowLogInMS()` — threshold for scans
  - Logs: operation type, key(s), duration, TiKV address
- **Go**: No built-in slow logging (uses external metrics/tracing)

## API Design

### PSR-3 Logger Integration
```php
use Psr\Log\LoggerInterface;

$client = RawKvClient::create(
    pdAddresses: ['127.0.0.1:2379'],
    logger: $monologLogger,
    slowLogConfig: new SlowLogConfig(
        readThresholdMs: 100,
        writeThresholdMs: 200,
        scanThresholdMs: 500,
    ),
);
```

### SlowLogConfig Value Object
```php
final readonly class SlowLogConfig
{
    public function __construct(
        public int $readThresholdMs = 100,       // get, getKeyTTL
        public int $writeThresholdMs = 200,      // put, delete, CAS
        public int $batchReadThresholdMs = 500,   // batchGet
        public int $batchWriteThresholdMs = 500,  // batchPut, batchDelete
        public int $scanThresholdMs = 1000,       // scan, reverseScan, batchScan
        public int $deleteRangeThresholdMs = 2000, // deleteRange, deletePrefix
    ) {}
}
```

## Implementation Details

### Timing Wrapper
```php
private function withSlowLog(string $operation, string $key, callable $fn): mixed
{
    $start = hrtime(true);
    try {
        return $fn();
    } finally {
        $durationMs = (hrtime(true) - $start) / 1_000_000;
        $threshold = $this->getSlowLogThreshold($operation);
        
        if ($this->logger && $threshold > 0 && $durationMs > $threshold) {
            $this->logger->warning('Slow TiKV operation', [
                'operation' => $operation,
                'key' => $key,
                'duration_ms' => round($durationMs, 2),
                'threshold_ms' => $threshold,
            ]);
        }
    }
}
```

### Dependencies
- `psr/log` package (PSR-3 LoggerInterface)
- Add to `composer.json` as optional dependency (`suggest`)

## Testing Strategy
1. Operations below threshold: no log output
2. Operations above threshold: warning logged
3. No logger configured: no errors (graceful no-op)
4. Custom thresholds respected
5. Log message contains operation type, key, and duration

## Priority: LOW
Nice-to-have for production observability. Not critical for functionality.
