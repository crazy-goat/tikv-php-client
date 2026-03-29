# TiKV PHP Client

[![Tests](https://github.com/crazy-goat/tikv-php-client/actions/workflows/tests.yml/badge.svg)](https://github.com/crazy-goat/tikv-php-client/actions/workflows/tests.yml)

PHP client for TiKV RawKV API using gRPC extension.

## Quick Start

```bash
# Start TiKV cluster and run example
make up
make example
```

## Usage

```php
use CrazyGoat\TiKV\Client\RawKv\RawKvClient;

$client = RawKvClient::create(['127.0.0.1:2379']);

// Basic CRUD
$client->put('key', 'value');
$value = $client->get('key');       // 'value'
$client->delete('key');

// Batch operations
$client->batchPut(['k1' => 'v1', 'k2' => 'v2', 'k3' => 'v3']);
$values = $client->batchGet(['k1', 'k2', 'k3']);
$client->batchDelete(['k1', 'k2', 'k3']);

// Scan
$results = $client->scan('start', 'end', limit: 100);
$results = $client->scanPrefix('user:');
$results = $client->reverseScan('end', 'start', limit: 100);
$results = $client->batchScan([['a:', 'a;'], ['b:', 'b;']], eachLimit: 50);

// Range delete
$client->deleteRange('temp:', 'temp;');
$client->deletePrefix('cache:');

// TTL (requires enable-ttl=true in tikv.toml)
$client->put('session', 'data', ttl: 3600);        // expires in 1 hour
$remaining = $client->getKeyTTL('session');          // seconds remaining

// Atomic operations
$result = $client->compareAndSwap('counter', '1', '2');  // CAS
if ($result->swapped) { /* success */ }

$existing = $client->putIfAbsent('lock', 'owner-1');     // insert if not exists
if ($existing === null) { /* acquired lock */ }

// Data integrity
$checksum = $client->checksum('data:', 'data;');
echo "Keys: {$checksum->totalKvs}, Bytes: {$checksum->totalBytes}";

$client->close();
```

## Implemented Operations

### Core CRUD
- ✅ **Get** / **Put** / **Delete** — Single key operations
- ✅ **BatchGet** / **BatchPut** / **BatchDelete** — Batch operations

### Scanning
- ✅ **Scan** — Range scan `[startKey, endKey)` with limit and keyOnly
- ✅ **ReverseScan** — Reverse range scan (native `reverse=true`)
- ✅ **ScanPrefix** — Prefix-based scanning
- ✅ **BatchScan** — Multiple non-contiguous range scanning

### Range Operations
- ✅ **DeleteRange** — Delete all keys in `[startKey, endKey)`
- ✅ **DeletePrefix** — Delete all keys with a given prefix

### TTL
- ✅ **PutWithTTL** — Store with expiration (seconds)
- ✅ **GetKeyTTL** — Get remaining TTL of a key

### Atomic Operations
- ✅ **CompareAndSwap** — Atomic CAS with `CasResult` (swapped + previousValue)
- ✅ **PutIfAbsent** — Conditional insert (returns existing value or null)

### Data Integrity
- ✅ **Checksum** — CRC64-XOR checksum over key range with `ChecksumResult`

### Infrastructure
- ✅ **PD Region Discovery** — With RegionEpoch support
- ✅ **Region Routing** — Direct to correct TiKV node
- ✅ **Retry Logic** — Automatic retry on EpochNotMatch

## Project Structure

```
src/
├── Client/
│   ├── Connection/
│   │   └── PdClient.php          # PD discovery & region routing
│   ├── Grpc/
│   │   └── GrpcClient.php        # Low-level gRPC wrapper
│   └── RawKv/
│       ├── RawKvClient.php        # Main client (20 operations)
│       ├── CasResult.php          # CompareAndSwap result
│       └── ChecksumResult.php     # Checksum result
└── Proto/                         # Generated protobuf classes
    ├── Kvrpcpb/                   # TiKV request/response
    ├── Pdpb/                      # PD request/response
    └── Tikvpb/                    # gRPC service stubs

tests/
├── Unit/                          # 21 unit tests
└── E2E/                           # 141 E2E tests
```

## Available Commands (Makefile)

```bash
make install          # Install PHP dependencies
make test             # Run all tests
make test-unit        # Run unit tests only
make test-e2e         # Run E2E tests with TiKV
make proto-generate   # Generate PHP from proto files
make proto-clean      # Remove generated proto classes
make up               # Start TiKV cluster
make down             # Stop TiKV cluster
make example          # Run basic example
```

## Roadmap

See [Implementation Plans](docs/superpowers/plans/README.md) for the full roadmap and feature comparison with Go and Java clients.

## License

MIT
