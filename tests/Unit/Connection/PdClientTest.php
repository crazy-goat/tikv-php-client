<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Connection;

use CrazyGoat\Proto\Metapb\Peer;
use CrazyGoat\Proto\Metapb\Region;
use CrazyGoat\Proto\Metapb\RegionEpoch;
use CrazyGoat\Proto\Pdpb\GetRegionResponse;
use CrazyGoat\Proto\Pdpb\ResponseHeader;
use CrazyGoat\TiKV\Client\Connection\PdClient;
use CrazyGoat\TiKV\Client\Connection\PdClientInterface;
use CrazyGoat\TiKV\Client\Exception\GrpcException;
use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;
use Google\Protobuf\Internal\Message;
use PHPUnit\Framework\TestCase;

class PdClientTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $grpc = $this->createMock(GrpcClientInterface::class);
        $client = new PdClient($grpc, 'pd:2379');

        $this->assertInstanceOf(PdClientInterface::class, $client);
    }

    public function testGetRegionReturnsRegionInfo(): void
    {
        $epoch = new RegionEpoch();
        $epoch->setConfVer(1);
        $epoch->setVersion(10);

        $region = new Region();
        $region->setId(42);
        $region->setRegionEpoch($epoch);

        $leader = new Peer();
        $leader->setId(7);
        $leader->setStoreId(3);

        $header = new ResponseHeader();
        $header->setClusterId(100);

        $response = new GetRegionResponse();
        $response->setHeader($header);
        $response->setRegion($region);
        $response->setLeader($leader);

        $grpc = $this->createMock(GrpcClientInterface::class);
        $grpc->expects($this->once())
            ->method('call')
            ->willReturn($response);

        $client = new PdClient($grpc, 'pd:2379');
        $result = $client->getRegion('my-key');

        $this->assertInstanceOf(RegionInfo::class, $result);
        $this->assertSame(42, $result->regionId);
        $this->assertSame(7, $result->leaderPeerId);
        $this->assertSame(3, $result->leaderStoreId);
        $this->assertSame(1, $result->epochConfVer);
        $this->assertSame(10, $result->epochVersion);
    }

    public function testClusterIdMismatchRetries(): void
    {
        $header = new ResponseHeader();
        $header->setClusterId(999);

        $region = new Region();
        $region->setId(1);
        $epoch = new RegionEpoch();
        $epoch->setConfVer(1);
        $epoch->setVersion(1);
        $region->setRegionEpoch($epoch);

        $leader = new Peer();
        $leader->setId(1);
        $leader->setStoreId(1);

        $response = new GetRegionResponse();
        $response->setHeader($header);
        $response->setRegion($region);
        $response->setLeader($leader);

        $grpc = $this->createMock(GrpcClientInterface::class);
        $grpc->expects($this->exactly(2))
            ->method('call')
            ->willReturnCallback(function () use ($response): Message {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    throw new GrpcException('mismatch cluster id, need 999 but got 0', 2);
                }
                return $response;
            });

        $client = new PdClient($grpc, 'pd:2379');
        $result = $client->getRegion('key');

        $this->assertSame(1, $result->regionId);
    }

    public function testCloseClosesGrpc(): void
    {
        $grpc = $this->createMock(GrpcClientInterface::class);
        $grpc->expects($this->once())->method('close');

        $client = new PdClient($grpc, 'pd:2379');
        $client->close();
    }
}
