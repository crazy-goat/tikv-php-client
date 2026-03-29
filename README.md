# TiKV PHP Client

[![Tests](https://github.com/crazy-goat/tikv-php-client/actions/workflows/tests.yml/badge.svg)](https://github.com/crazy-goat/tikv-php-client/actions/workflows/tests.yml)

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

tests/
├── Unit/                  # Unit tests (no external dependencies)
├── Integration/           # Integration tests
└── E2E/                   # End-to-end tests (requires TiKV)

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

## Testing

### Run All Tests

```bash
# Run E2E tests with TiKV cluster
./scripts/test-e2e.sh

# Run unit tests only
./scripts/test.sh
```

### Run Specific Test Suites

```bash
# Unit tests (no TiKV required)
docker-compose run --rm php-client vendor/bin/phpunit --testsuite Unit

# E2E tests (requires TiKV)
docker-compose --profile test up --build php-test
```

### Test Results

```
✅ Unit Tests: 7 tests, 8 assertions
✅ E2E Tests: 9 tests, 12 assertions
```

## Implemented Operations

- ✅ **RawGet** - Single key read
- ✅ **RawPut** - Single key write
- ✅ **RawDelete** - Single key delete
- ✅ **PD Region Discovery** - With RegionEpoch support
- ✅ **Retry Logic** - Automatic retry on EpochNotMatch
- ✅ **Region Routing** - Direct to correct TiKV node

## Planned Operations

See [docs/superpowers/plans/](docs/superpowers/plans/) for detailed implementation plans.

### Phase 1: Batch Operations
- 🔄 RawBatchGet
- 🔄 RawBatchPut
- 🔄 RawBatchDelete

### Phase 2: Scan Operations
- 🔄 RawScan
- 🔄 RawBatchScan

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

### Install Dependencies

```bash
docker-compose run --rm php-client composer install
```

## Key Features

- ⚡ **Instant builds** with Alpine Edge pre-built packages
- 🆕 **PHP 8.4** with latest gRPC 1.78.0
- 🔒 **No C++ compilation** required
- 📦 **Minimal dependencies**
- 🔄 **Automatic retry** on region changes
- 🎯 **Region routing** to correct nodes
- ✅ **Full test coverage** - Unit + E2E tests

## License

MIT
