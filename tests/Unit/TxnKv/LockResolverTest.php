<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\TxnKv;

use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use CrazyGoat\TiKV\Client\TxnKv\LockResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class LockResolverTest extends TestCase
{
    public function testConstruction(): void
    {
        $grpc = $this->createMock(GrpcClientInterface::class);
        $resolver = new LockResolver($grpc, new NullLogger());

        $this->assertInstanceOf(LockResolver::class, $resolver);
    }
}
