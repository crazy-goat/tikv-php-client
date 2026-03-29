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
        $testKeys = [
            'test-key', 'test-key-1', 'test-key-2', 'hello',
            'batch-key-1', 'batch-key-2', 'batch-key-3',
            'scan-a', 'scan-b', 'scan-c',
            'scan-limit-1', 'scan-limit-2', 'scan-limit-3',
            'scan-keyonly',
            'user:alice', 'user:bob', 'user:charlie', 'other:data',
            'pref-limit-1', 'pref-limit-2', 'pref-limit-3',
            'rev-z', 'rev-y', 'rev-x',
            'rev-limit-a', 'rev-limit-b', 'rev-limit-c',
            'consist-1', 'consist-2', 'consist-3',
        ];
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
    
    public function testBatchPutAndBatchGet(): void
    {
        $pairs = [
            'batch-key-1' => 'value-1',
            'batch-key-2' => 'value-2',
            'batch-key-3' => 'value-3',
        ];
        
        self::$client->batchPut($pairs);
        
        $results = self::$client->batchGet(array_keys($pairs));
        
        $this->assertEquals($pairs, $results);
    }
    
    public function testBatchGetWithMissingKeys(): void
    {
        $existingKey = 'batch-key-1';
        $missingKey = 'non-existent-batch-key-' . uniqid();
        
        self::$client->put($existingKey, 'value-1');
        
        $results = self::$client->batchGet([$existingKey, $missingKey]);
        
        $this->assertEquals('value-1', $results[$existingKey]);
        $this->assertNull($results[$missingKey]);
    }
    
    public function testBatchGetReturnsKeysInOrder(): void
    {
        $pairs = [
            'batch-key-1' => 'value-1',
            'batch-key-2' => 'value-2',
        ];
        
        self::$client->batchPut($pairs);
        
        // Request in reverse order
        $results = self::$client->batchGet(['batch-key-2', 'batch-key-1']);
        
        $this->assertEquals(['batch-key-2' => 'value-2', 'batch-key-1' => 'value-1'], $results);
    }
    
    public function testBatchGetEmptyArray(): void
    {
        $results = self::$client->batchGet([]);
        
        $this->assertEquals([], $results);
    }
    
    public function testBatchPutEmptyArray(): void
    {
        // Should not throw
        self::$client->batchPut([]);
        
        $this->assertTrue(true);
    }
    
    public function testBatchDelete(): void
    {
        $pairs = [
            'batch-key-1' => 'value-1',
            'batch-key-2' => 'value-2',
        ];
        
        self::$client->batchPut($pairs);
        
        // Verify keys exist
        $results = self::$client->batchGet(array_keys($pairs));
        $this->assertEquals($pairs, $results);
        
        // Delete keys
        self::$client->batchDelete(array_keys($pairs));
        
        // Verify keys are deleted
        $results = self::$client->batchGet(array_keys($pairs));
        $this->assertNull($results['batch-key-1']);
        $this->assertNull($results['batch-key-2']);
    }
    
    public function testBatchDeleteNonExistentKeys(): void
    {
        $keys = [
            'non-existent-key-' . uniqid(),
            'non-existent-key-' . uniqid(),
        ];
        
        // Should not throw
        self::$client->batchDelete($keys);
        
        $this->assertTrue(true);
    }
    
    public function testBatchDeleteEmptyArray(): void
    {
        // Should not throw
        self::$client->batchDelete([]);
        
        $this->assertTrue(true);
    }
    
    public function testBatchPutOverwritesExistingKeys(): void
    {
        $key = 'batch-key-1';
        
        self::$client->put($key, 'old-value');
        $this->assertEquals('old-value', self::$client->get($key));
        
        self::$client->batchPut([$key => 'new-value']);
        $this->assertEquals('new-value', self::$client->get($key));
    }
    
    public function testBatchOperationsWithBinaryData(): void
    {
        $pairs = [
            'batch-key-1' => "\x00\x01\x02\x03",
            'batch-key-2' => "\xff\xfe\xfd\xfc",
        ];
        
        self::$client->batchPut($pairs);
        $results = self::$client->batchGet(array_keys($pairs));
        
        $this->assertEquals($pairs, $results);
    }
    
    public function testScan(): void
    {
        // Insert test data
        $pairs = [
            'scan-a' => 'value-a',
            'scan-b' => 'value-b',
            'scan-c' => 'value-c',
        ];
        self::$client->batchPut($pairs);
        
        // Scan the range
        $results = self::$client->scan('scan-a', 'scan-d');
        
        $this->assertCount(3, $results);
        $this->assertEquals('scan-a', $results[0]['key']);
        $this->assertEquals('value-a', $results[0]['value']);
        $this->assertEquals('scan-b', $results[1]['key']);
        $this->assertEquals('scan-c', $results[2]['key']);
    }
    
    public function testScanWithLimit(): void
    {
        // Insert test data
        $pairs = [
            'scan-limit-1' => 'value-1',
            'scan-limit-2' => 'value-2',
            'scan-limit-3' => 'value-3',
        ];
        self::$client->batchPut($pairs);
        
        // Scan with limit
        $results = self::$client->scan('scan-limit-', 'scan-limit.', 2);
        
        $this->assertCount(2, $results);
    }
    
    public function testScanKeyOnly(): void
    {
        // Insert test data
        self::$client->put('scan-keyonly', 'secret-value');
        
        // Scan key only
        $results = self::$client->scan('scan-keyonly', 'scan-keyonly.', 0, true);
        
        $this->assertCount(1, $results);
        $this->assertEquals('scan-keyonly', $results[0]['key']);
        $this->assertNull($results[0]['value']);
    }
    
    public function testScanEmptyRange(): void
    {
        $results = self::$client->scan('non-existent-prefix-', 'non-existent-prefix.');
        
        $this->assertCount(0, $results);
        $this->assertEquals([], $results);
    }
    
    public function testScanPrefix(): void
    {
        // Insert test data with prefix
        $pairs = [
            'user:alice' => 'Alice',
            'user:bob' => 'Bob',
            'user:charlie' => 'Charlie',
            'other:data' => 'Other',
        ];
        self::$client->batchPut($pairs);
        
        // Scan prefix
        $results = self::$client->scanPrefix('user:');
        
        $this->assertCount(3, $results);
        $keys = array_column($results, 'key');
        $this->assertContains('user:alice', $keys);
        $this->assertContains('user:bob', $keys);
        $this->assertContains('user:charlie', $keys);
        $this->assertNotContains('other:data', $keys);
    }
    
    public function testScanPrefixWithLimit(): void
    {
        // Insert test data
        $pairs = [
            'pref-limit-1' => 'value-1',
            'pref-limit-2' => 'value-2',
            'pref-limit-3' => 'value-3',
        ];
        self::$client->batchPut($pairs);
        
        // Scan prefix with limit
        $results = self::$client->scanPrefix('pref-limit-', 2);
        
        $this->assertCount(2, $results);
    }
    
    public function testReverseScan(): void
    {
        // Insert test data
        $pairs = [
            'rev-z' => 'value-z',
            'rev-y' => 'value-y',
            'rev-x' => 'value-x',
        ];
        self::$client->batchPut($pairs);
        
        // Reverse scan
        $results = self::$client->reverseScan('rev-z', 'rev-w');
        
        $this->assertCount(3, $results);
        // Results should be in reverse order
        $this->assertEquals('rev-z', $results[0]['key']);
        $this->assertEquals('rev-y', $results[1]['key']);
        $this->assertEquals('rev-x', $results[2]['key']);
    }
    
    public function testReverseScanWithLimit(): void
    {
        // Insert test data
        $pairs = [
            'rev-limit-a' => 'value-a',
            'rev-limit-b' => 'value-b',
            'rev-limit-c' => 'value-c',
        ];
        self::$client->batchPut($pairs);
        
        // Reverse scan with limit
        $results = self::$client->reverseScan('rev-limit-c', 'rev-limit-', 2);
        
        $this->assertCount(2, $results);
        $this->assertEquals('rev-limit-c', $results[0]['key']);
        $this->assertEquals('rev-limit-b', $results[1]['key']);
    }
    
    public function testScanAndReverseScanConsistency(): void
    {
        // Insert test data
        $pairs = [
            'consist-1' => 'value-1',
            'consist-2' => 'value-2',
            'consist-3' => 'value-3',
        ];
        self::$client->batchPut($pairs);
        
        // Forward scan
        $forward = self::$client->scan('consist-', 'consist.');
        
        // Reverse scan
        $reverse = self::$client->reverseScan('consist-3', 'consist-');
        
        // Both should return same number of results
        $this->assertCount(3, $forward);
        $this->assertCount(3, $reverse);
        
        // Reverse should be opposite order
        $forwardKeys = array_column($forward, 'key');
        $reverseKeys = array_column($reverse, 'key');
        $this->assertEquals(array_reverse($forwardKeys), $reverseKeys);
    }
}
