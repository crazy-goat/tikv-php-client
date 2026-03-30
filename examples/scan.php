<?php
/**
 * TiKV PHP Client - Scanning Example
 * 
 * Demonstrates range scanning, prefix scanning, and reverse scanning.
 */

require __DIR__ . '/../vendor/autoload.php';

use CrazyGoat\TiKV\Client\RawKv\RawKvClient;

$pdEndpoints = getenv('PD_ENDPOINTS') ? explode(',', getenv('PD_ENDPOINTS')) : ['127.0.0.1:2379'];
$client = RawKvClient::create($pdEndpoints);

try {
    echo "TiKV PHP Client - Scanning Example\n";
    echo "===================================\n\n";
    
    // Setup: Insert test data
    echo "Setting up test data...\n";
    $testData = [
        'scan:user:001' => 'Alice',
        'scan:user:002' => 'Bob',
        'scan:user:003' => 'Charlie',
        'scan:user:004' => 'Diana',
        'scan:user:005' => 'Eve',
        'scan:product:001' => 'Laptop',
        'scan:product:002' => 'Phone',
        'scan:product:003' => 'Tablet',
    ];
    $client->batchPut($testData);
    echo "✓ Inserted " . count($testData) . " test records\n\n";
    
    // 1. Range Scan
    echo "1. Range Scan [scan:user:001, scan:user:004)\n";
    $results = $client->scan('scan:user:001', 'scan:user:004');
    foreach ($results as $result) {
        echo "   {$result['key']} => {$result['value']}\n";
    }
    echo "   Found " . count($results) . " records\n\n";
    
    // 2. Prefix Scan
    echo "2. Prefix Scan 'scan:product:'\n";
    $results = $client->scanPrefix('scan:product:');
    foreach ($results as $result) {
        echo "   {$result['key']} => {$result['value']}\n";
    }
    echo "   Found " . count($results) . " records\n\n";
    
    // 3. Limited Scan
    echo "3. Limited Scan (limit=3)\n";
    $results = $client->scan('scan:user:', 'scan:user:;', limit: 3);
    foreach ($results as $result) {
        echo "   {$result['key']} => {$result['value']}\n";
    }
    echo "   Found " . count($results) . " records (limited to 3)\n\n";
    
    // 4. Key-Only Scan
    echo "4. Key-Only Scan (values not retrieved)\n";
    $results = $client->scanPrefix('scan:user:', keyOnly: true);
    foreach ($results as $result) {
        echo "   {$result['key']} => " . ($result['value'] ?? 'null') . "\n";
    }
    echo "   Found " . count($results) . " keys\n\n";
    
    // 5. Reverse Scan
    echo "5. Reverse Scan (descending order)\n";
    echo "   Note: startKey=upper bound (exclusive), endKey=lower bound (inclusive)\n";
    $results = $client->reverseScan('scan:user:;', 'scan:user:000', limit: 3);
    foreach ($results as $result) {
        echo "   {$result['key']} => {$result['value']}\n";
    }
    echo "   Found " . count($results) . " records in reverse order\n\n";
    
    // 6. Batch Scan (multiple ranges)
    echo "6. Batch Scan (multiple non-contiguous ranges)\n";
    $ranges = [
        ['scan:user:001', 'scan:user:003'],
        ['scan:product:002', 'scan:product:004'],
    ];
    $results = $client->batchScan($ranges, eachLimit: 10);
    foreach ($results as $i => $rangeResults) {
        echo "   Range $i: " . count($rangeResults) . " records\n";
        foreach ($rangeResults as $result) {
            echo "     {$result['key']} => {$result['value']}\n";
        }
    }
    echo "\n";
    
    // Cleanup
    echo "Cleaning up test data...\n";
    $client->batchDelete(array_keys($testData));
    echo "✓ Cleanup complete\n\n";
    
    echo "All scanning operations completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} finally {
    $client->close();
}
