# NotLeader Handling Design

## Goal

Handle NotLeader errors from TiKV by switching to the correct leader peer locally (using the leader hint from the error response), or invalidating the region cache when no hint is available. This matches the Go client's proven approach and avoids unnecessary PD round-trips.

## Background

TiKV uses Raft consensus — each region has one leader and multiple follower peers. When a leader transfer occurs, requests sent to the old leader get a `NotLeader` error response. The response includes a structured `errorpb.NotLeader` message with:
- `region_id` — the region that had the leader change
- `leader` — optional `metapb.Peer` hint indicating the new leader (may be null if unknown)

### Current State (Problems)

1. **Region errors are silently ignored.** Our client never calls `getRegionError()` on TiKV responses. NotLeader errors (and other region errors) are returned as structured protobuf fields on successful gRPC responses, not as gRPC-level exceptions.
2. **`RegionInfo` only stores the leader.** No knowledge of other peers in the region, so we can't switch leaders locally.
3. **`classifyError()` only does string matching.** It can't extract structured data like the leader hint.

### Go Client Reference

The Go client (`region_cache.go`):
- Stores ALL peers per region via `regionStore` with `accessIndex` arrays
- On NotLeader with hint: switches `workTiKVIdx` directly to the hinted peer — no PD round-trip
- On NotLeader without hint: invalidates the region entirely (forces PD re-fetch)
- Uses `InvalidReason` enum to track why a region was invalidated

## Design Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Peer storage | Store all peers in `RegionInfo` | Enables local leader switching without PD round-trip |
| Mutability | Immutable `RegionInfo` + mutable `RegionEntry` wrapper | Clean separation of data vs cache state |
| Leader hint | Use hint when present, invalidate when absent | Matches Go client behavior exactly |
| Store cache | Not added now, keep using `PdClient.getStore()` | Independent concern, can layer on later |
| Error detection | Check `getRegionError()` on all responses | Fixes silent region error bug |
| Backoff | New `BackoffType::NotLeader` (2ms base, 500ms cap) | Fast retry after leader switch |
| NotLeader handling location | Before `classifyError()` in retry loop | Separate concern from backoff classification |

## Architecture

### New Components

#### `PeerInfo` DTO — `src/Client/RawKv/Dto/PeerInfo.php`

```php
final readonly class PeerInfo
{
    public function __construct(
        public int $peerId,
        public int $storeId,
    ) {
    }
}
```

#### `RegionEntry` — `src/Client/Cache/RegionEntry.php`

Mutable wrapper stored internally by `RegionCache`. Not exposed outside the cache layer.

```php
final class RegionEntry
{
    private int $leaderStoreId;
    private int $leaderPeerId;

    public function __construct(
        public readonly RegionInfo $region,
        public readonly int $expiresAt,
    ) {
        $this->leaderStoreId = $region->leaderStoreId;
        $this->leaderPeerId = $region->leaderPeerId;
    }

    public function getLeaderStoreId(): int
    public function getLeaderPeerId(): int

    /**
     * Switch leader to the peer with the given storeId.
     * Returns true if the peer was found among region->peers and leader was switched.
     * Returns false if storeId is not among known peers (caller should invalidate).
     */
    public function switchLeader(int $leaderStoreId): bool
}
```

#### `RegionErrorHandler` — `src/Client/RawKv/RegionErrorHandler.php`

Static helper that checks TiKV responses for region errors and throws `RegionException`.

```php
final class RegionErrorHandler
{
    public static function check(object $response): void
    {
        // If response has getRegionError() and it returns non-null,
        // throw RegionException::fromRegionError($regionError)
    }
}
```

### Modified Components

#### `RegionInfo` — add `peers` field

```php
final readonly class RegionInfo
{
    /** @param list<PeerInfo> $peers */
    public function __construct(
        public int $regionId,
        public int $leaderPeerId,
        public int $leaderStoreId,
        public int $epochConfVer,
        public int $epochVersion,
        public string $startKey = '',
        public string $endKey = '',
        public array $peers = [],
    ) {
    }
}
```

The `peers` field defaults to `[]` for backward compatibility with existing code and tests.

#### `RegionCacheInterface` — add `switchLeader()`

```php
interface RegionCacheInterface
{
    public function getByKey(string $key): ?RegionInfo;
    public function put(RegionInfo $region): void;
    public function invalidate(int $regionId): void;
    public function switchLeader(int $regionId, int $leaderStoreId): bool;
    public function clear(): void;
}
```

