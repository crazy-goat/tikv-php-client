# Feature: GetKeyTTL - Retrieve Remaining TTL

## Overview
Implement retrieval of remaining time-to-live for a key.

## Reference Implementation
- **Go**: `func (c *Client) GetKeyTTL(ctx context.Context, key []byte, options ...RawOption) (*uint64, error)`
- **Java**: `public Optional<Long> getKeyTTL(ByteString key)`

## Protobuf Messages Required
```protobuf
message RawGetKeyTTLRequest {
  Context context = 1;
  bytes key = 2;
}

message RawGetKeyTTLResponse {
  int64 ttl = 1;  // Remaining TTL in milliseconds, -1 if not found
  bool not_found = 2;
}
```

## Implementation Details

### API Design
```php
public function getKeyTTL(string $key): ?int
{
    // Returns: remaining TTL in milliseconds
    // null: key not found or has no TTL
}
```

## Testing Strategy
1. Test getting TTL of key with expiration
2. Test key without TTL (returns null)
3. Test non-existent key (returns null)
4. Test expired key (returns null)

## Priority: MEDIUM
Useful for cache management and debugging.
