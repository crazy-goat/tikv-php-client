# TiKV PHP Client

PHP client for TiKV RawKV API using gRPC extension.

## Quick Start

```bash
# 1. Download TiKV proto files from https://github.com/tikv/kvproto
# 2. Place them in proto/ directory
# 3. Generate PHP classes:
docker-compose run --rm php-client protoc --php_out=src/Proto proto/*.proto

# 4. Start TiKV cluster and run example
docker-compose up --build php-client
```

## Architecture

- **gRPC Extension**: Uses official gRPC PHP extension (v1.78.0)
- **Alpine Edge**: Pre-built packages for instant builds
- **Protobuf**: Generated classes from TiKV .proto files
- **PD Discovery**: Connects to PD for cluster topology
- **Direct TiKV**: Talks directly to TiKV nodes

## Docker Services

- **pd**: Placement Driver (port 2379)
- **tikv1-3**: TiKV nodes (ports 20160-20162)
- **php-client**: PHP 8.4 with gRPC extension

## Project Structure

```
proto/              # TiKV .proto files (download from kvproto repo)
src/
├── Proto/          # Generated PHP classes from .proto
├── Grpc/           # gRPC client wrapper
├── Connection/     # PD client
└── RawKv/          # Main RawKV client
examples/
└── basic.php       # Usage example
```

## Setup Steps

1. **Download proto files:**
   ```bash
   git clone https://github.com/tikv/kvproto.git /tmp/kvproto
   cp /tmp/kvproto/proto/*.proto proto/
   ```

2. **Generate PHP classes:**
   ```bash
   docker-compose run --rm php-client \
     protoc --php_out=src/Proto \
     --proto_path=proto \
     proto/kvrpcpb.proto \
     proto/pdpb.proto
   ```

3. **Update composer.json** with generated namespaces:
   ```json
   "autoload": {
     "psr-4": {
       "TiKvPhp\\": "src/",
       "Kvrpcpb\\": "src/Proto/Kvrpcpb/",
       "Pdpb\\": "src/Proto/Pdpb/"
     }
   }
   ```

4. **Run:**
   ```bash
   docker-compose up --build php-client
   ```

## Usage

```php
use TiKvPhp\RawKv\RawKvClient;

$client = RawKvClient::create(['127.0.0.1:2379']);
$client->put('key', 'value');
$value = $client->get('key');
$client->delete('key');
$client->close();
```

## Key Features

- ⚡ **Instant builds** with Alpine Edge pre-built packages
- 🆕 **PHP 8.4** with latest gRPC 1.78.0
- 🔒 **No C++ compilation** required
- 📦 **Minimal dependencies**

## Limitations (POC)

- Requires TiKV .proto files for full functionality
- Single TiKV node hardcoded
- No batch operations
- No region routing
