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
use CrazyGoat\TiKV\Client\RawKv\Dto\PeerInfo;
use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;
use Google\Protobuf\Internal\Message;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

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

    public function testGetRegionLogsGrpcCall(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with('PD gRPC call', ['method' => 'GetRegion', 'address' => 'pd1:2379']);

        $response = $this->makeGetRegionResponse();

        $grpc = $this->createMock(GrpcClientInterface::class);
        $grpc->expects($this->once())
            ->method('call')
            ->willReturn($response);

        $client = new PdClient($grpc, 'pd1:2379', $logger);
        $client->getRegion('key');
    }

    public function testGetRegionLogsClusterIdLearned(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Learned cluster ID', ['clusterId' => 12345]);

        $response = $this->makeGetRegionResponse();

        $grpc = $this->createMock(GrpcClientInterface::class);
        $grpc->expects($this->once())
            ->method('call')
            ->willReturn($response);

        $client = new PdClient($grpc, 'pd:2379', $logger);
        $client->getRegion('key');
    }

    public function testClusterIdMismatchLogsWarning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Cluster ID mismatch, retrying', ['method' => 'GetRegion', 'clusterId' => 99]);

        $response = $this->makeGetRegionResponse();

        $grpc = $this->createMock(GrpcClientInterface::class);
        $grpc->expects($this->exactly(2))
            ->method('call')
            ->willReturnCallback(function () use ($response): Message {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    throw new GrpcException('mismatch cluster id, need 99 but got 0', 2);
                }
                return $response;
            });

        $client = new PdClient($grpc, 'pd:2379', $logger);
        $client->getRegion('key');
    }

    public function testGetRegionPopulatesPeers(): void
    {
        $epoch = new RegionEpoch();
        $epoch->setConfVer(1);
        $epoch->setVersion(1);

        $peer1 = new Peer();
        $peer1->setId(10);
        $peer1->setStoreId(1);

        $peer2 = new Peer();
        $peer2->setId(20);
        $peer2->setStoreId(2);

        $peer3 = new Peer();
        $peer3->setId(30);
        $peer3->setStoreId(3);

        $region = new Region();
        $region->setId(42);
        $region->setRegionEpoch($epoch);
        $region->setPeers([$peer1, $peer2, $peer3]);

        $leader = new Peer();
        $leader->setId(10);
        $leader->setStoreId(1);

        $header = new ResponseHeader();
        $header->setClusterId(100);

        $response = new GetRegionResponse();
        $response->setHeader($header);
        $response->setRegion($region);
        $response->setLeader($leader);

        $grpc = $this->createMock(GrpcClientInterface::class);
        $grpc->method('call')->willReturn($response);

        $client = new PdClient($grpc, 'pd:2379');
        $result = $client->getRegion('key');

        $this->assertCount(3, $result->peers);
        $this->assertInstanceOf(PeerInfo::class, $result->peers[0]);
        $this->assertSame(10, $result->peers[0]->peerId);
        $this->assertSame(1, $result->peers[0]->storeId);
        $this->assertSame(20, $result->peers[1]->peerId);
        $this->assertSame(2, $result->peers[1]->storeId);
        $this->assertSame(30, $result->peers[2]->peerId);
        $this->assertSame(3, $result->peers[2]->storeId);
    }

    public function testGetRegionReturnsEmptyPeersWhenNoPeersInResponse(): void
    {
        $response = $this->makeGetRegionResponse();

        $grpc = $this->createMock(GrpcClientInterface::class);
        $grpc->method('call')->willReturn($response);

        $client = new PdClient($grpc, 'pd:2379');
        $result = $client->getRegion('key');

        $this->assertSame([], $result->peers);
    }

    private function makeGetRegionResponse(): GetRegionResponse
    {
        $epoch = new RegionEpoch();
        $epoch->setConfVer(1);
        $epoch->setVersion(1);

        $region = new Region();
        $region->setId(1);
        $region->setStartKey('');
        $region->setEndKey('');
        $region->setRegionEpoch($epoch);

        $leader = new Peer();
        $leader->setId(1);
        $leader->setStoreId(1);

        $header = new ResponseHeader();
        $header->setClusterId(12345);

        $response = new GetRegionResponse();
        $response->setHeader($header);
        $response->setRegion($region);
        $response->setLeader($leader);

        return $response;
    }
}
