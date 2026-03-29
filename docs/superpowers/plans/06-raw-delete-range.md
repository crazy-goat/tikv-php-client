# Feature: RawDeleteRange - Delete Key Range

## Overview
Implement deletion of all keys within a specified range.

## Reference Implementation
- **Go**: `func (c *Client) DeleteRange(ctx context.Context, startKey []byte, endKey []byte, options ...RawOption) error`
- **Java**: `public synchronized void deleteRange(ByteString startKey, ByteString endKey)`

## Protobuf Messages Required
```protobuf
message RawDeleteRangeRequest {
  Context context = 1;
  bytes start_key = 2;
  bytes end_key = 3;
}

message RawDeleteRangeResponse {
}
```

## Implementation Details

### Key Challenges
1. **Large ranges**: May contain millions of keys
2. **Multi-region**: Range spans multiple regions
3. **Progress tracking**: Long operation needs progress indication
4. **Safety**: Prevent accidental full database deletion

### Algorithm
1. Get all regions in range
2. For each region, send delete range request
3. Handle region splits during operation
4. Retry failed regions

### Safety Features
- Require explicit confirmation for large ranges
- Support dry-run mode (count keys without deleting)
- Progress callback

### API Design
```php
public function deleteRange(string $startKey, string $endKey): void
{
    // Deletes ALL keys in range [startKey, endKey)
    // Use with caution!
}
```

## Testing Strategy
1. Test small range deletion
2. Test multi-region range deletion
3. Test with non-existent range
4. Test safety limits

## Priority: MEDIUM
Useful for data cleanup and TTL-like operations.
