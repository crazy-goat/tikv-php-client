# Feature: RawScan - Range Scanning

## Overview
Implement range scanning to retrieve multiple keys within a key range.

## Reference Implementation
- **Go**: `func (c *Client) Scan(ctx context.Context, startKey, endKey []byte, limit int, options ...RawOption) (keys [][]byte, values [][]byte, err error)`
- **Java**: `public List<KvPair> scan(ByteString startKey, ByteString endKey, int limit)`

## Protobuf Messages Required
```protobuf
message RawScanRequest {
  Context context = 1;
  bytes start_key = 2;
  bytes end_key = 3;
  uint32 limit = 4;
  bool key_only = 5;
}

message RawScanResponse {
  repeated KvPair pairs = 1;
}
```

## Implementation Details

### Key Challenges
1. **Multi-region scans**: Range may span multiple regions
2. **Pagination**: Handle large ranges exceeding limit
3. **Key ordering**: Maintain sorted order across regions
4. **Continuation**: Support resuming scans

### Algorithm
1. Get regions covering the range from PD
2. Scan each region sequentially
3. Merge results maintaining order
4. Stop when limit reached
5. Return continuation token if more data exists

### API Design
```php
public function scan(string $startKey, string $endKey, int $limit = 100, bool $keyOnly = false): array
{
    // Returns: [['key' => 'key1', 'value' => 'value1'], ...]
    // or just keys if keyOnly=true
}
```

### Advanced Features
- Support for key-only scans (faster, less memory)
- Reverse scanning
- Prefix scanning helper method

## Testing Strategy
1. Test scan within single region
2. Test scan spanning multiple regions
3. Test with limit
4. Test key-only scan
5. Test empty range
6. Test large dataset performance

## Priority: HIGH
Essential for data exploration and pagination.
