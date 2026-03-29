<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\E2E;

use CrazyGoat\TiKV\Client\RawKv\CasResult;
use CrazyGoat\TiKV\Client\RawKv\RawKvClient;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end tests requiring running TiKV cluster.
 * 
 * Run with: docker-compose --profile test up --build php-test
 * 
 * Reverse scan semantics (per kvrpcpb.proto and Go client-go):
 *   reverseScan(startKey, endKey) scans [endKey, startKey) in descending order
 *   - startKey = upper bound (exclusive)
 *   - endKey   = lower bound (inclusive)
 */
class RawKvE2ETest extends TestCase
{
    private static ?RawKvClient $client = null;
    
    /** @var string[] Keys created during the current test, cleaned up in tearDown */
    private array $keysToCleanup = [];
    
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
        $this->keysToCleanup = [];
    }
    
    protected function tearDown(): void
    {
        foreach ($this->keysToCleanup as $key) {
            try {
                self::$client->delete($key);
            } catch (\Exception $e) {
                // Ignore errors during cleanup
            }
        }
    }
    
    /**
     * Helper: put key-value pairs and register them for cleanup.
     * 
     * @param array<string, string> $pairs
     */
    private function putAndTrack(array $pairs): void
    {
        self::$client->batchPut($pairs);
        foreach (array_keys($pairs) as $key) {
            $this->keysToCleanup[] = $key;
        }
    }
    
    /**
     * Helper: put a single key-value pair and register for cleanup.
     */
    private function putOneAndTrack(string $key, string $value): void
    {
        self::$client->put($key, $value);
        $this->keysToCleanup[] = $key;
    }
    
    // ========================================================================
    // Basic CRUD
    // ========================================================================
    
    public function testPutAndGet(): void
    {
        $this->putOneAndTrack('test-key', 'test-value');
        
        $this->assertEquals('test-value', self::$client->get('test-key'));
    }
    
    public function testGetNonExistentKey(): void
    {
        $this->assertNull(self::$client->get('non-existent-key-' . uniqid()));
    }
    
    public function testPutOverwrite(): void
    {
        $this->putOneAndTrack('test-key', 'value-1');
        $this->putOneAndTrack('test-key', 'value-2');
        
        $this->assertEquals('value-2', self::$client->get('test-key'));
    }
    
    public function testDelete(): void
    {
        $this->putOneAndTrack('test-key', 'test-value');
        $this->assertEquals('test-value', self::$client->get('test-key'));
        
        self::$client->delete('test-key');
        $this->assertNull(self::$client->get('test-key'));
    }
    
    public function testDeleteNonExistentKey(): void
    {
        // Should not throw
        self::$client->delete('non-existent-key-' . uniqid());
        $this->assertTrue(true);
    }
    
    public function testMultipleKeys(): void
    {
        $pairs = ['test-key-1' => 'value-1', 'test-key-2' => 'value-2'];
        $this->putAndTrack($pairs);
        
        foreach ($pairs as $key => $value) {
            $this->assertEquals($value, self::$client->get($key));
        }
    }
    
    public function testBinaryData(): void
    {
        $key = 'binary-key';
        $value = "\x00\x01\x02\x03\xff\xfe\xfd\xfc";
        
        $this->putOneAndTrack($key, $value);
        $this->assertEquals($value, self::$client->get($key));
    }
    
    public function testLargeValue(): void
    {
        $key = 'large-key';
        $value = str_repeat('x', 1024 * 1024); // 1MB
        
        $this->putOneAndTrack($key, $value);
        $this->assertEquals($value, self::$client->get($key));
    }
    
    public function testEmptyValue(): void
    {
        $key = 'empty-key';
        $this->putOneAndTrack($key, '');
        
        $this->assertEquals('', self::$client->get($key));
    }
    
    // ========================================================================
    // Batch operations
    // ========================================================================
    
    public function testBatchPutAndBatchGet(): void
    {
        $pairs = [
            'batch-key-1' => 'value-1',
            'batch-key-2' => 'value-2',
            'batch-key-3' => 'value-3',
        ];
        $this->putAndTrack($pairs);
        
        $results = self::$client->batchGet(array_keys($pairs));
        $this->assertEquals($pairs, $results);
    }
    
    public function testBatchGetWithMissingKeys(): void
    {
        $this->putOneAndTrack('batch-key-1', 'value-1');
        $missingKey = 'non-existent-batch-key-' . uniqid();
        
        $results = self::$client->batchGet(['batch-key-1', $missingKey]);
        
        $this->assertEquals('value-1', $results['batch-key-1']);
        $this->assertNull($results[$missingKey]);
    }
    
    public function testBatchGetReturnsKeysInOrder(): void
    {
        $pairs = ['batch-key-1' => 'value-1', 'batch-key-2' => 'value-2'];
        $this->putAndTrack($pairs);
        
        // Request in reverse order
        $results = self::$client->batchGet(['batch-key-2', 'batch-key-1']);
        $this->assertEquals(['batch-key-2' => 'value-2', 'batch-key-1' => 'value-1'], $results);
    }
    
    public function testBatchGetEmptyArray(): void
    {
        $this->assertEquals([], self::$client->batchGet([]));
    }
    
    public function testBatchPutEmptyArray(): void
    {
        self::$client->batchPut([]);
        $this->assertTrue(true);
    }
    
    public function testBatchDelete(): void
    {
        $pairs = ['batch-key-1' => 'value-1', 'batch-key-2' => 'value-2'];
        $this->putAndTrack($pairs);
        
        $results = self::$client->batchGet(array_keys($pairs));
        $this->assertEquals($pairs, $results);
        
        self::$client->batchDelete(array_keys($pairs));
        
        $results = self::$client->batchGet(array_keys($pairs));
        $this->assertNull($results['batch-key-1']);
        $this->assertNull($results['batch-key-2']);
    }
    
    public function testBatchDeleteNonExistentKeys(): void
    {
        self::$client->batchDelete(['non-existent-key-' . uniqid(), 'non-existent-key-' . uniqid()]);
        $this->assertTrue(true);
    }
    
    public function testBatchDeleteEmptyArray(): void
    {
        self::$client->batchDelete([]);
        $this->assertTrue(true);
    }
    
    public function testBatchPutOverwritesExistingKeys(): void
    {
        $this->putOneAndTrack('batch-key-1', 'old-value');
        $this->assertEquals('old-value', self::$client->get('batch-key-1'));
        
        self::$client->batchPut(['batch-key-1' => 'new-value']);
        $this->assertEquals('new-value', self::$client->get('batch-key-1'));
    }
    
    public function testBatchOperationsWithBinaryData(): void
    {
        $pairs = [
            'batch-key-1' => "\x00\x01\x02\x03",
            'batch-key-2' => "\xff\xfe\xfd\xfc",
        ];
        $this->putAndTrack($pairs);
        
        $this->assertEquals($pairs, self::$client->batchGet(array_keys($pairs)));
    }
    
    // ========================================================================
    // Forward scan
    // ========================================================================
    
    public function testScan(): void
    {
        $pairs = ['scan-a' => 'value-a', 'scan-b' => 'value-b', 'scan-c' => 'value-c'];
        $this->putAndTrack($pairs);
        
        $results = self::$client->scan('scan-a', 'scan-d');
        
        $this->assertCount(3, $results);
        $this->assertEquals('scan-a', $results[0]['key']);
        $this->assertEquals('value-a', $results[0]['value']);
        $this->assertEquals('scan-b', $results[1]['key']);
        $this->assertEquals('scan-c', $results[2]['key']);
    }
    
    public function testScanStartKeyIsInclusive(): void
    {
        $pairs = ['scan-inc-a' => 'va', 'scan-inc-b' => 'vb', 'scan-inc-c' => 'vc'];
        $this->putAndTrack($pairs);
        
        // Start exactly at 'scan-inc-a' — it should be included
        $results = self::$client->scan('scan-inc-a', 'scan-inc-d');
        $keys = array_column($results, 'key');
        
        $this->assertContains('scan-inc-a', $keys, 'startKey should be inclusive in forward scan');
    }
    
    public function testScanEndKeyIsExclusive(): void
    {
        $pairs = ['scan-exc-a' => 'va', 'scan-exc-b' => 'vb', 'scan-exc-c' => 'vc'];
        $this->putAndTrack($pairs);
        
        // End exactly at 'scan-exc-c' — it should NOT be included
        $results = self::$client->scan('scan-exc-a', 'scan-exc-c');
        $keys = array_column($results, 'key');
        
        $this->assertContains('scan-exc-a', $keys);
        $this->assertContains('scan-exc-b', $keys);
        $this->assertNotContains('scan-exc-c', $keys, 'endKey should be exclusive in forward scan');
    }
    
    public function testScanWithLimit(): void
    {
        $pairs = ['scan-limit-1' => 'v1', 'scan-limit-2' => 'v2', 'scan-limit-3' => 'v3'];
        $this->putAndTrack($pairs);
        
        $results = self::$client->scan('scan-limit-', 'scan-limit.', 2);
        
        $this->assertCount(2, $results);
        $this->assertEquals('scan-limit-1', $results[0]['key']);
        $this->assertEquals('scan-limit-2', $results[1]['key']);
    }
    
    public function testScanLimitZeroReturnsAll(): void
    {
        $pairs = ['scan-all-a' => 'va', 'scan-all-b' => 'vb', 'scan-all-c' => 'vc'];
        $this->putAndTrack($pairs);
        
        $results = self::$client->scan('scan-all-', 'scan-all.', 0);
        
        $this->assertCount(3, $results);
    }
    
    public function testScanLimitOne(): void
    {
        $pairs = ['scan-one-a' => 'va', 'scan-one-b' => 'vb'];
        $this->putAndTrack($pairs);
        
        $results = self::$client->scan('scan-one-', 'scan-one.', 1);
        
        $this->assertCount(1, $results);
        $this->assertEquals('scan-one-a', $results[0]['key']);
    }
    
    public function testScanKeyOnly(): void
    {
        $this->putOneAndTrack('scan-keyonly', 'secret-value');
        
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
    
    public function testScanReturnsAscendingOrder(): void
    {
        // Insert in random order
        $this->putOneAndTrack('scan-ord-c', 'vc');
        $this->putOneAndTrack('scan-ord-a', 'va');
        $this->putOneAndTrack('scan-ord-b', 'vb');
        
        $results = self::$client->scan('scan-ord-', 'scan-ord.');
        $keys = array_column($results, 'key');
        
        $this->assertEquals(['scan-ord-a', 'scan-ord-b', 'scan-ord-c'], $keys);
    }
    
    public function testScanWithBinaryKeys(): void
    {
        // Keys with binary content, lexicographically ordered
        $k1 = "scan-bin-\x01";
        $k2 = "scan-bin-\x02";
        $k3 = "scan-bin-\x03";
        
        $this->putOneAndTrack($k1, 'v1');
        $this->putOneAndTrack($k2, 'v2');
        $this->putOneAndTrack($k3, 'v3');
        
        $results = self::$client->scan("scan-bin-\x00", "scan-bin-\x04");
        
        $this->assertCount(3, $results);
        $this->assertEquals($k1, $results[0]['key']);
        $this->assertEquals($k2, $results[1]['key']);
        $this->assertEquals($k3, $results[2]['key']);
    }
    
    public function testScanSingleKeyRange(): void
    {
        $this->putOneAndTrack('scan-single', 'val');
        
        // Range that contains exactly one key: [scan-single, scan-single\x00)
        $results = self::$client->scan('scan-single', "scan-single\x00");
        
        $this->assertCount(1, $results);
        $this->assertEquals('scan-single', $results[0]['key']);
    }
    
    // ========================================================================
    // Scan prefix
    // ========================================================================
    
    public function testScanPrefix(): void
    {
        $pairs = [
            'user:alice' => 'Alice',
            'user:bob' => 'Bob',
            'user:charlie' => 'Charlie',
            'other:data' => 'Other',
        ];
        $this->putAndTrack($pairs);
        
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
        $pairs = ['pref-limit-1' => 'v1', 'pref-limit-2' => 'v2', 'pref-limit-3' => 'v3'];
        $this->putAndTrack($pairs);
        
        $results = self::$client->scanPrefix('pref-limit-', 2);
        
        $this->assertCount(2, $results);
    }
    
    public function testScanPrefixNoMatches(): void
    {
        $results = self::$client->scanPrefix('zzz-no-match-' . uniqid());
        
        $this->assertCount(0, $results);
    }
    
    public function testScanPrefixKeyOnly(): void
    {
        $pairs = ['pref-ko-a' => 'secret-a', 'pref-ko-b' => 'secret-b'];
        $this->putAndTrack($pairs);
        
        $results = self::$client->scanPrefix('pref-ko-', 0, true);
        
        $this->assertCount(2, $results);
        foreach ($results as $result) {
            $this->assertNull($result['value'], 'keyOnly scan should return null values');
        }
    }
    
    // ========================================================================
    // Reverse scan — native TiKV reverse=true
    //
    // Protocol semantics (kvrpcpb.proto):
    //   reverseScan(startKey, endKey) scans [endKey, startKey) in descending order
    //   startKey = upper bound (exclusive)
    //   endKey   = lower bound (inclusive)
    // ========================================================================
    
    public function testReverseScanBasic(): void
    {
        $pairs = ['rev-x' => 'value-x', 'rev-y' => 'value-y', 'rev-z' => 'value-z'];
        $this->putAndTrack($pairs);
        
        // reverseScan(startKey='rev-z\x00', endKey='rev-w')
        // scans [rev-w, rev-z\x00) in descending order => rev-z, rev-y, rev-x
        $results = self::$client->reverseScan("rev-z\x00", 'rev-w');
        
        $this->assertCount(3, $results);
        $this->assertEquals('rev-z', $results[0]['key']);
        $this->assertEquals('value-z', $results[0]['value']);
        $this->assertEquals('rev-y', $results[1]['key']);
        $this->assertEquals('rev-x', $results[2]['key']);
    }
    
    public function testReverseScanStartKeyIsExclusive(): void
    {
        $pairs = ['rev-exc-a' => 'va', 'rev-exc-b' => 'vb', 'rev-exc-c' => 'vc'];
        $this->putAndTrack($pairs);
        
        // startKey='rev-exc-c' is exclusive, so 'rev-exc-c' should NOT appear
        $results = self::$client->reverseScan('rev-exc-c', 'rev-exc-a');
        $keys = array_column($results, 'key');
        
        $this->assertNotContains('rev-exc-c', $keys, 'startKey should be exclusive in reverse scan');
        $this->assertContains('rev-exc-b', $keys);
        $this->assertContains('rev-exc-a', $keys);
    }
    
    public function testReverseScanEndKeyIsInclusive(): void
    {
        $pairs = ['rev-inc-a' => 'va', 'rev-inc-b' => 'vb', 'rev-inc-c' => 'vc'];
        $this->putAndTrack($pairs);
        
        // endKey='rev-inc-a' is inclusive, so 'rev-inc-a' SHOULD appear
        $results = self::$client->reverseScan("rev-inc-c\x00", 'rev-inc-a');
        $keys = array_column($results, 'key');
        
        $this->assertContains('rev-inc-a', $keys, 'endKey should be inclusive in reverse scan');
    }
    
    public function testReverseScanWithLimit(): void
    {
        $pairs = ['rev-lim-a' => 'va', 'rev-lim-b' => 'vb', 'rev-lim-c' => 'vc'];
        $this->putAndTrack($pairs);
        
        // Scan from above 'rev-lim-c' down to 'rev-lim-', limit 2
        $results = self::$client->reverseScan("rev-lim-c\x00", 'rev-lim-', 2);
        
        $this->assertCount(2, $results);
        $this->assertEquals('rev-lim-c', $results[0]['key']);
        $this->assertEquals('rev-lim-b', $results[1]['key']);
    }
    
    public function testReverseScanLimitOne(): void
    {
        $pairs = ['rev-l1-a' => 'va', 'rev-l1-b' => 'vb', 'rev-l1-c' => 'vc'];
        $this->putAndTrack($pairs);
        
        $results = self::$client->reverseScan("rev-l1-c\x00", 'rev-l1-', 1);
        
        $this->assertCount(1, $results);
        $this->assertEquals('rev-l1-c', $results[0]['key']);
    }
    
    public function testReverseScanEmptyRange(): void
    {
        $results = self::$client->reverseScan('zzz-no-match.', 'zzz-no-match-');
        
        $this->assertCount(0, $results);
    }
    
    public function testReverseScanKeyOnly(): void
    {
        $pairs = ['rev-ko-a' => 'secret-a', 'rev-ko-b' => 'secret-b'];
        $this->putAndTrack($pairs);
        
        $results = self::$client->reverseScan("rev-ko-b\x00", 'rev-ko-a', 0, true);
        
        $this->assertCount(2, $results);
        $this->assertEquals('rev-ko-b', $results[0]['key']);
        $this->assertNull($results[0]['value'], 'keyOnly reverse scan should return null values');
        $this->assertEquals('rev-ko-a', $results[1]['key']);
        $this->assertNull($results[1]['value']);
    }
    
    public function testReverseScanReturnsDescendingOrder(): void
    {
        // Insert in random order
        $this->putOneAndTrack('rev-ord-b', 'vb');
        $this->putOneAndTrack('rev-ord-d', 'vd');
        $this->putOneAndTrack('rev-ord-a', 'va');
        $this->putOneAndTrack('rev-ord-c', 'vc');
        
        $results = self::$client->reverseScan('rev-ord-e', 'rev-ord-');
        $keys = array_column($results, 'key');
        
        $this->assertEquals(
            ['rev-ord-d', 'rev-ord-c', 'rev-ord-b', 'rev-ord-a'],
            $keys,
            'Reverse scan must return keys in descending lexicographic order'
        );
    }
    
    public function testReverseScanWithBinaryKeys(): void
    {
        $k1 = "rev-bin-\x01";
        $k2 = "rev-bin-\x02";
        $k3 = "rev-bin-\x03";
        
        $this->putOneAndTrack($k1, 'v1');
        $this->putOneAndTrack($k2, 'v2');
        $this->putOneAndTrack($k3, 'v3');
        
        $results = self::$client->reverseScan("rev-bin-\x04", "rev-bin-\x00");
        
        $this->assertCount(3, $results);
        $this->assertEquals($k3, $results[0]['key']);
        $this->assertEquals($k2, $results[1]['key']);
        $this->assertEquals($k1, $results[2]['key']);
    }
    
    public function testReverseScanSingleKey(): void
    {
        $this->putOneAndTrack('rev-single', 'val');
        
        // Range [rev-single, rev-single\x00) contains exactly one key
        $results = self::$client->reverseScan("rev-single\x00", 'rev-single');
        
        $this->assertCount(1, $results);
        $this->assertEquals('rev-single', $results[0]['key']);
        $this->assertEquals('val', $results[0]['value']);
    }
    
    public function testReverseScanLimitZeroReturnsAll(): void
    {
        $pairs = ['rev-all-a' => 'va', 'rev-all-b' => 'vb', 'rev-all-c' => 'vc'];
        $this->putAndTrack($pairs);
        
        $results = self::$client->reverseScan('rev-all-d', 'rev-all-', 0);
        
        $this->assertCount(3, $results);
    }
    
    public function testReverseScanValuesAreCorrect(): void
    {
        $pairs = ['rev-val-a' => 'alpha', 'rev-val-b' => 'bravo', 'rev-val-c' => 'charlie'];
        $this->putAndTrack($pairs);
        
        $results = self::$client->reverseScan('rev-val-d', 'rev-val-');
        
        $this->assertCount(3, $results);
        // Verify each key has the correct value
        $resultMap = [];
        foreach ($results as $r) {
            $resultMap[$r['key']] = $r['value'];
        }
        $this->assertEquals('alpha', $resultMap['rev-val-a']);
        $this->assertEquals('bravo', $resultMap['rev-val-b']);
        $this->assertEquals('charlie', $resultMap['rev-val-c']);
    }
    
    // ========================================================================
    // Forward scan ↔ Reverse scan consistency
    // ========================================================================
    
    public function testScanAndReverseScanReturnSameKeys(): void
    {
        $pairs = [
            'consist-1' => 'value-1',
            'consist-2' => 'value-2',
            'consist-3' => 'value-3',
        ];
        $this->putAndTrack($pairs);
        
        // Forward scan [consist-, consist.)
        $forward = self::$client->scan('consist-', 'consist.');
        
        // Reverse scan [consist-, consist.) — same logical range, reversed
        $reverse = self::$client->reverseScan('consist.', 'consist-');
        
        $this->assertCount(3, $forward, 'Forward scan should find 3 keys');
        $this->assertCount(3, $reverse, 'Reverse scan should find 3 keys');
        
        $forwardKeys = array_column($forward, 'key');
        $reverseKeys = array_column($reverse, 'key');
        
        $this->assertEquals(
            array_reverse($forwardKeys),
            $reverseKeys,
            'Reverse scan keys should be the mirror of forward scan keys'
        );
    }
    
    public function testScanAndReverseScanReturnSameValues(): void
    {
        $pairs = [
            'val-consist-a' => 'alpha',
            'val-consist-b' => 'bravo',
            'val-consist-c' => 'charlie',
        ];
        $this->putAndTrack($pairs);
        
        $forward = self::$client->scan('val-consist-', 'val-consist.');
        $reverse = self::$client->reverseScan('val-consist.', 'val-consist-');
        
        // Build key=>value maps from both
        $forwardMap = [];
        foreach ($forward as $r) {
            $forwardMap[$r['key']] = $r['value'];
        }
        $reverseMap = [];
        foreach ($reverse as $r) {
            $reverseMap[$r['key']] = $r['value'];
        }
        
        $this->assertEquals($forwardMap, $reverseMap, 'Both scans should return identical key-value pairs');
    }
    
    public function testScanAndReverseScanWithLimitAreComplementary(): void
    {
        $pairs = [
            'comp-a' => 'va',
            'comp-b' => 'vb',
            'comp-c' => 'vc',
            'comp-d' => 'vd',
            'comp-e' => 've',
        ];
        $this->putAndTrack($pairs);
        
        // Forward scan first 2
        $forwardFirst2 = self::$client->scan('comp-', 'comp.', 2);
        // Reverse scan last 2
        $reverseLast2 = self::$client->reverseScan('comp.', 'comp-', 2);
        
        $forwardKeys = array_column($forwardFirst2, 'key');
        $reverseKeys = array_column($reverseLast2, 'key');
        
        $this->assertEquals(['comp-a', 'comp-b'], $forwardKeys, 'Forward limit=2 should return first 2');
        $this->assertEquals(['comp-e', 'comp-d'], $reverseKeys, 'Reverse limit=2 should return last 2');
        
        // They should not overlap
        $this->assertEmpty(
            array_intersect($forwardKeys, $reverseKeys),
            'First 2 forward and last 2 reverse should not overlap with 5 keys'
        );
    }
    
    // ========================================================================
    // Edge cases and boundary conditions
    // ========================================================================
    
    public function testScanAdjacentKeys(): void
    {
        // Keys that are lexicographically adjacent
        $this->putOneAndTrack("adj-\x00", 'v0');
        $this->putOneAndTrack("adj-\x01", 'v1');
        
        $results = self::$client->scan("adj-\x00", "adj-\x02");
        
        $this->assertCount(2, $results);
    }
    
    public function testReverseScanAdjacentKeys(): void
    {
        $this->putOneAndTrack("rev-adj-\x00", 'v0');
        $this->putOneAndTrack("rev-adj-\x01", 'v1');
        
        $results = self::$client->reverseScan("rev-adj-\x02", "rev-adj-\x00");
        
        $this->assertCount(2, $results);
        $this->assertEquals("rev-adj-\x01", $results[0]['key']);
        $this->assertEquals("rev-adj-\x00", $results[1]['key']);
    }
    
    public function testScanAfterDeleteReturnsNothing(): void
    {
        $this->putOneAndTrack('scan-del-a', 'va');
        $this->putOneAndTrack('scan-del-b', 'vb');
        
        self::$client->delete('scan-del-a');
        self::$client->delete('scan-del-b');
        
        $results = self::$client->scan('scan-del-', 'scan-del.');
        $this->assertCount(0, $results);
    }
    
    public function testReverseScanAfterDeleteReturnsNothing(): void
    {
        $this->putOneAndTrack('rev-del-a', 'va');
        $this->putOneAndTrack('rev-del-b', 'vb');
        
        self::$client->delete('rev-del-a');
        self::$client->delete('rev-del-b');
        
        $results = self::$client->reverseScan('rev-del.', 'rev-del-');
        $this->assertCount(0, $results);
    }
    
    public function testScanAfterOverwriteReturnsNewValues(): void
    {
        $this->putOneAndTrack('scan-ow-a', 'old-a');
        $this->putOneAndTrack('scan-ow-b', 'old-b');
        
        // Overwrite
        self::$client->put('scan-ow-a', 'new-a');
        self::$client->put('scan-ow-b', 'new-b');
        
        $results = self::$client->scan('scan-ow-', 'scan-ow.');
        
        $this->assertCount(2, $results);
        $this->assertEquals('new-a', $results[0]['value']);
        $this->assertEquals('new-b', $results[1]['value']);
    }
    
    public function testReverseScanAfterOverwriteReturnsNewValues(): void
    {
        $this->putOneAndTrack('rev-ow-a', 'old-a');
        $this->putOneAndTrack('rev-ow-b', 'old-b');
        
        self::$client->put('rev-ow-a', 'new-a');
        self::$client->put('rev-ow-b', 'new-b');
        
        $results = self::$client->reverseScan('rev-ow.', 'rev-ow-');
        
        $this->assertCount(2, $results);
        $this->assertEquals('new-b', $results[0]['value']);
        $this->assertEquals('new-a', $results[1]['value']);
    }
    
    public function testScanPartialDeleteShowsRemainingKeys(): void
    {
        $this->putOneAndTrack('scan-pd-a', 'va');
        $this->putOneAndTrack('scan-pd-b', 'vb');
        $this->putOneAndTrack('scan-pd-c', 'vc');
        
        self::$client->delete('scan-pd-b');
        
        $results = self::$client->scan('scan-pd-', 'scan-pd.');
        $keys = array_column($results, 'key');
        
        $this->assertCount(2, $results);
        $this->assertContains('scan-pd-a', $keys);
        $this->assertNotContains('scan-pd-b', $keys);
        $this->assertContains('scan-pd-c', $keys);
    }
    
    public function testReverseScanPartialDeleteShowsRemainingKeys(): void
    {
        $this->putOneAndTrack('rev-pd-a', 'va');
        $this->putOneAndTrack('rev-pd-b', 'vb');
        $this->putOneAndTrack('rev-pd-c', 'vc');
        
        self::$client->delete('rev-pd-b');
        
        $results = self::$client->reverseScan('rev-pd.', 'rev-pd-');
        $keys = array_column($results, 'key');
        
        $this->assertCount(2, $results);
        $this->assertEquals('rev-pd-c', $keys[0]);
        $this->assertEquals('rev-pd-a', $keys[1]);
    }
    
    public function testScanLimitExceedingTotalKeysReturnsAll(): void
    {
        $pairs = ['scan-big-a' => 'va', 'scan-big-b' => 'vb'];
        $this->putAndTrack($pairs);
        
        // Limit 100 but only 2 keys exist
        $results = self::$client->scan('scan-big-', 'scan-big.', 100);
        
        $this->assertCount(2, $results);
    }
    
    public function testReverseScanLimitExceedingTotalKeysReturnsAll(): void
    {
        $pairs = ['rev-big-a' => 'va', 'rev-big-b' => 'vb'];
        $this->putAndTrack($pairs);
        
        $results = self::$client->reverseScan('rev-big.', 'rev-big-', 100);
        
        $this->assertCount(2, $results);
    }
    
    public function testScanManyKeys(): void
    {
        $pairs = [];
        for ($i = 0; $i < 50; $i++) {
            $pairs[sprintf('scan-many-%03d', $i)] = "value-$i";
        }
        $this->putAndTrack($pairs);
        
        $results = self::$client->scan('scan-many-', 'scan-many.');
        
        $this->assertCount(50, $results);
        
        // Verify ascending order
        $keys = array_column($results, 'key');
        $sorted = $keys;
        sort($sorted);
        $this->assertEquals($sorted, $keys, 'Forward scan of many keys should be in ascending order');
    }
    
    public function testReverseScanManyKeys(): void
    {
        $pairs = [];
        for ($i = 0; $i < 50; $i++) {
            $pairs[sprintf('rev-many-%03d', $i)] = "value-$i";
        }
        $this->putAndTrack($pairs);
        
        $results = self::$client->reverseScan('rev-many.', 'rev-many-');
        
        $this->assertCount(50, $results);
        
        // Verify descending order
        $keys = array_column($results, 'key');
        $sorted = $keys;
        rsort($sorted);
        $this->assertEquals($sorted, $keys, 'Reverse scan of many keys should be in descending order');
    }
    
    public function testReverseScanManyKeysWithLimit(): void
    {
        $pairs = [];
        for ($i = 0; $i < 50; $i++) {
            $pairs[sprintf('rev-ml-%03d', $i)] = "value-$i";
        }
        $this->putAndTrack($pairs);
        
        $results = self::$client->reverseScan('rev-ml.', 'rev-ml-', 10);
        
        $this->assertCount(10, $results);
        // Should be the last 10 keys in descending order
        $this->assertEquals('rev-ml-049', $results[0]['key']);
        $this->assertEquals('rev-ml-040', $results[9]['key']);
    }
    
    // ========================================================================
    // DeleteRange
    // ========================================================================
    
    public function testDeleteRangeBasic(): void
    {
        $pairs = ['dr-a' => 'va', 'dr-b' => 'vb', 'dr-c' => 'vc'];
        $this->putAndTrack($pairs);
        
        // Delete range [dr-a, dr-d) — should delete all three
        self::$client->deleteRange('dr-a', 'dr-d');
        
        $this->assertNull(self::$client->get('dr-a'));
        $this->assertNull(self::$client->get('dr-b'));
        $this->assertNull(self::$client->get('dr-c'));
    }
    
    public function testDeleteRangePartial(): void
    {
        $pairs = ['dr-part-a' => 'va', 'dr-part-b' => 'vb', 'dr-part-c' => 'vc'];
        $this->putAndTrack($pairs);
        
        // Delete only [dr-part-a, dr-part-c) — should delete a and b, keep c
        self::$client->deleteRange('dr-part-a', 'dr-part-c');
        
        $this->assertNull(self::$client->get('dr-part-a'));
        $this->assertNull(self::$client->get('dr-part-b'));
        $this->assertEquals('vc', self::$client->get('dr-part-c'), 'endKey is exclusive — dr-part-c should survive');
    }
    
    public function testDeleteRangeStartKeyIsInclusive(): void
    {
        $this->putOneAndTrack('dr-inc-a', 'va');
        $this->putOneAndTrack('dr-inc-b', 'vb');
        
        self::$client->deleteRange('dr-inc-a', 'dr-inc-c');
        
        $this->assertNull(self::$client->get('dr-inc-a'), 'startKey should be inclusive');
    }
    
    public function testDeleteRangeEndKeyIsExclusive(): void
    {
        $this->putOneAndTrack('dr-exc-a', 'va');
        $this->putOneAndTrack('dr-exc-b', 'vb');
        
        self::$client->deleteRange('dr-exc-a', 'dr-exc-b');
        
        $this->assertNull(self::$client->get('dr-exc-a'));
        $this->assertEquals('vb', self::$client->get('dr-exc-b'), 'endKey should be exclusive');
    }
    
    public function testDeleteRangeEmptyRange(): void
    {
        $this->putOneAndTrack('dr-empty-a', 'va');
        
        // Delete a range with no keys
        self::$client->deleteRange('dr-empty-zzz', 'dr-empty-zzzz');
        
        // Original key should be untouched
        $this->assertEquals('va', self::$client->get('dr-empty-a'));
    }
    
    public function testDeleteRangeSameStartAndEnd(): void
    {
        $this->putOneAndTrack('dr-same', 'val');
        
        // Same start and end — should be a no-op
        self::$client->deleteRange('dr-same', 'dr-same');
        
        $this->assertEquals('val', self::$client->get('dr-same'));
    }
    
    public function testDeleteRangeSingleKey(): void
    {
        $this->putOneAndTrack('dr-single', 'val');
        
        // Range [dr-single, dr-single\x00) contains exactly one key
        self::$client->deleteRange('dr-single', "dr-single\x00");
        
        $this->assertNull(self::$client->get('dr-single'));
    }
    
    public function testDeleteRangeVerifyWithScan(): void
    {
        $pairs = [
            'dr-scan-a' => 'va',
            'dr-scan-b' => 'vb',
            'dr-scan-c' => 'vc',
            'dr-scan-d' => 'vd',
        ];
        $this->putAndTrack($pairs);
        
        // Delete middle range [dr-scan-b, dr-scan-d)
        self::$client->deleteRange('dr-scan-b', 'dr-scan-d');
        
        $results = self::$client->scan('dr-scan-', 'dr-scan.');
        $keys = array_column($results, 'key');
        
        $this->assertCount(2, $results);
        $this->assertEquals(['dr-scan-a', 'dr-scan-d'], $keys);
    }
    
    public function testDeleteRangeManyKeys(): void
    {
        $pairs = [];
        for ($i = 0; $i < 50; $i++) {
            $pairs[sprintf('dr-many-%03d', $i)] = "value-$i";
        }
        $this->putAndTrack($pairs);
        
        // Verify all exist
        $results = self::$client->scan('dr-many-', 'dr-many.');
        $this->assertCount(50, $results);
        
        // Delete all
        self::$client->deleteRange('dr-many-', 'dr-many.');
        
        // Verify all gone
        $results = self::$client->scan('dr-many-', 'dr-many.');
        $this->assertCount(0, $results);
    }
    
    public function testDeleteRangeWithBinaryKeys(): void
    {
        $k1 = "dr-bin-\x01";
        $k2 = "dr-bin-\x02";
        $k3 = "dr-bin-\x03";
        
        $this->putOneAndTrack($k1, 'v1');
        $this->putOneAndTrack($k2, 'v2');
        $this->putOneAndTrack($k3, 'v3');
        
        self::$client->deleteRange("dr-bin-\x01", "dr-bin-\x03");
        
        $this->assertNull(self::$client->get($k1));
        $this->assertNull(self::$client->get($k2));
        $this->assertEquals('v3', self::$client->get($k3), 'endKey is exclusive');
    }
    
    public function testDeleteRangeDoesNotAffectOutsideKeys(): void
    {
        $this->putOneAndTrack('dr-out-before', 'before');
        $this->putOneAndTrack('dr-out-target-a', 'ta');
        $this->putOneAndTrack('dr-out-target-b', 'tb');
        $this->putOneAndTrack('dr-out-zafter', 'after');
        
        self::$client->deleteRange('dr-out-target-', 'dr-out-target.');
        
        $this->assertEquals('before', self::$client->get('dr-out-before'));
        $this->assertNull(self::$client->get('dr-out-target-a'));
        $this->assertNull(self::$client->get('dr-out-target-b'));
        $this->assertEquals('after', self::$client->get('dr-out-zafter'));
    }
    
    // ========================================================================
    // DeletePrefix
    // ========================================================================
    
    public function testDeletePrefixBasic(): void
    {
        $pairs = [
            'dp-user:alice' => 'Alice',
            'dp-user:bob' => 'Bob',
            'dp-user:charlie' => 'Charlie',
            'dp-other:data' => 'Other',
        ];
        $this->putAndTrack($pairs);
        
        self::$client->deletePrefix('dp-user:');
        
        $this->assertNull(self::$client->get('dp-user:alice'));
        $this->assertNull(self::$client->get('dp-user:bob'));
        $this->assertNull(self::$client->get('dp-user:charlie'));
        $this->assertEquals('Other', self::$client->get('dp-other:data'), 'Keys outside prefix should survive');
    }
    
    public function testDeletePrefixNoMatches(): void
    {
        $this->putOneAndTrack('dp-survive', 'val');
        
        // Delete a prefix that doesn't match anything
        self::$client->deletePrefix('dp-nonexistent-');
        
        $this->assertEquals('val', self::$client->get('dp-survive'));
    }
    
    public function testDeletePrefixVerifyWithScanPrefix(): void
    {
        $pairs = [
            'dp-scan-a' => 'va',
            'dp-scan-b' => 'vb',
            'dp-scan-c' => 'vc',
        ];
        $this->putAndTrack($pairs);
        
        // Verify keys exist
        $results = self::$client->scanPrefix('dp-scan-');
        $this->assertCount(3, $results);
        
        // Delete by prefix
        self::$client->deletePrefix('dp-scan-');
        
        // Verify all gone
        $results = self::$client->scanPrefix('dp-scan-');
        $this->assertCount(0, $results);
    }
    
    public function testDeletePrefixManyKeys(): void
    {
        $pairs = [];
        for ($i = 0; $i < 50; $i++) {
            $pairs[sprintf('dp-many-%03d', $i)] = "value-$i";
        }
        $this->putAndTrack($pairs);
        
        self::$client->deletePrefix('dp-many-');
        
        $results = self::$client->scanPrefix('dp-many-');
        $this->assertCount(0, $results);
    }
    
    public function testDeletePrefixEmptyStringThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        self::$client->deletePrefix('');
    }
    
    public function testDeletePrefixThenReinsert(): void
    {
        $this->putOneAndTrack('dp-reins-a', 'old-a');
        $this->putOneAndTrack('dp-reins-b', 'old-b');
        
        self::$client->deletePrefix('dp-reins-');
        
        $this->assertNull(self::$client->get('dp-reins-a'));
        
        // Re-insert
        $this->putOneAndTrack('dp-reins-a', 'new-a');
        
        $this->assertEquals('new-a', self::$client->get('dp-reins-a'));
        $this->assertNull(self::$client->get('dp-reins-b'));
    }
    
    public function testDeletePrefixDoesNotAffectSiblingPrefixes(): void
    {
        $this->putOneAndTrack('dp-sib-aaa-1', 'v1');
        $this->putOneAndTrack('dp-sib-aab-1', 'v2');
        $this->putOneAndTrack('dp-sib-aac-1', 'v3');
        
        self::$client->deletePrefix('dp-sib-aab');
        
        $this->assertEquals('v1', self::$client->get('dp-sib-aaa-1'));
        $this->assertNull(self::$client->get('dp-sib-aab-1'));
        $this->assertEquals('v3', self::$client->get('dp-sib-aac-1'));
    }
    
    // ========================================================================
    // TTL Operations
    // ========================================================================
    
    public function testPutWithTtl(): void
    {
        // Put with 60 second TTL
        self::$client->put('ttl-basic', 'value', 60);
        $this->keysToCleanup[] = 'ttl-basic';
        
        $this->assertEquals('value', self::$client->get('ttl-basic'));
    }
    
    public function testPutWithTtlKeyExpires(): void
    {
        // Put with 2 second TTL
        self::$client->put('ttl-expire', 'temporary', 2);
        $this->keysToCleanup[] = 'ttl-expire';
        
        // Key should exist immediately
        $this->assertEquals('temporary', self::$client->get('ttl-expire'));
        
        // Wait for expiration
        sleep(3);
        
        // Key should be gone
        $this->assertNull(self::$client->get('ttl-expire'), 'Key should expire after TTL');
    }
    
    public function testPutWithoutTtlDoesNotExpire(): void
    {
        // Put without TTL (default = 0 = no expiration)
        $this->putOneAndTrack('ttl-none', 'permanent');
        
        $this->assertEquals('permanent', self::$client->get('ttl-none'));
        
        // getKeyTTL should return null for keys without TTL
        $ttl = self::$client->getKeyTTL('ttl-none');
        $this->assertNull($ttl, 'Key without TTL should return null from getKeyTTL');
    }
    
    public function testGetKeyTtlReturnsRemainingTime(): void
    {
        // Put with 60 second TTL
        self::$client->put('ttl-remaining', 'value', 60);
        $this->keysToCleanup[] = 'ttl-remaining';
        
        $ttl = self::$client->getKeyTTL('ttl-remaining');
        
        $this->assertNotNull($ttl, 'Key with TTL should return a value');
        $this->assertGreaterThan(0, $ttl, 'Remaining TTL should be positive');
        $this->assertLessThanOrEqual(60, $ttl, 'Remaining TTL should not exceed original TTL');
    }
    
    public function testGetKeyTtlNonExistentKey(): void
    {
        $ttl = self::$client->getKeyTTL('ttl-nonexistent-' . uniqid());
        
        $this->assertNull($ttl, 'Non-existent key should return null');
    }
    
    public function testGetKeyTtlAfterExpiration(): void
    {
        self::$client->put('ttl-expired', 'temp', 2);
        $this->keysToCleanup[] = 'ttl-expired';
        
        // Wait for expiration
        sleep(3);
        
        $ttl = self::$client->getKeyTTL('ttl-expired');
        $this->assertNull($ttl, 'Expired key should return null from getKeyTTL');
    }
    
    public function testPutWithTtlOverwriteRefreshesTtl(): void
    {
        // Put with short TTL
        self::$client->put('ttl-refresh', 'old', 2);
        $this->keysToCleanup[] = 'ttl-refresh';
        
        // Overwrite with longer TTL
        self::$client->put('ttl-refresh', 'new', 60);
        
        // Wait past original TTL
        sleep(3);
        
        // Key should still exist with new value
        $this->assertEquals('new', self::$client->get('ttl-refresh'), 'Overwritten key should survive past original TTL');
        
        $ttl = self::$client->getKeyTTL('ttl-refresh');
        $this->assertNotNull($ttl);
        $this->assertGreaterThan(0, $ttl);
    }
    
    public function testPutWithTtlZeroMeansNoExpiration(): void
    {
        // Explicit TTL=0 should behave same as no TTL
        self::$client->put('ttl-zero', 'permanent', 0);
        $this->keysToCleanup[] = 'ttl-zero';
        
        $this->assertEquals('permanent', self::$client->get('ttl-zero'));
        
        $ttl = self::$client->getKeyTTL('ttl-zero');
        $this->assertNull($ttl, 'TTL=0 should mean no expiration');
    }
    
    public function testBatchPutWithTtl(): void
    {
        $pairs = [
            'ttl-batch-a' => 'va',
            'ttl-batch-b' => 'vb',
        ];
        self::$client->batchPut($pairs, 60);
        $this->keysToCleanup[] = 'ttl-batch-a';
        $this->keysToCleanup[] = 'ttl-batch-b';
        
        $this->assertEquals('va', self::$client->get('ttl-batch-a'));
        $this->assertEquals('vb', self::$client->get('ttl-batch-b'));
        
        // Both should have TTL
        $ttlA = self::$client->getKeyTTL('ttl-batch-a');
        $ttlB = self::$client->getKeyTTL('ttl-batch-b');
        $this->assertNotNull($ttlA);
        $this->assertNotNull($ttlB);
        $this->assertGreaterThan(0, $ttlA);
        $this->assertGreaterThan(0, $ttlB);
    }
    
    public function testBatchPutWithTtlExpires(): void
    {
        $pairs = [
            'ttl-bexp-a' => 'va',
            'ttl-bexp-b' => 'vb',
        ];
        self::$client->batchPut($pairs, 2);
        $this->keysToCleanup[] = 'ttl-bexp-a';
        $this->keysToCleanup[] = 'ttl-bexp-b';
        
        // Should exist immediately
        $this->assertEquals('va', self::$client->get('ttl-bexp-a'));
        
        // Wait for expiration
        sleep(3);
        
        $this->assertNull(self::$client->get('ttl-bexp-a'), 'Batch put key should expire after TTL');
        $this->assertNull(self::$client->get('ttl-bexp-b'), 'Batch put key should expire after TTL');
    }
    
    public function testBatchPutWithoutTtl(): void
    {
        $pairs = ['ttl-bnone-a' => 'va', 'ttl-bnone-b' => 'vb'];
        self::$client->batchPut($pairs); // no TTL
        $this->keysToCleanup[] = 'ttl-bnone-a';
        $this->keysToCleanup[] = 'ttl-bnone-b';
        
        $ttl = self::$client->getKeyTTL('ttl-bnone-a');
        $this->assertNull($ttl, 'batchPut without TTL should not set expiration');
    }
    
    public function testScanIncludesKeysWithTtl(): void
    {
        self::$client->put('ttl-scan-a', 'va', 60);
        self::$client->put('ttl-scan-b', 'vb', 60);
        $this->keysToCleanup[] = 'ttl-scan-a';
        $this->keysToCleanup[] = 'ttl-scan-b';
        
        $results = self::$client->scan('ttl-scan-', 'ttl-scan.');
        
        $this->assertCount(2, $results);
        $this->assertEquals('ttl-scan-a', $results[0]['key']);
        $this->assertEquals('va', $results[0]['value']);
    }
    
    public function testScanExcludesExpiredKeys(): void
    {
        self::$client->put('ttl-scanexp-a', 'va', 2);
        $this->putOneAndTrack('ttl-scanexp-b', 'vb'); // no TTL
        $this->keysToCleanup[] = 'ttl-scanexp-a';
        
        sleep(3);
        
        $results = self::$client->scan('ttl-scanexp-', 'ttl-scanexp.');
        $keys = array_column($results, 'key');
        
        $this->assertNotContains('ttl-scanexp-a', $keys, 'Expired key should not appear in scan');
        $this->assertContains('ttl-scanexp-b', $keys, 'Non-expired key should appear in scan');
    }
    
    // ========================================================================
    // CompareAndSwap (CAS)
    // ========================================================================
    
    public function testCasSuccessfulSwap(): void
    {
        $this->putOneAndTrack('cas-basic', 'old-value');
        
        $result = self::$client->compareAndSwap('cas-basic', 'old-value', 'new-value');
        
        $this->assertInstanceOf(CasResult::class, $result);
        $this->assertTrue($result->swapped, 'CAS should succeed when expected value matches');
        $this->assertEquals('old-value', $result->previousValue);
        
        // Verify the new value is stored
        $this->assertEquals('new-value', self::$client->get('cas-basic'));
    }
    
    public function testCasFailedSwapValueMismatch(): void
    {
        $this->putOneAndTrack('cas-fail', 'actual-value');
        
        $result = self::$client->compareAndSwap('cas-fail', 'wrong-expected', 'new-value');
        
        $this->assertFalse($result->swapped, 'CAS should fail when expected value does not match');
        $this->assertEquals('actual-value', $result->previousValue, 'Should return the actual current value');
        
        // Verify the value was NOT changed
        $this->assertEquals('actual-value', self::$client->get('cas-fail'));
    }
    
    public function testCasExpectNullKeyDoesNotExist(): void
    {
        $key = 'cas-null-' . uniqid();
        $this->keysToCleanup[] = $key;
        
        // CAS with expectedValue=null on a non-existent key should succeed
        $result = self::$client->compareAndSwap($key, null, 'created');
        
        $this->assertTrue($result->swapped, 'CAS with null expected should succeed when key does not exist');
        $this->assertNull($result->previousValue, 'Previous value should be null for non-existent key');
        
        // Verify the value was created
        $this->assertEquals('created', self::$client->get($key));
    }
    
    public function testCasExpectNullKeyExists(): void
    {
        $this->putOneAndTrack('cas-null-exists', 'existing');
        
        // CAS with expectedValue=null on an existing key should fail
        $result = self::$client->compareAndSwap('cas-null-exists', null, 'new-value');
        
        $this->assertFalse($result->swapped, 'CAS with null expected should fail when key exists');
        $this->assertEquals('existing', $result->previousValue, 'Should return the existing value');
        
        // Verify the value was NOT changed
        $this->assertEquals('existing', self::$client->get('cas-null-exists'));
    }
    
    public function testCasMultipleSwapsInSequence(): void
    {
        $this->putOneAndTrack('cas-seq', 'v1');
        
        // First swap: v1 → v2
        $r1 = self::$client->compareAndSwap('cas-seq', 'v1', 'v2');
        $this->assertTrue($r1->swapped);
        $this->assertEquals('v1', $r1->previousValue);
        
        // Second swap: v2 → v3
        $r2 = self::$client->compareAndSwap('cas-seq', 'v2', 'v3');
        $this->assertTrue($r2->swapped);
        $this->assertEquals('v2', $r2->previousValue);
        
        // Third swap with stale expected: should fail
        $r3 = self::$client->compareAndSwap('cas-seq', 'v1', 'v4');
        $this->assertFalse($r3->swapped);
        $this->assertEquals('v3', $r3->previousValue);
        
        // Final value should be v3
        $this->assertEquals('v3', self::$client->get('cas-seq'));
    }
    
    public function testCasWithBinaryData(): void
    {
        $key = "cas-bin-\x01\x02";
        $oldValue = "\x00\x01\x02\x03";
        $newValue = "\xff\xfe\xfd\xfc";
        
        self::$client->put($key, $oldValue);
        $this->keysToCleanup[] = $key;
        
        $result = self::$client->compareAndSwap($key, $oldValue, $newValue);
        
        $this->assertTrue($result->swapped);
        $this->assertEquals($oldValue, $result->previousValue);
        $this->assertEquals($newValue, self::$client->get($key));
    }
    
    public function testCasWithEmptyStringValue(): void
    {
        $this->putOneAndTrack('cas-empty', 'non-empty');
        
        // Swap to empty string
        $result = self::$client->compareAndSwap('cas-empty', 'non-empty', '');
        
        $this->assertTrue($result->swapped);
        $this->assertEquals('non-empty', $result->previousValue);
        $this->assertEquals('', self::$client->get('cas-empty'));
    }
    
    public function testCasWithTtl(): void
    {
        $key = 'cas-ttl-' . uniqid();
        $this->keysToCleanup[] = $key;
        
        // Create key via CAS with TTL
        $result = self::$client->compareAndSwap($key, null, 'temp-value', 60);
        $this->assertTrue($result->swapped);
        
        // Verify TTL was set
        $ttl = self::$client->getKeyTTL($key);
        $this->assertNotNull($ttl, 'CAS with TTL should set expiration');
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(60, $ttl);
    }
    
    public function testCasWithTtlExpires(): void
    {
        $key = 'cas-ttl-exp-' . uniqid();
        $this->keysToCleanup[] = $key;
        
        // Create key via CAS with short TTL
        $result = self::$client->compareAndSwap($key, null, 'temporary', 2);
        $this->assertTrue($result->swapped);
        $this->assertEquals('temporary', self::$client->get($key));
        
        // Wait for expiration
        sleep(3);
        
        $this->assertNull(self::$client->get($key), 'CAS key should expire after TTL');
    }
    
    public function testCasSwapThenSwapAgain(): void
    {
        $this->putOneAndTrack('cas-double', 'initial');
        
        // First CAS succeeds
        $r1 = self::$client->compareAndSwap('cas-double', 'initial', 'middle');
        $this->assertTrue($r1->swapped);
        
        // Second CAS with the new value succeeds
        $r2 = self::$client->compareAndSwap('cas-double', 'middle', 'final');
        $this->assertTrue($r2->swapped);
        
        $this->assertEquals('final', self::$client->get('cas-double'));
    }
    
    public function testCasOnDeletedKey(): void
    {
        $this->putOneAndTrack('cas-deleted', 'value');
        self::$client->delete('cas-deleted');
        
        // CAS with null expected on a deleted key should succeed
        $result = self::$client->compareAndSwap('cas-deleted', null, 'resurrected');
        $this->keysToCleanup[] = 'cas-deleted';
        
        $this->assertTrue($result->swapped, 'CAS with null expected should succeed on deleted key');
        $this->assertEquals('resurrected', self::$client->get('cas-deleted'));
    }
    
    public function testCasOnDeletedKeyWithWrongExpected(): void
    {
        $this->putOneAndTrack('cas-del-wrong', 'value');
        self::$client->delete('cas-del-wrong');
        
        // CAS with non-null expected on a deleted key should fail
        $result = self::$client->compareAndSwap('cas-del-wrong', 'value', 'new');
        
        $this->assertFalse($result->swapped, 'CAS with non-null expected should fail on deleted key');
        $this->assertNull($result->previousValue, 'Previous value should be null for deleted key');
    }
    
    public function testCasReturnsPreviousValueOnFailure(): void
    {
        $this->putOneAndTrack('cas-prev', 'current-value');
        
        $result = self::$client->compareAndSwap('cas-prev', 'wrong-expected', 'new-value');
        
        $this->assertFalse($result->swapped);
        $this->assertEquals('current-value', $result->previousValue,
            'Failed CAS should return the actual current value for retry logic');
    }
    
    public function testCasAtomicCounter(): void
    {
        // Simulate an atomic counter using CAS
        $key = 'cas-counter';
        $this->putOneAndTrack($key, '0');
        
        // Increment: read-compare-swap loop
        $current = self::$client->get($key);
        $newVal = (string)((int)$current + 1);
        
        $result = self::$client->compareAndSwap($key, $current, $newVal);
        $this->assertTrue($result->swapped);
        $this->assertEquals('1', self::$client->get($key));
        
        // Increment again
        $current = self::$client->get($key);
        $newVal = (string)((int)$current + 1);
        
        $result = self::$client->compareAndSwap($key, $current, $newVal);
        $this->assertTrue($result->swapped);
        $this->assertEquals('2', self::$client->get($key));
    }
    
    public function testCasWithLargeValue(): void
    {
        $key = 'cas-large';
        $oldValue = str_repeat('A', 1024 * 100); // 100KB
        $newValue = str_repeat('B', 1024 * 100); // 100KB
        
        self::$client->put($key, $oldValue);
        $this->keysToCleanup[] = $key;
        
        $result = self::$client->compareAndSwap($key, $oldValue, $newValue);
        
        $this->assertTrue($result->swapped);
        $this->assertEquals($newValue, self::$client->get($key));
    }
    
    // ========================================================================
    // PutIfAbsent
    // ========================================================================
    
    public function testPutIfAbsentNewKey(): void
    {
        $key = 'pia-new-' . uniqid();
        $this->keysToCleanup[] = $key;
        
        $result = self::$client->putIfAbsent($key, 'first-value');
        
        $this->assertNull($result, 'putIfAbsent should return null when key was successfully inserted');
        $this->assertEquals('first-value', self::$client->get($key));
    }
    
    public function testPutIfAbsentExistingKey(): void
    {
        $this->putOneAndTrack('pia-exists', 'existing-value');
        
        $result = self::$client->putIfAbsent('pia-exists', 'new-value');
        
        $this->assertEquals('existing-value', $result, 'putIfAbsent should return existing value when key exists');
        
        // Verify the value was NOT changed
        $this->assertEquals('existing-value', self::$client->get('pia-exists'));
    }
    
    public function testPutIfAbsentIdempotent(): void
    {
        $key = 'pia-idem-' . uniqid();
        $this->keysToCleanup[] = $key;
        
        // First call — inserts
        $r1 = self::$client->putIfAbsent($key, 'value');
        $this->assertNull($r1);
        
        // Second call — returns existing value
        $r2 = self::$client->putIfAbsent($key, 'different-value');
        $this->assertEquals('value', $r2);
        
        // Third call — still returns original value
        $r3 = self::$client->putIfAbsent($key, 'yet-another');
        $this->assertEquals('value', $r3);
        
        // Value should still be the original
        $this->assertEquals('value', self::$client->get($key));
    }
    
    public function testPutIfAbsentWithBinaryData(): void
    {
        $key = "pia-bin-\x01\x02";
        $value = "\x00\xff\xfe\xfd";
        $this->keysToCleanup[] = $key;
        
        $result = self::$client->putIfAbsent($key, $value);
        
        $this->assertNull($result);
        $this->assertEquals($value, self::$client->get($key));
    }
    
    public function testPutIfAbsentWithEmptyValue(): void
    {
        $key = 'pia-empty-' . uniqid();
        $this->keysToCleanup[] = $key;
        
        $result = self::$client->putIfAbsent($key, '');
        
        $this->assertNull($result);
        $this->assertEquals('', self::$client->get($key));
    }
    
    public function testPutIfAbsentWithTtl(): void
    {
        $key = 'pia-ttl-' . uniqid();
        $this->keysToCleanup[] = $key;
        
        $result = self::$client->putIfAbsent($key, 'temp-value', 60);
        
        $this->assertNull($result);
        $this->assertEquals('temp-value', self::$client->get($key));
        
        $ttl = self::$client->getKeyTTL($key);
        $this->assertNotNull($ttl);
        $this->assertGreaterThan(0, $ttl);
        $this->assertLessThanOrEqual(60, $ttl);
    }
    
    public function testPutIfAbsentWithTtlExpires(): void
    {
        $key = 'pia-ttl-exp-' . uniqid();
        $this->keysToCleanup[] = $key;
        
        $result = self::$client->putIfAbsent($key, 'temporary', 2);
        $this->assertNull($result);
        
        sleep(3);
        
        $this->assertNull(self::$client->get($key), 'putIfAbsent key should expire after TTL');
        
        // After expiration, putIfAbsent should succeed again
        $result2 = self::$client->putIfAbsent($key, 'reinserted');
        $this->assertNull($result2, 'putIfAbsent should succeed after key expires');
        $this->assertEquals('reinserted', self::$client->get($key));
    }
    
    public function testPutIfAbsentAfterDelete(): void
    {
        $key = 'pia-del-' . uniqid();
        $this->keysToCleanup[] = $key;
        
        // Insert
        $r1 = self::$client->putIfAbsent($key, 'first');
        $this->assertNull($r1);
        
        // Delete
        self::$client->delete($key);
        
        // putIfAbsent should succeed again
        $r2 = self::$client->putIfAbsent($key, 'second');
        $this->assertNull($r2);
        $this->assertEquals('second', self::$client->get($key));
    }
    
    public function testPutIfAbsentDoesNotOverwriteExistingWithTtl(): void
    {
        $this->putOneAndTrack('pia-no-ow', 'permanent');
        
        // Try to putIfAbsent with TTL — should fail because key exists
        $result = self::$client->putIfAbsent('pia-no-ow', 'temp', 60);
        
        $this->assertEquals('permanent', $result);
        
        // Original key should have no TTL (it was put without one)
        $ttl = self::$client->getKeyTTL('pia-no-ow');
        $this->assertNull($ttl, 'Failed putIfAbsent should not modify existing key TTL');
    }
    
    public function testPutIfAbsentWithLargeValue(): void
    {
        $key = 'pia-large-' . uniqid();
        $value = str_repeat('X', 1024 * 100); // 100KB
        $this->keysToCleanup[] = $key;
        
        $result = self::$client->putIfAbsent($key, $value);
        
        $this->assertNull($result);
        $this->assertEquals($value, self::$client->get($key));
    }
}
