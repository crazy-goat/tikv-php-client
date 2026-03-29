# Feature: RawBatchDelete - Batch Key Deletion

## Overview
Implement batch deletion of multiple keys in a single operation.

## Reference Implementation
- **Go**: `func (c *Client) BatchDelete(ctx context.Context, keys [][]byte, options ...RawOption) error`
- **Java**: `public void batchDelete(List<ByteString> keys)`

## Protobuf Messages Required
```protobuf
message RawBatchDeleteRequest {
  Context context = 1;
  repeated bytes keys = 2;
}

message RawBatchDeleteResponse {
}
```

## Implementation Details

### Algorithm
1. Group keys by region
2. Send parallel delete requests
3. Verify all deletions succeeded
4. Retry failed regions

### API Design
```php
public function batchDelete(array $keys): void
{
    // Input: ['key1', 'key2', 'key3']
    // Throws exception on failure
}
```

## Testing Strategy
1. Test deleting existing keys
2. Test deleting non-existent keys (should not error)
3. Test multi-region deletion
4. Test partial failure handling

## Priority: HIGH
Complements batch put operations.
