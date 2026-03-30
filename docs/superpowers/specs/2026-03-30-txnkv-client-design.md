# TxnKV Client Design

## Status

Draft

## Context

Currently only RawKvClient is implemented. We need transactional support with MVCC, pessimistic/optimistic modes, and proper lock handling.

## Goals

- Implement TxnKV client compatible with Go client (txnkv.go)
- Support both pessimistic and optimistic transaction modes
- Handle MVCC with timestamps
- Implement lock resolver for deadlock detection
- Add transactional backoff types (TxnLock, TxnLockFast, TxnNotFound)

## Architecture

### Components

1. **TxnKvClient** - Main transactional client
2. **Transaction** - Transaction context and state
3. **LockResolver** - Deadlock detection and resolution
4. **MvccReader** - MVCC timestamp handling
5. **BackoffType extensions** - TxnLock, TxnLockFast, TxnNotFound

### TxnKvClient API

```php
final class TxnKvClient
{
    public function __construct(
        private readonly PdClientInterface $pdClient,
        private readonly GrpcClientInterface $grpc,
        private readonly RegionCacheInterface $regionCache,
        private readonly int $maxBackoffMs = 20000,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Begin a new transaction.
     * 
     * @param array{
     *   pessimistic?: bool,  // default: true
     *   priority?: int,       // default: 0
     *   syncLog?: bool,       // default: true
     * } $options
     */
    public function begin(array $options = []): Transaction;
}
```

### Transaction

```php
final class Transaction
{
    private string $txnId;
    private int $startTs;           // MVCC start timestamp
    private ?int $commitTs = null;  // MVCC commit timestamp (null until committed)
    private TransactionStatus $status;
    private bool $pessimistic;
    private int $priority;
    private array $writeSet = [];    // Keys modified in this transaction
    private array $readSet = [];     // Keys read in this transaction
    private array $locks = [];       // Current locks held (pessimistic mode)
    private LockResolver $lockResolver;
    
    public function get(string $key): ?string;
    public function set(string $key, string $value): void;
    public function delete(string $key): void;
    public function scan(string $startKey, string $endKey): array;
    
    public function commit(): void;
    public function rollback(): void;
}

enum TransactionStatus
{
    case Active;
    case Committed;
    case RolledBack;
}
```

### LockResolver

```php
final class LockResolver
{
    public function __construct(
        private readonly GrpcClientInterface $grpc,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Resolve locks for a key.
     * Called when encountering a lock during read/write.
     */
    public function resolveLock(string $key, int $lockTs): void;

    /**
     * Check for deadlocks.
     * Called periodically in pessimistic mode.
     */
    public function checkDeadlock(string $txnId): bool;
}
```

### BackoffType Extensions

```php
enum BackoffType
{
    // ... existing cases ...
    
    // Transactional backoff types
    case TxnLock;      // 200ms base / 3000ms cap, EqualJitter
    case TxnLockFast;  // 100ms base / 3000ms cap, EqualJitter
    case TxnNotFound;  // 2ms base / 500ms cap, NoJitter
}
```

## Transaction Modes

### Pessimistic (default)

- Acquires locks on read/write
- Better for write-heavy workloads
- Deadlock detection via LockResolver
- Longer backoff (TxnLock: 200ms base)

```php
$txn = $client->begin(['pessimistic' => true]);
$txn->set('key1', 'value1');  // Acquires lock immediately
$txn->commit();
```

### Optimistic

- No locks during read/write
- Conflict detection at commit time
- Better for read-heavy workloads
- Shorter backoff (TxnLockFast: 100ms base)

```php
$txn = $client->begin(['pessimistic' => false]);
$txn->set('key1', 'value1');  // No lock, buffered in writeSet
$txn->commit();  // Checks for conflicts, may retry
```

## MVCC Implementation

### Timestamp Oracle

- PD provides monotonic timestamps
- `startTs` assigned at Begin()
- `commitTs` assigned at Commit()

### Read Path

```php
public function get(string $key): ?string
{
    // 1. Check writeSet (own writes)
    if (isset($this->writeSet[$key])) {
        return $this->writeSet[$key];
    }
    
    // 2. Read from TiKV with startTs
    $value = $this->readFromStore($key, $this->startTs);
    
    // 3. Record in readSet (for conflict detection in optimistic mode)
    $this->readSet[$key] = $value;
    
    return $value;
}
```

### Write Path

```php
public function set(string $key, string $value): void
{
    if ($this->pessimistic) {
        // Acquire lock immediately
        $this->acquireLock($key);
    }
    
    // Buffer write
    $this->writeSet[$key] = $value;
}
```

### Commit Path

```php
public function commit(): void
{
    if ($this->pessimistic) {
        // 1. Pre-write (check locks, write intents)
        $this->preWrite();
        
        // 2. Get commit timestamp
        $this->commitTs = $this->pdClient->getTimestamp();
        
        // 3. Commit (remove locks, make writes visible)
        $this->commitWrites();
    } else {
        // Optimistic: check for conflicts first
        if (!$this->checkConflict()) {
            throw new TransactionConflictException();
        }
        
        // Same as pessimistic from here
        $this->preWrite();
        $this->commitTs = $this->pdClient->getTimestamp();
        $this->commitWrites();
    }
    
    $this->status = TransactionStatus::Committed;
}
```

## Error Handling

### TransactionConflictException

Thrown in optimistic mode when commit detects a conflict.

```php
final class TransactionConflictException extends TiKvException
{
    public function __construct(
        string $message = 'Transaction conflict detected',
        ?array $conflictingKeys = null,
    ) {
        parent::__construct($message);
    }
}
```

### LockWaitTimeoutException

Thrown in pessimistic mode when lock acquisition times out.

```php
final class LockWaitTimeoutException extends TiKvException
{
    public function __construct(
        string $key,
        int $timeoutMs,
    ) {
        parent::__construct("Lock wait timeout for key: {$key}");
    }
}
```

## Backoff Configuration

| BackoffType | baseMs | capMs | Jitter | Use Case |
|-------------|--------|-------|--------|----------|
| TxnLock | 200 | 3000 | Equal | Lock conflict (pessimistic) |
| TxnLockFast | 100 | 3000 | Equal | Fast path retry |
| TxnNotFound | 2 | 500 | No | Async commit, txn not found |

## Testing Strategy

1. Unit tests for Transaction state management
2. Unit tests for LockResolver
3. Unit tests for MVCC read/write
4. Integration tests for commit/rollback
5. Integration tests for deadlock detection
6. Integration tests for conflict resolution

## Files to Create

```
src/Client/TxnKv/TxnKvClient.php
src/Client/TxnKv/Transaction.php
src/Client/TxnKv/LockResolver.php
src/Client/TxnKv/TransactionStatus.php
src/Client/TxnKv/Exception/TransactionConflictException.php
src/Client/TxnKv/Exception/LockWaitTimeoutException.php
tests/Unit/TxnKv/TxnKvClientTest.php
tests/Unit/TxnKv/TransactionTest.php
tests/Unit/TxnKv/LockResolverTest.php
tests/Integration/TxnKvIntegrationTest.php
```

## Files to Modify

```
src/Client/Retry/BackoffType.php (add TxnLock, TxnLockFast, TxnNotFound)
```

## Backward Compatibility

- New client, doesn't affect existing RawKvClient
- Can use both clients simultaneously
- No breaking changes to existing API
