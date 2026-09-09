<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Grpc;

use CrazyGoat\TiKV\Client\Exception\InvalidArgumentException;
use CrazyGoat\TiKV\Client\Grpc\GrpcClient;
use PHPUnit\Framework\TestCase;

/**
 * Channel-argument configuration for GrpcClient (issue #265).
 *
 * Pure PHP — no ext-grpc and no real channels are created; the channel
 * argument array is inspected through reflection on the private
 * GrpcClient::channelArgs() seam, so these tests run in the Unit suite.
 */
class GrpcClientChannelArgsTest extends TestCase
{
    public function testDefaultChannelArgsRaiseTheTransportLimits(): void
    {
        $args = $this->channelArgsOf(new GrpcClient());

        $this->assertSame(64 * 1024 * 1024, $args['grpc.max_receive_message_length']);
        $this->assertSame(64 * 1024 * 1024, $args['grpc.max_send_message_length']);
    }

    public function testDefaultKeepaliveArgs(): void
    {
        $args = $this->channelArgsOf(new GrpcClient());

        $this->assertSame(10000, $args['grpc.keepalive_time_ms']);
        $this->assertSame(3000, $args['grpc.keepalive_timeout_ms']);
        $this->assertSame(1, $args['grpc.keepalive_permit_without_calls']);
        $this->assertSame(0, $args['grpc.http2.max_pings_without_data']);
    }

    public function testCustomLimitsArePassedIntoChannelArgs(): void
    {
        $client = new GrpcClient(
            maxReceiveMessageBytes: 128 * 1024 * 1024,
            maxSendMessageBytes: 16 * 1024 * 1024,
            keepaliveTimeMs: 5000,
            keepaliveTimeoutMs: 1000,
        );

        $args = $this->channelArgsOf($client);

        $this->assertSame(128 * 1024 * 1024, $args['grpc.max_receive_message_length']);
        $this->assertSame(16 * 1024 * 1024, $args['grpc.max_send_message_length']);
        $this->assertSame(5000, $args['grpc.keepalive_time_ms']);
        $this->assertSame(1000, $args['grpc.keepalive_timeout_ms']);
    }

    public function testMaxReceiveMessageBytesMustBeAtLeastOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxReceiveMessageBytes must be at least 1');
        new GrpcClient(maxReceiveMessageBytes: 0);
    }

    public function testMaxSendMessageBytesMustBeAtLeastOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxSendMessageBytes must be at least 1');
        new GrpcClient(maxSendMessageBytes: -1);
    }

    public function testKeepaliveTimeMsMustBeAtLeastOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('keepaliveTimeMs must be at least 1');
        new GrpcClient(keepaliveTimeMs: 0);
    }

    public function testKeepaliveTimeoutMsMustBeAtLeastOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('keepaliveTimeoutMs must be at least 1');
        new GrpcClient(keepaliveTimeoutMs: 0);
    }

    /**
     * @return array<string, int|string|bool>
     */
    private function channelArgsOf(GrpcClient $client): array
    {
        $method = new \ReflectionMethod(GrpcClient::class, 'channelArgs');

        /** @var array<string, int|string|bool> */
        return $method->invoke($client);
    }
}
