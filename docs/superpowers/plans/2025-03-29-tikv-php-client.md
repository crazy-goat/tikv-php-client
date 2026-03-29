# TiKV PHP Client Implementation Plan

> **Goal:** Create minimal working TiKV RawKV client in pure PHP (no extensions) with Docker test cluster

**Architecture:** 
- Use amphp/http-client (pure PHP HTTP/2) + google/protobuf (pure PHP)
- Implement gRPC framing layer manually (HTTP/2 headers + protobuf + gRPC message framing)
- Connect to PD for cluster discovery, then directly to TiKV nodes
- Support basic RawKV operations: get, put, delete, batch operations

**Tech Stack:**
- PHP 8.2+
- amphp/http-client (HTTP/2 client)
- google/protobuf (protobuf serialization)
- Docker Compose (TiKV cluster for testing)

---

## Task 1: Project Setup

**Files:**
- Create: `composer.json`
- Create: `.gitignore`

**Implementation:**

```json
{
    "name": "tikv/php-client",
    "description": "Pure PHP TiKV RawKV client",
    "type": "library",
    "require": {
        "php": ">=8.2",
        "amphp/http-client": "^5.0",
        "google/protobuf": "^3.25"
    },
    "autoload": {
        "psr-4": {
            "TiKvPhp\\": "src/"
        }
    }
}
```

```gitignore
/vendor/
/composer.lock
/.idea/
*.log
```

---

## Task 2: Docker Compose for TiKV Cluster

**Files:**
- Create: `docker-compose.yml`

**Implementation:**

```yaml
version: '3.8'

services:
  pd:
    image: pingcap/pd:v7.1.0
    ports:
      - "2379:2379"
    command: >
      --name=pd
      --client-urls=http://0.0.0.0:2379
      --peer-urls=http://0.0.0.0:2380
      --advertise-client-urls=http://pd:2379
      --advertise-peer-urls=http://pd:2380
      --initial-cluster=pd=http://pd:2380
      --data-dir=/data/pd
    volumes:
      - pd-data:/data/pd

  tikv1:
    image: pingcap/tikv:v7.1.0
    ports:
      - "20160:20160"
    command: >
      --addr=0.0.0.0:20160
      --advertise-addr=tikv1:20160
      --pd-endpoints=pd:2379
      --data-dir=/data/tikv1
    volumes:
      - tikv1-data:/data/tikv1
    depends_on:
      - pd

  tikv2:
    image: pingcap/tikv:v7.1.0
    ports:
      - "20161:20161"
    command: >
      --addr=0.0.0.0:20161
      --advertise-addr=tikv2:20161
      --pd-endpoints=pd:2379
      --data-dir=/data/tikv2
    volumes:
      - tikv2-data:/data/tikv2
    depends_on:
      - pd

  tikv3:
    image: pingcap/tikv:v7.1.0
    ports:
      - "20162:20162"
    command: >
      --addr=0.0.0.0:20162
      --advertise-addr=tikv3:20162
      --pd-endpoints=pd:2379
      --data-dir=/data/tikv3
    volumes:
      - tikv3-data:/data/tikv3
    depends_on:
      - pd

volumes:
  pd-data:
  tikv1-data:
  tikv2-data:
  tikv3-data:
```

---

## Task 3: Download and Generate Protobuf Classes

**Files:**
- Create: `scripts/download-proto.sh`
- Create: `src/Proto/` (directory structure)

**Implementation:**

```bash
#!/bin/bash
# scripts/download-proto.sh

PROTO_DIR="proto"
mkdir -p $PROTO_DIR

# Download kvproto from GitHub
curl -L https://github.com/tikv/kvproto/archive/refs/heads/master.tar.gz | tar xz -C $PROTO_DIR --strip-components=1

# Generate PHP classes
protoc --php_out=src/Proto \
  --proto_path=$PROTO_DIR/proto \
  $PROTO_DIR/proto/kvrpcpb.proto \
  $PROTO_DIR/proto/pdpb.proto \
  $PROTO_DIR/proto/errorpb.proto \
  $PROTO_DIR/proto/metapb.proto
```

---

## Task 4: Implement gRPC Framing Layer

**Files:**
- Create: `src/Grpc/GrpcClient.php`
- Create: `src/Grpc/GrpcFrame.php`

**Implementation:**

```php
<?php
declare(strict_types=1);

namespace TiKvPhp\Grpc;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Google\Protobuf\Internal\Message;

class GrpcClient
{
    private $httpClient;
    
    public function __construct()
    {
        $this->httpClient = HttpClientBuilder::buildDefault();
    }
    
    public function call(string $address, string $service, string $method, Message $request): Message
    {
        // gRPC HTTP/2 framing:
        // 1 byte: compression flag (0 = no compression)
        // 4 bytes: message length (big-endian)
        // N bytes: protobuf message
        
        $serialized = $request->serializeToString();
        $length = strlen($serialized);
        
        $frame = pack('C', 0); // compression flag
        $frame .= pack('N', $length); // message length
        $frame .= $serialized;
        
        $httpRequest = new Request('http://' . $address . '/' . $service . '/' . $method, 'POST');
        $httpRequest->setHeader('Content-Type', 'application/grpc');
        $httpRequest->setHeader('TE', 'trailers');
        $httpRequest->setBody($frame);
        
        $response = $this->httpClient->request($httpRequest);
        $body = $response->getBody()->buffer();
        
        // Parse gRPC response frame
        $compression = ord($body[0]);
        $length = unpack('N', substr($body, 1, 4))[1];
        $message = substr($body, 5, $length);
        
        return $message;
    }
}
```

