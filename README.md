# TiKV PHP Client

PHP client for TiKV RawKV API using gRPC extension.

## Quick Start

```bash
# Start TiKV cluster and run example
docker-compose up --build php-client
```

## Architecture

- **gRPC Extension**: Uses official gRPC PHP extension (v1.78.0)
- **Alpine Edge**: Pre-built packages for instant builds
- **Protobuf**: Generated from official TiKV proto files
- **PD Discovery**: Connects to PD for cluster topology with RegionEpoch
- **Direct TiKV**: Talks directly to TiKV nodes with retry logic

## Project Structure

```
proto/                      # Protocol buffer definitions (git submodules)
├── kvproto/               # TiKV proto files
├── gogo/                  # gogo protobuf
└── googleapis/            # Google APIs

src/
├── Proto/                 # Generated PHP classes from proto
├── Grpc/GrpcClient.php    # gRPC client wrapper
├── Connection/PdClient.php # PD discovery with RegionEpoch
└── RawKv/RawKvClient.php  # Main RawKV interface with retry

examples/
└── basic.php              # Usage example
```

## Docker Services

- **pd**: Placement Driver (port 2379)
- **tikv1-3**: TiKV nodes (ports 20160-20162)
- **php-client**: PHP 8.4 with gRPC extension

## Usage

```php
use CrazyGoat\TiKV\RawKv\RawKvClient;

// Connect to TiKV cluster
$client = RawKvClient::create(['127.0.0.1:2379']);

// Put
$client->put('key', 'value');

// Get
$value = $client->get('key');

// Delete
$client->delete('key');

// Close
$client->close();
```

## Implemented Operations

- ✅ **RawGet** - Single key read
- ✅ **RawPut** - Single key write
- ✅ **RawDelete** - Single key delete
- ✅ **PD Region Discovery** - With RegionEpoch support
- ✅ **Retry Logic** - Automatic retry on EpochNotMatch
- ✅ **Region Routing** - Direct to correct TiKV node

## Planned Operations

See [docs/superpowers/plans/2025-03-29-tikv-php-full-rawkv.md](docs/superpowers/plans/2025-03-29-tikv-php-full-rawkv.md) for full implementation plan.

### Phase 1: Batch Operations
- 🔄 RawBatchGet
- 🔄 RawBatchPut
- 🔄 RawBatchDelete

### Phase 2: Scan Operations
- 🔄 RawScan
- 🔄 RawBatchScan

### Phase 3: Range Operations
- 🔄 RawDeleteRange

### Phase 4: Advanced Features
- 🔄 RawCAS (Compare and Swap)
- 🔄 RawGetKeyTTL
- 🔄 RawChecksum

## Development

### Generate PHP Classes from Proto

```bash
# Generate from TiKV proto files
docker-compose run --rm php-client protoc \
  --php_out=src/Proto \
  -I proto/kvproto/proto \
  -I proto/gogo \
  -I proto/googleapis \
  proto/kvproto/proto/kvrpcpb.proto \
  proto/kvproto/proto/pdpb.proto
```

### Run Tests

```bash
docker-compose up --build php-client
```

## Key Features

- ⚡ **Instant builds** with Alpine Edge pre-built packages
- 🆕 **PHP 8.4** with latest gRPC 1.78.0
- 🔒 **No C++ compilation** required
- 📦 **Minimal dependencies**
- 🔄 **Automatic retry** on region changes
- 🎯 **Region routing** to correct nodes

## License

MIT

## Testing

### Run Unit Tests

```bash
# Run unit tests only (no TiKV required)
composer install
vendor/bin/phpunit --testsuite Unit
```

### Run E2E Tests

```bash
# Option 1: Using the test script (recommended)
./scripts/test-e2e.sh

# Option 2: Using docker-compose profile
docker-compose --profile test up --build php-test

# Option 3: Manual steps
docker-compose up -d pd tikv1 tikv2 tikv3
docker-compose run --rm -e PD_ENDPOINTS=pd:2379 php-client vendor/bin/phpunit --testsuite E2E
docker-compose down
```

### Test Structure

```
tests/
├── Unit/                    # Unit tests (no external dependencies)
│   ├── Grpc/
│   ├── Connection/
│   └── RawKv/
├── Integration/             # Integration tests
└── E2E/                     # End-to-end tests (requires TiKV)
    └── RawKvE2ETest.php
```

### Test Coverage

- ✅ **Unit Tests**: Test individual classes in isolation
- ✅ **E2E Tests**: Test full workflow with real TiKV cluster
- 🔄 **Integration Tests**: Test component interactions (planned)

## Development

### Generate PHP Classes from Proto

```bash
# Generate from TiKV proto files
docker-compose run --rm php-client protoc \
  --php_out=src/Proto \
  -I proto/kvproto/proto \
  -I proto/gogo \
  -I proto/googleapis \
  proto/kvproto/proto/kvrpcpb.proto \
  proto/kvproto/proto/pdpb.proto
```

### Code Style

```bash
# Check code style
vendor/bin/phpunit --testsuite Unit
```
