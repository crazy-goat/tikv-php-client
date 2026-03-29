# Feature: RawBatchScan - Multiple Range Scanning

## Overview
Implement scanning of multiple non-contiguous ranges in parallel.

## Reference Implementation
- **Go**: Not explicitly available
- **Java**: `public List<List<KvPair>> batchScan(List<ScanOption> ranges)`

## Protobuf Messages Required
```protobuf
message RawBatchScanRequest {
  Context context = 1;
  repeated bytes start_keys = 2;
  uint32 limit = 3;
}

message RawBatchScanResponse {
  repeated KvPair pairs = 1;
}
```

## Implementation Details

### Algorithm
1. Group start keys by region
2. Send parallel scan requests
3. Merge results
4. Apply limit across all ranges

### API Design
```php
public function batchScan(array $startKeys, int $limit = 100): array
{
    // Input: ['prefix1:', 'prefix2:', 'prefix3:']
    // Scans from each start key to next region boundary
}
```

## Priority: LOW
Specialized use case, can be simulated with multiple scans.
