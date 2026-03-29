# Feature: Scan Iterator (Lazy Auto-Pagination)

## Overview
Implement a lazy iterator for scan operations that fetches results in batches (pages) on demand, instead of loading all results into memory at once. Essential for scanning large datasets.

## Reference Implementation
- **Java**: `scan0()` returns `Iterator<KvPair>` / `TikvIterator`
  - With limit: `RawScanIterator` — fetches `min(limit, scanBatchSize)` keys per RPC, auto-advances to next region
  - Without limit: `TikvIterator` — creates new `RawScanIterator` for each page, unlimited auto-pagination
  - `scanBatchSize` is configurable (default varies)
- **Go**: No iterator API, but `Scan()` internally auto-paginates across regions

## API Design

### PHP Iterator Interface
```php
use CrazyGoat\TiKV\Client\RawKv\ScanIterator;

// Create iterator — no data fetched yet
$iterator = $client->scanIterator('start', 'end', batchSize: 256);

// Lazy iteration — fetches in batches of 256
foreach ($iterator as $key => $value) {
    echo "$key: $value\n";
    if ($someCondition) break; // early exit, no wasted fetches
}

// Prefix iterator
$iterator = $client->scanPrefixIterator('user:', batchSize: 100);

// Key-only iterator
$iterator = $client->scanIterator('start', 'end', batchSize: 256, keyOnly: true);
foreach ($iterator as $key => $value) {
    // $value is null in keyOnly mode
}
```

### ScanIterator Class
```php
class ScanIterator implements \Iterator
{
    private array $buffer = [];
    private int $bufferIndex = 0;
    private string $currentStartKey;
    private bool $exhausted = false;
    
    public function __construct(
        private RawKvClient $client,
        private string $startKey,
        private string $endKey,
        private int $batchSize = 256,
        private bool $keyOnly = false,
    ) {}
    
    // Iterator interface: current(), key(), next(), rewind(), valid()
    // Internally calls $client->scan() with $batchSize limit
    // When buffer is exhausted, fetches next batch starting from last key + \x00
    // Stops when scan returns fewer results than batchSize (region exhausted)
}
```

## Implementation Details
1. `ScanIterator` implements PHP's `\Iterator` interface
2. On first `valid()` / `current()` call, fetches first batch via `$client->scan()`
3. When buffer is consumed, fetches next batch with `startKey = lastKey + "\x00"`
4. Stops when:
   - Scan returns empty results
   - Scan returns fewer results than `batchSize` AND we've reached `endKey`
5. `rewind()` resets to initial `startKey` and clears buffer
6. Memory usage: only `batchSize` entries in memory at any time

### Methods to Add to RawKvClient
```php
public function scanIterator(
    string $startKey,
    string $endKey,
    int $batchSize = 256,
    bool $keyOnly = false,
): ScanIterator

public function scanPrefixIterator(
    string $prefix,
    int $batchSize = 256,
    bool $keyOnly = false,
): ScanIterator
```

## Testing Strategy
1. Iterator over small dataset (< batchSize) — single fetch
2. Iterator over large dataset (> batchSize) — multiple fetches
3. Iterator with early break — no unnecessary fetches
4. Iterator rewind — re-scans from start
5. Key-only iterator — values are null
6. Prefix iterator — correct range calculation
7. Empty range — iterator is immediately exhausted
8. Iterator count matches scan() count for same range
9. Memory: iterator with batchSize=10 over 1000 keys uses constant memory

## Priority: HIGH
Critical for production use with large datasets. Without this, scanning 1M keys requires loading all into memory.
