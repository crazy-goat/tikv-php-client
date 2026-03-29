# Feature: RawReverseScan - Reverse Range Scanning

## Overview
Implement reverse range scanning to retrieve keys in descending order.

## Reference Implementation
- **Go**: `func (c *Client) ReverseScan(ctx context.Context, startKey, endKey []byte, limit int, options ...RawOption) (keys [][]byte, values [][]byte, err error)`
- **Java**: Not explicitly available, can be simulated

## Protobuf Messages
Uses same RawScanRequest/Response with reverse flag (if supported) or manual implementation.

## Implementation Details

### Algorithm
1. Similar to scan but iterate regions in reverse order
2. Within each region, request reverse iteration
3. Merge results maintaining reverse order

### API Design
```php
public function reverseScan(string $startKey, string $endKey, int $limit = 100): array
{
    // Returns keys in reverse (descending) order
}
```

## Priority: MEDIUM
Useful for time-series data and pagination.
