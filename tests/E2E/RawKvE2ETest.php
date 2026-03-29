<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\E2E;

use CrazyGoat\TiKV\RawKv\RawKvClient;
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
}
