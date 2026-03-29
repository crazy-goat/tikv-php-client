# TiKV PHP Client - gRPC Extension + Docker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create working TiKV RawKV client in PHP using gRPC extension with Docker-based TiKV cluster

**Architecture:** 
- Use official gRPC PHP extension for communication with TiKV/PD
- Implement minimal protobuf message classes for RawKV operations
- Docker Compose: PD + 3 TiKV nodes + PHP client container
- POC scope: single-key get/put/delete (no batch, no region routing)

**Tech Stack:**
- PHP 8.2+ with grpc extension
- Docker & Docker Compose
- TiKV v7.1.0

---

## Task 1: Create Project Structure

**Files:**
- Create: `composer.json`
- Create: `.gitignore`

**Implementation:**

```json
{
    "name": "tikv/php-client",
    "description": "PHP TiKV RawKV client using gRPC extension",
    "type": "library",
    "require": {
        "php": ">=8.2",
        "grpc/grpc": "^1.57"
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
.DS_Store
```

---

## Task 2: Create Docker Compose for TiKV Cluster

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
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:2379/pd/api/v1/health"]
      interval: 5s
      timeout: 3s
      retries: 5

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
      pd:
        condition: service_healthy

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
      pd:
        condition: service_healthy

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
      pd:
        condition: service_healthy

  php-client:
    build: .
    volumes:
      - .:/app
    depends_on:
      - pd
      - tikv1
      - tikv2
      - tikv3
    environment:
      - PD_ENDPOINTS=pd:2379
      - TIKV_ENDPOINTS=tikv1:20160
    command: php examples/basic.php

volumes:
  pd-data:
  tikv1-data:
  tikv2-data:
  tikv3-data:
```

---

## Task 3: Create Dockerfile with gRPC Extension

**Files:**
- Create: `Dockerfile`

**Implementation:**

```dockerfile
FROM composer:latest as composer

FROM php:8.2-cli-alpine

# Install gRPC extension
RUN apk add --no-cache \
    autoconf \
    g++ \
    make \
    linux-headers \
    && pecl install grpc \
    && docker-php-ext-enable grpc \
    && apk del autoconf g++ make linux-headers

# Copy composer from official image
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files
COPY composer.json ./

# Install dependencies
RUN composer install --no-interaction --no-scripts --no-autoloader

# Copy source code
COPY . .

# Generate autoloader
RUN composer dump-autoload

# Run example by default
CMD ["php", "examples/basic.php"]
```

---

## Task 4: Create Minimal Protobuf Classes

**Files:**
- Create: `src/Proto/RawGetRequest.php`
- Create: `src/Proto/RawGetResponse.php`
- Create: `src/Proto/RawPutRequest.php`
- Create: `src/Proto/RawPutResponse.php`
- Create: `src/Proto/RawDeleteRequest.php`
- Create: `src/Proto/RawDeleteResponse.php`

**Implementation:**

```php
<?php
declare(strict_types=1);

namespace TiKvPhp\Proto;

class RawGetRequest
{
    private string $key = '';
    
    public function setKey(string $key): void
    {
        $this->key = $key;
    }
    
    public function serializeToString(): string
    {
        $keyLen = strlen($this->key);
        return chr(0x0a) . chr($keyLen) . $this->key;
    }
}
```

```php
<?php
declare(strict_types=1);

namespace TiKvPhp\Proto;

class RawGetResponse
{
    private ?string $value = null;
    
    public static function parseFromString(string $data): self
    {
        $response = new self();
        $offset = 0;
        
        while ($offset < strlen($data)) {
            $tag = ord($data[$offset]);
            $offset++;
            $fieldNum = $tag >> 3;
            $wireType = $tag & 0x07;
            
            if ($wireType === 2) {
                $len = ord($data[$offset]);
                $offset++;
                $value = substr($data, $offset, $len);
                $offset += $len;
                if ($fieldNum === 1) {
                    $response->value = $value;
                }
            }
        }
        
        return $response;
    }
    
    public function getValue(): ?string
    {
        return $this->value;
    }
}
```

```php
<?php
declare(strict_types=1);

