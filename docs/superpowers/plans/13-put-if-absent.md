# Feature: PutIfAbsent - Conditional Insertion

## Overview
Implement atomic put operation that only succeeds if key doesn't exist.

## Reference Implementation
- **Go**: Not explicit, can use CAS
- **Java**: `public Optional<ByteString> putIfAbsent(ByteString key, ByteString value)`

## Implementation Details

### Algorithm
1. Check if key exists
2. If not exists, put value
3. Return previous value (null if inserted)

### API Design
```php
public function putIfAbsent(string $key, string $value): ?string
{
    // Returns: previous value if key existed, null if inserted
}
```

### Use Cases
- Idempotent operations
- Initialization patterns
- Distributed locks

## Testing Strategy
1. Test inserting new key (returns null)
2. Test with existing key (returns existing value)
3. Test atomicity under concurrency

## Priority: MEDIUM
Useful for initialization and idempotent operations.
