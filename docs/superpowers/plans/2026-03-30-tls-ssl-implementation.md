# TLS/SSL Support Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add TLS/SSL support for gRPC connections with mTLS capability

**Architecture:** Create TlsConfig and TlsConfigBuilder for certificate management, integrate into GrpcClient with auto-detection of file paths vs content, expose via RawKvClient options

**Tech Stack:** PHP 8.2+, gRPC extension, PHPUnit

---

## File Map

**Create:**
- `src/Client/Tls/TlsConfig.php`
- `src/Client/Tls/TlsConfigBuilder.php`
- `tests/Unit/Tls/TlsConfigTest.php`
- `tests/Unit/Tls/TlsConfigBuilderTest.php`

**Modify:**
- `src/Client/Grpc/GrpcClient.php`
- `src/Client/Grpc/GrpcClientInterface.php`
- `src/Client/RawKv/RawKvClient.php`

---

## Task 1: TlsConfig Value Object

**Files:**
- Create: `src/Client/Tls/TlsConfig.php`
- Test: `tests/Unit/Tls/TlsConfigTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Tls;

use CrazyGoat\TiKV\Client\Tls\TlsConfig;
use PHPUnit\Framework\TestCase;

class TlsConfigTest extends TestCase
{
    public function testConstructionWithAllFields(): void
    {
        $config = new TlsConfig(
            caCert: 'ca-content',
            clientCert: 'client-cert-content',
            clientKey: 'client-key-content',
        );

        $this->assertSame('ca-content', $config->caCert);
        $this->assertSame('client-cert-content', $config->clientCert);
        $this->assertSame('client-key-content', $config->clientKey);
    }

    public function testConstructionWithNulls(): void
    {
        $config = new TlsConfig();

        $this->assertNull($config->caCert);
        $this->assertNull($config->clientCert);
        $this->assertNull($config->clientKey);
    }

    public function testIsEnabledReturnsTrueWhenCaCertPresent(): void
    {
        $config = new TlsConfig(caCert: 'ca-content');
        $this->assertTrue($config->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenCaCertNull(): void
    {
        $config = new TlsConfig();
        $this->assertFalse($config->isEnabled());
    }
}
```

Run: `./vendor/bin/phpunit tests/Unit/Tls/TlsConfigTest.php`
Expected: FAIL - TlsConfig class does not exist

- [ ] **Step 2: Create TlsConfig class**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Tls;

final readonly class TlsConfig
{
    public function __construct(
        public ?string $caCert = null,
        public ?string $clientCert = null,
        public ?string $clientKey = null,
    ) {}

    public function isEnabled(): bool
    {
        return $this->caCert !== null;
    }
}
```

- [ ] **Step 3: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Tls/TlsConfigTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Client/Tls/TlsConfig.php tests/Unit/Tls/TlsConfigTest.php
git commit -m "feat(tls): add TlsConfig value object"
```

---

## Task 2: TlsConfigBuilder

**Files:**
- Create: `src/Client/Tls/TlsConfigBuilder.php`
- Test: `tests/Unit/Tls/TlsConfigBuilderTest.php`

- [ ] **Step 1: Write test for file path auto-detection**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Tls;

use CrazyGoat\TiKV\Client\Tls\TlsConfigBuilder;
use PHPUnit\Framework\TestCase;

class TlsConfigBuilderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/tikv-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
    }

    public function testWithCaCertFromFile(): void
    {
        $certContent = 'test-ca-cert-content';
        $certPath = $this->tempDir . '/ca.crt';
        file_put_contents($certPath, $certContent);

        $config = (new TlsConfigBuilder())
            ->withCaCert($certPath)
            ->build();

        $this->assertSame($certContent, $config->caCert);
    }

    public function testWithCaCertFromContent(): void
    {
        $certContent = 'inline-ca-cert-content';

        $config = (new TlsConfigBuilder())
            ->withCaCert($certContent)
            ->build();

        $this->assertSame($certContent, $config->caCert);
    }

    public function testWithClientCertFromFiles(): void
    {
        $certContent = 'test-client-cert';
        $keyContent = 'test-client-key';
        $certPath = $this->tempDir . '/client.crt';
        $keyPath = $this->tempDir . '/client.key';
        file_put_contents($certPath, $certContent);
        file_put_contents($keyPath, $keyContent);

        $config = (new TlsConfigBuilder())
            ->withClientCert($certPath, $keyPath)
            ->build();

        $this->assertSame($certContent, $config->clientCert);
        $this->assertSame($keyContent, $config->clientKey);
    }

    public function testBuildReturnsEmptyConfig(): void
    {
        $config = (new TlsConfigBuilder())->build();

        $this->assertNull($config->caCert);
        $this->assertNull($config->clientCert);
        $this->assertNull($config->clientKey);
        $this->assertFalse($config->isEnabled());
    }
}
```

Run: `./vendor/bin/phpunit tests/Unit/Tls/TlsConfigBuilderTest.php`
Expected: FAIL - TlsConfigBuilder class does not exist

- [ ] **Step 2: Create TlsConfigBuilder class**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Tls;

use CrazyGoat\TiKV\Client\Exception\InvalidArgumentException;

final class TlsConfigBuilder
{
    private ?string $caCert = null;
    private ?string $clientCert = null;
    private ?string $clientKey = null;

    public function withCaCert(string $caCert): self
    {
        $this->caCert = $this->resolveContent($caCert);
        return $this;
    }

    public function withClientCert(string $cert, string $key): self
    {
        $this->clientCert = $this->resolveContent($cert);
        $this->clientKey = $this->resolveContent($key);
        return $this;
    }

    public function build(): TlsConfig
    {
        return new TlsConfig($this->caCert, $this->clientCert, $this->clientKey);
    }

    private function resolveContent(string $value): string
    {
        if (file_exists($value) && is_readable($value)) {
            $content = file_get_contents($value);
            if ($content === false) {
                throw new InvalidArgumentException("Cannot read file: {$value}");
            }
            return $content;
        }
        return $value;
    }
}
```

- [ ] **Step 3: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Tls/TlsConfigBuilderTest.php`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
git add src/Client/Tls/TlsConfigBuilder.php tests/Unit/Tls/TlsConfigBuilderTest.php
git commit -m "feat(tls): add TlsConfigBuilder with auto-detection"
```

---

## Task 3: Update GrpcClientInterface

**Files:**
- Modify: `src/Client/Grpc/GrpcClientInterface.php`

- [ ] **Step 1: Add TlsConfig import and constructor signature**

```php
<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Grpc;

use CrazyGoat\Google\Protobuf\Internal\Message;
use CrazyGoat\TiKV\Client\Tls\TlsConfig;
use Psr\Log\LoggerInterface;

interface GrpcClientInterface
{
    /**
     * Create a new gRPC client.
     *
     * @param LoggerInterface $logger PSR-3 logger instance
     * @param TlsConfig|null $tlsConfig Optional TLS configuration for secure connections
     */
    public function __construct(
        LoggerInterface $logger = new \Psr\Log\NullLogger(),
        ?TlsConfig $tlsConfig = null,
    );

    /**
     * Execute a gRPC call.
     *
     * @template T of Message
     * @param string $address Target address (e.g., "127.0.0.1:2379")
     * @param string $service Service name (e.g., "pdpb.PD")
     * @param string $method Method name (e.g., "GetRegion")
     * @param Message $request Protobuf request message
     * @param class-string<T> $responseClass Response message class name
     * @return T Response message
     * @throws \CrazyGoat\TiKV\Client\Exception\GrpcException On gRPC error
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

    /**
     * Close a single channel by address, forcing reconnect on next call.
     *
     * @param string $address Channel address to close
     */
    public function closeChannel(string $address): void;
}
```

- [ ] **Step 2: Run PHPStan to verify interface is valid**

