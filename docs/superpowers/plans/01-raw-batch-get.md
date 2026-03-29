# Feature: RawBatchGet - Batch Key Retrieval

## Overview
Implement batch retrieval of multiple keys in a single operation for improved performance.

## Reference Implementation
- **Go**: `func (c *Client) BatchGet(ctx context.Context, keys [][]byte, options ...RawOption) ([][]byte, error)`
- **Java**: `public List<KvPair> batchGet(List<ByteString> keys)`

## Protobuf Messages Required
```protobuf
message KvPair {
  bytes key = 1;
  bytes value = 2;
}

message RawBatchGetRequest {
  Context context = 1;
  repeated bytes keys = 2;
}

message RawBatchGetResponse {
  repeated KvPair pairs = 1;
}
```

## Implementation Details

### Key Challenges
1. **Multi-region handling**: Keys may belong to different regions
2. **Parallel requests**: Send requests to multiple TiKV nodes concurrently
3. **Result aggregation**: Merge results from multiple regions
4. **Partial failures**: Handle cases where some keys fail

### Algorithm
1. Group keys by region using PD
2. Create batch request per region
3. Send parallel gRPC requests to all regions
4. Collect and merge responses
5. Return array of values (null for missing keys)

### API Design
```php
public function batchGet(array $keys): array
{
    // Input: ['key1', 'key2', 'key3']
    // Output: ['value1', null, 'value3'] (null if key not found)
}
```

### Error Handling
- If a region fails, retry that specific region
- Return null for individual key failures
- Throw exception only if all regions fail

## Testing Strategy
1. Test with keys in single region
2. Test with keys spanning multiple regions
3. Test with some keys missing
4. Test with partial region failures
5. Performance benchmark vs individual gets

## Priority: HIGH
Batch operations are essential for production use and provide significant performance improvements.
