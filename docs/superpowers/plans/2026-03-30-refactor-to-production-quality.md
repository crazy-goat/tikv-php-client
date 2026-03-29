# Refactor tikv-php to Production Quality Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transform the tikv-php client from prototype quality to production-grade code with proper type safety, exception hierarchy, interfaces for testability, and meaningful unit tests.

**Architecture:** Extract DTOs for all untyped arrays (RegionInfo, KeyValue), create interfaces for GrpcClient and PdClient to enable proper mocking, build a custom exception hierarchy, extract duplicated retry/cluster-ID logic, and break the 1000-line RawKvClient god class into focused components. Keep the public API surface identical so E2E tests pass without changes.

**Tech Stack:** PHP 8.2+, PHPUnit 11, gRPC extension, protobuf

---

## File Structure

### New files to create:
- `src/Client/Exception/TiKvException.php` — Base exception for all TiKV errors
- `src/Client/Exception/ClientClosedException.php` — Thrown when operating on closed client
- `src/Client/Exception/GrpcException.php` — gRPC transport errors
- `src/Client/Exception/RegionException.php` — Region-level errors (epoch mismatch, not found)
- `src/Client/Exception/StoreNotFoundException.php` — Store not found in PD
- `src/Client/Exception/InvalidArgumentException.php` — Domain-specific invalid arguments
- `src/Client/Grpc/GrpcClientInterface.php` — Interface for gRPC transport
- `src/Client/Connection/PdClientInterface.php` — Interface for PD operations
- `src/Client/RawKv/Dto/RegionInfo.php` — Typed DTO for region metadata
- `src/Client/RawKv/Dto/KeyValue.php` — Typed DTO for key-value scan results
- `src/Client/RawKv/RegionContext.php` — Builds protobuf Context from RegionInfo
- `tests/Unit/RawKv/Dto/RegionInfoTest.php` — Tests for RegionInfo DTO
- `tests/Unit/RawKv/Dto/KeyValueTest.php` — Tests for KeyValue DTO
- `tests/Unit/RawKv/RegionContextTest.php` — Tests for RegionContext
- `tests/Unit/Connection/PdClientTest.php` — Tests for PdClient with mocked gRPC

### Files to modify:
- `src/Client/Grpc/GrpcClient.php` — Implement GrpcClientInterface, use GrpcException
- `src/Client/Connection/PdClient.php` — Implement PdClientInterface, return RegionInfo DTOs, use exceptions, extract cluster ID retry
- `src/Client/RawKv/RawKvClient.php` — Use interfaces, DTOs, custom exceptions, DI for GrpcClient
- `tests/Unit/RawKv/RawKvClientTest.php` — Rewrite with proper mocks testing real logic
- `tests/Unit/Grpc/GrpcClientTest.php` — Update to use new exception types
- `tests/E2E/RawKvE2ETest.php` — Minimal changes (update exception class references if needed)

### Files unchanged:
- `src/Client/RawKv/CasResult.php` — Already well-structured readonly DTO
- `src/Client/RawKv/ChecksumResult.php` — Already well-structured readonly DTO

---

### Task 1: Exception Hierarchy

**Files:**
- Create: `src/Client/Exception/TiKvException.php`
- Create: `src/Client/Exception/ClientClosedException.php`
- Create: `src/Client/Exception/GrpcException.php`
- Create: `src/Client/Exception/RegionException.php`
- Create: `src/Client/Exception/StoreNotFoundException.php`
- Create: `src/Client/Exception/InvalidArgumentException.php`

- [ ] **Step 1: Create TiKvException base class**

```php
// src/Client/Exception/TiKvException.php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Exception;

class TiKvException extends \RuntimeException
{
}
```

- [ ] **Step 2: Create ClientClosedException**

```php
// src/Client/Exception/ClientClosedException.php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Exception;

final class ClientClosedException extends TiKvException
{
    public function __construct()
    {
        parent::__construct('Client is closed');
    }
}
```

- [ ] **Step 3: Create GrpcException**

```php
// src/Client/Exception/GrpcException.php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Exception;

final class GrpcException extends TiKvException
{
    public function __construct(
        string $details,
        public readonly int $grpcStatusCode,
    ) {
        parent::__construct("gRPC error: {$details}", $grpcStatusCode);
    }
}
```

- [ ] **Step 4: Create RegionException**

```php
// src/Client/Exception/RegionException.php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Exception;

final class RegionException extends TiKvException
{
    public function __construct(string $operation, string $error)
    {
        parent::__construct("{$operation} failed: {$error}");
    }
}
```

- [ ] **Step 5: Create StoreNotFoundException**

```php
// src/Client/Exception/StoreNotFoundException.php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Exception;

final class StoreNotFoundException extends TiKvException
{
    public function __construct(public readonly int $storeId)
    {
        parent::__construct("Store {$storeId} not found in PD");
    }
}
```

- [ ] **Step 6: Create InvalidArgumentException**

```php
// src/Client/Exception/InvalidArgumentException.php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Exception;

final class InvalidArgumentException extends \InvalidArgumentException
{
}
```

- [ ] **Step 7: Verify all files created**

Run: `php -l src/Client/Exception/TiKvException.php && php -l src/Client/Exception/ClientClosedException.php && php -l src/Client/Exception/GrpcException.php && php -l src/Client/Exception/RegionException.php && php -l src/Client/Exception/StoreNotFoundException.php && php -l src/Client/Exception/InvalidArgumentException.php`
Expected: No syntax errors

- [ ] **Step 8: Commit**

```bash
git add src/Client/Exception/
git commit -m "refactor: add custom exception hierarchy for TiKV client"
```

---

### Task 2: DTOs — RegionInfo and KeyValue

**Files:**
- Create: `src/Client/RawKv/Dto/RegionInfo.php`
- Create: `src/Client/RawKv/Dto/KeyValue.php`
- Create: `tests/Unit/RawKv/Dto/RegionInfoTest.php`
- Create: `tests/Unit/RawKv/Dto/KeyValueTest.php`

- [ ] **Step 1: Write RegionInfo DTO test**

```php
// tests/Unit/RawKv/Dto/RegionInfoTest.php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\RawKv\Dto;

use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;
use PHPUnit\Framework\TestCase;

class RegionInfoTest extends TestCase
{
    public function testConstructionAndProperties(): void
    {
        $region = new RegionInfo(
            regionId: 42,
            leaderPeerId: 7,
            leaderStoreId: 3,
            epochConfVer: 1,
            epochVersion: 10,
            startKey: 'aaa',
            endKey: 'zzz',
        );

        $this->assertSame(42, $region->regionId);
        $this->assertSame(7, $region->leaderPeerId);
        $this->assertSame(3, $region->leaderStoreId);
        $this->assertSame(1, $region->epochConfVer);
        $this->assertSame(10, $region->epochVersion);
        $this->assertSame('aaa', $region->startKey);
        $this->assertSame('zzz', $region->endKey);
    }

    public function testDefaultKeysAreEmptyStrings(): void
    {
        $region = new RegionInfo(
            regionId: 1,
            leaderPeerId: 1,
            leaderStoreId: 1,
            epochConfVer: 1,
            epochVersion: 1,
        );

        $this->assertSame('', $region->startKey);
        $this->assertSame('', $region->endKey);
    }

    public function testIsReadonly(): void
    {
        $ref = new \ReflectionClass(RegionInfo::class);
        $this->assertTrue($ref->isReadonly());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/RawKv/Dto/RegionInfoTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Create RegionInfo DTO**

```php
// src/Client/RawKv/Dto/RegionInfo.php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\RawKv\Dto;

