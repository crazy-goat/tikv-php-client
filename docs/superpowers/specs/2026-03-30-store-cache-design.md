# Store Cache Design

## Status

Approved

## Context

Currently `PdClient` has a simple in-memory cache for store data (`storeCache` array) without TTL support. On every NotLeader error, `RawKvClient.resolveStoreAddress()` calls `PdClient.getStore()` which either hits this cache or makes a PD call.

A proper store cache with TTL will:
- Eliminate redundant PD calls for store address resolution
- Enable zero-PD-call leader switches after NotLeader errors
- Provide consistent behavior with RegionCache (which already uses TTL)

## Architecture

### Components

1. **StoreCacheInterface** - interface defining cache operations
2. **StoreCache** - implementation with TTL and jitter (mirrors RegionCache pattern)
3. **StoreEntry** - value object holding Store + expiration timestamp

### StoreCacheInterface

```php
interface StoreCacheInterface
{
    public function get(int $storeId): ?Store;
    public function put(Store $store): void;
    public function invalidate(int $storeId): void;
    public function clear(): void;
}
```

### StoreEntry

```php
final class StoreEntry
{
    public function __construct(
        public readonly Store $store,
        public readonly int $expiresAt,
    ) {}
}
```

### StoreCache

```php
class StoreCache implements StoreCacheInterface
{
    /** @var StoreEntry[] */
    private array $entries = [];

    public function __construct(
        private readonly int $ttlSeconds = 600,
        private readonly int $jitterSeconds = 60,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    public function get(int $storeId): ?Store { /* ... */ }
    public function put(Store $store): void { /* ... */ }
    public function invalidate(int $storeId): void { /* ... */ }
    public function clear(): void { /* ... */ }
}
```

## Behavior

### TTL with Jitter

- Default TTL: 600 seconds (same as RegionCache)
- Default jitter: 60 seconds (same as RegionCache)
- Jitter is randomly added to TTL to prevent thundering herd on cache expiration

### Cache Lookup (`get`)

1. Check if storeId exists in entries
2. If exists and not expired, return Store
3. If expired, remove and return null
4. If not found, return null

### Cache Write (`put`)

1. Remove any existing entry for the same storeId
2. Insert new entry with `expiresAt = now + ttl + jitter`

### Invalidation

- Individual store invalidation via `invalidate(storeId)`
- Full cache clear via `clear()`

## Integration with PdClient

PdClient will accept an optional `StoreCacheInterface` in constructor:

```php
public function __construct(
    GrpcClientInterface $grpc,
    string $pdAddress,
    LoggerInterface $logger = new NullLogger(),
    ?StoreCacheInterface $storeCache = null,
)
```

When cache is null, a default `StoreCache` is created internally.

## Error Handling

- `get()` returns null on cache miss or expired entry (graceful degradation)
- No exceptions thrown for cache operations
- All operations are idempotent

## Testing Strategy

1. Unit tests for StoreCache covering:
   - Cache hit/miss
   - TTL expiration
   - Jitter randomness
   - Invalidation
   - Clear
2. Unit tests for StoreEntry
3. Integration with PdClient tests (mock StoreCacheInterface)

## Files to Create

```
src/Client/Cache/StoreCacheInterface.php
src/Client/Cache/StoreCache.php
src/Client/Cache/StoreEntry.php
tests/Unit/Cache/StoreCacheTest.php
tests/Unit/Cache/StoreEntryTest.php
```

## Files to Modify

```
src/Client/Connection/PdClient.php (add StoreCacheInterface dependency)
src/Client/Connection/PdClientInterface.php (no changes needed - interface already has getStore)
```
