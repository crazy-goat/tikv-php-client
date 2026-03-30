# Connection Pooling — Design Spec

## Overview

Improve gRPC connection management: single shared client, per-address channel close for forced reconnect, and channel health checking before use.

## Background

The Go client (`client-go`) uses a sophisticated `RPCClient` with per-address connection pools (N connections, round-robin), idle recycling, connection monitoring, batch command multiplexing, and versioned reconnects. Most of this is irrelevant for PHP (single-threaded, synchronous). We adapt the three features that matter.

Our `GrpcClient` already caches `Channel` objects per address. What's missing:
1. `RawKvClient::create()` creates two separate `GrpcClient` instances (PD and TiKV) — wasteful, prevents channel sharing.
2. No way to close a single channel after errors — the only option is `close()` which kills everything.
3. No health checking — a channel in `FATAL_FAILURE` state is reused until it fails.

## Changes

### 1. Single Shared GrpcClient

`RawKvClient::create()` passes the same `GrpcClient` instance to both `PdClient` and `RawKvClient`. One channel pool for all connections.

```php
public static function create(array $pdEndpoints, ?LoggerInterface $logger = null): self
{
    $resolvedLogger = $logger ?? new NullLogger();
    $grpc = new GrpcClient($resolvedLogger);
    $pdClient = new PdClient($grpc, $pdEndpoints[0], $resolvedLogger);

    return new self($pdClient, $grpc, new RegionCache(logger: $resolvedLogger), logger: $resolvedLogger);
}
```

### 2. `closeChannel(string $address)` Method

New method on `GrpcClientInterface` and `GrpcClient`. Closes a single channel by address, forcing reconnect on next call.

```php
// GrpcClientInterface
public function closeChannel(string $address): void;

// GrpcClient
public function closeChannel(string $address): void
{
    if (isset($this->channels[$address])) {
        $this->channels[$address]->close();
        unset($this->channels[$address]);
    }
}
```

**Integration with retry:** `RawKvClient::executeWithRetry()` calls `$this->grpc->closeChannel($address)` when it catches a `GrpcException` (classified as `BackoffType::TiKvRpc`). This ensures the next retry attempt gets a fresh connection.

To support this, `executeWithRetry()` needs access to the address that failed. The approach: store the last resolved address in a local variable within the closure, and pass it back via a wrapper or by restructuring the retry to track the address.

Simpler approach: `classifyError()` already identifies `GrpcException`. When a `GrpcException` is caught in `executeWithRetry()`, we resolve the store address from the cached region and close that channel. The region is already being invalidated, so on retry a fresh region lookup + fresh channel will be used.

### 3. Channel Health Checking

Before using a cached channel, check its connectivity state. If `CHANNEL_FATAL_FAILURE` (state 4), close and recreate.

```php
private function getChannel(string $address): Channel
{
    if (isset($this->channels[$address])) {
        $state = $this->channels[$address]->getConnectivityState();
        if ($state === \Grpc\CHANNEL_FATAL_FAILURE) {
            $this->logger->warning('Channel in fatal failure, reconnecting', ['address' => $address]);
            $this->closeChannel($address);
        }
    }

    if (!isset($this->channels[$address])) {
        $this->logger->debug('Opening gRPC channel', ['address' => $address]);
        $this->channels[$address] = new Channel($address, [
            'credentials' => ChannelCredentials::createInsecure(),
        ]);
    }

    return $this->channels[$address];
}
```

Only `CHANNEL_FATAL_FAILURE` triggers reconnect. `TRANSIENT_FAILURE` is handled internally by gRPC's own reconnect backoff.

### 4. Logging in GrpcClient

`GrpcClient` gets a `LoggerInterface` constructor parameter (defaults to `NullLogger`), same pattern as PdClient/RegionCache.

Log events:
- `debug('Opening gRPC channel', ['address' => $address])` — new channel created
- `warning('Channel in fatal failure, reconnecting', ['address' => $address])` — health check triggered reconnect
- `debug('Channel closed', ['address' => $address])` — channel explicitly closed via `closeChannel()`

## Files Changed

- `src/Client/Grpc/GrpcClientInterface.php` — add `closeChannel(string $address): void`
- `src/Client/Grpc/GrpcClient.php` — add `closeChannel()`, health checking in `getChannel()`, logger parameter
- `src/Client/RawKv/RawKvClient.php` — update `create()` to use single GrpcClient, call `closeChannel()` on GrpcException in retry
- `tests/Unit/RawKv/RawKvClientTest.php` — update mock expectations for `closeChannel()`
- `tests/Unit/Grpc/GrpcClientTest.php` — new tests for `closeChannel()` and health checking (limited — gRPC extension not available locally, so test interface compliance and logging only)

## Non-Goals

- Multiple connections per address (no concurrency in PHP)
- Idle connection recycling (no background threads)
- Batch command multiplexing (no goroutines)
- Connection versioning (single-threaded, no race conditions)
- TLS/SSL support (separate feature)