namespace TiKvPhp\Proto;

class RawPutRequest
{
    private string $key = '';
    private string $value = '';
    
    public function setKey(string $key): void
    {
        $this->key = $key;
    }
    
    public function setValue(string $value): void
    {
        $this->value = $value;
    }
    
    public function serializeToString(): string
    {
        $result = '';
        $keyLen = strlen($this->key);
        $result .= chr(0x0a) . chr($keyLen) . $this->key;
        $valLen = strlen($this->value);
        $result .= chr(0x12) . chr($valLen) . $this->value;
        return $result;
    }
}
```

```php
<?php
declare(strict_types=1);

namespace TiKvPhp\Proto;

class RawPutResponse
{
    public static function parseFromString(string $data): self
    {
        return new self();
    }
}
```

```php
<?php
declare(strict_types=1);

namespace TiKvPhp\Proto;

class RawDeleteRequest
{
    private string $key = '';
    
    public function setKey(string $key): void
    {
        $this->key = $key;
    }
    
    public function serializeToString(): string
    {
        $keyLen = strlen($this->key);
        return chr(0x0a) . chr($keyLen) . $this->key;
    }
}
```

```php
<?php
declare(strict_types=1);

namespace TiKvPhp\Proto;

class RawDeleteResponse
{
    public static function parseFromString(string $data): self
    {
        return new self();
    }
}
```

---

## Task 5: Create gRPC Client Wrapper

**Files:**
- Create: `src/Grpc/GrpcClient.php`

**Implementation:**

```php
<?php
declare(strict_types=1);

namespace TiKvPhp\Grpc;

use Grpc\Channel;
use Grpc\ChannelCredentials;

class GrpcClient
{
    private array $channels = [];
    
    public function call(string $address, string $service, string $method, $request, string $responseClass)
    {
        if (!isset($this->channels[$address])) {
            $this->channels[$address] = new Channel($address, [
                'credentials' => ChannelCredentials::createInsecure(),
            ]);
        }
        
        $channel = $this->channels[$address];
        $call = new \Grpc\UnaryCall(
            $channel,
            '/' . $service . '/' . $method,
            ['timeout' => 5000000]
        );
        
        $call->start($request->serializeToString());
        $event = $call->wait();
        
        if ($event->status->code !== \Grpc\STATUS_OK) {
            throw new \RuntimeException(
                'gRPC error: ' . $event->status->details,
                $event->status->code
            );
        }
        
        return $responseClass::parseFromString($event->message);
    }
    
    public function close(): void
    {
        foreach ($this->channels as $channel) {
            $channel->close();
        }
        $this->channels = [];
    }
}
```

---

## Task 6: Create PD Client

**Files:**
- Create: `src/Connection/PdClient.php`

**Implementation:**

```php
<?php
declare(strict_types=1);

namespace TiKvPhp\Connection;

use TiKvPhp\Grpc\GrpcClient;

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
        // POC: return dummy region info
        return [
            'region' => ['id' => 1],
            'leader' => ['store_id' => 1],
        ];
    }
}
```

---

## Task 7: Create RawKV Client

**Files:**
- Create: `src/RawKv/RawKvClient.php`

**Implementation:**

```php
<?php
declare(strict_types=1);

namespace TiKvPhp\RawKv;

use TiKvPhp\Connection\PdClient;
use TiKvPhp\Grpc\GrpcClient;
use TiKvPhp\Proto\RawGetRequest;
use TiKvPhp\Proto\RawGetResponse;
use TiKvPhp\Proto\RawPutRequest;
use TiKvPhp\Proto\RawPutResponse;
use TiKvPhp\Proto\RawDeleteRequest;
use TiKvPhp\Proto\RawDeleteResponse;

class RawKvClient
{
    private PdClient $pdClient;
    private GrpcClient $grpc;
    private bool $closed = false;
    
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
        $this->ensureOpen();
        
        $tikvAddress = getenv('TIKV_ENDPOINTS') ?: '127.0.0.1:20160';
        