final readonly class RegionInfo
{
    public function __construct(
        public int $regionId,
        public int $leaderPeerId,
        public int $leaderStoreId,
        public int $epochConfVer,
        public int $epochVersion,
        public string $startKey = '',
        public string $endKey = '',
    ) {
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/RawKv/Dto/RegionInfoTest.php`
Expected: OK (3 tests)

- [ ] **Step 5: Write KeyValue DTO test**

```php
// tests/Unit/RawKv/Dto/KeyValueTest.php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\RawKv\Dto;

use CrazyGoat\TiKV\Client\RawKv\Dto\KeyValue;
use PHPUnit\Framework\TestCase;

class KeyValueTest extends TestCase
{
    public function testConstructionWithValue(): void
    {
        $kv = new KeyValue(key: 'my-key', value: 'my-value');

        $this->assertSame('my-key', $kv->key);
        $this->assertSame('my-value', $kv->value);
    }

    public function testConstructionKeyOnly(): void
    {
        $kv = new KeyValue(key: 'my-key', value: null);

        $this->assertSame('my-key', $kv->key);
        $this->assertNull($kv->value);
    }

    public function testIsReadonly(): void
    {
        $ref = new \ReflectionClass(KeyValue::class);
        $this->assertTrue($ref->isReadonly());
    }
}
```

- [ ] **Step 6: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/RawKv/Dto/KeyValueTest.php`
Expected: FAIL — class not found

- [ ] **Step 7: Create KeyValue DTO**

```php
// src/Client/RawKv/Dto/KeyValue.php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\RawKv\Dto;

final readonly class KeyValue
{
    public function __construct(
        public string $key,
        public ?string $value,
    ) {
    }
}
```

- [ ] **Step 8: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/RawKv/Dto/KeyValueTest.php`
Expected: OK (3 tests)

- [ ] **Step 9: Commit**

```bash
git add src/Client/RawKv/Dto/ tests/Unit/RawKv/Dto/
git commit -m "refactor: add RegionInfo and KeyValue DTOs"
```

---

### Task 3: GrpcClientInterface and GrpcClient Refactor

**Files:**
- Create: `src/Client/Grpc/GrpcClientInterface.php`
- Modify: `src/Client/Grpc/GrpcClient.php`
- Modify: `tests/Unit/Grpc/GrpcClientTest.php`

- [ ] **Step 1: Create GrpcClientInterface**

```php
// src/Client/Grpc/GrpcClientInterface.php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Grpc;

use CrazyGoat\TiKV\Client\Exception\GrpcException;
use Google\Protobuf\Internal\Message;

interface GrpcClientInterface
{
    /**
     * Execute a unary gRPC call.
     *
     * @template T of Message
     *
     * @param string $address  Target host:port
     * @param string $service  Fully-qualified service name (e.g. "tikvpb.Tikv")
     * @param string $method   RPC method name (e.g. "RawGet")
     * @param Message $request Serializable protobuf request
     * @param class-string<T> $responseClass FQCN of the expected response message
     *
     * @return T Deserialized response
     *
     * @throws GrpcException On transport or protocol error
     */
    public function call(
        string $address,
        string $service,
        string $method,
        Message $request,
        string $responseClass,
    ): Message;

    /**
     * Close all open channels and release resources.
     */
    public function close(): void;
}
```

- [ ] **Step 2: Update GrpcClient to implement interface and use GrpcException**

Replace the entire `src/Client/Grpc/GrpcClient.php` with:

```php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Grpc;

use CrazyGoat\TiKV\Client\Exception\GrpcException;
use Google\Protobuf\Internal\Message;
use Grpc\Call;
use Grpc\Channel;
use Grpc\ChannelCredentials;
use Grpc\Timeval;

final class GrpcClient implements GrpcClientInterface
{
    /** @var array<string, Channel> */
    private array $channels = [];

    public function call(
        string $address,
        string $service,
        string $method,
        Message $request,
        string $responseClass,
    ): Message {
        $channel = $this->getChannel($address);

        $call = new Call(
            $channel,
            "/{$service}/{$method}",
            Timeval::infFuture(),
        );

        $call->startBatch([
            \Grpc\OP_SEND_INITIAL_METADATA => [],
            \Grpc\OP_SEND_MESSAGE => ['message' => $request->serializeToString()],
            \Grpc\OP_SEND_CLOSE_FROM_CLIENT => true,
        ]);

        $event = $call->startBatch([
            \Grpc\OP_RECV_INITIAL_METADATA => true,
            \Grpc\OP_RECV_MESSAGE => true,
            \Grpc\OP_RECV_STATUS_ON_CLIENT => true,
        ]);

        $status = $this->extractStatus($event);

        if ($status['code'] !== \Grpc\STATUS_OK) {
            throw new GrpcException(
                details: $status['details'],
                grpcStatusCode: $status['code'],
            );
        }

        return $this->deserializeResponse($event, $responseClass);
    }

    public function close(): void
    {
        foreach ($this->channels as $channel) {
            $channel->close();
        }
        $this->channels = [];
    }

    private function getChannel(string $address): Channel
    {
        if (!isset($this->channels[$address])) {
            $this->channels[$address] = new Channel($address, [
                'credentials' => ChannelCredentials::createInsecure(),
            ]);
        }

        return $this->channels[$address];
    }

    /**
     * @return array{code: int, details: string}
     */
    private function extractStatus(mixed $event): array
    {
        if (is_object($event)) {
            $event = (array) $event;
        }

        $status = $event['status'] ?? null;
        if (is_object($status)) {
            $status = (array) $status;
        }

        return [
            'code' => (int) ($status['code'] ?? 0),
            'details' => (string) ($status['details'] ?? ''),
        ];
    }

    /**
     * @template T of Message
     * @param class-string<T> $responseClass
     * @return T
     */
    private function deserializeResponse(mixed $event, string $responseClass): Message
    {
        if (is_object($event)) {
            $event = (array) $event;
        }

        $message = $event['message'] ?? null;

        /** @var T $response */
        $response = new $responseClass();

        if ($message !== null && $message !== '') {
            $response->mergeFromString($message);
        }

        return $response;
    }
}
```

- [ ] **Step 3: Update GrpcClientTest to use GrpcException**

Replace the entire `tests/Unit/Grpc/GrpcClientTest.php` with:

```php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Grpc;

use CrazyGoat\TiKV\Client\Exception\GrpcException;
use CrazyGoat\TiKV\Client\Grpc\GrpcClient;
use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use PHPUnit\Framework\TestCase;

class GrpcClientTest extends TestCase
{
    private ?GrpcClient $client = null;

    protected function setUp(): void
    {
        if (!extension_loaded('grpc')) {
            $this->markTestSkipped('gRPC extension not available');
        }
        $this->client = new GrpcClient();
    }

    protected function tearDown(): void
    {
        $this->client?->close();
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(GrpcClientInterface::class, $this->client);
    }

    public function testCloseIsIdempotent(): void
    {
        $this->client->close();
        $this->client->close();
        $this->expectNotToPerformAssertions();
    }

    public function testCallWithInvalidAddressThrowsGrpcException(): void
    {
        $this->expectException(GrpcException::class);

        $request = new \CrazyGoat\Proto\Kvrpcpb\RawGetRequest();
        $request->setKey('test');

        $this->client->call(
            'invalid-address:99999',
            'tikvpb.Tikv',
            'RawGet',
            $request,
            \CrazyGoat\Proto\Kvrpcpb\RawGetResponse::class,
        );
    }
}
```

- [ ] **Step 4: Run tests**

Run: `vendor/bin/phpunit tests/Unit/Grpc/GrpcClientTest.php`
Expected: OK (3 tests) — or skipped if no gRPC extension

- [ ] **Step 5: Commit**

```bash
git add src/Client/Grpc/ tests/Unit/Grpc/
git commit -m "refactor: extract GrpcClientInterface and use GrpcException"
```

---

### Task 4: PdClientInterface and PdClient Refactor

**Files:**
- Create: `src/Client/Connection/PdClientInterface.php`
- Modify: `src/Client/Connection/PdClient.php`
- Create: `tests/Unit/Connection/PdClientTest.php`

- [ ] **Step 1: Create PdClientInterface**

```php
// src/Client/Connection/PdClientInterface.php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Connection;

use CrazyGoat\TiKV\Client\Exception\GrpcException;
use CrazyGoat\TiKV\Client\Exception\TiKvException;
use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;
use CrazyGoat\Proto\Metapb\Store;

interface PdClientInterface
{
    /**
     * Get the region that contains the given key.
     *
     * @throws GrpcException On transport error
     * @throws TiKvException On PD error
     */
    public function getRegion(string $key): RegionInfo;

    /**
     * Get store metadata by ID.
     *
     * @throws GrpcException On transport error
     * @throws TiKvException On PD error
     */
    public function getStore(int $storeId): ?Store;

    /**
     * Scan all regions covering the key range [startKey, endKey).
     *
     * @return RegionInfo[]
     *
     * @throws GrpcException On transport error
     * @throws TiKvException On PD error
     */
    public function scanRegions(string $startKey, string $endKey, int $limit = 0): array;

    /**
     * Close the PD connection and release resources.
     */
    public function close(): void;
}
```

- [ ] **Step 2: Refactor PdClient to implement interface, return RegionInfo DTOs, extract cluster ID retry**

Replace the entire `src/Client/Connection/PdClient.php` with:

```php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Connection;

use CrazyGoat\TiKV\Client\Exception\GrpcException;
use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;
use CrazyGoat\Proto\Metapb\Store;
use CrazyGoat\Proto\Pdpb\GetRegionRequest;
use CrazyGoat\Proto\Pdpb\GetRegionResponse;
use CrazyGoat\Proto\Pdpb\GetStoreRequest;
use CrazyGoat\Proto\Pdpb\GetStoreResponse;
use CrazyGoat\Proto\Pdpb\RequestHeader;
use CrazyGoat\Proto\Pdpb\ScanRegionsRequest;
use CrazyGoat\Proto\Pdpb\ScanRegionsResponse;
use Google\Protobuf\Internal\Message;

final class PdClient implements PdClientInterface
{
    private ?int $clusterId = null;

    /** @var array<int, Store> */
    private array $storeCache = [];

    public function __construct(
        private readonly GrpcClientInterface $grpc,
        private readonly string $pdAddress,
    ) {
    }

    public function getRegion(string $key): RegionInfo
    {
        $request = new GetRegionRequest();
        $request->setHeader($this->createHeader());
        $request->setRegionKey($key);

        /** @var GetRegionResponse $response */
        $response = $this->callWithClusterIdRetry(
            'GetRegion',
            $request,
            GetRegionResponse::class,
        );

        $region = $response->getRegion();
        $leader = $response->getLeader();
        $regionEpoch = $region?->getRegionEpoch();

        return new RegionInfo(
            regionId: $region ? $region->getId() : 0,
            leaderPeerId: $leader ? $leader->getId() : 0,
            leaderStoreId: $leader ? $leader->getStoreId() : 1,
            epochConfVer: $regionEpoch ? $regionEpoch->getConfVer() : 0,
            epochVersion: $regionEpoch ? $regionEpoch->getVersion() : 0,
        );
    }

    public function getStore(int $storeId): ?Store
    {
        if (isset($this->storeCache[$storeId])) {
            return $this->storeCache[$storeId];
        }

        $request = new GetStoreRequest();
        $request->setHeader($this->createHeader());
        $request->setStoreId($storeId);

        /** @var GetStoreResponse $response */
        $response = $this->callWithClusterIdRetry(
            'GetStore',
            $request,
            GetStoreResponse::class,
        );

        $store = $response->getStore();
        if ($store !== null) {
            $this->storeCache[$storeId] = $store;
        }

        return $store;
    }

    /**
     * @return RegionInfo[]
     */
    public function scanRegions(string $startKey, string $endKey, int $limit = 0): array
    {
        $request = new ScanRegionsRequest();
        $request->setHeader($this->createHeader());
        $request->setStartKey($startKey);
        $request->setEndKey($endKey);
        $request->setLimit($limit);

        /** @var ScanRegionsResponse $response */
        $response = $this->callWithClusterIdRetry(
            'ScanRegions',
            $request,
            ScanRegionsResponse::class,
        );

        $regions = [];
        $regionMetas = $response->getRegionMetas();
        $leaders = $response->getLeaders();

        foreach ($regionMetas as $index => $region) {
            $leader = $leaders[$index] ?? null;
            $regionEpoch = $region?->getRegionEpoch();

            $regions[] = new RegionInfo(
                regionId: $region ? $region->getId() : 0,
                leaderPeerId: $leader ? $leader->getId() : 0,
                leaderStoreId: $leader ? $leader->getStoreId() : 1,
                epochConfVer: $regionEpoch ? $regionEpoch->getConfVer() : 0,
                epochVersion: $regionEpoch ? $regionEpoch->getVersion() : 0,
                startKey: $region ? $region->getStartKey() : '',
                endKey: $region ? $region->getEndKey() : '',
            );
        }

        return $regions;
    }

    public function close(): void
    {
        $this->grpc->close();
    }

    private function createHeader(): RequestHeader
    {
        $header = new RequestHeader();
        $header->setClusterId($this->clusterId ?? 0);
        return $header;
    }

    /**
     * Execute a PD gRPC call with automatic cluster ID mismatch retry.
     *
     * On first connect the client sends cluster_id=0. PD may reject with
     * "mismatch cluster id, need X but got 0". We extract X, cache it,
     * and retry exactly once.
     *
     * @template T of Message
     * @param class-string<T> $responseClass
     * @return T
     */
    private function callWithClusterIdRetry(
        string $method,
        Message $request,
        string $responseClass,
    ): Message {
        try {
            $response = $this->grpc->call(
                $this->pdAddress,
                'pdpb.PD',
                $method,
                $request,
                $responseClass,
            );

            $this->learnClusterId($response);

            return $response;
        } catch (GrpcException $e) {
            $extractedId = $this->extractClusterIdFromError($e->getMessage());
            if ($extractedId !== null) {
                $this->clusterId = $extractedId;
                $request->setHeader($this->createHeader());

                $response = $this->grpc->call(
                    $this->pdAddress,
                    'pdpb.PD',
                    $method,
                    $request,
                    $responseClass,
                );

                $this->learnClusterId($response);

                return $response;
            }

            throw $e;
        }
    }

    /**
     * Learn cluster ID from a successful PD response header.
     */
    private function learnClusterId(Message $response): void
    {
        if ($this->clusterId !== null) {
            return;
        }

        if (method_exists($response, 'getHeader')) {
            $header = $response->getHeader();
            if ($header !== null && method_exists($header, 'getClusterId')) {
                $this->clusterId = $header->getClusterId();
            }
        }
    }

    private function extractClusterIdFromError(string $message): ?int
    {
        if (str_contains($message, 'mismatch cluster id')) {
            if (preg_match('/need (\d+) but got/', $message, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }
}
```

- [ ] **Step 3: Write PdClient unit tests**

```php
// tests/Unit/Connection/PdClientTest.php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Connection;

use CrazyGoat\TiKV\Client\Connection\PdClient;
use CrazyGoat\TiKV\Client\Connection\PdClientInterface;
use CrazyGoat\TiKV\Client\Exception\GrpcException;
use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;
use CrazyGoat\Proto\Metapb\Peer;
use CrazyGoat\Proto\Metapb\Region;
use CrazyGoat\Proto\Metapb\RegionEpoch;
use CrazyGoat\Proto\Pdpb\GetRegionResponse;
use CrazyGoat\Proto\Pdpb\ResponseHeader;
use Google\Protobuf\Internal\Message;
use PHPUnit\Framework\TestCase;

class PdClientTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $grpc = $this->createMock(GrpcClientInterface::class);
        $client = new PdClient($grpc, 'pd:2379');

        $this->assertInstanceOf(PdClientInterface::class, $client);
    }

    public function testGetRegionReturnsRegionInfo(): void
    {
        $epoch = new RegionEpoch();
        $epoch->setConfVer(1);
        $epoch->setVersion(10);

        $region = new Region();
        $region->setId(42);
        $region->setRegionEpoch($epoch);

        $leader = new Peer();
        $leader->setId(7);
        $leader->setStoreId(3);

        $header = new ResponseHeader();
        $header->setClusterId(100);

        $response = new GetRegionResponse();
        $response->setHeader($header);
        $response->setRegion($region);
        $response->setLeader($leader);

        $grpc = $this->createMock(GrpcClientInterface::class);
        $grpc->expects($this->once())
            ->method('call')
            ->willReturn($response);

        $client = new PdClient($grpc, 'pd:2379');
        $result = $client->getRegion('my-key');

        $this->assertInstanceOf(RegionInfo::class, $result);
        $this->assertSame(42, $result->regionId);
        $this->assertSame(7, $result->leaderPeerId);
        $this->assertSame(3, $result->leaderStoreId);
        $this->assertSame(1, $result->epochConfVer);
        $this->assertSame(10, $result->epochVersion);
    }

    public function testClusterIdMismatchRetries(): void
    {
        $header = new ResponseHeader();
        $header->setClusterId(999);

        $region = new Region();
        $region->setId(1);
        $epoch = new RegionEpoch();
        $epoch->setConfVer(1);
        $epoch->setVersion(1);
        $region->setRegionEpoch($epoch);

        $leader = new Peer();
        $leader->setId(1);
        $leader->setStoreId(1);

        $response = new GetRegionResponse();
        $response->setHeader($header);
        $response->setRegion($region);
        $response->setLeader($leader);

        $grpc = $this->createMock(GrpcClientInterface::class);
        $grpc->expects($this->exactly(2))
            ->method('call')
            ->willReturnCallback(function () use ($response): Message {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    throw new GrpcException('mismatch cluster id, need 999 but got 0', 2);
                }
                return $response;
            });

        $client = new PdClient($grpc, 'pd:2379');
        $result = $client->getRegion('key');

        $this->assertSame(1, $result->regionId);
    }

    public function testCloseClosesGrpc(): void
    {
        $grpc = $this->createMock(GrpcClientInterface::class);
        $grpc->expects($this->once())->method('close');

        $client = new PdClient($grpc, 'pd:2379');
        $client->close();
    }
}
```

- [ ] **Step 4: Run tests**

Run: `vendor/bin/phpunit tests/Unit/Connection/PdClientTest.php`
Expected: OK (4 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Client/Connection/ tests/Unit/Connection/
git commit -m "refactor: extract PdClientInterface, return RegionInfo DTOs, deduplicate cluster ID retry"
```

---

### Task 5: RegionContext Helper

**Files:**
- Create: `src/Client/RawKv/RegionContext.php`
- Create: `tests/Unit/RawKv/RegionContextTest.php`

- [ ] **Step 1: Write RegionContext test**

```php
// tests/Unit/RawKv/RegionContextTest.php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\RawKv;

use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;
use CrazyGoat\TiKV\Client\RawKv\RegionContext;
use CrazyGoat\Proto\Kvrpcpb\Context;
use PHPUnit\Framework\TestCase;

class RegionContextTest extends TestCase
{
    public function testCreatesContextFromRegionInfo(): void
    {
        $region = new RegionInfo(
            regionId: 42,
            leaderPeerId: 7,
            leaderStoreId: 3,
            epochConfVer: 1,
            epochVersion: 10,
        );

        $ctx = RegionContext::fromRegionInfo($region);

        $this->assertInstanceOf(Context::class, $ctx);
        $this->assertSame(42, $ctx->getRegionId());
        $this->assertSame(1, $ctx->getRegionEpoch()->getConfVer());
        $this->assertSame(10, $ctx->getRegionEpoch()->getVersion());
        $this->assertSame(7, $ctx->getPeer()->getId());
        $this->assertSame(3, $ctx->getPeer()->getStoreId());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/Unit/RawKv/RegionContextTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Create RegionContext**

```php
// src/Client/RawKv/RegionContext.php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\RawKv;

use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;
use CrazyGoat\Proto\Kvrpcpb\Context;
use CrazyGoat\Proto\Metapb\Peer;
use CrazyGoat\Proto\Metapb\RegionEpoch;

final class RegionContext
{
    /**
     * Build a protobuf Context from a RegionInfo DTO.
     */
    public static function fromRegionInfo(RegionInfo $region): Context
    {
        $epoch = new RegionEpoch();
        $epoch->setConfVer($region->epochConfVer);
        $epoch->setVersion($region->epochVersion);

        $peer = new Peer();
        $peer->setId($region->leaderPeerId);
        $peer->setStoreId($region->leaderStoreId);

        $ctx = new Context();
        $ctx->setRegionId($region->regionId);
        $ctx->setRegionEpoch($epoch);
        $ctx->setPeer($peer);

        return $ctx;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/Unit/RawKv/RegionContextTest.php`
Expected: OK (1 test)

- [ ] **Step 5: Commit**

```bash
git add src/Client/RawKv/RegionContext.php tests/Unit/RawKv/RegionContextTest.php
git commit -m "refactor: extract RegionContext helper for building protobuf Context"
```

---

### Task 6: Refactor RawKvClient — Use Interfaces, DTOs, Custom Exceptions

This is the main refactoring task. The public API stays identical so E2E tests continue to pass.

**Files:**
- Modify: `src/Client/RawKv/RawKvClient.php`

- [ ] **Step 1: Rewrite RawKvClient**

Replace the entire `src/Client/RawKv/RawKvClient.php` with the refactored version that:
- Accepts `PdClientInterface` and `GrpcClientInterface` via constructor DI
- Uses `RegionInfo` DTO instead of untyped arrays
- Uses `RegionContext::fromRegionInfo()` instead of inline context building
- Uses `KeyValue` DTO for scan results
- Uses custom exceptions (`ClientClosedException`, `StoreNotFoundException`, `RegionException`, `InvalidArgumentException`)
- Uses `str_contains()` instead of `strpos()`
- Keeps the `create()` factory method for backward compatibility

```php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\RawKv;

use CrazyGoat\TiKV\Client\Connection\PdClient;
use CrazyGoat\TiKV\Client\Connection\PdClientInterface;
use CrazyGoat\TiKV\Client\Exception\ClientClosedException;
use CrazyGoat\TiKV\Client\Exception\InvalidArgumentException;
use CrazyGoat\TiKV\Client\Exception\RegionException;
use CrazyGoat\TiKV\Client\Exception\StoreNotFoundException;
use CrazyGoat\TiKV\Client\Exception\TiKvException;
use CrazyGoat\TiKV\Client\Grpc\GrpcClient;
use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use CrazyGoat\TiKV\Client\RawKv\Dto\KeyValue;
use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;
use CrazyGoat\Proto\Kvrpcpb\ChecksumAlgorithm;
use CrazyGoat\Proto\Kvrpcpb\KeyRange;
use CrazyGoat\Proto\Kvrpcpb\KvPair;
use CrazyGoat\Proto\Kvrpcpb\RawBatchDeleteRequest;
use CrazyGoat\Proto\Kvrpcpb\RawBatchDeleteResponse;
use CrazyGoat\Proto\Kvrpcpb\RawBatchGetRequest;
use CrazyGoat\Proto\Kvrpcpb\RawBatchGetResponse;
use CrazyGoat\Proto\Kvrpcpb\RawBatchPutRequest;
use CrazyGoat\Proto\Kvrpcpb\RawBatchPutResponse;
use CrazyGoat\Proto\Kvrpcpb\RawCASRequest;
use CrazyGoat\Proto\Kvrpcpb\RawCASResponse;
use CrazyGoat\Proto\Kvrpcpb\RawChecksumRequest;
use CrazyGoat\Proto\Kvrpcpb\RawChecksumResponse;
use CrazyGoat\Proto\Kvrpcpb\RawDeleteRangeRequest;
use CrazyGoat\Proto\Kvrpcpb\RawDeleteRangeResponse;
use CrazyGoat\Proto\Kvrpcpb\RawDeleteRequest;
use CrazyGoat\Proto\Kvrpcpb\RawDeleteResponse;
use CrazyGoat\Proto\Kvrpcpb\RawGetKeyTTLRequest;
use CrazyGoat\Proto\Kvrpcpb\RawGetKeyTTLResponse;
use CrazyGoat\Proto\Kvrpcpb\RawGetRequest;
use CrazyGoat\Proto\Kvrpcpb\RawGetResponse;
use CrazyGoat\Proto\Kvrpcpb\RawPutRequest;
use CrazyGoat\Proto\Kvrpcpb\RawPutResponse;
use CrazyGoat\Proto\Kvrpcpb\RawScanRequest;
use CrazyGoat\Proto\Kvrpcpb\RawScanResponse;

final class RawKvClient
{
    private bool $closed = false;

    /** @var array<string, RegionInfo> */
    private array $regionCache = [];

    /**
     * Create a client connected to a PD cluster.
     *
     * @param string[] $pdEndpoints PD addresses (currently only the first is used)
     */
    public static function create(array $pdEndpoints): self
    {
        $grpc = new GrpcClient();
        $pdClient = new PdClient($grpc, $pdEndpoints[0]);

        return new self($pdClient, new GrpcClient());
    }

    public function __construct(
        private readonly PdClientInterface $pdClient,
        private readonly GrpcClientInterface $grpc,
        private readonly int $maxRetries = 3,
    ) {
    }

    // ========================================================================
    // Single-key operations
    // ========================================================================

    public function get(string $key): ?string
    {
        $this->ensureOpen();

        return $this->executeWithRetry($key, function () use ($key): ?string {
            $region = $this->getRegionInfo($key);
            $address = $this->resolveStoreAddress($region->leaderStoreId);

            $request = new RawGetRequest();
            $request->setContext(RegionContext::fromRegionInfo($region));
            $request->setKey($key);

            /** @var RawGetResponse $response */
            $response = $this->grpc->call($address, 'tikvpb.Tikv', 'RawGet', $request, RawGetResponse::class);

            $value = $response->getValue();
            return $value !== '' ? $value : null;
        });
    }

    /**
     * @param int $ttl Time-to-live in seconds (0 = no expiration)
     */
    public function put(string $key, string $value, int $ttl = 0): void
    {
        $this->ensureOpen();

        $this->executeWithRetry($key, function () use ($key, $value, $ttl): null {
            $region = $this->getRegionInfo($key);
            $address = $this->resolveStoreAddress($region->leaderStoreId);

            $request = new RawPutRequest();
            $request->setContext(RegionContext::fromRegionInfo($region));
            $request->setKey($key);
            $request->setValue($value);
            if ($ttl > 0) {
                $request->setTtl($ttl);
            }

            $this->grpc->call($address, 'tikvpb.Tikv', 'RawPut', $request, RawPutResponse::class);
            return null;
        });
    }

    public function delete(string $key): void
    {
        $this->ensureOpen();

        $this->executeWithRetry($key, function () use ($key): null {
            $region = $this->getRegionInfo($key);
            $address = $this->resolveStoreAddress($region->leaderStoreId);

            $request = new RawDeleteRequest();
            $request->setContext(RegionContext::fromRegionInfo($region));
            $request->setKey($key);

            $this->grpc->call($address, 'tikvpb.Tikv', 'RawDelete', $request, RawDeleteResponse::class);
            return null;
        });
    }

    /**
     * Get the remaining TTL (time-to-live) for a key.
     *
     * @return int|null Remaining TTL in seconds, or null if key not found or has no TTL
     */
    public function getKeyTTL(string $key): ?int
    {
        $this->ensureOpen();

        return $this->executeWithRetry($key, function () use ($key): ?int {
            $region = $this->getRegionInfo($key);
            $address = $this->resolveStoreAddress($region->leaderStoreId);

            $request = new RawGetKeyTTLRequest();
            $request->setContext(RegionContext::fromRegionInfo($region));
            $request->setKey($key);

            /** @var RawGetKeyTTLResponse $response */
            $response = $this->grpc->call($address, 'tikvpb.Tikv', 'RawGetKeyTTL', $request, RawGetKeyTTLResponse::class);

            $error = $response->getError();
            if ($error !== '') {
                throw new RegionException('RawGetKeyTTL', $error);
            }

            if ($response->getNotFound()) {
                return null;
            }

            $ttl = (int) $response->getTtl();
            return $ttl > 0 ? $ttl : null;
        });
    }

    /**
     * Atomic Compare-And-Swap operation.
     *
     * @param string|null $expectedValue Expected current value, or null if the key should not exist
     * @param int $ttl Time-to-live in seconds for the new value (0 = no expiration)
     */
    public function compareAndSwap(string $key, ?string $expectedValue, string $newValue, int $ttl = 0): CasResult
    {
        $this->ensureOpen();

        return $this->executeWithRetry($key, function () use ($key, $expectedValue, $newValue, $ttl): CasResult {
            $region = $this->getRegionInfo($key);
            $address = $this->resolveStoreAddress($region->leaderStoreId);

            $request = new RawCASRequest();
            $request->setContext(RegionContext::fromRegionInfo($region));
            $request->setKey($key);
            $request->setValue($newValue);

            if ($expectedValue === null) {
                $request->setPreviousNotExist(true);
            } else {
                $request->setPreviousNotExist(false);
                $request->setPreviousValue($expectedValue);
            }

            if ($ttl > 0) {
                $request->setTtl($ttl);
            }

            /** @var RawCASResponse $response */
            $response = $this->grpc->call($address, 'tikvpb.Tikv', 'RawCompareAndSwap', $request, RawCASResponse::class);

            $error = $response->getError();
            if ($error !== '') {
                throw new RegionException('RawCompareAndSwap', $error);
            }

            return new CasResult(
                swapped: $response->getSucceed(),
                previousValue: $response->getPreviousNotExist() ? null : $response->getPreviousValue(),
            );
        });
    }

    /**
     * Atomically put a value only if the key does not already exist.
     *
     * @return string|null null if inserted successfully, or the existing value
     */
    public function putIfAbsent(string $key, string $value, int $ttl = 0): ?string
    {
        $result = $this->compareAndSwap($key, null, $value, $ttl);

        return $result->swapped ? null : $result->previousValue;
    }

    // ========================================================================
    // Batch operations
    // ========================================================================

    /**
     * @param string[] $keys
     * @return array<string, ?string> Values indexed by key (null for missing keys)
     */
    public function batchGet(array $keys): array
    {
        $this->ensureOpen();

        if ($keys === []) {
            return [];
        }

        $keysByRegion = $this->groupKeysByRegion($keys);

        $results = [];
        foreach ($keysByRegion as $regionData) {
            $regionResults = $this->executeBatchGetForRegion($regionData['region'], $regionData['keys']);
            $results = array_merge($results, $regionResults);
        }

        $ordered = [];
        foreach ($keys as $key) {
            $ordered[$key] = $results[$key] ?? null;
        }

        return $ordered;
    }

    /**
     * @param array<string, string> $keyValuePairs
     * @param int $ttl Time-to-live in seconds applied to all keys (0 = no expiration)
     */
    public function batchPut(array $keyValuePairs, int $ttl = 0): void
    {
        $this->ensureOpen();

        if ($keyValuePairs === []) {
            return;
        }

        $pairsByRegion = [];
        foreach ($keyValuePairs as $key => $value) {
            $region = $this->getRegionInfo($key);
            $regionId = $region->regionId;
            if (!isset($pairsByRegion[$regionId])) {
                $pairsByRegion[$regionId] = ['region' => $region, 'pairs' => []];
            }
            $pair = new KvPair();
            $pair->setKey($key);
            $pair->setValue($value);
            $pairsByRegion[$regionId]['pairs'][] = $pair;
        }

        foreach ($pairsByRegion as $regionData) {
            $this->executeBatchPutForRegion($regionData['region'], $regionData['pairs'], $ttl);
        }
    }

    /**
     * @param string[] $keys
     */
    public function batchDelete(array $keys): void
    {
        $this->ensureOpen();

        if ($keys === []) {
            return;
        }

        $keysByRegion = $this->groupKeysByRegion($keys);

        foreach ($keysByRegion as $regionData) {
            $this->executeBatchDeleteForRegion($regionData['region'], $regionData['keys']);
        }
    }

    // ========================================================================
    // Scan operations
    // ========================================================================

    /**
     * Scan a range of keys [startKey, endKey).
     *
     * @param int $limit Maximum results (0 = unlimited)
     * @return array<array{key: string, value: ?string}>
     */
    public function scan(string $startKey, string $endKey, int $limit = 0, bool $keyOnly = false): array
    {
        $this->ensureOpen();

        $regions = $this->pdClient->scanRegions($startKey, $endKey, 0);
        $results = [];
        $remaining = $limit;

        foreach ($regions as $region) {
            $scanStart = $startKey > $region->startKey ? $startKey : $region->startKey;
            $scanEnd = $endKey === '' ? $region->endKey : ($region->endKey !== '' && $endKey > $region->endKey ? $region->endKey : $endKey);

            if ($scanStart >= $scanEnd && $scanEnd !== '') {
                continue;
            }

            $regionLimit = $remaining === 0 ? PHP_INT_MAX : $remaining;
            $regionResults = $this->executeScanForRegion($region, $scanStart, $scanEnd, $regionLimit, $keyOnly, false);
            $results = array_merge($results, $regionResults);

            if ($remaining > 0) {
                $remaining -= count($regionResults);
                if ($remaining <= 0) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Scan keys with a given prefix.
     *
     * @return array<array{key: string, value: ?string}>
     */
    public function scanPrefix(string $prefix, int $limit = 0, bool $keyOnly = false): array
    {
        $this->ensureOpen();

        return $this->scan($prefix, $this->calculatePrefixEndKey($prefix), $limit, $keyOnly);
    }

    /**
     * Reverse scan a range of keys in descending order.
     *
     * Per kvrpcpb.proto: startKey = upper bound (exclusive), endKey = lower bound (inclusive).
     *
     * @return array<array{key: string, value: ?string}>
     */
    public function reverseScan(string $startKey, string $endKey, int $limit = 0, bool $keyOnly = false): array
    {
        $this->ensureOpen();

        $regions = $this->pdClient->scanRegions($endKey, $startKey, 0);
        $regions = array_reverse($regions);

        $results = [];
        $remaining = $limit;

        foreach ($regions as $region) {
            $scanStartKey = ($region->endKey === '' || $startKey < $region->endKey) ? $startKey : $region->endKey;
            $scanEndKey = ($endKey > $region->startKey) ? $endKey : $region->startKey;

            if ($scanEndKey >= $scanStartKey && $scanEndKey !== '') {
                continue;
            }

            $regionLimit = $remaining === 0 ? PHP_INT_MAX : $remaining;
            $regionResults = $this->executeScanForRegion($region, $scanStartKey, $scanEndKey, $regionLimit, $keyOnly, true);
            $results = array_merge($results, $regionResults);

            if ($remaining > 0) {
                $remaining -= count($regionResults);
                if ($remaining <= 0) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Scan multiple non-contiguous key ranges.
     *
     * @param array<array{0: string, 1: string}> $ranges
     * @return array<array<array{key: string, value: ?string}>>
     */
    public function batchScan(array $ranges, int $eachLimit, bool $keyOnly = false): array
    {
        $this->ensureOpen();

        if ($ranges === []) {
            return [];
        }

        if ($eachLimit <= 0) {
            throw new InvalidArgumentException('eachLimit must be greater than 0');
        }

        $results = [];
        foreach ($ranges as $range) {
            if (!is_array($range) || count($range) !== 2) {
                throw new InvalidArgumentException('Each range must be an array of [startKey, endKey]');
            }
            [$startKey, $endKey] = $range;
            $results[] = $this->scan($startKey, $endKey, $eachLimit, $keyOnly);
        }

        return $results;
    }

    // ========================================================================
    // Range operations
    // ========================================================================

    /**
     * Delete all keys in range [startKey, endKey).
     */
    public function deleteRange(string $startKey, string $endKey): void
    {
        $this->ensureOpen();

        if ($startKey === $endKey) {
            return;
        }

        $regions = $this->pdClient->scanRegions($startKey, $endKey, 0);

        foreach ($regions as $region) {
            $rangeStart = $startKey > $region->startKey ? $startKey : $region->startKey;
            $rangeEnd = ($endKey === '' || ($region->endKey !== '' && $endKey > $region->endKey)) ? $region->endKey : $endKey;

            if ($rangeStart >= $rangeEnd && $rangeEnd !== '') {
                continue;
            }

            $this->executeDeleteRangeForRegion($region, $rangeStart, $rangeEnd);
        }
    }

    /**
     * Delete all keys with the given prefix.
     */
    public function deletePrefix(string $prefix): void
    {
        $this->ensureOpen();

        if ($prefix === '') {
            throw new InvalidArgumentException('Prefix must not be empty -- refusing to delete all keys');
        }

        $this->deleteRange($prefix, $this->calculatePrefixEndKey($prefix));
    }

    /**
     * Compute a CRC64-XOR checksum over all key-value pairs in [startKey, endKey).
     */
    public function checksum(string $startKey, string $endKey): ChecksumResult
    {
        $this->ensureOpen();

        $regions = $this->pdClient->scanRegions($startKey, $endKey, 0);

        $mergedChecksum = 0;
        $mergedTotalKvs = 0;
        $mergedTotalBytes = 0;

        foreach ($regions as $region) {
            $rangeStart = $startKey > $region->startKey ? $startKey : $region->startKey;
            $rangeEnd = ($endKey === '' || ($region->endKey !== '' && $endKey > $region->endKey)) ? $region->endKey : $endKey;

            if ($rangeStart >= $rangeEnd && $rangeEnd !== '') {
                continue;
            }

            $result = $this->executeChecksumForRegion($region, $rangeStart, $rangeEnd);
            $mergedChecksum ^= $result->checksum;
            $mergedTotalKvs += $result->totalKvs;
            $mergedTotalBytes += $result->totalBytes;
        }

        return new ChecksumResult(
            checksum: $mergedChecksum,
            totalKvs: $mergedTotalKvs,
            totalBytes: $mergedTotalBytes,
        );
    }

    // ========================================================================
    // Lifecycle
    // ========================================================================

    public function close(): void
    {
        if (!$this->closed) {
            $this->grpc->close();
            $this->pdClient->close();
            $this->closed = true;
        }
    }

    // ========================================================================
    // Private helpers
    // ========================================================================

    private function ensureOpen(): void
    {
        if ($this->closed) {
            throw new ClientClosedException();
        }
    }

    private function getRegionInfo(string $key): RegionInfo
    {
        if (!isset($this->regionCache[$key])) {
            $this->regionCache[$key] = $this->pdClient->getRegion($key);
        }

        return $this->regionCache[$key];
    }

    private function clearRegionCache(string $key): void
    {
        unset($this->regionCache[$key]);
    }

    private function resolveStoreAddress(int $storeId): string
    {
        $store = $this->pdClient->getStore($storeId);
        if ($store === null) {
            throw new StoreNotFoundException($storeId);
        }

        $address = $store->getAddress();
        if ($address === '' || $address === null) {
            throw new StoreNotFoundException($storeId);
        }

        return $address;
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    private function executeWithRetry(string $key, callable $operation): mixed
    {
        $lastException = null;

        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (TiKvException $e) {
                $lastException = $e;

                if (str_contains($e->getMessage(), 'EpochNotMatch')) {
                    $this->clearRegionCache($key);
                    continue;
                }

                throw $e;
            }
        }

        throw $lastException ?? new TiKvException('Max retries exceeded');
    }

    /**
     * @param string[] $keys
     * @return array<int, array{region: RegionInfo, keys: string[]}>
     */
    private function groupKeysByRegion(array $keys): array
    {
        $grouped = [];
        foreach ($keys as $key) {
            $region = $this->getRegionInfo($key);
            $regionId = $region->regionId;
            if (!isset($grouped[$regionId])) {
                $grouped[$regionId] = ['region' => $region, 'keys' => []];
            }
            $grouped[$regionId]['keys'][] = $key;
        }

        return $grouped;
    }

    private function calculatePrefixEndKey(string $prefix): string
    {
        if ($prefix === '') {
            return '';
        }

        $lastByte = ord($prefix[strlen($prefix) - 1]);

        if ($lastByte === 255) {
            $trimmed = rtrim($prefix, "\xff");
            if ($trimmed === '') {
                return '';
            }
            $lastByte = ord($trimmed[strlen($trimmed) - 1]);
            return substr($trimmed, 0, -1) . chr($lastByte + 1);
        }

        return substr($prefix, 0, -1) . chr($lastByte + 1);
    }

    // ========================================================================
    // Region-level RPC executors
    // ========================================================================

    /**
     * @return array<string, ?string>
     */
    private function executeBatchGetForRegion(RegionInfo $region, array $keys): array
    {
        return $this->executeWithRetry($keys[0], function () use ($region, $keys): array {
            $address = $this->resolveStoreAddress($region->leaderStoreId);

            $request = new RawBatchGetRequest();
            $request->setContext(RegionContext::fromRegionInfo($region));
            $request->setKeys($keys);

            /** @var RawBatchGetResponse $response */
            $response = $this->grpc->call($address, 'tikvpb.Tikv', 'RawBatchGet', $request, RawBatchGetResponse::class);

            $results = [];
            foreach ($response->getPairs() as $pair) {
                $results[$pair->getKey()] = $pair->getValue() !== '' ? $pair->getValue() : null;
            }

            return $results;
        });
    }

    /**
     * @param KvPair[] $pairs
     */
    private function executeBatchPutForRegion(RegionInfo $region, array $pairs, int $ttl): void
    {
        $this->executeWithRetry($pairs[0]->getKey(), function () use ($region, $pairs, $ttl): null {
            $address = $this->resolveStoreAddress($region->leaderStoreId);

            $request = new RawBatchPutRequest();
            $request->setContext(RegionContext::fromRegionInfo($region));
            $request->setPairs($pairs);
            if ($ttl > 0) {
                $request->setTtls([$ttl]);
            }

            $this->grpc->call($address, 'tikvpb.Tikv', 'RawBatchPut', $request, RawBatchPutResponse::class);
            return null;
        });
    }

    private function executeBatchDeleteForRegion(RegionInfo $region, array $keys): void
    {
        $this->executeWithRetry($keys[0], function () use ($region, $keys): null {
            $address = $this->resolveStoreAddress($region->leaderStoreId);

            $request = new RawBatchDeleteRequest();
            $request->setContext(RegionContext::fromRegionInfo($region));
            $request->setKeys($keys);

            $this->grpc->call($address, 'tikvpb.Tikv', 'RawBatchDelete', $request, RawBatchDeleteResponse::class);
            return null;
        });
    }

    /**
     * @return array<array{key: string, value: ?string}>
     */
    private function executeScanForRegion(
        RegionInfo $region,
        string $startKey,
        string $endKey,
        int $limit,
        bool $keyOnly,
        bool $reverse,
    ): array {
        return $this->executeWithRetry($startKey, function () use ($region, $startKey, $endKey, $limit, $keyOnly, $reverse): array {
            $address = $this->resolveStoreAddress($region->leaderStoreId);

            $request = new RawScanRequest();
            $request->setContext(RegionContext::fromRegionInfo($region));
            $request->setStartKey($startKey);
            if ($endKey !== '') {
                $request->setEndKey($endKey);
            }
            if ($limit > 0) {
                $request->setLimit($limit);
            }
            $request->setKeyOnly($keyOnly);
            $request->setReverse($reverse);

            /** @var RawScanResponse $response */
            $response = $this->grpc->call($address, 'tikvpb.Tikv', 'RawScan', $request, RawScanResponse::class);

            $results = [];
            foreach ($response->getKvs() as $pair) {
                $results[] = [
                    'key' => $pair->getKey(),
                    'value' => $keyOnly ? null : $pair->getValue(),
                ];
            }

            return $results;
        });
    }

    private function executeDeleteRangeForRegion(RegionInfo $region, string $startKey, string $endKey): void
    {
        $this->executeWithRetry($startKey, function () use ($region, $startKey, $endKey): null {
            $address = $this->resolveStoreAddress($region->leaderStoreId);

            $request = new RawDeleteRangeRequest();
            $request->setContext(RegionContext::fromRegionInfo($region));
            $request->setStartKey($startKey);
            $request->setEndKey($endKey);

            /** @var RawDeleteRangeResponse $response */
            $response = $this->grpc->call($address, 'tikvpb.Tikv', 'RawDeleteRange', $request, RawDeleteRangeResponse::class);

            $error = $response->getError();
            if ($error !== '') {
                throw new RegionException('RawDeleteRange', $error);
            }

            return null;
        });
    }

    private function executeChecksumForRegion(RegionInfo $region, string $startKey, string $endKey): ChecksumResult
    {
        return $this->executeWithRetry($startKey, function () use ($region, $startKey, $endKey): ChecksumResult {
            $address = $this->resolveStoreAddress($region->leaderStoreId);

            $range = new KeyRange();
            $range->setStartKey($startKey);
            if ($endKey !== '') {
                $range->setEndKey($endKey);
            }

            $request = new RawChecksumRequest();
            $request->setContext(RegionContext::fromRegionInfo($region));
            $request->setAlgorithm(ChecksumAlgorithm::Crc64_Xor);
            $request->setRanges([$range]);

            /** @var RawChecksumResponse $response */
            $response = $this->grpc->call($address, 'tikvpb.Tikv', 'RawChecksum', $request, RawChecksumResponse::class);

            $error = $response->getError();
            if ($error !== '') {
                throw new RegionException('RawChecksum', $error);
            }

            return new ChecksumResult(
                checksum: (int) $response->getChecksum(),
                totalKvs: (int) $response->getTotalKvs(),
                totalBytes: (int) $response->getTotalBytes(),
            );
        });
    }
}
```

- [ ] **Step 2: Verify syntax**

Run: `php -l src/Client/RawKv/RawKvClient.php`
Expected: No syntax errors

- [ ] **Step 3: Commit**

```bash
git add src/Client/RawKv/RawKvClient.php
git commit -m "refactor: rewrite RawKvClient with interfaces, DTOs, custom exceptions, and DI"
```

---

### Task 7: Rewrite Unit Tests with Proper Mocking

**Files:**
- Modify: `tests/Unit/RawKv/RawKvClientTest.php`

- [ ] **Step 1: Rewrite RawKvClientTest with proper mocks**

Replace the entire `tests/Unit/RawKv/RawKvClientTest.php` with:

```php
<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\RawKv;

use CrazyGoat\TiKV\Client\Connection\PdClientInterface;
use CrazyGoat\TiKV\Client\Exception\ClientClosedException;
use CrazyGoat\TiKV\Client\Exception\InvalidArgumentException;
use CrazyGoat\TiKV\Client\Exception\StoreNotFoundException;
use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use CrazyGoat\TiKV\Client\RawKv\CasResult;
use CrazyGoat\TiKV\Client\RawKv\ChecksumResult;
use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;
use CrazyGoat\TiKV\Client\RawKv\RawKvClient;
use CrazyGoat\Proto\Kvrpcpb\RawGetResponse;
use CrazyGoat\Proto\Kvrpcpb\RawPutResponse;
use CrazyGoat\Proto\Kvrpcpb\RawDeleteResponse;
use CrazyGoat\Proto\Kvrpcpb\RawBatchGetResponse;
use CrazyGoat\Proto\Kvrpcpb\RawBatchPutResponse;
use CrazyGoat\Proto\Kvrpcpb\RawBatchDeleteResponse;
use CrazyGoat\Proto\Kvrpcpb\RawScanResponse;
use CrazyGoat\Proto\Kvrpcpb\RawDeleteRangeResponse;
use CrazyGoat\Proto\Kvrpcpb\RawGetKeyTTLResponse;
use CrazyGoat\Proto\Kvrpcpb\RawCASResponse;
use CrazyGoat\Proto\Kvrpcpb\RawChecksumResponse;
use CrazyGoat\Proto\Kvrpcpb\KvPair;
use CrazyGoat\Proto\Metapb\Store;
use Google\Protobuf\Internal\Message;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RawKvClientTest extends TestCase
{
    private PdClientInterface&MockObject $pdClient;
    private GrpcClientInterface&MockObject $grpc;
    private RawKvClient $client;

    private function defaultRegion(): RegionInfo
    {
        return new RegionInfo(
            regionId: 1,
            leaderPeerId: 1,
            leaderStoreId: 1,
            epochConfVer: 1,
            epochVersion: 1,
        );
    }

    private function defaultStore(): Store
    {
        $store = new Store();
        $store->setId(1);
        $store->setAddress('tikv1:20160');
        return $store;
    }

    protected function setUp(): void
    {
        $this->pdClient = $this->createMock(PdClientInterface::class);
        $this->grpc = $this->createMock(GrpcClientInterface::class);
        $this->client = new RawKvClient($this->pdClient, $this->grpc);
    }

    // ========================================================================
    // Lifecycle
    // ========================================================================

    public function testCreateFactoryMethodExists(): void
    {
        $this->assertTrue(method_exists(RawKvClient::class, 'create'));
    }

    public function testCloseIsIdempotent(): void
    {
        $this->client->close();
        $this->client->close();
        $this->expectNotToPerformAssertions();
    }

    // ========================================================================
    // ClientClosedException on all operations
    // ========================================================================

    /**
     * @dataProvider closedOperationsProvider
     */
    public function testThrowsClientClosedExceptionWhenClosed(string $method, array $args): void
    {
        $this->client->close();

        $this->expectException(ClientClosedException::class);
        $this->expectExceptionMessage('Client is closed');

        $this->client->$method(...$args);
    }

    public static function closedOperationsProvider(): iterable
    {
        yield 'get' => ['get', ['key']];
        yield 'put' => ['put', ['key', 'value']];
        yield 'delete' => ['delete', ['key']];
        yield 'batchGet' => ['batchGet', [['k1', 'k2']]];
        yield 'batchPut' => ['batchPut', [['k1' => 'v1']]];
        yield 'batchDelete' => ['batchDelete', [['k1']]];
        yield 'scan' => ['scan', ['start', 'end']];
        yield 'scanPrefix' => ['scanPrefix', ['prefix']];
        yield 'reverseScan' => ['reverseScan', ['start', 'end']];
        yield 'deleteRange' => ['deleteRange', ['start', 'end']];
        yield 'deletePrefix' => ['deletePrefix', ['prefix']];
        yield 'getKeyTTL' => ['getKeyTTL', ['key']];
        yield 'compareAndSwap' => ['compareAndSwap', ['key', 'old', 'new']];
        yield 'putIfAbsent' => ['putIfAbsent', ['key', 'value']];
        yield 'checksum' => ['checksum', ['start', 'end']];
        yield 'batchScan' => ['batchScan', [[['a', 'b']], 10]];
    }

    // ========================================================================
    // get()
    // ========================================================================

    public function testGetReturnsValue(): void
    {
        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $response = new RawGetResponse();
        $response->setValue('hello');

        $this->grpc->method('call')->willReturn($response);

        $this->assertSame('hello', $this->client->get('key'));
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $response = new RawGetResponse();
        // Empty string = key not found in TiKV
        $response->setValue('');

        $this->grpc->method('call')->willReturn($response);

        $this->assertNull($this->client->get('missing'));
    }

    public function testGetThrowsStoreNotFoundWhenStoreIsNull(): void
    {
        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn(null);

        $this->expectException(StoreNotFoundException::class);

        $this->client->get('key');
    }

    // ========================================================================
    // put()
    // ========================================================================

    public function testPutCallsGrpc(): void
    {
        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $this->grpc->expects($this->once())
            ->method('call')
            ->with(
                'tikv1:20160',
                'tikvpb.Tikv',
                'RawPut',
                $this->isInstanceOf(Message::class),
                RawPutResponse::class,
            )
            ->willReturn(new RawPutResponse());

        $this->client->put('key', 'value');
    }

    // ========================================================================
    // delete()
    // ========================================================================

    public function testDeleteCallsGrpc(): void
    {
        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $this->grpc->expects($this->once())
            ->method('call')
            ->willReturn(new RawDeleteResponse());

        $this->client->delete('key');
    }

    // ========================================================================
    // batchGet()
    // ========================================================================

    public function testBatchGetEmptyReturnsEmpty(): void
    {
        $this->assertSame([], $this->client->batchGet([]));
    }

    public function testBatchGetReturnsOrderedResults(): void
    {
        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $pair1 = new KvPair();
        $pair1->setKey('b');
        $pair1->setValue('val-b');

        $pair2 = new KvPair();
        $pair2->setKey('a');
        $pair2->setValue('val-a');

        $response = new RawBatchGetResponse();
        $response->setPairs([$pair1, $pair2]);

        $this->grpc->method('call')->willReturn($response);

        $result = $this->client->batchGet(['a', 'b']);

        $this->assertSame(['a' => 'val-a', 'b' => 'val-b'], $result);
    }

    public function testBatchGetReturnsNullForMissingKeys(): void
    {
        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $pair = new KvPair();
        $pair->setKey('a');
        $pair->setValue('val-a');

        $response = new RawBatchGetResponse();
        $response->setPairs([$pair]);

        $this->grpc->method('call')->willReturn($response);

        $result = $this->client->batchGet(['a', 'missing']);

        $this->assertSame('val-a', $result['a']);
        $this->assertNull($result['missing']);
    }

    // ========================================================================
    // batchPut()
    // ========================================================================

    public function testBatchPutEmptyIsNoop(): void
    {
        $this->grpc->expects($this->never())->method('call');
        $this->client->batchPut([]);
    }

    // ========================================================================
    // batchDelete()
    // ========================================================================

    public function testBatchDeleteEmptyIsNoop(): void
    {
        $this->grpc->expects($this->never())->method('call');
        $this->client->batchDelete([]);
    }

    // ========================================================================
    // scan()
    // ========================================================================

    public function testScanReturnsResults(): void
    {
        $this->pdClient->method('scanRegions')->willReturn([$this->defaultRegion()]);
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $pair = new KvPair();
        $pair->setKey('k1');
        $pair->setValue('v1');

        $response = new RawScanResponse();
        $response->setKvs([$pair]);

        $this->grpc->method('call')->willReturn($response);

        $result = $this->client->scan('k', 'l');

        $this->assertCount(1, $result);
        $this->assertSame('k1', $result[0]['key']);
        $this->assertSame('v1', $result[0]['value']);
    }

    // ========================================================================
    // deletePrefix()
    // ========================================================================

    public function testDeletePrefixThrowsOnEmptyPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->client->deletePrefix('');
    }

    // ========================================================================
    // batchScan()
    // ========================================================================

    public function testBatchScanThrowsOnInvalidEachLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->client->batchScan([['a', 'b']], 0);
    }

    public function testBatchScanThrowsOnInvalidRangeFormat(): void
    {
        $this->pdClient->method('scanRegions')->willReturn([]);
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $this->expectException(InvalidArgumentException::class);
        $this->client->batchScan([['only-one']], 10);
    }

    public function testBatchScanEmptyReturnsEmpty(): void
    {
        $this->assertSame([], $this->client->batchScan([], 10));
    }

    // ========================================================================
    // compareAndSwap()
    // ========================================================================

    public function testCompareAndSwapSuccess(): void
    {
        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $response = new RawCASResponse();
        $response->setSucceed(true);
        $response->setPreviousNotExist(false);
        $response->setPreviousValue('old');

        $this->grpc->method('call')->willReturn($response);

        $result = $this->client->compareAndSwap('key', 'old', 'new');

        $this->assertInstanceOf(CasResult::class, $result);
        $this->assertTrue($result->swapped);
        $this->assertSame('old', $result->previousValue);
    }

    public function testCompareAndSwapFailure(): void
    {
        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $response = new RawCASResponse();
        $response->setSucceed(false);
        $response->setPreviousNotExist(false);
        $response->setPreviousValue('actual');

        $this->grpc->method('call')->willReturn($response);

        $result = $this->client->compareAndSwap('key', 'wrong', 'new');

        $this->assertFalse($result->swapped);
        $this->assertSame('actual', $result->previousValue);
    }

    // ========================================================================
    // putIfAbsent()
    // ========================================================================

    public function testPutIfAbsentReturnsNullOnSuccess(): void
    {
        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $response = new RawCASResponse();
        $response->setSucceed(true);
        $response->setPreviousNotExist(true);

        $this->grpc->method('call')->willReturn($response);

        $this->assertNull($this->client->putIfAbsent('key', 'value'));
    }

    public function testPutIfAbsentReturnsExistingValue(): void
    {
        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $response = new RawCASResponse();
        $response->setSucceed(false);
        $response->setPreviousNotExist(false);
        $response->setPreviousValue('existing');

        $this->grpc->method('call')->willReturn($response);

        $this->assertSame('existing', $this->client->putIfAbsent('key', 'value'));
    }

    // ========================================================================
    // checksum()
    // ========================================================================

    public function testChecksumReturnsResult(): void
    {
        $region = new RegionInfo(1, 1, 1, 1, 1, 'a', 'z');
        $this->pdClient->method('scanRegions')->willReturn([$region]);
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $response = new RawChecksumResponse();
        $response->setChecksum(12345);
        $response->setTotalKvs(3);
        $response->setTotalBytes(100);

        $this->grpc->method('call')->willReturn($response);

        $result = $this->client->checksum('a', 'z');

        $this->assertInstanceOf(ChecksumResult::class, $result);
        $this->assertSame(12345, $result->checksum);
        $this->assertSame(3, $result->totalKvs);
        $this->assertSame(100, $result->totalBytes);
    }

    // ========================================================================
    // getKeyTTL()
    // ========================================================================

    public function testGetKeyTTLReturnsValue(): void
    {
        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $response = new RawGetKeyTTLResponse();
        $response->setTtl(42);
        $response->setNotFound(false);

        $this->grpc->method('call')->willReturn($response);

        $this->assertSame(42, $this->client->getKeyTTL('key'));
    }

    public function testGetKeyTTLReturnsNullWhenNotFound(): void
    {
        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $response = new RawGetKeyTTLResponse();
        $response->setNotFound(true);

        $this->grpc->method('call')->willReturn($response);

        $this->assertNull($this->client->getKeyTTL('missing'));
    }

    public function testGetKeyTTLReturnsNullWhenZero(): void
    {
        $this->pdClient->method('getRegion')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());

        $response = new RawGetKeyTTLResponse();
        $response->setTtl(0);
        $response->setNotFound(false);

        $this->grpc->method('call')->willReturn($response);

        $this->assertNull($this->client->getKeyTTL('no-ttl'));
    }
}
```

- [ ] **Step 2: Run unit tests**

Run: `vendor/bin/phpunit tests/Unit/`
Expected: All tests pass

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/RawKv/RawKvClientTest.php
git commit -m "refactor: rewrite unit tests with proper interface mocking and behavior verification"
```

---

### Task 8: Update E2E Tests and Final Verification

**Files:**
- Modify: `tests/E2E/RawKvE2ETest.php` (minimal — only exception class references if needed)
- Modify: `examples/basic.php` (no changes needed — public API unchanged)

- [ ] **Step 1: Check E2E test compatibility**

The E2E tests use `\RuntimeException` and `\InvalidArgumentException`. Our custom exceptions extend these, so `catch (\RuntimeException)` still works. The `expectException(\RuntimeException::class)` in E2E tests will still match `ClientClosedException` (which extends `TiKvException` which extends `\RuntimeException`). The `expectException(\InvalidArgumentException::class)` will still match our `InvalidArgumentException` (which extends `\InvalidArgumentException`).

No changes needed to E2E tests.

- [ ] **Step 2: Run all unit tests**

Run: `vendor/bin/phpunit tests/Unit/`
Expected: All tests pass

- [ ] **Step 3: Verify syntax of all modified files**

Run: `php -l src/Client/Exception/TiKvException.php && php -l src/Client/Exception/ClientClosedException.php && php -l src/Client/Exception/GrpcException.php && php -l src/Client/Exception/RegionException.php && php -l src/Client/Exception/StoreNotFoundException.php && php -l src/Client/Exception/InvalidArgumentException.php && php -l src/Client/Grpc/GrpcClientInterface.php && php -l src/Client/Grpc/GrpcClient.php && php -l src/Client/Connection/PdClientInterface.php && php -l src/Client/Connection/PdClient.php && php -l src/Client/RawKv/Dto/RegionInfo.php && php -l src/Client/RawKv/Dto/KeyValue.php && php -l src/Client/RawKv/RegionContext.php && php -l src/Client/RawKv/RawKvClient.php`
Expected: No syntax errors in any file

- [ ] **Step 4: Commit final state**

```bash
git add -A
git commit -m "refactor: complete production-quality refactoring of tikv-php client"
```
