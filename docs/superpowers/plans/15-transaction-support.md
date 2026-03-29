# Feature: Transaction Support (Future)

## Overview
Implement ACID transaction support for multiple operations.

## Reference Implementation
- **Go**: Available in separate transaction client
- **Java**: Available in transaction client

## Note
This is a major feature requiring:
- Transaction coordinator
- 2-phase commit protocol
- Lock management
- MVCC support

## Priority: LOW (Future Release)
RawKV is designed for simple operations. Full transactions require separate implementation.

## Recommendation
For transactional needs, consider:
1. Using TiKV with TiDB (SQL layer)
2. Implementing saga pattern in application
3. Using CAS operations for simple atomicity
