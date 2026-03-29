# Feature: Checksum - Data Integrity Verification

## Overview
Implement checksum calculation for a range of keys to verify data integrity.

## Reference Implementation
- **Go**: `func (c *Client) Checksum(ctx context.Context, startKey, endKey []byte, options ...RawOption) (checksum uint64, totalKvs uint64, totalBytes uint64, err error)`
- **Java**: Not explicitly available

## Protobuf Messages Required
```protobuf
message RawChecksumRequest {
  Context context = 1;
  bytes start_key = 2;
  bytes end_key = 3;
  uint32 algorithm = 4;  // 0: CRC64, 1: XXHash
}

message RawChecksumResponse {
  uint64 checksum = 1;
  uint64 total_kvs = 2;
  uint64 total_bytes = 3;
}
```

## Implementation Details

### Algorithms
- CRC64 (default) - widely compatible
- XXHash - faster, good for large datasets

### API Design
```php
public function checksum(string $startKey, string $endKey, string $algorithm = 'crc64'): array
{
    // Returns: ['checksum' => ..., 'totalKvs' => ..., 'totalBytes' => ...]
}
```

### Use Cases
- Data consistency verification
- Backup validation
- Data migration verification

## Testing Strategy
1. Test checksum of empty range
2. Test checksum of single key
3. Test checksum of multi-region range
4. Test different algorithms
5. Verify checksum changes when data changes

## Priority: LOW
Useful for data integrity but not essential for basic operations.
