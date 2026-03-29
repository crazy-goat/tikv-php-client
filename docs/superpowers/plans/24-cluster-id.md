# Feature: Cluster ID

## Overview
Expose the TiKV cluster ID from PD. Useful for diagnostics, multi-cluster management, and verifying the client is connected to the expected cluster.

## Reference Implementation
- **Go**: `client.ClusterID() uint64` — returns the cluster ID obtained during PD connection
- **Java**: Available via `client.getSession().getPDClient().getClusterId()`

## Protobuf Details
The cluster ID is returned in every PD response header:
```protobuf
message ResponseHeader {
  uint64 cluster_id = 1;
  // ...
}
```

It's also available via `GetMembers` RPC:
```protobuf
message GetMembersResponse {
  ResponseHeader header = 1;
  repeated Member members = 2;
  Member leader = 3;
  // ...
}
```

## API Design
```php
$client = RawKvClient::create(['127.0.0.1:2379']);
$clusterId = $client->getClusterId(); // returns int
```

## Implementation Details
1. `PdClient` already connects to PD and gets member info
2. Store `cluster_id` from the PD response header during initial connection
3. Add `getClusterId(): int` to `PdClient`
4. Add `getClusterId(): int` to `RawKvClient` (delegates to PdClient)

## Testing Strategy
1. getClusterId returns a non-zero integer
2. getClusterId is consistent across multiple calls
3. getClusterId matches the cluster ID from PD API

## Priority: MEDIUM
Simple to implement, useful for diagnostics and multi-cluster setups.
