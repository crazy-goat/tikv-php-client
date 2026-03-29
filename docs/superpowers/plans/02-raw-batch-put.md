# Feature: RawBatchPut - Batch Key Storage

## Overview
Implement batch storage of multiple key-value pairs in a single operation for improved write performance.

## Reference Implementation
- **Go**: `func (c *Client) BatchPut(ctx context.Context, keys, values [][]byte, options ...RawOption) error`
- **Java**: `public void batchPut(Map<ByteString, ByteString> kvPairs)`

## Protobuf Messages Required
```protobuf
message RawBatchPutRequest {
  Context context = 1;
  repeated KvPair pairs = 2;
}

message RawBatchPutResponse {
}
```

## Implementation Details

### Key Challenges
1. **Atomicity**: All keys in batch should succeed or fail together per region
2. **Multi-region writes**: Keys may go to different TiKV nodes
3. **Batch size limits**: TiKV has limits on batch size
4. **Error handling**: Partial failures need retry logic

### Algorithm
1. Group key-value pairs by target region
2. Split large batches if exceeding TiKV limits
3. Send parallel writes to all regions
4. Verify all succeeded
5. Retry failed regions

### API Design
```php
public function batchPut(array $keyValuePairs): void
{
    // Input: ['key1' => 'value1', 'key2' => 'value2']
    // Throws exception on failure
}
```

### Error Handling
- Atomic per region: all keys in a region succeed or fail together
- Retry failed regions individually
- Throw exception if any region fails after retries

## Testing Strategy
1. Test single region batch put
2. Test multi-region batch put
3. Test large batch splitting
4. Test partial failure retry
5. Performance comparison

## Priority: HIGH
Essential for high-throughput applications.
