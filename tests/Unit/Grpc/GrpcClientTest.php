<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Grpc;

use CrazyGoat\TiKV\Grpc\GrpcClient;
use PHPUnit\Framework\TestCase;

class GrpcClientTest extends TestCase
{
    private ?GrpcClient $client = null;
    
    protected function setUp(): void
    {
        // Skip tests if gRPC extension is not available
        if (!extension_loaded('grpc')) {
            $this->markTestSkipped('gRPC extension not available');
        }
        $this->client = new GrpcClient();
    }
    
    protected function tearDown(): void
    {
        if ($this->client !== null) {
            $this->client->close();
        }
    }
    
    public function testClientCanBeCreated(): void
    {
        $this->assertInstanceOf(GrpcClient::class, $this->client);
    }
    
    public function testClientCanBeClosed(): void
    {
        $this->client->close();
        $this->assertTrue(true); // No exception means success
    }
    
    public function testCallWithInvalidAddressThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        
        $request = new \Kvrpcpb\RawGetRequest();
        $request->setKey('test');
        
        $this->client->call(
            'invalid-address:99999',
            'tikvpb.Tikv',
            'RawGet',
            $request,
            \Kvrpcpb\RawGetResponse::class
        );
    }
}