#### `RegionCache` — internal refactor to `RegionEntry`

- Internal storage changes from `RegionInfo[]` to `RegionEntry[]`
- Separate `$ttls` array eliminated — TTL stored in `RegionEntry.expiresAt`
- `getByKey()` returns `RegionInfo` with current leader reflected. When leader has been switched, constructs a new `RegionInfo` with updated `leaderStoreId`/`leaderPeerId`
- `switchLeader()` finds entry by regionId, delegates to `RegionEntry.switchLeader()`. Logs at info level.

#### `RegionException` — add structured NotLeader data

```php
final class RegionException extends TiKvException
{
    public function __construct(
        string $operation,
        string $message,
        public readonly ?\CrazyGoat\Proto\Errorpb\NotLeader $notLeader = null,
    ) {
        parent::__construct("{$operation} failed: {$message}");
    }

    public static function fromRegionError(\CrazyGoat\Proto\Errorpb\Error $error): self
    {
        return new self(
            operation: 'RegionError',
            message: $error->getMessage(),
            notLeader: $error->getNotLeader(),
        );
    }
}
```

The existing constructor signature changes — the second parameter is renamed from `$error` to `$message` for clarity, and `$notLeader` is added as an optional third parameter. Existing call sites pass positional args so they continue to work.

#### `BackoffType` — add `NotLeader` case

```php
case NotLeader;
// baseMs: 2, capMs: 500, equalJitter: false
```

#### `PdClient` — extract peers from responses

Both `getRegion()` and `scanRegions()` iterate `$region->getPeers()` to build `list<PeerInfo>` and pass it to the `RegionInfo` constructor.

#### `RawKvClient` — region error checking + NotLeader handling

**Region error checking:** Every RPC method calls `RegionErrorHandler::check($response)` after the gRPC call. This surfaces silent region errors as `RegionException` instances.

**NotLeader handling in `executeWithRetry()`:** Before calling `classifyError()`, check if the exception is a `RegionException` with a non-null `notLeader` field:

1. If `notLeader->getLeader()` is not null (hint present):
   - Call `$this->regionCache->switchLeader($regionId, $leaderStoreId)`
   - If `switchLeader()` returns false (unknown peer), call `$this->regionCache->invalidate($regionId)`
2. If `notLeader->getLeader()` is null (no hint):
   - Call `$this->regionCache->invalidate($regionId)`
3. Use `BackoffType::NotLeader` for the retry sleep (skip `classifyError()`)

**`classifyError()` fallback:** Add `str_contains($message, 'NotLeader') → BackoffType::NotLeader` as defensive fallback for string-based NotLeader errors.

## Data Flow

### Normal Request (cache hit)
```
RawKvClient.get(key)
  → regionCache.getByKey(key) → RegionInfo (with current leader)
  → resolveStoreAddress(leaderStoreId) → address
  → grpc.call(address, ...) → response
  → RegionErrorHandler::check(response) → ok
  → return value
```

### NotLeader with Hint
```
RawKvClient.get(key)
  → grpc.call(oldLeaderAddress, ...) → response
  → RegionErrorHandler::check(response) → throws RegionException(notLeader={leader: Peer{storeId: 3}})
  → executeWithRetry catches RegionException
  → regionCache.switchLeader(regionId, 3) → true
  → BackoffType::NotLeader.sleepMs(0) → ~2ms
  → retry: regionCache.getByKey(key) → RegionInfo with leaderStoreId=3
  → resolveStoreAddress(3) → newAddress
  → grpc.call(newAddress, ...) → success
```

### NotLeader without Hint
```
RawKvClient.get(key)
  → grpc.call(oldLeaderAddress, ...) → response
  → RegionErrorHandler::check(response) → throws RegionException(notLeader={leader: null})
  → executeWithRetry catches RegionException
  → regionCache.invalidate(regionId)
  → BackoffType::NotLeader.sleepMs(0) → ~2ms
  → retry: regionCache.getByKey(key) → null (cache miss)
  → pdClient.getRegion(key) → fresh RegionInfo with new leader
  → regionCache.put(region)
  → resolveStoreAddress(newLeaderStoreId) → address
  → grpc.call(address, ...) → success
```

## Error Handling