Run: `./vendor/bin/phpstan analyse src/Client/Grpc/GrpcClientInterface.php --level=9`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add src/Client/Grpc/GrpcClientInterface.php
git commit -m "feat(tls): add TlsConfig to GrpcClientInterface"
```

---

## Task 4: Update GrpcClient

**Files:**
- Modify: `src/Client/Grpc/GrpcClient.php`

- [ ] **Step 1: Add TlsConfig import and property**

Add to imports:
```php
use CrazyGoat\TiKV\Client\Tls\TlsConfig;
```

Update constructor:
```php
public function __construct(
    private readonly LoggerInterface $logger = new NullLogger(),
    private readonly ?TlsConfig $tlsConfig = null,
) {
}
```

- [ ] **Step 2: Modify getChannel to support TLS**

Replace the getChannel method:
```php
private function getChannel(string $address): Channel
{
    if (isset($this->channels[$address])) {
        $state = $this->channels[$address]->getConnectivityState();
        if ($state === \Grpc\CHANNEL_FATAL_FAILURE) {
            $this->logger->warning('Channel in fatal failure, reconnecting', ['address' => $address]);
            $this->closeChannel($address);
        }
    }

    if (!isset($this->channels[$address])) {
        $this->logger->debug('Opening gRPC channel', [
            'address' => $address,
            'tls' => $this->tlsConfig?->isEnabled() ?? false,
        ]);

        $credentials = $this->tlsConfig !== null && $this->tlsConfig->isEnabled()
            ? $this->createTlsCredentials()
            : ChannelCredentials::createInsecure();

        $this->channels[$address] = new Channel($address, [
            'credentials' => $credentials,
        ]);
    }

    return $this->channels[$address];
}
```

- [ ] **Step 3: Add createTlsCredentials method**

Add new private method:
```php
private function createTlsCredentials(): ChannelCredentials
{
    $certChain = $this->tlsConfig->clientCert;
    $privateKey = $this->tlsConfig->clientKey;

    return ChannelCredentials::createSsl(
        $this->tlsConfig->caCert,
        $certChain,
        $privateKey,
    );
}
```

- [ ] **Step 4: Run tests to verify nothing broke**

Run: `./vendor/bin/phpunit tests/Unit/Grpc/GrpcClientTest.php`
Expected: PASS

- [ ] **Step 5: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Client/Grpc/GrpcClient.php --level=9`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Client/Grpc/GrpcClient.php
git commit -m "feat(tls): integrate TlsConfig into GrpcClient"
```

---

## Task 5: Update RawKvClient

**Files:**
- Modify: `src/Client/RawKv/RawKvClient.php`

- [ ] **Step 1: Add TlsConfigBuilder import**

Add to imports:
```php
use CrazyGoat\TiKV\Client\Tls\TlsConfigBuilder;
```

- [ ] **Step 2: Modify create method to handle TLS options**

Find the section where GrpcClient is created (around line 62) and replace:
```php
$grpc = new GrpcClient($resolvedLogger);
```

With:
```php
$tlsConfig = null;
if (isset($options['tls'])) {
    $tlsOptions = $options['tls'];
    $builder = new TlsConfigBuilder();

    if (isset($tlsOptions['caCert'])) {
        $builder->withCaCert($tlsOptions['caCert']);
    }

    if (isset($tlsOptions['clientCert']) && isset($tlsOptions['clientKey'])) {
        $builder->withClientCert($tlsOptions['clientCert'], $tlsOptions['clientKey']);
    }

    $tlsConfig = $builder->build();
}

$grpc = new GrpcClient($resolvedLogger, $tlsConfig);
```

- [ ] **Step 3: Run tests to verify nothing broke**

Run: `./vendor/bin/phpunit tests/Unit/RawKv/RawKvClientTest.php`
Expected: PASS

- [ ] **Step 4: Run PHPStan**

Run: `./vendor/bin/phpstan analyse src/Client/RawKv/RawKvClient.php --level=9`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Client/RawKv/RawKvClient.php
git commit -m "feat(tls): add TLS configuration via RawKvClient options"
```

---

## Task 6: Final Verification

- [ ] **Step 1: Run full test suite**

Run: `./vendor/bin/phpunit --testsuite Unit`
Expected: ALL PASS (132 tests)

- [ ] **Step 2: Run PHPStan on all files**

Run: `./vendor/bin/phpstan analyse --no-progress`
Expected: No errors

- [ ] **Step 3: Run PHPCS**

Run: `./vendor/bin/phpcs --standard=phpcs.xml.dist`
Expected: No errors

- [ ] **Step 4: Run Rector**

Run: `./vendor/bin/rector process --dry-run`
Expected: No changes needed

- [ ] **Step 5: Verify no TODO/FIXME in new code**

Run: `grep -r "TODO\|FIXME" src/Client/Tls/`
Expected: No matches

---

## Summary

**New files:** 4
**Modified files:** 3
**Total commits:** 6

**After all tasks:**
- TLS connections supported via `options['tls']`
- mTLS supported with client certificates
- Auto-detection of file paths vs content
- All existing tests pass
- PHPStan level 9 clean
