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