        $request = new RawGetRequest();
        $request->setKey($key);
        
        $response = $this->grpc->call(
            $tikvAddress,
            'tikvpb.Tikv',
            'RawGet',
            $request,
            RawGetResponse::class
        );
        
        return $response->getValue();
    }
    
    public function put(string $key, string $value): void
    {
        $this->ensureOpen();
        
        $tikvAddress = getenv('TIKV_ENDPOINTS') ?: '127.0.0.1:20160';
        
        $request = new RawPutRequest();
        $request->setKey($key);
        $request->setValue($value);
        
        $this->grpc->call(
            $tikvAddress,
            'tikvpb.Tikv',
            'RawPut',
            $request,
            RawPutResponse::class
        );
    }
    
    public function delete(string $key): void
    {
        $this->ensureOpen();
        
        $tikvAddress = getenv('TIKV_ENDPOINTS') ?: '127.0.0.1:20160';
        
        $request = new RawDeleteRequest();
        $request->setKey($key);
        
        $this->grpc->call(
            $tikvAddress,
            'tikvpb.Tikv',
            'RawDelete',
            $request,
            RawDeleteResponse::class
        );
    }
    
    public function close(): void
    {
        if (!$this->closed) {
            $this->grpc->close();
            $this->closed = true;
        }
    }
    
    private function ensureOpen(): void
    {
        if ($this->closed) {
            throw new \RuntimeException('Client is closed');
        }
    }
}
```

---

## Task 8: Create Basic Example

**Files:**
- Create: `examples/basic.php`

**Implementation:**

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use TiKvPhp\RawKv\RawKvClient;

$pdEndpoints = getenv('PD_ENDPOINTS') ? explode(',', getenv('PD_ENDPOINTS')) : ['127.0.0.1:2379'];
$client = RawKvClient::create($pdEndpoints);

try {
    echo "TiKV PHP Client - Basic Example\n";
    echo "================================\n\n";
    
    echo "Putting key 'hello' with value 'world'...\n";
    $client->put('hello', 'world');
    echo "✓ Put successful\n\n";
    
    echo "Getting key 'hello'...\n";
    $value = $client->get('hello');
    echo "✓ Got value: " . ($value ?? 'null') . "\n\n";
    
    echo "Deleting key 'hello'...\n";
    $client->delete('hello');
    echo "✓ Delete successful\n\n";
    
    echo "Getting key 'hello' after delete...\n";
    $value = $client->get('hello');
    echo "✓ Value after delete: " . ($value ?? 'null') . "\n\n";
    
    echo "All operations completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} finally {
    $client->close();
}
```

---

## Task 9: Create README

**Files:**
- Create: `README.md`

**Implementation:**

```markdown
# TiKV PHP Client

PHP client for TiKV RawKV API using gRPC extension.

## Quick Start

```bash
# Start TiKV cluster and run example
docker-compose up --build php-client
```

## Architecture

- **gRPC Extension**: Official gRPC PHP extension
- **PD Discovery**: Connects to PD for cluster topology
- **Direct TiKV**: Talks directly to TiKV nodes

## Docker Services

- **pd**: Placement Driver (port 2379)
- **tikv1-3**: TiKV nodes (ports 20160-20162)
- **php-client**: PHP with gRPC extension

## Usage

```php
use TiKvPhp\RawKv\RawKvClient;

$client = RawKvClient::create(['127.0.0.1:2379']);
$client->put('key', 'value');
$value = $client->get('key');
$client->delete('key');
$client->close();
```

## Limitations (POC)

- Single TiKV node hardcoded
- Minimal protobuf implementation
- No batch operations
- No region routing
```

---

## Testing Instructions

```bash
# Start TiKV cluster
docker-compose up -d pd tikv1 tikv2 tikv3

# Wait for cluster to be ready
docker-compose logs -f pd

# Build and run PHP client
docker-compose up --build php-client
```

## Success Criteria

- [ ] `docker-compose up --build php-client` completes without errors
- [ ] Example script outputs "All operations completed successfully!"
- [ ] Put/Get/Delete operations work correctly
