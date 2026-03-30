<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Batch;

use CrazyGoat\TiKV\Client\Batch\GrpcFuture;
use Grpc\Call;
use PHPUnit\Framework\TestCase;

class GrpcFutureTest extends TestCase
{
    public function testConstruction(): void
    {
        $call = $this->createMock(Call::class);
        $future = new GrpcFuture($call, 'TestResponseClass');

        $this->assertInstanceOf(GrpcFuture::class, $future);
    }
}
