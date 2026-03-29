# TiKV PHP Client - Implementation Plans

This directory contains detailed implementation plans for all RawKV features.

## Current Status

### ✅ Implemented
1. **RawGet** - Single key retrieval
2. **RawPut** - Single key storage
3. **RawDelete** - Single key deletion
4. **PD Region Discovery** - With RegionEpoch support
5. **Retry Logic** - Automatic retry on EpochNotMatch
6. **Region Routing** - Direct to correct TiKV node
7. **RawBatchGet** - Batch key retrieval ✅
8. **RawBatchPut** - Batch key storage ✅
9. **RawBatchDelete** - Batch key deletion ✅

### 🚧 Planned (in priority order)

### 🚧 Planned (in priority order)

#### Phase 1: Batch Operations (High Priority) ✅ COMPLETED
- ~~[01-raw-batch-get.md](01-raw-batch-get.md) - Batch key retrieval~~
- ~~[02-raw-batch-put.md](02-raw-batch-put.md) - Batch key storage~~
- ~~[03-raw-batch-delete.md](03-raw-batch-delete.md) - Batch key deletion~~

#### Phase 2: Scan Operations (High Priority)
- [04-raw-scan.md](04-raw-scan.md) - Range scanning
- [11-scan-prefix.md](11-scan-prefix.md) - Prefix scanning
- [05-raw-reverse-scan.md](05-raw-reverse-scan.md) - Reverse scanning

#### Phase 3: Range Operations (Medium Priority)
- [06-raw-delete-range.md](06-raw-delete-range.md) - Delete key range
- [12-delete-prefix.md](12-delete-prefix.md) - Delete by prefix

#### Phase 4: TTL Operations (Medium Priority)
- [07-put-with-ttl.md](07-put-with-ttl.md) - Store with expiration
- [08-get-key-ttl.md](08-get-key-ttl.md) - Get remaining TTL

#### Phase 5: Advanced Operations (Low Priority)
- [09-compare-and-swap.md](09-compare-and-swap.md) - Atomic CAS
- [13-put-if-absent.md](13-put-if-absent.md) - Conditional insert
- [10-checksum.md](10-checksum.md) - Data integrity
- [14-batch-scan.md](14-batch-scan.md) - Multiple range scan

#### Phase 6: Future (Not Planned)
- [15-transaction-support.md](15-transaction-support.md) - Full ACID transactions

## Implementation Guidelines

1. **Protobuf First**: Define proto messages before implementation
2. **Test Driven**: Write tests before implementation
3. **Error Handling**: Implement proper retry and error handling
4. **Documentation**: Update README with new features
5. **Performance**: Benchmark against single operations

## Reference Clients
- **Go**: https://github.com/tikv/client-go
- **Java**: https://github.com/tikv/client-java
