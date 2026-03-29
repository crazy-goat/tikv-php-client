# Feature: CompareAndSwap (CAS) - Atomic Compare and Set

## Overview
Implement atomic compare-and-swap operation for optimistic locking.

## Reference Implementation
- **Go**: `func (c *Client) CompareAndSwap(ctx context.Context, key, previousValue, newValue []byte, options ...RawOption) ([]byte, bool, error)`
- **Java**: `public void compareAndSet(ByteString key, Optional<ByteString> prevValue, ByteString value)`

## Protobuf Messages Required
```protobuf
message RawCASRequest {
  Context context = 1;
  bytes key = 2;
  bytes value = 3;
  bytes previous_value = 4;  // Expected current value
  uint64 ttl = 5;
}

message RawCASResponse {
  bytes value = 1;      // Current value in store
  bool succeed = 2;     // True if swap succeeded
}
```

## Implementation Details

### Algorithm
1. Read current value
2. Compare with expected value
3. If match, write new value
4. Return success/failure and actual value

### API Design
```php
public function compareAndSwap(string $key, ?string $expectedValue, string $newValue): bool
{
    // Returns: true if swap succeeded, false otherwise
    // $expectedValue: null means key should not exist
}
```

### Use Cases
- Optimistic locking
- Atomic counters
- Leader election
- Distributed locks

## Testing Strategy
1. Test successful swap
2. Test failed swap (value changed)
3. Test swap with null expected (key shouldn't exist)
4. Test concurrent swaps

## Priority: MEDIUM
Important for distributed coordination.
