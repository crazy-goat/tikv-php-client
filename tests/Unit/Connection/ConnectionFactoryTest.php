<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Connection;

use CrazyGoat\TiKV\Client\Connection\ConnectionFactory;
use CrazyGoat\TiKV\Client\Exception\InvalidArgumentException;
use CrazyGoat\TiKV\Client\Grpc\GrpcClient;
use PHPUnit\Framework\TestCase;

class ConnectionFactoryTest extends TestCase
{
    public function testAllowedStorePortsDefaultsToNull(): void
    {
        $bundle = ConnectionFactory::create(['127.0.0.1:2379']);

        $this->assertNull($bundle->allowedStorePorts);
    }

    // ========================================================================
    // options['grpc'] — gRPC channel arguments (issue #265)
    // ========================================================================

    public function testGrpcOptionsDefaultToGrpcClientDefaults(): void
    {
        $grpc = $this->grpcFromFactory(['127.0.0.1:2379']);

        $this->assertSame(
            GrpcClient::DEFAULT_MAX_RECEIVE_MESSAGE_BYTES,
            $this->grpcProperty($grpc, 'maxReceiveMessageBytes'),
        );
        $this->assertSame(
            GrpcClient::DEFAULT_MAX_SEND_MESSAGE_BYTES,
            $this->grpcProperty($grpc, 'maxSendMessageBytes'),
        );
        $this->assertSame(
            GrpcClient::DEFAULT_KEEPALIVE_TIME_MS,
            $this->grpcProperty($grpc, 'keepaliveTimeMs'),
        );
        $this->assertSame(
            GrpcClient::DEFAULT_KEEPALIVE_TIMEOUT_MS,
            $this->grpcProperty($grpc, 'keepaliveTimeoutMs'),
        );
    }

    public function testGrpcOptionsThreadedThroughToGrpcClient(): void
    {
        $grpc = $this->grpcFromFactory(['127.0.0.1:2379'], [
            'grpc' => [
                'maxReceiveMessageBytes' => 128 * 1024 * 1024,
                'maxSendMessageBytes' => 8 * 1024 * 1024,
                'keepaliveTimeMs' => 5000,
                'keepaliveTimeoutMs' => 1000,
            ],
        ]);

        $this->assertSame(128 * 1024 * 1024, $this->grpcProperty($grpc, 'maxReceiveMessageBytes'));
        $this->assertSame(8 * 1024 * 1024, $this->grpcProperty($grpc, 'maxSendMessageBytes'));
        $this->assertSame(5000, $this->grpcProperty($grpc, 'keepaliveTimeMs'));
        $this->assertSame(1000, $this->grpcProperty($grpc, 'keepaliveTimeoutMs'));
    }

    public function testGrpcOptionsRejectNonIntValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("options['grpc'][maxReceiveMessageBytes] must be an int >= 1");

        ConnectionFactory::create(
            ['127.0.0.1:2379'],
            options: ['grpc' => ['maxReceiveMessageBytes' => '64MB']],
        );
    }

    public function testGrpcOptionsRejectValueBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("options['grpc'][keepaliveTimeMs] must be an int >= 1");

        ConnectionFactory::create(
            ['127.0.0.1:2379'],
            options: ['grpc' => ['keepaliveTimeMs' => 0]],
        );
    }

    /**
     * @param string[] $pdEndpoints
     * @param array<string, mixed> $options
     */
    private function grpcFromFactory(
        array $pdEndpoints,
        array $options = [],
    ): GrpcClient {
        $grpc = ConnectionFactory::create($pdEndpoints, options: $options)->grpc;
        assert($grpc instanceof GrpcClient);

        return $grpc;
    }

    private function grpcProperty(GrpcClient $grpc, string $property): int
    {
        /** @var int */
        return (new \ReflectionProperty(GrpcClient::class, $property))->getValue($grpc);
    }

    public function testAllowedStorePortsThreadedThroughBundle(): void
    {
        $bundle = ConnectionFactory::create(
            ['127.0.0.1:2379'],
            options: ['allowedStorePorts' => [20160, 20161]],
        );

        $this->assertSame([20160, 20161], $bundle->allowedStorePorts);
    }

    public function testAllowedStorePortsExplicitNullIsAccepted(): void
    {
        $bundle = ConnectionFactory::create(
            ['127.0.0.1:2379'],
            options: ['allowedStorePorts' => null],
        );

        $this->assertNull($bundle->allowedStorePorts);
    }

    public function testAllowedStorePortsRejectsNonArrayValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionFactory::create(
            ['127.0.0.1:2379'],
            options: ['allowedStorePorts' => '20160'],
        );
    }

    public function testAllowedStorePortsRejectsNonIntEntries(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionFactory::create(
            ['127.0.0.1:2379'],
            options: ['allowedStorePorts' => ['20160']],
        );
    }

    public function testAllowedStorePortsRejectsOutOfRangeEntries(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConnectionFactory::create(
            ['127.0.0.1:2379'],
            options: ['allowedStorePorts' => [0]],
        );
    }
}
