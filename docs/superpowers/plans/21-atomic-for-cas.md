# Feature: Atomic Mode for CAS (ForCas Flag)

## Overview
When using CompareAndSwap, all write operations (Put, Delete, BatchPut, BatchDelete) must set `for_cas = true` on their requests to ensure linearizability. Without this flag, concurrent Put and CAS operations on the same key may produce inconsistent results.

## Reference Implementation
- **Go**: `client.SetAtomicForCAS(true)` — client-level toggle
  - When enabled, ALL writes include `ForCas: true` in the protobuf request
  - `CompareAndSwap()` **requires** atomic mode to be on, returns error otherwise
  - Affected methods: Put, PutWithTTL, Delete, BatchPut, BatchPutWithTTL, BatchDelete
- **Java**: `atomicForCAS` config flag (from `TiConfiguration.isEnableAtomicForCAS()`)
  - `compareAndSet()` throws `IllegalArgumentException` if not enabled

## Protobuf Details
```protobuf
message RawPutRequest {
  // ...
  bool for_cas = 5;
}
message RawDeleteRequest {
  // ...
  bool for_cas = 4;
}
message RawBatchPutRequest {
  // ...
  bool for_cas = 5;
}
message RawBatchDeleteRequest {
  // ...
  bool for_cas = 4;
}
```

## Why This Matters
TiKV uses different write paths for CAS and non-CAS operations. When `for_cas = true`:
- Writes go through the **Raft propose** path with linearizable ordering
- This ensures that a CAS operation sees the latest value, even if a concurrent Put happened

Without `for_cas = true` on regular writes, a Put might bypass the CAS ordering guarantee, leading to lost updates.

## API Design
```php
$client = RawKvClient::create(['127.0.0.1:2379']);
$client->setAtomicForCAS(true); // Enable atomic mode

// Now all writes include for_cas=true
$client->put('key', 'value');
$client->compareAndSwap('key', 'value', 'new-value');
```

```php
public function setAtomicForCAS(bool $enabled): self
{
    $this->atomicForCAS = $enabled;
    return $this;
}

public function isAtomicForCAS(): bool
{
    return $this->atomicForCAS;
}
```

## Implementation Details
1. Add `private bool $atomicForCAS = false` to `RawKvClient`
2. Add `setAtomicForCAS()` / `isAtomicForCAS()` methods
3. In `put()`, `delete()`, `batchPut()`, `batchDelete()`:
   ```php
   if ($this->atomicForCAS) {
       $request->setForCas(true);
   }
   ```
4. In `compareAndSwap()`: optionally require `$this->atomicForCAS === true` (or just warn in docs)

## Testing Strategy
1. With atomic mode off: put/delete work normally (for_cas not set)
2. With atomic mode on: put/delete include for_cas=true
3. CAS with atomic mode on: works correctly
4. CAS with atomic mode off: still works (but warn in docs about linearizability)
5. Toggle atomic mode on/off mid-session

## Priority: MEDIUM
Important for correctness when mixing CAS with regular writes. Most users who only use CAS (without concurrent puts) won't notice the difference, but it's a correctness issue.
