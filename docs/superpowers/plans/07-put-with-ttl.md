# Feature: PutWithTTL - Time-To-Live Storage

## Overview
Implement key-value storage with automatic expiration after specified time.

## Reference Implementation
- **Go**: `func (c *Client) PutWithTTL(ctx context.Context, key, value []byte, ttl uint64, options ...RawOption) error`
- **Java**: `public void put(ByteString key, ByteString value, long ttl)`

## Protobuf Messages Required
```protobuf
message RawPutRequest {
  Context context = 1;
  bytes key = 2;
  bytes value = 3;
  uint64 ttl = 4;  // TTL in milliseconds
}
```

## Implementation Details

### TTL Handling
- TTL specified in milliseconds
- Key automatically deleted after TTL expires
- Zero or omitted TTL means no expiration

### API Design
```php
public function put(string $key, string $value, ?int $ttlMs = null): void
{
    // $ttlMs: time to live in milliseconds
    // null means no expiration
}
```

## Testing Strategy
1. Test put with TTL
2. Test that key expires after TTL
3. Test put without TTL (no expiration)
4. Test batch put with TTL

## Priority: MEDIUM
Useful for session data, caching, and temporary data.