| Error | Action | Backoff |
|---|---|---|
| NotLeader + hint present + peer known | Switch leader in cache | NotLeader (2ms base) |
| NotLeader + hint present + peer unknown | Invalidate region | NotLeader (2ms base) |
| NotLeader + no hint | Invalidate region | NotLeader (2ms base) |
| EpochNotMatch | Invalidate region (existing) | None (0ms) |
| RegionNotFound | Invalidate region (existing) | RegionMiss (2ms base) |
| ServerIsBusy | Invalidate region (existing) | ServerBusy (2s base) |
| RaftEntryTooLarge | Fatal, no retry | N/A |
| KeyNotInRegion | Fatal, no retry | N/A |

## Testing Strategy

### New Test Files

1. **`tests/Unit/RawKv/Dto/PeerInfoTest.php`** (~2 tests)
   - Construction with valid values
   - Readonly properties accessible

2. **`tests/Unit/Cache/RegionEntryTest.php`** (~6 tests)
   - Construction sets leader from RegionInfo
   - `switchLeader()` with valid storeId → returns true, updates leader
   - `switchLeader()` with unknown storeId → returns false, leader unchanged
   - `switchLeader()` updates both leaderStoreId and leaderPeerId
   - getLeaderStoreId/getLeaderPeerId return current values

3. **`tests/Unit/RawKv/RegionErrorHandlerTest.php`** (~5 tests)
   - Response without `getRegionError` method → no exception
   - Response with null region error → no exception
   - Response with NotLeader (with hint) → throws RegionException with notLeader field
   - Response with NotLeader (no hint) → throws RegionException with notLeader.leader = null
   - Response with other region error → throws RegionException

### Modified Test Files

4. **`tests/Unit/Cache/RegionCacheTest.php`** (~8 new tests)
   - `switchLeader()` succeeds → `getByKey()` returns RegionInfo with new leader
   - `switchLeader()` with unknown storeId → returns false
   - `switchLeader()` with unknown regionId → returns false
   - `getByKey()` after leader switch returns correct leaderStoreId/leaderPeerId
   - Existing 21 tests continue to pass

5. **`tests/Unit/RawKv/RawKvClientTest.php`** (~6 new tests)
   - NotLeader with leader hint → switches leader, retries, succeeds
   - NotLeader without hint → invalidates region, retries with PD re-fetch
   - NotLeader with hint for unknown peer → invalidates region
   - `BackoffType::NotLeader` sleep values correct
   - Region error checking surfaces NotLeader from response
   - String-based NotLeader fallback in classifyError

6. **`tests/Unit/Connection/PdClientTest.php`** (~2 new tests)
   - `getRegion()` populates peers array
   - `scanRegions()` populates peers array

### Estimated Totals
- ~28 new tests
- ~130 total unit/integration tests (up from 102)
- 141 E2E tests unchanged

## Files Changed

### New Source Files (3)
- `src/Client/RawKv/Dto/PeerInfo.php`
- `src/Client/Cache/RegionEntry.php`
- `src/Client/RawKv/RegionErrorHandler.php`

### New Test Files (3)
- `tests/Unit/RawKv/Dto/PeerInfoTest.php`
- `tests/Unit/Cache/RegionEntryTest.php`
- `tests/Unit/RawKv/RegionErrorHandlerTest.php`

### Modified Files (7)
- `src/Client/RawKv/Dto/RegionInfo.php` — add `peers` field
- `src/Client/Cache/RegionCacheInterface.php` — add `switchLeader()` method
- `src/Client/Cache/RegionCache.php` — internal RegionEntry storage, switchLeader impl
- `src/Client/Connection/PdClient.php` — extract peers from PD responses
- `src/Client/Exception/RegionException.php` — add `notLeader` field + factory
- `src/Client/Retry/BackoffType.php` — add `NotLeader` case
- `src/Client/RawKv/RawKvClient.php` — region error checking + NotLeader handling

### Modified Test Files (3)
- `tests/Unit/Cache/RegionCacheTest.php`
- `tests/Unit/RawKv/RawKvClientTest.php`
- `tests/Unit/Connection/PdClientTest.php`

## Out of Scope

- Store cache (storeId → address mapping with TTL) — independent future work
- Other region error types (DiskFull, RegionNotInitialized, ReadIndexNotReady) — future work
- InvalidReason enum (Go client concept) — not needed without store cache
- TiFlash access mode / peer role filtering — not needed for RawKV
