# TLS/SSL Support for gRPC Connections Design

## Status

Draft

## Context

Currently `GrpcClient` uses `ChannelCredentials::createInsecure()` for all connections. This prevents usage with TLS-enabled TiKV clusters, which is a requirement for production deployments.

## Goals

- Enable TLS connections to PD and TiKV nodes
- Support mTLS (client certificate authentication)
- Maintain backward compatibility (TLS optional)
- Simple configuration via `RawKvClient::create()` options

## Architecture

### Components

1. **TlsConfig** - Value object holding TLS configuration
2. **TlsConfigBuilder** - Helper for building TlsConfig with auto-detection
3. **GrpcClient** - Modified to accept optional TlsConfig
4. **RawKvClient** - Pass TLS config through options

### TlsConfig

```php
final readonly class TlsConfig
{
    public function __construct(
        public ?string $caCert = null,      // CA certificate (path or content)
        public ?string $clientCert = null,  // Client certificate (path or content)
        public ?string $clientKey = null,    // Client private key (path or content)
    ) {}

    /**
     * Check if TLS is enabled (at least CA cert provided)
     */
    public function isEnabled(): bool
    {
        return $this->caCert !== null;
    }
}
```

### TlsConfigBuilder

```php
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

    /**
     * Auto-detect if value is file path or content
     */
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

### GrpcClient Integration

```php
final class GrpcClient implements GrpcClientInterface
{
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ?TlsConfig $tlsConfig = null,
    ) {}

    private function getChannel(string $address): Channel
    {
        if (!isset($this->channels[$address])) {
            $this->logger->debug('Opening gRPC channel', [
                'address' => $address,
                'tls' => $this->tlsConfig?->isEnabled() ?? false,
            ]);

            $credentials = $this->tlsConfig?->isEnabled()
                ? $this->createTlsCredentials()
                : ChannelCredentials::createInsecure();

            $this->channels[$address] = new Channel($address, [
                'credentials' => $credentials,
            ]);
        }

        return $this->channels[$address];
    }

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
}
```

### RawKvClient Configuration

```php
public static function create(
    array $pdEndpoints,
    array $options = [],
): self
{
    // ... existing code ...

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

    // ... rest of the code ...
}
```

## Usage Examples

### Basic TLS (server verification only)

```php
$client = RawKvClient::create(
    ['pd1:2379', 'pd2:2379'],
    [
        'tls' => [
            'caCert' => '/path/to/ca.crt',  // or file_get_contents('/path/to/ca.crt')
        ],
    ]
);
```

### mTLS (mutual TLS with client auth)

```php
$client = RawKvClient::create(
    ['pd1:2379', 'pd2:2379'],
    [
        'tls' => [
            'caCert' => '/path/to/ca.crt',
            'clientCert' => '/path/to/client.crt',
            'clientKey' => '/path/to/client.key',
        ],
    ]
);
```

### Plaintext (backward compatible)

```php
$client = RawKvClient::create(
    ['pd1:2379', 'pd2:2379'],
    []  // No TLS config = plaintext
);
```

## Error Handling

- Invalid certificate file path → `InvalidArgumentException`
- Unreadable certificate file → `InvalidArgumentException`
- Client cert without client key → `InvalidArgumentException`
- TLS handshake failure → `GrpcException` (propagated from gRPC)

## Testing Strategy

1. Unit tests for TlsConfig
2. Unit tests for TlsConfigBuilder (auto-detection logic)
3. Unit tests for GrpcClient with TLS (mocked)
4. Integration tests with TLS-enabled cluster (if available)

## Files to Create

```
src/Client/Tls/TlsConfig.php
src/Client/Tls/TlsConfigBuilder.php
tests/Unit/Tls/TlsConfigTest.php
tests/Unit/Tls/TlsConfigBuilderTest.php
```

## Files to Modify

```
src/Client/Grpc/GrpcClient.php
src/Client/Grpc/GrpcClientInterface.php
src/Client/RawKv/RawKvClient.php
```

## Security Considerations

- Private keys are loaded into memory (unavoidable for gRPC)
- Certificate content is stored in TlsConfig (immutable, readonly)
- No logging of certificate content (only presence/absence)
- File permissions should be checked (readable by process owner only)
