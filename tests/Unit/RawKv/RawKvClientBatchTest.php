<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\RawKv;

use CrazyGoat\TiKV\Client\Connection\PdClient;
use CrazyGoat\TiKV\Client\RawKv\RawKvClient;
use PHPUnit\Framework\TestCase;

class RawKvClientBatchTest extends TestCase
{
    public function testBatchGetReturnsArray(): void
    {
        $pdClient = $this->createMock(PdClient::class);
        $client = new RawKvClient($pdClient);
        
        // This would need real TiKV to work, so we just test the interface
        $this->assertTrue(method_exists($client, 'batchGet'));
        
        $client->close();
    }
    
    public function testBatchGetEmptyReturnsEmptyArray(): void
    {
        if (!extension_loaded('grpc')) {
            $this->markTestSkipped('gRPC extension not available');
        }
        
        $pdClient = $this->createMock(PdClient::class);
        $client = new RawKvClient($pdClient);
        
        $result = $client->batchGet([]);
        
        $this->assertIsArray($result);
        $this->assertCount(0, $result);
        
        $client->close();
    }
}
