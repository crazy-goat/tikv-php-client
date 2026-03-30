# PSR-3 Structured Logging — Design Spec

## Overview

Add PSR-3 structured logging to the tikv-php client. Logging is fully optional — defaults to `NullLogger` when no logger is provided.

## Dependency

- Add `psr/log: ^3.0` to `require` in `composer.json`
- Default to `Psr\Log\NullLogger` when no logger is injected

## Injection Point

`RawKvClient` is the single user-facing injection point. Internally it passes the logger to `PdClient` and `RegionCache` through their constructors.

- `PdClientInterface` and `RegionCacheInterface` do NOT change — logging is an implementation detail of the concrete classes.

### Constructor Changes

```php
// RawKvClient
public static function create(array $pdEndpoints, ?LoggerInterface $logger = null): self
public function __construct(
    PdClientInterface $pdClient,
    GrpcClientInterface $grpc,
    RegionCacheInterface $regionCache = new RegionCache(),
    int $maxBackoffMs = 20000,
    ?LoggerInterface $logger = null,
)

// PdClient
public function __construct(
    GrpcClientInterface $grpc,
    string $pdAddress,
    ?LoggerInterface $logger = null,
)

// RegionCache
public function __construct(
    int $ttlSeconds = 600,
    int $jitterSeconds = 60,
    ?LoggerInterface $logger = null,
)
```

All three store `$this->logger = $logger ?? new NullLogger()`.

## Log Events

### RawKvClient

| Event | Level | Message | Context |
|---|---|---|---|
| Retry attempt | `warning` | `Retrying operation` | `key`, `attempt`, `backoffType`, `sleepMs`, `totalBackoffMs` |
| Retry budget exhausted | `error` | `Retry budget exhausted` | `key`, `attempt`, `totalBackoffMs`, `maxBackoffMs` |
| Fatal error (no retry) | `error` | `Fatal error, not retrying` | `key`, `error` |
| Cache invalidation on retry | `info` | `Invalidated region on retry` | `key`, `regionId` |

### RegionCache

| Event | Level | Message | Context |
|---|---|---|---|
| Cache hit | `debug` | `Region cache hit` | `key`, `regionId` |
| Cache miss | `debug` | `Region cache miss` | `key` |
| Region put | `debug` | `Region cached` | `regionId`, `startKey`, `endKey`, `ttl` |
| Region invalidated | `info` | `Region invalidated` | `regionId` |

### PdClient

| Event | Level | Message | Context |
|---|---|---|---|
| gRPC call | `debug` | `PD gRPC call` | `method`, `address` |
| Cluster ID learned | `info` | `Learned cluster ID` | `clusterId` |
| Cluster ID retry | `warning` | `Cluster ID mismatch, retrying` | `method`, `clusterId` |

## Key Visibility

Keys are always included in log context. Users control what gets recorded by choosing their logger implementation.

## Files Changed

- `composer.json` — add `psr/log: ^3.0` to `require`
- `src/Client/RawKv/RawKvClient.php` — add logger parameter, log retry/error events
- `src/Client/Connection/PdClient.php` — add logger parameter, log gRPC/cluster events
- `src/Client/Cache/RegionCache.php` — add logger parameter, log cache hit/miss/put/invalidate
- `tests/Unit/RawKv/RawKvClientTest.php` — pass NullLogger in tests
- `tests/Unit/Cache/RegionCacheTest.php` — pass NullLogger in tests

## Non-Goals

- No log filtering or level configuration — that's the logger implementation's job
- No custom log formatter — PSR-3 context arrays are sufficient
- No changes to interfaces (`PdClientInterface`, `RegionCacheInterface`)
