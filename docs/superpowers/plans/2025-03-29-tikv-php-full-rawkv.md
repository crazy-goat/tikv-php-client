# TiKV PHP Client - Full RawKV Implementation Plan

> **Goal:** Implement complete RawKV API support for TiKV PHP Client

**Architecture:** 
- Extend existing client with batch operations, scanning, and advanced features
- Maintain retry logic and region routing
- Keep minimal proto approach for fast builds

**Tech Stack:**
- PHP 8.2+ with gRPC extension
- google/protobuf for serialization
- Docker Compose for testing

---

## Current Status

### ✅ Implemented (MVP)
- [x] RawGet - single key read
- [x] RawPut - single key write
- [x] RawDelete - single key delete
- [x] PD Region Discovery with RegionEpoch
- [x] Retry logic for EpochNotMatch
- [x] Region routing to correct TiKV node

### 🚧 Missing Operations

## Phase 1: Batch Operations (High Priority)

### Task 1: RawBatchGet
**Files:**
- Modify: `proto/minimal/kvrpcpb.proto`
- Modify: `src/RawKv/RawKvClient.php`

**Implementation:**

```protobuf
// Add to kvrpcpb.proto
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

```php
public function batchGet(array $keys): array
{
    // Group keys by region
    // Send parallel requests to different regions
    // Merge results
}
```

### Task 2: RawBatchPut
**Files:**
- Modify: `proto/minimal/kvrpcpb.proto`
- Modify: `src/RawKv/RawKvClient.php`

**Implementation:**

```protobuf
message RawBatchPutRequest {
  Context context = 1;
  repeated KvPair pairs = 2;
}

message RawBatchPutResponse {
}
```

### Task 3: RawBatchDelete
**Files:**
- Modify: `proto/minimal/kvrpcpb.proto`
- Modify: `src/RawKv/RawKvClient.php`

**Implementation:**

```protobuf
message RawBatchDeleteRequest {
  Context context = 1;
  repeated bytes keys = 2;
}

message RawBatchDeleteResponse {
}
```

---

## Phase 2: Scan Operations (High Priority)

### Task 4: RawScan
**Files:**
- Modify: `proto/minimal/kvrpcpb.proto`
- Modify: `src/RawKv/RawKvClient.php`

**Implementation:**

```protobuf
message RawScanRequest {
  Context context = 1;
  bytes start_key = 2;
  bytes end_key = 3;
  uint32 limit = 4;
  bool key_only = 5;
  bool reverse = 6;
}

message RawScanResponse {
  repeated KvPair pairs = 1;
}
```

```php
public function scan(string $startKey, string $endKey, int $limit = 100, bool $keyOnly = false): array
{
    // Handle multi-region scans
    // Paginate through regions
    // Return merged results
}
```

### Task 5: RawBatchScan
**Files:**
- Modify: `proto/minimal/kvrpcpb.proto`
- Modify: `src/RawKv/RawKvClient.php`

**Implementation:**

```protobuf
message RawBatchScanRequest {
  Context context = 1;
  repeated bytes start_keys = 2;
  uint32 limit = 3;
}

message RawBatchScanResponse {
  repeated KvPair pairs = 1;
}
```

---

## Phase 3: Range Operations (Medium Priority)

### Task 6: RawDeleteRange
**Files:**
- Modify: `proto/minimal/kvrpcpb.proto`
- Modify: `src/RawKv/RawKvClient.php`

**Implementation:**

```protobuf
message RawDeleteRangeRequest {
  Context context = 1;
  bytes start_key = 2;
  bytes end_key = 3;
}

message RawDeleteRangeResponse {
}
```

```php
public function deleteRange(string $startKey, string $endKey): void
{
    // Delete all keys in range across multiple regions
}
```

---

## Phase 4: Advanced Features (Medium Priority)

### Task 7: RawCAS (Compare and Swap)
**Files:**
- Modify: `proto/minimal/kvrpcpb.proto`
- Modify: `src/RawKv/RawKvClient.php`

**Implementation:**

```protobuf
message RawCASRequest {
  Context context = 1;
  bytes key = 2;
  bytes value = 3;
  bytes previous_value = 4;
  uint64 ttl = 5;
}