---

## Task 5: Implement PD Client

**Files:**
- Create: `src/Connection/PdClient.php`

**Implementation:**

```php
<?php
declare(strict_types=1);

namespace TiKvPhp\Connection;

use TiKvPhp\Grpc\GrpcClient;
use TiKvPhp\Proto\Pdpb\GetRegionRequest;
use TiKvPhp\Proto\Pdpb\GetRegionResponse;

class PdClient
{
    private GrpcClient $grpc;
    private string $pdAddress;
    
    public function __construct(string $pdAddress)
    {
        $this->grpc = new GrpcClient();
        $this->pdAddress = $pdAddress;
    }
    
    public function getRegion(string $key): array
    {
        $request = new GetRegionRequest();
        $request->setKey($key);
        
        $response = $this->grpc->call(
            $this->pdAddress,
            'pdpb.PD',
            'GetRegion',
            $request
        );
        
        /** @var GetRegionResponse $response */
        return [
            'region' => $response->getRegion(),
            'leader' => $response->getLeader(),
        ];
    }
    
    public function getStore(int $storeId): array
    {
        // Get store info to find TiKV address
    }
}
```

---

## Task 6: Implement RawKV Client

**Files:**
- Create: `src/RawKv/RawKvClient.php`
- Create: `src/RawKv/RegionConnection.php`

**Implementation:**

```php
<?php
declare(strict_types=1);

namespace TiKvPhp\RawKv;

use TiKvPhp\Connection\PdClient;
use TiKvPhp\Grpc\GrpcClient;
use TiKvPhp\Proto\Kvrpcpb\RawGetRequest;
use TiKvPhp\Proto\Kvrpcpb\RawPutRequest;
use TiKvPhp\Proto\Kvrpcpb\RawDeleteRequest;

class RawKvClient
{
    private PdClient $pdClient;
    private GrpcClient $grpc;
    private array $regionCache = [];
    
    public static function create(array $pdEndpoints): self
    {
        $pdClient = new PdClient($pdEndpoints[0]);
        return new self($pdClient);
    }
    
    public function __construct(PdClient $pdClient)
    {
        $this->pdClient = $pdClient;
        $this->grpc = new GrpcClient();
    }
    
    public function get(string $key): ?string
    {
        $region = $this->getRegionForKey($key);
        
        $request = new RawGetRequest();
        $request->setKey($key);
        
        $response = $this->grpc->call(
            $region['leader']['address'],
            'tikvpb.Tikv',
            'RawGet',
            $request
        );
        
        return $response->getValue() ?: null;
    }
    
    public function put(string $key, string $value): void
    {
        $region = $this->getRegionForKey($key);
        
        $request = new RawPutRequest();
        $request->setKey($key);
        $request->setValue($value);
        
        $this->grpc->call(
            $region['leader']['address'],
            'tikvpb.Tikv',
            'RawPut',
            $request
        );
    }
    
    public function delete(string $key): void
    {
        $region = $this->getRegionForKey($key);
        
        $request = new RawDeleteRequest();
        $request->setKey($key);
        
        $this->grpc->call(
            $region['leader']['address'],
            'tikvpb.Tikv',
            'RawDelete',
            $request
        );
    }
    
    private function getRegionForKey(string $key): array
    {
        if (!isset($this->regionCache[$key])) {
            $this->regionCache[$key] = $this->pdClient->getRegion($key);
        }
        return $this->regionCache[$key];
    }
}
```

---

## Task 7: Create Usage Examples

**Files:**
- Create: `examples/basic.php`

**Implementation:**

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use TiKvPhp\RawKv\RawKvClient;

// Connect to TiKV cluster
$client = RawKvClient::create(['127.0.0.1:2379']);

try {
    // Put
    $client->put('hello', 'world');
    echo "Put: hello => world\n";
    
    // Get
    $value = $client->get('hello');
    echo "Get: hello => $value\n";
    
    // Delete
    $client->delete('hello');
    echo "Deleted: hello\n";
    
    // Verify deletion
    $value = $client->get('hello');
    echo "Get after delete: " . ($value ?? 'null') . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

---

## Task 8: Documentation

**Files:**
- Create: `README.md`

**Content:**
- Installation instructions
- Docker setup
- Basic usage examples
- Architecture overview

---

## Testing Instructions

```bash
# Start TiKV cluster
docker-compose up -d

# Wait for cluster to be ready (check logs)
docker-compose logs -f pd

# Install dependencies
composer install

# Download and generate protobuf classes
bash scripts/download-proto.sh

# Run example
php examples/basic.php
```

---

## Success Criteria

- [ ] `composer install` works without errors
- [ ] `docker-compose up` starts TiKV cluster
- [ ] `php examples/basic.php` executes put/get/delete successfully
- [ ] No PHP extensions required (pure PHP implementation)
