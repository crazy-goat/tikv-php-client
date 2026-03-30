# Retry/Backoff — Future Tasks

Tasks to implement when corresponding features are added.

## NotLeader handling

Requires store cache with leader tracking. When NotLeader response includes new leader info, update cache and retry immediately without PD round-trip. Without new leader info, use RegionScheduling backoff (2/500 NoJitter).

Depends on: store address cache refactoring.

## Transactional backoff types

To implement alongside TxnKV API:

- **TxnLock**: 200 / 3000 EqualJitter — lock conflict during transaction
- **TxnLockFast**: 100 / 3000 EqualJitter — fast path lock retry
- **TxnNotFound**: 2 / 500 NoJitter — transaction not found (async commit)

## Additional error types

Lower priority, implement as needed:

- **DiskFull**: 500 / 5000 NoJitter
- **RegionNotInitialized**: 2 / 1000 NoJitter
- **ReadIndexNotReady**: 2 / 500 NoJitter (RegionScheduling equivalent)
- **ProposalInMergingMode**: 2 / 500 NoJitter (RegionScheduling equivalent)
- **RecoveryInProgress**: 100 / 10000 EqualJitter
- **IsWitness**: 1000 / 10000 EqualJitter
- **MaxTimestampNotSynced**: 2 / 500 NoJitter

## ServerBusy excluded budget

Go client has a separate 10-minute budget for ServerBusy errors that doesn't consume the main retry budget. Consider implementing if ServerBusy becomes a problem in production.
