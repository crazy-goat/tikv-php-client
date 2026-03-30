# Retry with Exponential Backoff — Design Spec

## Problem

Current `executeWithRetry` does a fixed number of retries (default 3) with no delay between attempts. This doesn't match TiKV's error semantics — some errors need immediate retry (EpochNotMatch), some need exponential backoff (ServerIsBusy), and some are fatal (RaftEntryTooLarge).

## Solution

Replace fixed retry count with budget-based retry (20s total backoff, matching Java's `RAWKV_MAX_BACKOFF`). Each error type maps to a specific backoff strategy with base/cap delays and optional jitter, matching the Go client's approach.

## Backoff Algorithm

`min(cap, base * 2^attempt)` with optional jitter.

Jitter strategies:
- **NoJitter**: use calculated value directly
- **EqualJitter**: `expo/2 + rand(0, expo/2)`

## Error Classification

| Error | BackoffType | Base/Cap (ms) | Jitter | Behavior |
|-------|-------------|---------------|--------|----------|
| EpochNotMatch | None | 0 | - | Invalidate cache, retry immediately |
| ServerIsBusy | ServerBusy | 2000 / 10000 | Equal | Sleep, invalidate cache, retry |
| StaleCommand | StaleCmd | 2 / 1000 | No | Sleep, invalidate cache, retry |
| RegionNotFound | RegionMiss | 2 / 500 | No | Sleep, invalidate cache, retry |
| gRPC transport error | TiKvRpc | 100 / 2000 | Equal | Sleep, invalidate cache, retry |
| RaftEntryTooLarge | Fatal | - | - | Throw immediately, no retry |
| KeyNotInRegion | Fatal | - | - | Throw immediately, no retry |

## Budget

`maxBackoffMs = 20000` (20 seconds), configurable via constructor. When total accumulated backoff exceeds budget, throw the last exception.

## Components

### BackoffType (enum)

```php
enum BackoffType
{
    case None;       // 0ms, immediate retry
    case ServerBusy; // 2000/10000 EqualJitter
    case StaleCmd;   // 2/1000 NoJitter
    case RegionMiss; // 2/500 NoJitter
    case TiKvRpc;    // 100/2000 EqualJitter
}
```

### Backoff (calculator)

```php
final class Backoff
{
    public static function exponential(int $baseMs, int $capMs, int $attempt, bool $equalJitter = false): int;
}
```

### RawKvClient changes

- Constructor: `$maxBackoffMs = 20000` replaces `$maxRetries = 3`
- `executeWithRetry`: budget-based loop with `classifyError()` dispatch
- `classifyError(TiKvException $e): ?BackoffType` — returns null for fatal errors
- Fatal errors throw immediately without retry

## File Structure

New:
- `src/Client/Retry/BackoffType.php`
- `src/Client/Retry/Backoff.php`
- `tests/Unit/Retry/BackoffTest.php`

Modified:
- `src/Client/RawKv/RawKvClient.php`
- `tests/Unit/RawKv/RawKvClientTest.php`

## Testing

- **BackoffTest**: exponential calculation, cap enforcement, equalJitter range, base at attempt=0
- **RawKvClientTest**: fatal errors throw immediately, ServerIsBusy triggers retry, EpochNotMatch retries without sleep, budget exceeded throws
- **E2E**: no changes expected