message RawCASResponse {
  bytes value = 1;
  bool succeed = 2;
}
```

```php
public function compareAndSwap(string $key, string $value, ?string $previousValue = null): bool
{
    // Atomic compare and swap operation
}
```

### Task 8: RawGetKeyTTL
**Files:**
- Modify: `proto/minimal/kvrpcpb.proto`
- Modify: `src/RawKv/RawKvClient.php`

**Implementation:**

```protobuf
message RawGetKeyTTLRequest {
  Context context = 1;
  bytes key = 2;
}

message RawGetKeyTTLResponse {
  int64 ttl = 1;
  bool not_found = 2;
}
```

---

## Phase 5: Utility Operations (Low Priority)

### Task 9: RawChecksum
**Files:**
- Modify: `proto/minimal/kvrpcpb.proto`
- Modify: `src/RawKv/RawKvClient.php`

**Implementation:**

```protobuf
message RawChecksumRequest {
  Context context = 1;
  bytes start_key = 2;
  bytes end_key = 3;
  uint32 algorithm = 4; // 0: CRC64, 1: XXHash
}

message RawChecksumResponse {
  uint64 checksum = 1;
  uint64 total_kvs = 2;
  uint64 total_bytes = 3;
}
```

### Task 10: RawCoprocessor
**Files:**
- Modify: `proto/minimal/kvrpcpb.proto`
- Modify: `src/RawKv/RawKvClient.php`

**Implementation:**

```protobuf
message RawCoprocessorRequest {
  Context context = 1;
  bytes start_key = 2;
  bytes end_key = 3;
  string coprocessor_name = 4;
  bytes data = 5;
}

message RawCoprocessorResponse {
  bytes data = 1;
  bool is_empty = 2;
}
```

---

## Implementation Priority

### Must Have (Phase 1-2)
1. ✅ RawGet - DONE
2. ✅ RawPut - DONE
3. ✅ RawDelete - DONE
4. 🔄 RawBatchGet - NEXT
5. 🔄 RawBatchPut - NEXT
6. 🔄 RawBatchDelete - NEXT
7. 🔄 RawScan - NEXT

### Should Have (Phase 3)
8. RawDeleteRange
9. RawBatchScan

### Nice to Have (Phase 4-5)
10. RawCAS
11. RawGetKeyTTL
12. RawChecksum
13. RawCoprocessor

---

## Testing Strategy

### Unit Tests
- Test each operation individually
- Mock TiKV responses
- Test error handling

### Integration Tests
- Test with real TiKV cluster
- Test multi-region scenarios
- Test retry logic

### Performance Tests
- Benchmark batch operations
- Compare single vs batch performance
- Test scan performance with large datasets

---

## API Design Principles

1. **Simple API** - Easy to use for common cases
2. **Batch Operations** - Efficient for bulk operations
3. **Async Support** - Future support for async operations
4. **Error Handling** - Clear error messages with retry info
5. **Type Safety** - Strict typing with PHP 8.2

---

## Example Usage (Future)

```php
use TiKvPhp\RawKv\RawKvClient;

$client = RawKvClient::create(['127.0.0.1:2379']);

// Batch operations
$values = $client->batchGet(['key1', 'key2', 'key3']);
$client->batchPut(['key1' => 'value1', 'key2' => 'value2']);
$client->batchDelete(['key1', 'key2']);

// Scan
$results = $client->scan('prefix:', 'prefix:~', 100);

// Range delete
$client->deleteRange('start:', 'end:');

// CAS
$success = $client->compareAndSwap('key', 'new_value', 'old_value');

$client->close();
```
