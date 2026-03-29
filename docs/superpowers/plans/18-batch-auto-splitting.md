# Feature: Batch Auto-Splitting by Size and Count

## Overview
Automatically split large batch operations into smaller sub-batches to prevent exceeding TiKV's per-request limits. Currently, the PHP client sends arbitrarily large batches to a single region, which can fail or cause performance issues.

## Reference Implementation

### Go Client Limits
- `rawBatchPutSize = 16 * 1024` (16KB) — max bytes per batch put sub-request
- `rawBatchPairCount = 512` — max keys per batch get/delete sub-request

### Java Client Limits
- `MAX_RAW_BATCH_LIMIT = 1024` — max keys per single batch RPC to one region
- `RAW_BATCH_PUT_SIZE = 1048576` (1MB) — max total byte size of a batch put request
- `RAW_BATCH_GET_SIZE = 16384` (16KB) — max total byte size of keys in a batch get
- `RAW_BATCH_DELETE_SIZE = 16384` (16KB) — max total byte size of keys in a batch delete

### Splitting Strategy (Java)
1. Group keys by region (already done in PHP)
2. Within each region group, split into sub-batches by:
   - Key count: max `MAX_RAW_BATCH_LIMIT` (1024) keys per sub-batch
   - Byte size: max `RAW_BATCH_PUT_SIZE` / `RAW_BATCH_GET_SIZE` / `RAW_BATCH_DELETE_SIZE`
3. Send each sub-batch as a separate RPC

## API Design
No API changes — splitting is transparent to the caller. Add constants:

```php
class RawKvClient
{
    public const MAX_BATCH_LIMIT = 512;        // max keys per batch sub-request
    public const MAX_BATCH_PUT_SIZE = 16384;   // 16KB max bytes per batch put (Go-style conservative)
    public const MAX_BATCH_GET_SIZE = 16384;   // 16KB max bytes for keys in batch get
    public const MAX_BATCH_DELETE_SIZE = 16384; // 16KB max bytes for keys in batch delete
}
```

## Implementation Details

### batchPut
1. Group pairs by region (existing logic)
2. For each region group, split into sub-batches where:
   - Each sub-batch has at most `MAX_BATCH_LIMIT` pairs
   - Total serialized size of pairs in sub-batch ≤ `MAX_BATCH_PUT_SIZE`
3. Send each sub-batch as a separate `RawBatchPutRequest`

### batchGet
1. Group keys by region (existing logic)
2. For each region group, split into sub-batches where:
   - Each sub-batch has at most `MAX_BATCH_LIMIT` keys
   - Total byte size of keys ≤ `MAX_BATCH_GET_SIZE`
3. Send each sub-batch as a separate `RawBatchGetRequest`
4. Merge results

### batchDelete
1. Group keys by region (existing logic)
2. For each region group, split into sub-batches where:
   - Each sub-batch has at most `MAX_BATCH_LIMIT` keys
   - Total byte size of keys ≤ `MAX_BATCH_DELETE_SIZE`
3. Send each sub-batch as a separate `RawBatchDeleteRequest`

### Size Calculation
```php
// For batchPut: sum of key lengths + value lengths
$batchSize = array_sum(array_map(fn($k, $v) => strlen($k) + strlen($v), $keys, $values));

// For batchGet/batchDelete: sum of key lengths
$batchSize = array_sum(array_map('strlen', $keys));
```

## Testing Strategy
1. batchPut with > 512 keys splits correctly
2. batchPut with large values (total > 16KB) splits by size
3. batchGet with > 512 keys splits correctly
4. batchDelete with > 512 keys splits correctly
5. Verify all keys are processed despite splitting
6. Verify results are merged correctly for batchGet
7. Edge case: exactly 512 keys (no split needed)
8. Edge case: single key larger than size limit (should still work as 1-key batch)

## Priority: HIGH
Without splitting, large batches can fail silently or cause TiKV performance issues. This is a safety and reliability feature.
