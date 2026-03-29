<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\RawKv;

use CrazyGoat\TiKV\Client\Connection\PdClient;
use CrazyGoat\TiKV\Client\RawKv\RawKvClient;
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
    
    public function testBatchGetThrowsWhenClosed(): void
    {
        $pdClient = $this->createMock(PdClient::class);
        $client = new RawKvClient($pdClient);
        $client->close();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Client is closed');
        
        $client->batchGet(['key1', 'key2']);
    }
    
    public function testBatchPutThrowsWhenClosed(): void
    {
        $pdClient = $this->createMock(PdClient::class);
        $client = new RawKvClient($pdClient);
        $client->close();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Client is closed');
        
        $client->batchPut(['key1' => 'value1', 'key2' => 'value2']);
    }
    
    public function testBatchDeleteThrowsWhenClosed(): void
    {
        $pdClient = $this->createMock(PdClient::class);
        $client = new RawKvClient($pdClient);
        $client->close();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Client is closed');
        
        $client->batchDelete(['key1', 'key2']);
    }
    
    public function testScanThrowsWhenClosed(): void
    {
        $pdClient = $this->createMock(PdClient::class);
        $client = new RawKvClient($pdClient);
        $client->close();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Client is closed');
        
        $client->scan('start', 'end');
    }
    
    public function testScanPrefixThrowsWhenClosed(): void
    {
        $pdClient = $this->createMock(PdClient::class);
        $client = new RawKvClient($pdClient);
        $client->close();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Client is closed');
        
        $client->scanPrefix('prefix');
    }
    
    public function testReverseScanThrowsWhenClosed(): void
    {
        $pdClient = $this->createMock(PdClient::class);
        $client = new RawKvClient($pdClient);
        $client->close();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Client is closed');
        
        $client->reverseScan('start', 'end');
    }
    
    public function testDeleteRangeThrowsWhenClosed(): void
    {
        $pdClient = $this->createMock(PdClient::class);
        $client = new RawKvClient($pdClient);
        $client->close();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Client is closed');
        
        $client->deleteRange('start', 'end');
    }
    
    public function testDeletePrefixThrowsWhenClosed(): void
    {
        $pdClient = $this->createMock(PdClient::class);
        $client = new RawKvClient($pdClient);
        $client->close();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Client is closed');
        
        $client->deletePrefix('prefix');
    }
    
    public function testDeletePrefixThrowsOnEmptyPrefix(): void
    {
        $pdClient = $this->createMock(PdClient::class);
        $client = new RawKvClient($pdClient);
        
        $this->expectException(\InvalidArgumentException::class);
        
        $client->deletePrefix('');
    }
    
    public function testGetKeyTTLThrowsWhenClosed(): void
    {
        $pdClient = $this->createMock(PdClient::class);
        $client = new RawKvClient($pdClient);
        $client->close();
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Client is closed');
        
        $client->getKeyTTL('key');
    }
}
