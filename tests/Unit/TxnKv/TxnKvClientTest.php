<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\TxnKv;

use CrazyGoat\TiKV\Client\TxnKv\TxnKvClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class TxnKvClientTest extends TestCase
{
    public function testConstruction(): void
    {
        $client = new TxnKvClient(logger: new NullLogger());

        $this->assertInstanceOf(TxnKvClient::class, $client);
    }
}
