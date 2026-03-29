# Feature: Column Family Support

## Overview
Add support for TiKV column families (CF). TiKV has three column families: `default`, `write`, and `lock`. RawKV operations can target a specific CF. This is a client-level default with per-call override.

## Reference Implementation
- **Go**: 
  - Client-level: `client.SetColumnFamily("write")` — sets default CF for all operations
  - Per-call: `client.Get(ctx, key, rawkv.SetColumnFamily("lock"))` — overrides for single call
  - Resolution: per-call wins if non-empty, otherwise client-level default
  - TiKV CFs: `"default"` (empty string = default), `"write"`, `"lock"`
- **Java**: No explicit CF support in public API

## Protobuf Details
Most RawKV request messages have a `string cf` field:
```protobuf
message RawGetRequest {
  Context context = 1;
  bytes key = 2;
  string cf = 3;    // column family
  // ...
}
```

Present on: RawGet, RawPut, RawDelete, RawBatchGet, RawBatchPut, RawBatchDelete, RawScan, RawDeleteRange, RawCAS, RawBatchScan.

## API Design

### Client-Level Default
```php
$client = RawKvClient::create(['127.0.0.1:2379']);
$client->setColumnFamily('write'); // all subsequent ops use 'write' CF

$value = $client->get('key'); // reads from 'write' CF
```

### Per-Call Override (future consideration)
Per-call override would require changing every method signature to add an optional `$cf` parameter. This is verbose. Alternative: use a fluent builder pattern:
```php
$client->withColumnFamily('lock')->get('key'); // one-off override
```

### Decision
Start with client-level `setColumnFamily()` only. Per-call override can be added later if needed.

```php
public function setColumnFamily(string $cf): self
{
    $this->columnFamily = $cf;
    return $this;
}

public function getColumnFamily(): string
{
    return $this->columnFamily;
}
```

## Implementation Details
1. Add `private string $columnFamily = ''` to `RawKvClient`
2. Add `setColumnFamily()` / `getColumnFamily()` methods
3. In every RPC method that creates a request with a `cf` field, set it:
   ```php
   if ($this->columnFamily !== '') {
       $request->setCf($this->columnFamily);
   }
   ```
4. Affected methods: get, put, delete, batchGet, batchPut, batchDelete, scan, reverseScan, deleteRange, compareAndSwap, batchScan, checksum

## Testing Strategy
1. Default CF (empty string) works as before
2. Set CF to 'write' — operations target write CF
3. Set CF to 'lock' — operations target lock CF
4. Set CF back to '' — returns to default
5. Invalid CF name — TiKV returns error (test error handling)
6. CF is preserved across multiple operations

## Priority: MEDIUM
Useful for advanced use cases (accessing TiDB's write/lock CFs), but most RawKV users only need the default CF.
