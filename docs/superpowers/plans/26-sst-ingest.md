# Feature: SST Ingest (Bulk Import)

## Overview
Implement bulk data import via TiKV's SST (Sorted String Table) Import API. This bypasses the normal Raft write path and directly ingests pre-sorted data into TiKV regions, achieving much higher throughput for large data loads.

## Reference Implementation
- **Java**: `ingest(List<Pair<ByteString, ByteString>> list)` and `ingest(list, Long ttl)`
  - Switches TiKV to import mode
  - Pre-splits regions at data boundaries
  - Groups keys by region
  - Writes SST files via `ImporterClient`
  - Switches back to normal mode
- **Go**: Not available in rawkv package (available in separate import tools)

## Protobuf Details
Uses the `ImportSST` service (separate from `Tikv` service):
```protobuf
service ImportSST {
  rpc SwitchMode(SwitchModeRequest) returns (SwitchModeResponse) {}
  rpc Upload(stream UploadRequest) returns (UploadResponse) {}
  rpc Ingest(IngestRequest) returns (IngestResponse) {}
  rpc Write(stream WriteRequest) returns (WriteResponse) {}
  // ...
}
```

## API Design
```php
$client = RawKvClient::create(['127.0.0.1:2379']);

// Bulk import — data must be sorted by key
$pairs = [
    'key1' => 'value1',
    'key2' => 'value2',
    // ... millions of pairs
];
$client->ingest($pairs);

// With TTL
$client->ingest($pairs, ttl: 3600);
```

## Implementation Details

### High-Level Flow
1. **Sort keys** — SST requires sorted input
2. **Switch to import mode** — `SwitchMode(Import)` on all TiKV stores
3. **Split regions** — pre-split at data boundaries for parallelism
4. **Group by region** — assign key ranges to regions
5. **Write SST** — stream sorted KV pairs to each region's leader
6. **Ingest** — tell TiKV to ingest the SST files
7. **Switch to normal mode** — `SwitchMode(Normal)` on all stores

### Complexity
This is a complex feature requiring:
- Streaming gRPC (Write RPC is client-streaming)
- Import mode management (cluster-wide state change)
- Region splitting coordination
- Error recovery (must switch back to normal mode on failure)

### Dependencies
- `ImportSST` service protobuf classes (may need generation)
- Streaming gRPC support in PHP grpc extension

## Testing Strategy
1. Ingest small dataset (100 keys) — verify all keys readable
2. Ingest large dataset (10K keys) — verify throughput
3. Ingest with TTL — keys expire correctly
4. Ingest unsorted data — client sorts automatically
5. Ingest empty dataset — no-op
6. Error recovery — cluster returns to normal mode on failure

## Priority: LOW
Specialized use case for data migration, backup restore, and initial data loading. Most applications use regular put/batchPut. The streaming gRPC requirement adds significant implementation complexity.
