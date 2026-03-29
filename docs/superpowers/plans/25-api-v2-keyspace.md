# Feature: API V2 and Keyspace Support

## Overview
Support TiKV API V2, which provides key isolation between RawKV, TxnKV, and TiDB on the same cluster, plus multi-tenant keyspace support.

## Reference Implementation
- **Go**: Full V2 support via `WithAPIVersion(kvrpcpb.APIVersion_V2)` and `WithKeyspace("name")`
  - Codec abstraction: `CodecV1` (passthrough) and `CodecV2` (prefix encoding)
  - Key encoding: `mode_prefix(1 byte) + keyspace_id(3 bytes) + user_key`
  - MCE (Memory Comparable Encoding) for region routing keys
  - Keyspace loaded from PD by name → numeric ID

## API Versions

### V1 (current PHP implementation)
- Keys stored as-is, no encoding
- No isolation between RawKV/TxnKV/TiDB
- TTL via `enable-ttl = true` in tikv.toml (V1TTL mode)

### V2
- Keys prefixed: `'r' + keyspace_id[3 bytes] + user_key` (for RawKV)
- Full isolation: RawKV (`r`), TxnKV (`x`), TiDB (`m`/`t`) coexist safely
- Built-in TTL support (no separate config needed)
- CDC support via timestamps
- Column family restricted to `default` only
- Requires TiDB instance for GC

## Key Encoding (V2)

### For RPC requests (sent to TiKV)
```
encoded_key = 0x72 + keyspace_id[3 bytes big-endian] + user_key
```
Example: keyspace ID 1, user key "hello"
```
0x72 0x00 0x00 0x01 h e l l o
```

### For region routing (sent to PD)
```
region_key = MCE(encoded_key)
```
MCE = Memory Comparable Encoding (TiDB codec, 8-byte groups with pad bytes)

### For range scans with empty endKey
Empty endKey → substitute keyspace end boundary:
```
keyspace_end = prefix_as_uint32 + 1  (next keyspace start)
```

## API Design

### Construction
```php
$client = RawKvClient::create(
    pdAddresses: ['127.0.0.1:2379'],
    apiVersion: ApiVersion::V2,
    keyspace: 'my_app',  // optional, default = 'DEFAULT' (ID 0)
);
```

### Codec Interface
```php
interface Codec
{
    public function encodeKey(string $key): string;
    public function decodeKey(string $encodedKey): string;
    public function encodeRegionKey(string $key): string;
    public function decodeRegionKey(string $encodedKey): string;
    public function encodeRange(string $startKey, string $endKey): array;
}
```

### Implementations
- `CodecV1`: passthrough (no encoding)
- `CodecV2`: prefix encoding + MCE for region keys

## Implementation Details

### Phase 1: Codec Abstraction
1. Create `Codec` interface
2. Create `CodecV1` (current behavior, no-op)
3. Inject codec into `RawKvClient`

### Phase 2: CodecV2 Key Encoding
1. Implement prefix encoding: `chr(0x72) . substr(pack('N', $keyspaceId), 1)`
2. Implement MCE encoding (port from TiDB's `codec.EncodeBytes`)
3. Encode all outgoing keys, decode all incoming keys

### Phase 3: Keyspace Loading
1. Add PD keyspace API call: `LoadKeyspace(name) → KeyspaceMeta`
2. Extract keyspace ID from metadata
3. Default keyspace: ID 0, name "DEFAULT"

### Phase 4: Context Changes
1. Set `api_version = V2` in request context
2. Set `keyspace_id` in request context

## Testing Strategy
1. V1 mode works unchanged (backward compatible)
2. V2 key encoding is correct (compare with Go client output)
3. V2 key decoding strips prefix correctly
4. MCE encoding matches TiDB codec
5. Keyspace isolation: two keyspaces don't see each other's data
6. Empty endKey in V2 uses keyspace boundary
7. Region routing with MCE-encoded keys works

## Priority: LOW
V2 is GA since TiKV 6.1 but not widely adopted. Most RawKV users use V1. Implement only if multi-tenancy or RawKV+TiDB coexistence is needed.
