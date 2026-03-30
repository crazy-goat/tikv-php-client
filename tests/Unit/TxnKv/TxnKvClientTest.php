<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\TxnKv;

use CrazyGoat\TiKV\Client\Cache\RegionCacheInterface;
use CrazyGoat\TiKV\Client\Connection\PdClientInterface;
use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use CrazyGoat\TiKV\Client\TxnKv\TxnKvClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class TxnKvClientTest extends TestCase
{
    public function testConstruction(): void
    {
        $pdClient = $this->createMock(PdClientInterface::class);
        $grpc = $this->createMock(GrpcClientInterface::class);
        $regionCache = $this->createMock(RegionCacheInterface::class);

        $client = new TxnKvClient(
            logger: new NullLogger(),
            pdClient: $pdClient,
            grpc: $grpc,
            regionCache: $regionCache,
        );

        $this->assertInstanceOf(TxnKvClient::class, $client);
    }
}
