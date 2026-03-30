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
