<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\E2E;

use CrazyGoat\TiKV\Client\RawKv\RawKvClient;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end tests requiring running TiKV cluster
 * Run with: docker-compose up --build php-client
 */
class RawKvE2ETest extends TestCase
{
    private static ?RawKvClient $client = null;
    
    public static function setUpBeforeClass(): void
    {
        $pdEndpoints = getenv('PD_ENDPOINTS') ? explode(',', getenv('PD_ENDPOINTS')) : ['pd:2379'];
        self::$client = RawKvClient::create($pdEndpoints);
    }
    
    public static function tearDownAfterClass(): void
    {
        if (self::$client !== null) {
            self::$client->close();
            self::$client = null;
        }
    }
    
    protected function setUp(): void
    {
        if (self::$client === null) {
            $this->markTestSkipped('TiKV cluster not available');
        }
    }
    
    protected function tearDown(): void
    {
        // Clean up test keys after each test
        $testKeys = ['test-key', 'test-key-1', 'test-key-2', 'hello'];
        foreach ($testKeys as $key) {
            try {
                self::$client->delete($key);
            } catch (\Exception $e) {
                // Ignore errors during cleanup
            }
        }
    }
    
    public function testPutAndGet(): void
    {
        $key = 'test-key';
        $value = 'test-value';
        
        self::$client->put($key, $value);
        $result = self::$client->get($key);
        
        $this->assertEquals($value, $result);
    }
    
    public function testGetNonExistentKey(): void
    {
        $key = 'non-existent-key-' . uniqid();
        $result = self::$client->get($key);
        
        $this->assertNull($result);
    }
    
    public function testPutOverwrite(): void
    {
        $key = 'test-key';
        $value1 = 'value-1';
        $value2 = 'value-2';
        
        self::$client->put($key, $value1);
        self::$client->put($key, $value2);
        
        $result = self::$client->get($key);
        $this->assertEquals($value2, $result);
    }
    
    public function testDelete(): void
    {
        $key = 'test-key';
        $value = 'test-value';
        
        self::$client->put($key, $value);
        $this->assertEquals($value, self::$client->get($key));
        
        self::$client->delete($key);
        $this->assertNull(self::$client->get($key));
    }
    
    public function testDeleteNonExistentKey(): void
    {
        $key = 'non-existent-key-' . uniqid();
        
        // Should not throw
        self::$client->delete($key);
        
        $this->assertTrue(true);
    }
    
    public function testMultipleKeys(): void
    {
        $keys = ['test-key-1', 'test-key-2', 'test-key-3'];
        $values = ['value-1', 'value-2', 'value-3'];
        
        foreach ($keys as $i => $key) {
            self::$client->put($key, $values[$i]);
        }
        
        foreach ($keys as $i => $key) {
            $this->assertEquals($values[$i], self::$client->get($key));
        }
    }
    
    public function testBinaryData(): void
    {
        $key = 'binary-key';
        $value = "\x00\x01\x02\x03\xff\xfe\xfd\xfc";
        
        self::$client->put($key, $value);
        $result = self::$client->get($key);
        
        $this->assertEquals($value, $result);
    }
    
    public function testLargeValue(): void
    {
        $key = 'large-key';
        $value = str_repeat('x', 1024 * 1024); // 1MB
        
        self::$client->put($key, $value);
        $result = self::$client->get($key);
        
        $this->assertEquals($value, $result);
    }
    
    public function testEmptyValue(): void
    {
        $key = 'empty-key';
        $value = '';
        
        self::$client->put($key, $value);
        $result = self::$client->get($key);
        
        $this->assertEquals($value, $result);
    }
    
    public function testBatchGet(): void
    {
        $keys = ['batch-key-1', 'batch-key-2', 'batch-key-3'];
        $values = ['value-1', 'value-2', 'value-3'];
        
        // Put values
        foreach ($keys as $i => $key) {
            self::$client->put($key, $values[$i]);
        }
        
        // Batch get
        $results = self::$client->batchGet($keys);
        
        $this->assertCount(3, $results);
        $this->assertEquals($values[0], $results[0]);
        $this->assertEquals($values[1], $results[1]);
        $this->assertEquals($values[2], $results[2]);
    }
    
    public function testBatchGetWithMissingKeys(): void
    {
        // Put only 2 out of 3 keys
        self::$client->put('existing-1', 'value-1');
        self::$client->put('existing-2', 'value-2');
        
        // Batch get including missing key
        $results = self::$client->batchGet(['existing-1', 'missing-key', 'existing-2']);
        
        $this->assertCount(3, $results);
        $this->assertEquals('value-1', $results[0]);
        $this->assertNull($results[1]);
        $this->assertEquals('value-2', $results[2]);
    }
    
    public function testBatchGetEmpty(): void
    {
        $results = self::$client->batchGet([]);
        
        $this->assertCount(0, $results);
        $this->assertIsArray($results);
    }
    
    public function testBatchGetSingleKey(): void
    {
        self::$client->put('single-batch-key', 'single-value');
        
        $results = self::$client->batchGet(['single-batch-key']);
        
        $this->assertCount(1, $results);
        $this->assertEquals('single-value', $results[0]);
    }
}
