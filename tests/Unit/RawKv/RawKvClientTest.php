<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\RawKv;

use CrazyGoat\TiKV\Connection\PdClient;
use CrazyGoat\TiKV\RawKv\RawKvClient;
use PHPUnit\Framework\TestCase;

class RawKvClientTest extends TestCase
{
    public function testClientCanBeCreated(): void
    {
        $pdClient = $this->createMock(PdClient::class);
        $client = new RawKvClient($pdClient);
        
        $this->assertInstanceOf(RawKvClient::class, $client);
        
        $client->close();
    }
    
    public function testCreateFactoryMethod(): void
    {
        // This would need real PD in integration test
        // For unit test, we just verify the method exists
        $this->assertTrue(method_exists(RawKvClient::class, 'create'));
    }
    
    public function testCloseCanBeCalledMultipleTimes(): void
    {
        $pdClient = $this->createMock(PdClient::class);
        $client = new RawKvClient($pdClient);
        
        $client->close();
        $client->close(); // Should not throw
        
        $this->assertTrue(true);
    }
    
    public function testOperationsThrowWhenClosed(): void
    {
        $pdClient = $this->createMock(PdClient::class);
        $client = new RawKvClient($pdClient);
        $client->close();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Client is closed');
        
        $client->get('key');
    }
}
