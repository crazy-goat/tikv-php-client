# Feature: Configurable Per-Operation Timeouts

## Overview
Add configurable timeouts for different operation types instead of using a global infinite timeout (`Timeval::infFuture()`). Different operations have different latency profiles and should have appropriate timeouts.

## Reference Implementation
- **Java**: Per-operation timeout configuration:
  - `getRawKVReadTimeoutInMS()` — get, getKeyTTL
  - `getRawKVWriteTimeoutInMS()` — put, delete, CAS
  - `getRawKVBatchReadTimeoutInMS()` — batchGet
  - `getRawKVBatchWriteTimeoutInMS()` — batchPut, batchDelete
  - `getRawKVScanTimeoutInMS()` — scan, batchScan
  - `getRawKVCleanTimeoutInMS()` — deleteRange
- **Go**: Uses context with deadline (`context.WithTimeout`)

## Current Behavior
```php
// GrpcClient.php
$call = new Call(
    $channel,
    '/' . $service . '/' . $method,
    Timeval::infFuture()  // No timeout — waits forever
);
```

## API Design

### TimeoutConfig Value Object
```php
final readonly class TimeoutConfig
{
    public function __construct(
        public int $readTimeoutMs = 5000,       // get, getKeyTTL
        public int $writeTimeoutMs = 5000,      // put, delete, CAS
        public int $batchReadTimeoutMs = 10000,  // batchGet
        public int $batchWriteTimeoutMs = 10000, // batchPut, batchDelete
        public int $scanTimeoutMs = 20000,       // scan, reverseScan, scanPrefix, batchScan
        public int $deleteRangeTimeoutMs = 30000, // deleteRange, deletePrefix
        public int $checksumTimeoutMs = 30000,    // checksum
    ) {}
}
```

### Construction
```php
$client = RawKvClient::create(
    pdAddresses: ['127.0.0.1:2379'],
    timeoutConfig: new TimeoutConfig(
        readTimeoutMs: 3000,
        writeTimeoutMs: 5000,
    ),
);
```

## Implementation Details

### GrpcClient Changes
```php
public function call(string $address, string $service, string $method, 
                     Message $request, string $responseClass, int $timeoutMs = 0): Message
{
    $deadline = $timeoutMs > 0
        ? Timeval::infFuture() // TODO: calculate from now + timeoutMs
        : Timeval::infFuture();
    
    $call = new Call($channel, "/$service/$method", $deadline);
    // ...
}
```

The PHP gRPC extension's `Timeval` supports:
```php
$deadline = new Timeval(time() + ($timeoutMs / 1000));
// or
$deadline = Timeval::now()->add(new Timeval($timeoutMs * 1000)); // microseconds
```

### RawKvClient Changes
Pass appropriate timeout to each `$this->grpc->call()` invocation based on operation type.

## Testing Strategy
1. Default timeouts work without explicit configuration
2. Custom timeout config is respected
3. Operation that exceeds timeout throws RuntimeException with DEADLINE_EXCEEDED
4. Short timeout on slow operation (e.g., large scan) triggers timeout
5. Infinite timeout (0) works as before (backward compatible)

## Priority: LOW
Important for production resilience, but the current infinite timeout works for development. Implement when preparing for production hardening.
