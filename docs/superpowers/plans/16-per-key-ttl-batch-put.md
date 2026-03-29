# Feature: Per-Key TTL in BatchPut

## Overview
Extend `batchPut()` to accept individual TTL values per key, instead of a single TTL for all keys.

## Reference Implementation
- **Go**: `BatchPutWithTTL(ctx, keys, values [][]byte, ttls []uint64, options ...RawOption) error`
  - Three parallel arrays: keys[i] ↔ values[i] ↔ ttls[i]
  - `ttls` can be nil (no TTL for any key)
  - If `len(ttls) > 0`, must equal `len(keys)`
- **Java**: `batchPut(Map<ByteString, ByteString> kvPairs, long ttl)` — single TTL only (same as current PHP)

## Protobuf Details
```protobuf
message RawBatchPutRequest {
  Context context = 1;
  repeated KvPair pairs = 2;
  string cf = 3;
  uint64 ttl = 4;           // DEPRECATED — single TTL for all keys
  bool for_cas = 5;
  repeated uint64 ttls = 6; // NEW — per-key TTLs (if len==1, applies to all)
}
```

The `ttls` repeated field (field 6) supports:
- Empty: no TTL for any key
- Length 1: single TTL applied to all keys
- Length N (== len(pairs)): individual TTL per key

## API Design

### Option A: Overloaded parameter (recommended)
```php
// Current: single TTL for all
public function batchPut(array $keyValuePairs, int $ttl = 0): void

// New: accept array of TTLs
public function batchPut(array $keyValuePairs, int|array $ttl = 0): void
// When array: ['key1' => 60, 'key2' => 120] or [60, 120] indexed same as pairs
```

### Option B: Separate method
```php
public function batchPutWithTTL(array $keyValuePairs, array $ttls): void
```

### Decision
Option A is more ergonomic — single method, backward compatible. When `$ttl` is an `int`, use the existing single-TTL behavior. When `$ttl` is an `array`, use per-key TTLs via the `ttls` repeated field.

## Implementation Details
1. If `$ttl` is `int` and > 0: set `ttls = [$ttl]` (single TTL for all, field 6 with length 1)
2. If `$ttl` is `array`: validate `count($ttl) === count($keyValuePairs)`, set `ttls` field with per-key values
3. If `$ttl` is 0 or empty array: don't set any TTL fields

## Testing Strategy
1. batchPut with per-key TTLs — different TTLs per key
2. Verify each key expires at its own time
3. Mix of TTL=0 (no expiry) and TTL>0 in same batch
4. Backward compatibility: int TTL still works
5. Validation: array TTL length mismatch throws InvalidArgumentException
6. Empty array TTL means no expiration

## Priority: HIGH
Per-key TTL is a real feature gap — Go client has it, and it's needed for cache-like workloads where different keys have different lifetimes.
