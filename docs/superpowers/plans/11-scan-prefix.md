# Feature: ScanPrefix - Prefix-based Scanning

## Overview
Implement convenient prefix scanning for keys with common prefix.

## Reference Implementation
- **Go**: Not explicit, but can be built on top of Scan
- **Java**: `public List<KvPair> scanPrefix(ByteString prefixKey, int limit, boolean keyOnly)`

## Implementation Details

### Algorithm
1. Calculate end key from prefix (increment last byte)
2. Use regular scan with calculated range
3. Return results

### API Design
```php
public function scanPrefix(string $prefix, int $limit = 100, bool $keyOnly = false): array
{
    // Scans all keys starting with $prefix
    // Returns: [['key' => ..., 'value' => ...], ...]
}
```

### Helper Function
```php
private function prefixEndKey(string $prefix): string
{
    // Calculate end key for prefix scan
    // Example: "user:" -> "user;"
}
```

## Testing Strategy
1. Test prefix scan with matching keys
2. Test with no matching keys
3. Test with limit
4. Test key-only scan

## Priority: MEDIUM
Very useful for hierarchical data (users:, orders:, etc.).
