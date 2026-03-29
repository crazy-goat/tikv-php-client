# Feature: TLS/Security Support

## Overview
Add TLS encryption for gRPC connections to PD and TiKV nodes. Production TiKV deployments typically require mutual TLS (mTLS) with CA certificate, client certificate, and client key.

## Reference Implementation
- **Go**: `WithSecurity(config.Security{ClusterSSLCA, ClusterSSLCert, ClusterSSLKey})` — constructor option
  - Creates `tls.Config` from PEM files
  - Uses `grpc.WithTransportCredentials(credentials.NewTLS(tlsConfig))`
- **Java**: TLS configured via `TiConfiguration` with `pdAddrsWithTLS`, `trustCertCollectionFile`, `keyCertChainFile`, `keyFile`

## Current Behavior
PHP client uses `ChannelCredentials::createInsecure()` for all gRPC connections. No TLS support.

## API Design

### Constructor Option
```php
$client = RawKvClient::create(
    pdAddresses: ['127.0.0.1:2379'],
    tlsConfig: new TlsConfig(
        caCertPath: '/path/to/ca.pem',
        clientCertPath: '/path/to/client.pem',  // optional for mTLS
        clientKeyPath: '/path/to/client-key.pem', // optional for mTLS
    ),
);
```

### TlsConfig Value Object
```php
final readonly class TlsConfig
{
    public function __construct(
        public string $caCertPath,
        public ?string $clientCertPath = null,
        public ?string $clientKeyPath = null,
    ) {}
}
```

## Implementation Details

### GrpcClient Changes
```php
// Current:
$credentials = ChannelCredentials::createInsecure();

// With TLS:
if ($this->tlsConfig !== null) {
    $credentials = ChannelCredentials::createSsl(
        file_get_contents($this->tlsConfig->caCertPath),
        $this->tlsConfig->clientKeyPath ? file_get_contents($this->tlsConfig->clientKeyPath) : null,
        $this->tlsConfig->clientCertPath ? file_get_contents($this->tlsConfig->clientCertPath) : null,
    );
} else {
    $credentials = ChannelCredentials::createInsecure();
}
```

### PdClient Changes
Same TLS config must be applied to PD connections (PD uses gRPC too).

### Certificate Validation
- Validate file paths exist and are readable
- Validate PEM format (basic check)
- If clientCert is provided, clientKey must also be provided (and vice versa)

## Testing Strategy
1. Connection with valid TLS certs works
2. Connection with invalid CA cert fails with clear error
3. Connection with expired cert fails
4. mTLS: client cert + key required by server
5. Insecure mode (no TLS) still works (backward compatible)
6. TLS config validation: missing files throw InvalidArgumentException

Note: TLS tests require a TiKV cluster configured with TLS, which needs additional docker-compose setup with certificate generation.

## Priority: MEDIUM
Required for production deployments. Most development/testing uses insecure connections, but production clusters almost always require TLS.
