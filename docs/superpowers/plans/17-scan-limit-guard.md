# Feature: Scan Limit Guard (MAX_RAW_SCAN_LIMIT)

## Overview
Enforce a maximum scan limit of 10240 keys per scan request, matching Go and Java client behavior. Prevents accidental OOM on TiKV nodes from unbounded scans.

## Reference Implementation
- **Go**: `MaxRawKVScanLimit = 10240` (exported var), returns `ErrMaxScanLimitExceeded` if exceeded
- **Java**: `MAX_RAW_SCAN_LIMIT = 10240` (constant), throws exception if exceeded

## Current Behavior
PHP client has no limit enforcement. `scan()`, `reverseScan()`, `scanPrefix()`, and `batchScan()` accept any limit value, including 0 (meaning "all keys"). This can cause TiKV to return millions of keys in a single response, leading to OOM.

## API Design
```php
class RawKvClient
{
    public const MAX_SCAN_LIMIT = 10240;
    
    public function scan(string $startKey, string $endKey, int $limit = 0, bool $keyOnly = false): array
    {
        // If limit is 0 (all), cap at MAX_SCAN_LIMIT
        // If limit > MAX_SCAN_LIMIT, throw InvalidArgumentException
    }
}
```

## Implementation Details
1. Add `MAX_SCAN_LIMIT = 10240` constant to `RawKvClient`
2. In `scan()`, `reverseScan()`, `scanPrefix()`:
   - If `$limit === 0`: use `MAX_SCAN_LIMIT` as effective limit
   - If `$limit > MAX_SCAN_LIMIT`: throw `InvalidArgumentException`
   - If `$limit > 0 && $limit <= MAX_SCAN_LIMIT`: use as-is
3. In `batchScan()`: apply same logic to `$eachLimit`

### Breaking Change Consideration
Currently `$limit = 0` means "return all keys". Changing it to cap at 10240 is a **behavioral change**. Options:
- **Option A**: Cap silently (Go approach) — less disruptive
- **Option B**: Throw exception (Java approach) — more explicit
- **Recommended**: Option A for `$limit = 0` (cap silently), Option B for explicit `$limit > 10240` (throw)

## Testing Strategy
1. Scan with limit > 10240 throws InvalidArgumentException
2. Scan with limit = 0 returns at most 10240 keys
3. Scan with limit = 10240 works normally
4. ReverseScan same behavior
5. ScanPrefix same behavior
6. BatchScan eachLimit same behavior

## Priority: HIGH
Safety guard — prevents accidental resource exhaustion on TiKV.
