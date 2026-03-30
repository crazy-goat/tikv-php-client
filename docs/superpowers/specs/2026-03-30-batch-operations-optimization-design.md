# Batch Operations Optimization Design

## Status

Draft

## Context

Currently batch operations (batchGet, batchPut, batchDelete) group keys by region but execute each region's request sequentially using `executeWithRetry()`. For multi-region batches spanning many regions, this creates unnecessary latency as requests wait for each other.

## Goals

- Execute per-region batch requests in parallel using async gRPC
- Reduce total latency for multi-region batch operations
- Maintain existing retry logic per-region
- Keep backward compatibility (same API)

## Architecture

### Current Flow (Sequential)

```
Keys → Group by Region → Execute Region 1 → Execute Region 2 → ... → Merge Results
```

### New Flow (Parallel Async)

```
Keys → Group by Region → Start Async Call 1 → Start Async Call 2 → ... → Collect All Results → Merge
```

### Components

1. **BatchAsyncExecutor** - Manages parallel async gRPC calls
2. **Modified executeBatch*ForRegion methods** - Support async initiation
3. **Result aggregation** - Collect and merge results from all regions

### BatchAsyncExecutor

```php
final class BatchAsyncExecutor
{
    /**
     * Execute multiple callables concurrently and return results.
     *
     * @template T
     * @param array<string, callable(): T> $regionCalls Array of regionId => callable
     * @return array<string, T> Array of regionId => result
     * @throws TiKvException If any region fails (aggregated errors)
     */
    public function executeParallel(array $regionCalls): array
    {
        // Start all calls asynchronously
        $pending = [];
        foreach ($regionCalls as $regionId => $callable) {
            $pending[$regionId] = $callable(); // Returns async handle/future
        }
        
        // Collect all results
        $results = [];
        $errors = [];
        
        foreach ($pending as $regionId => $future) {
            try {
                $results[$regionId] = $future->wait();
            } catch (TiKvException $e) {
                $errors[$regionId] = $e;
            }
        }
        
        if (!empty($errors)) {
            throw new BatchPartialFailureException($errors);
        }
        
        return $results;
    }
}
```

### Modified batchGet Flow

```php
public function batchGet(array $keys): array
{
    // ... existing grouping logic ...
    
    $regionCalls = [];
    foreach ($keysByRegion as $regionId => $regionData) {
        $regionCalls[$regionId] = function() use ($regionData) {
            return $this->executeBatchGetForRegionAsync($regionData['region'], $regionData['keys']);
        };
    }
    
    $executor = new BatchAsyncExecutor($this->logger);
    $regionResults = $executor->executeParallel($regionCalls);
    
    // Merge results maintaining key order
    $results = [];
    foreach ($regionResults as $regionResult) {
        $results = array_merge($results, $regionResult);
    }
    
    // ... existing ordering logic ...
}
```

### Async gRPC Implementation

Using gRPC's native async API:

```php
private function executeBatchGetForRegionAsync(RegionInfo $region, array $keys): Future
{
    return $this->executeWithRetryAsync($keys[0], function () use ($region, $keys): Future {
        $address = $this->resolveStoreAddress($region->leaderStoreId);
        
        $request = new RawBatchGetRequest();
        $request->setContext(RegionContext::fromRegionInfo($region));
        $request->setKeys($keys);
        
        // Start async gRPC call
        $call = new Call($this->getChannel($address), '/tikvpb.Tikv/RawBatchGet', Timeval::infFuture());
        
        $call->startBatch([
            \Grpc\OP_SEND_INITIAL_METADATA => [],
            \Grpc\OP_SEND_MESSAGE => ['message' => $request->serializeToString()],
            \Grpc\OP_SEND_CLOSE_FROM_CLIENT => true,
        ]);
        
        // Return future/promise that can be awaited
        return new GrpcFuture($call, RawBatchGetResponse::class);
    });
}
```

### GrpcFuture

```php
final class GrpcFuture
{
    private bool $completed = false;
    private ?Message $result = null;
    private ?GrpcException $error = null;
    
    public function __construct(
        private readonly Call $call,
        private readonly string $responseClass,
    ) {}
    
    public function wait(): Message
    {
        if ($this->completed) {
            if ($this->error !== null) {
                throw $this->error;
            }
            return $this->result;
        }
        
        $event = $this->call->startBatch([
            \Grpc\OP_RECV_INITIAL_METADATA => true,
            \Grpc\OP_RECV_MESSAGE => true,
            \Grpc\OP_RECV_STATUS_ON_CLIENT => true,
        ]);
        
        $status = $this->extractStatus($event);
        
        if ($status['code'] !== \Grpc\STATUS_OK) {
            $this->error = new GrpcException($status['details'], $status['code']);
            $this->completed = true;
            throw $this->error;
        }
        
        $this->result = $this->deserializeResponse($event, $this->responseClass);
        $this->completed = true;
        
        return $this->result;
    }
}
```

## Error Handling

### Partial Failure

When some regions succeed and others fail:

```php
class BatchPartialFailureException extends TiKvException
{
    /**
     * @param array<int, TiKvException> $regionErrors regionId => exception
     */
    public function __construct(
        private readonly array $regionErrors,
    ) {
        parent::__construct(
            sprintf(
                'Batch operation partially failed: %d of %d regions failed. First error: %s',
                count($regionErrors),
                count($regionErrors), // total would be passed
                reset($regionErrors)->getMessage(),
            )
        );
    }
    
    /**
     * @return array<int, TiKvException>
     */
    public function getRegionErrors(): array
    {
        return $this->regionErrors;
    }
}
```

### Retry Strategy

Each region's async call still uses `executeWithRetry()` internally, so retries happen per-region independently and in parallel.

## Performance Considerations

### Benefits

- **Latency reduction**: For N regions, latency drops from `sum(region_latencies)` to `max(region_latencies)`
- **Throughput increase**: Better utilization of network bandwidth
- **No blocking**: While waiting for slow region, other regions complete

### Trade-offs

- **Memory usage**: All async calls hold resources until completion
- **Connection overhead**: More concurrent connections to different stores
- **Complexity**: Harder to debug than sequential execution

### Limits

Add configurable limit for max concurrent async calls:

```php
public function __construct(
    private readonly int $maxConcurrentRegions = 10,
) {}
```

If more regions, process in chunks.

## Testing Strategy

1. Unit tests for BatchAsyncExecutor
2. Unit tests for GrpcFuture
3. Unit tests for BatchPartialFailureException
4. Integration tests with mocked async behavior
5. Performance benchmarks (sequential vs parallel)

## Files to Create

```
src/Client/Batch/BatchAsyncExecutor.php
src/Client/Batch/GrpcFuture.php
src/Client/Exception/BatchPartialFailureException.php
tests/Unit/Batch/BatchAsyncExecutorTest.php
tests/Unit/Batch/GrpcFutureTest.php
```

## Files to Modify

```
src/Client/RawKv/RawKvClient.php (batchGet, batchPut, batchDelete)
src/Client/RawKv/RawKvClient.php (add execute*Async methods)
```

## Backward Compatibility

- Public API unchanged
- Behavior change: operations now parallel (should be faster, not breaking)
- New exception type for partial failures (extends TiKvException)

## Future Enhancements

1. Configurable concurrency limit
2. Circuit breaker for failing regions
3. Metrics for per-region latency
4. Adaptive batch sizing based on region performance
