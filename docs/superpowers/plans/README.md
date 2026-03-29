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
10. **RawScan** - Range scanning ✅
11. **ScanPrefix** - Prefix-based scanning ✅
12. **ReverseScan** - Reverse range scanning (native reverse=true) ✅
13. **DeleteRange** - Delete all keys in range ✅
14. **DeletePrefix** - Delete all keys by prefix ✅
15. **PutWithTTL** - Store with expiration (seconds) ✅
16. **GetKeyTTL** - Get remaining TTL ✅
17. **CompareAndSwap** - Atomic CAS operation ✅
18. **PutIfAbsent** - Conditional insert (built on CAS) ✅
19. **Checksum** - CRC64-XOR data integrity verification ✅
20. **BatchScan** - Multiple non-contiguous range scanning ✅

### 🚧 Planned (in priority order)

#### Phase 1: Batch Operations (High Priority) ✅ COMPLETED
- ~~[01-raw-batch-get.md](01-raw-batch-get.md) - Batch key retrieval~~
- ~~[02-raw-batch-put.md](02-raw-batch-put.md) - Batch key storage~~
- ~~[03-raw-batch-delete.md](03-raw-batch-delete.md) - Batch key deletion~~

#### Phase 2: Scan Operations (High Priority) ✅ COMPLETED
- ~~[04-raw-scan.md](04-raw-scan.md) - Range scanning~~
- ~~[11-scan-prefix.md](11-scan-prefix.md) - Prefix scanning~~
- ~~[05-raw-reverse-scan.md](05-raw-reverse-scan.md) - Reverse scanning~~

#### Phase 3: Range Operations (Medium Priority) ✅ COMPLETED
- ~~[06-raw-delete-range.md](06-raw-delete-range.md) - Delete key range~~
- ~~[12-delete-prefix.md](12-delete-prefix.md) - Delete by prefix~~

#### Phase 4: TTL Operations (Medium Priority) ✅ COMPLETED
- ~~[07-put-with-ttl.md](07-put-with-ttl.md) - Store with expiration~~
- ~~[08-get-key-ttl.md](08-get-key-ttl.md) - Get remaining TTL~~

#### Phase 5: Advanced Operations (Low Priority) ✅ COMPLETED
- ~~[09-compare-and-swap.md](09-compare-and-swap.md) - Atomic CAS~~ ✅
- ~~[13-put-if-absent.md](13-put-if-absent.md) - Conditional insert~~ ✅
- ~~[10-checksum.md](10-checksum.md) - Data integrity~~ ✅
- ~~[14-batch-scan.md](14-batch-scan.md) - Multiple range scan~~ ✅

#### Phase 6: Robustness & Safety (High Priority)
- [16-per-key-ttl-batch-put.md](16-per-key-ttl-batch-put.md) - Per-key TTL in batchPut
- [17-scan-limit-guard.md](17-scan-limit-guard.md) - Scan limit enforcement (MAX 10240)
- [18-batch-auto-splitting.md](18-batch-auto-splitting.md) - Auto-split large batches by size/count
- [19-scan-iterator.md](19-scan-iterator.md) - Lazy scan iterator with auto-pagination

#### Phase 7: Client Configuration (Medium Priority)
- [20-column-family.md](20-column-family.md) - Column family support
- [21-atomic-for-cas.md](21-atomic-for-cas.md) - Atomic mode (ForCas flag) for linearizable CAS
- [22-tls-security.md](22-tls-security.md) - TLS/mTLS for production deployments
- [23-exponential-backoff.md](23-exponential-backoff.md) - Exponential backoff retry with more error types
- [24-cluster-id.md](24-cluster-id.md) - Expose cluster ID from PD

#### Phase 8: Advanced Infrastructure (Low Priority)
- [25-api-v2-keyspace.md](25-api-v2-keyspace.md) - API V2 with keyspace multi-tenancy
- [26-sst-ingest.md](26-sst-ingest.md) - SST bulk import
- [27-configurable-timeouts.md](27-configurable-timeouts.md) - Per-operation timeouts
- [28-slow-query-logging.md](28-slow-query-logging.md) - Slow operation logging (PSR-3)

#### Phase 9: Future (Not Planned)
- [15-transaction-support.md](15-transaction-support.md) - Full ACID transactions

## Feature Comparison: PHP vs Go vs Java

| Feature | Go | Java | PHP | Plan |
|---------|:--:|:----:|:---:|------|
| Get / Put / Delete | ✅ | ✅ | ✅ | — |
| BatchGet / BatchPut / BatchDelete | ✅ | ✅ | ✅ | — |
| Scan / ReverseScan | ✅ | ✅ | ✅ | — |
| ScanPrefix | — | ✅ | ✅ | — |
| DeleteRange / DeletePrefix | ✅ | ✅ | ✅ | — |
| PutWithTTL / GetKeyTTL | ✅ | ✅ | ✅ | — |
| CompareAndSwap | ✅ | ✅ | ✅ | — |
| PutIfAbsent | — | ✅ | ✅ | — |
| Checksum | ✅ | — | ✅ | — |
| BatchScan | — | ✅ | ✅ | — |
| **Per-key TTL in BatchPut** | ✅ | — | ❌ | #16 |
| **Scan limit guard (10240)** | ✅ | ✅ | ❌ | #17 |
| **Batch auto-splitting** | ✅ | ✅ | ❌ | #18 |
| **Scan iterator (lazy)** | — | ✅ | ❌ | #19 |
| **Column family** | ✅ | — | ❌ | #20 |
| **Atomic mode (ForCas)** | ✅ | ✅ | ❌ | #21 |
| **TLS/Security** | ✅ | ✅ | ❌ | #22 |
| **Exponential backoff** | ✅ | ✅ | ❌ | #23 |
| **Cluster ID** | ✅ | ✅ | ❌ | #24 |
| **API V2 / Keyspace** | ✅ | — | ❌ | #25 |
| **SST Ingest** | — | ✅ | ❌ | #26 |
| **Configurable timeouts** | ✅ | ✅ | ❌ | #27 |
| **Slow query logging** | — | ✅ | ❌ | #28 |

## Implementation Guidelines

1. **Protobuf First**: Define proto messages before implementation
2. **Test Driven**: Write tests before implementation
3. **Error Handling**: Implement proper retry and error handling
4. **Documentation**: Update README with new features
5. **Performance**: Benchmark against single operations

## Reference Clients
- **Go**: https://github.com/tikv/client-go
- **Java**: https://github.com/tikv/client-java
