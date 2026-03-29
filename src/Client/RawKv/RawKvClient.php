<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\RawKv;

use CrazyGoat\TiKV\Client\Connection\PdClient;
use CrazyGoat\TiKV\Client\Grpc\GrpcClient;
use CrazyGoat\Proto\Kvrpcpb\Context;
use CrazyGoat\Proto\Kvrpcpb\RawGetRequest;
use CrazyGoat\Proto\Kvrpcpb\RawGetResponse;
use CrazyGoat\Proto\Kvrpcpb\RawPutRequest;
use CrazyGoat\Proto\Kvrpcpb\RawPutResponse;
use CrazyGoat\Proto\Kvrpcpb\RawDeleteRequest;
use CrazyGoat\Proto\Kvrpcpb\RawDeleteResponse;
use CrazyGoat\Proto\Kvrpcpb\RawBatchGetRequest;
use CrazyGoat\Proto\Kvrpcpb\RawBatchGetResponse;
use CrazyGoat\Proto\Kvrpcpb\RawBatchPutRequest;
use CrazyGoat\Proto\Kvrpcpb\RawBatchPutResponse;
use CrazyGoat\Proto\Kvrpcpb\RawBatchDeleteRequest;
use CrazyGoat\Proto\Kvrpcpb\RawBatchDeleteResponse;
use CrazyGoat\Proto\Kvrpcpb\RawScanRequest;
use CrazyGoat\Proto\Kvrpcpb\RawScanResponse;
use CrazyGoat\Proto\Kvrpcpb\KvPair;
use CrazyGoat\Proto\Metapb\Peer;
use CrazyGoat\Proto\Metapb\RegionEpoch;

class RawKvClient
{
    private PdClient $pdClient;
    private GrpcClient $grpc;
    private bool $closed = false;
    private array $regionCache = [];
    private int $maxRetries = 3;
    
    public static function create(array $pdEndpoints): self
    {
        $pdClient = new PdClient($pdEndpoints[0]);
        return new self($pdClient);
    }
    
    public function __construct(PdClient $pdClient)
    {
        $this->pdClient = $pdClient;
        $this->grpc = new GrpcClient();
    }
    
    private function getRegionInfo(string $key): array
    {
        if (!isset($this->regionCache[$key])) {
            $this->regionCache[$key] = $this->pdClient->getRegion($key);
        }
        return $this->regionCache[$key];
    }
    
    private function clearRegionCache(string $key): void
    {
        unset($this->regionCache[$key]);
    }
    
    private function createContext(array $regionInfo): Context
    {
        $ctx = new Context();
        $ctx->setRegionId($regionInfo['region_id']);
        
        // Add RegionEpoch
        $epoch = new RegionEpoch();
        $epoch->setConfVer($regionInfo['region_epoch_conf_ver']);
        $epoch->setVersion($regionInfo['region_epoch_version']);
        $ctx->setRegionEpoch($epoch);
        
        $peer = new Peer();
        $peer->setId($regionInfo['leader_peer_id']);
        $peer->setStoreId($regionInfo['leader_store_id']);
        $ctx->setPeer($peer);
        
        return $ctx;
    }
    
    /**
     * Get TiKV address from PD
     * 
     * @param int $storeId Store ID from PD
     * @return string TiKV address (e.g., "tikv1:20160")
     */
    private function getTikvAddress(int $storeId): string
    {
        $store = $this->pdClient->getStore($storeId);
        if ($store === null) {
            throw new \RuntimeException("Store {$storeId} not found in PD");
        }
        
        $address = $store->getAddress();
        if (empty($address)) {
            throw new \RuntimeException("Store {$storeId} has no address");
        }
        
        return $address;
    }
    
    private function isEpochNotMatch(string $message): bool
    {
        return strpos($message, 'EpochNotMatch') !== false;
    }
    
    private function executeWithRetry(string $key, callable $operation): mixed
    {
        $lastException = null;
        
        for ($attempt = 0; $attempt < $this->maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (\RuntimeException $e) {
                $lastException = $e;
                
                // If epoch mismatch, clear cache and retry
                if ($this->isEpochNotMatch($e->getMessage())) {
                    $this->clearRegionCache($key);
                    continue;
                }
                
                // Other errors, throw immediately
                throw $e;
            }
        }
        
        throw $lastException ?? new \RuntimeException('Max retries exceeded');
    }
    
    public function get(string $key): ?string
    {
        $this->ensureOpen();
        
        return $this->executeWithRetry($key, function() use ($key) {
            $regionInfo = $this->getRegionInfo($key);
            $tikvAddress = $this->getTikvAddress($regionInfo['leader_store_id']);
            
            $request = new RawGetRequest();
            $request->setContext($this->createContext($regionInfo));
            $request->setKey($key);
            
            $response = $this->grpc->call(
                $tikvAddress,
                'tikvpb.Tikv',
                'RawGet',
                $request,
                RawGetResponse::class
            );
            
            $value = $response->getValue();
            return $value !== '' ? $value : null;
        });
    }
    
    public function put(string $key, string $value): void
    {
        $this->ensureOpen();
        
        $this->executeWithRetry($key, function() use ($key, $value) {
            $regionInfo = $this->getRegionInfo($key);
            $tikvAddress = $this->getTikvAddress($regionInfo['leader_store_id']);
            
            $request = new RawPutRequest();
            $request->setContext($this->createContext($regionInfo));
            $request->setKey($key);
            $request->setValue($value);
            
            $this->grpc->call(
                $tikvAddress,
                'tikvpb.Tikv',
                'RawPut',
                $request,
                RawPutResponse::class
            );
            
            return null;
        });
    }
    
    public function delete(string $key): void
    {
        $this->ensureOpen();
        
        $this->executeWithRetry($key, function() use ($key) {
            $regionInfo = $this->getRegionInfo($key);
            $tikvAddress = $this->getTikvAddress($regionInfo['leader_store_id']);
            
            $request = new RawDeleteRequest();
            $request->setContext($this->createContext($regionInfo));
            $request->setKey($key);
            
            $this->grpc->call(
                $tikvAddress,
                'tikvpb.Tikv',
                'RawDelete',
                $request,
                RawDeleteResponse::class
            );
            
            return null;
        });
    }
    
    /**
     * Batch get multiple keys from TiKV
     * 
     * @param array $keys Array of keys to retrieve
     * @return array Array of values (null for missing keys), indexed by key
     */
    public function batchGet(array $keys): array
    {
        $this->ensureOpen();
        
        if (empty($keys)) {
            return [];
        }
        
        // Group keys by region
        $keysByRegion = [];
        foreach ($keys as $key) {
            $regionInfo = $this->getRegionInfo($key);
            $regionId = $regionInfo['region_id'];
            if (!isset($keysByRegion[$regionId])) {
                $keysByRegion[$regionId] = [
                    'region_info' => $regionInfo,
                    'keys' => []
                ];
            }
            $keysByRegion[$regionId]['keys'][] = $key;
        }
        
        // Execute batch get for each region
        $results = [];
        foreach ($keysByRegion as $regionData) {
            $regionResults = $this->executeBatchGetForRegion($regionData['region_info'], $regionData['keys']);
            $results = array_merge($results, $regionResults);
        }
        
        // Return results in the same order as input keys
        $orderedResults = [];
        foreach ($keys as $key) {
            $orderedResults[$key] = $results[$key] ?? null;
        }
        
        return $orderedResults;
    }
    
    private function executeBatchGetForRegion(array $regionInfo, array $keys): array
    {
        return $this->executeWithRetry($keys[0], function() use ($regionInfo, $keys) {
            $tikvAddress = $this->getTikvAddress($regionInfo['leader_store_id']);
            
            $request = new RawBatchGetRequest();
            $request->setContext($this->createContext($regionInfo));
            $request->setKeys($keys);
            
            $response = $this->grpc->call(
                $tikvAddress,
                'tikvpb.Tikv',
                'RawBatchGet',
                $request,
                RawBatchGetResponse::class
            );
            
            // Convert response pairs to associative array
            $results = [];
            foreach ($response->getPairs() as $pair) {
                $results[$pair->getKey()] = $pair->getValue() !== '' ? $pair->getValue() : null;
            }
            
            return $results;
        });
    }
    
    /**
     * Batch put multiple key-value pairs to TiKV
     * 
     * @param array $keyValuePairs Associative array of key => value pairs
     * @throws \RuntimeException If any region fails after retries
     */
    public function batchPut(array $keyValuePairs): void
    {
        $this->ensureOpen();
        
        if (empty($keyValuePairs)) {
            return;
        }
        
        // Group pairs by region
        $pairsByRegion = [];
        foreach ($keyValuePairs as $key => $value) {
            $regionInfo = $this->getRegionInfo($key);
            $regionId = $regionInfo['region_id'];
            if (!isset($pairsByRegion[$regionId])) {
                $pairsByRegion[$regionId] = [
                    'region_info' => $regionInfo,
                    'pairs' => []
                ];
            }
            $pair = new KvPair();
            $pair->setKey($key);
            $pair->setValue($value);
            $pairsByRegion[$regionId]['pairs'][] = $pair;
        }
        
        // Execute batch put for each region
        foreach ($pairsByRegion as $regionData) {
            $this->executeBatchPutForRegion($regionData['region_info'], $regionData['pairs']);
        }
    }
    
    private function executeBatchPutForRegion(array $regionInfo, array $pairs): void
    {
        $this->executeWithRetry($pairs[0]->getKey(), function() use ($regionInfo, $pairs) {
            $tikvAddress = $this->getTikvAddress($regionInfo['leader_store_id']);
            
            $request = new RawBatchPutRequest();
            $request->setContext($this->createContext($regionInfo));
            $request->setPairs($pairs);
            
            $this->grpc->call(
                $tikvAddress,
                'tikvpb.Tikv',
                'RawBatchPut',
                $request,
                RawBatchPutResponse::class
            );
            
            return null;
        });
    }
    
    /**
     * Batch delete multiple keys from TiKV
     * 
     * @param array $keys Array of keys to delete
     * @throws \RuntimeException If any region fails after retries
     */
    public function batchDelete(array $keys): void
    {
        $this->ensureOpen();
        
        if (empty($keys)) {
            return;
        }
        
        // Group keys by region
        $keysByRegion = [];
        foreach ($keys as $key) {
            $regionInfo = $this->getRegionInfo($key);
            $regionId = $regionInfo['region_id'];
            if (!isset($keysByRegion[$regionId])) {
                $keysByRegion[$regionId] = [
                    'region_info' => $regionInfo,
                    'keys' => []
                ];
            }
            $keysByRegion[$regionId]['keys'][] = $key;
        }
        
        // Execute batch delete for each region
        foreach ($keysByRegion as $regionData) {
            $this->executeBatchDeleteForRegion($regionData['region_info'], $regionData['keys']);
        }
    }
    
    private function executeBatchDeleteForRegion(array $regionInfo, array $keys): void
    {
        $this->executeWithRetry($keys[0], function() use ($regionInfo, $keys) {
            $tikvAddress = $this->getTikvAddress($regionInfo['leader_store_id']);
            
            $request = new RawBatchDeleteRequest();
            $request->setContext($this->createContext($regionInfo));
            $request->setKeys($keys);
            
            $this->grpc->call(
                $tikvAddress,
                'tikvpb.Tikv',
                'RawBatchDelete',
                $request,
                RawBatchDeleteResponse::class
            );
            
            return null;
        });
    }
    
    /**
     * Scan a range of keys from TiKV
     * 
     * @param string $startKey Start key (inclusive)
     * @param string $endKey End key (exclusive), empty string means no upper bound
     * @param int $limit Maximum number of keys to return (0 = unlimited)
     * @param bool $keyOnly If true, return only keys without values
     * @return array Array of ['key' => key, 'value' => value] pairs
     */
    public function scan(string $startKey, string $endKey, int $limit = 0, bool $keyOnly = false): array
    {
        $this->ensureOpen();
        
        // Get all regions covering the range
        $regions = $this->pdClient->scanRegions($startKey, $endKey, 0);
        
        $results = [];
        $remainingLimit = $limit;
        
        foreach ($regions as $regionInfo) {
            // Calculate the actual range for this region
            $regionStart = $regionInfo['start_key'];
            $regionEnd = $regionInfo['end_key'];
            
            // Adjust range to match our scan range
            $scanStart = max($startKey, $regionStart);
            $scanEnd = $endKey === '' ? $regionEnd : min($endKey, $regionEnd);
            
            // Skip if range is invalid
            if ($scanStart >= $scanEnd && $scanEnd !== '') {
                continue;
            }
            
            // Calculate limit for this region
            // Use PHP_INT_MAX when no limit specified (limit=0 means unlimited)
            $regionLimit = $remainingLimit === 0 ? PHP_INT_MAX : $remainingLimit;
            
            $regionResults = $this->executeScanForRegion(
                $regionInfo, 
                $scanStart, 
                $scanEnd, 
                $regionLimit, 
                $keyOnly,
                false // forward scan
            );
            
            $results = array_merge($results, $regionResults);
            
            // Update remaining limit
            if ($remainingLimit > 0) {
                $remainingLimit -= count($regionResults);
                if ($remainingLimit <= 0) {
                    break;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Scan keys with a given prefix
     * 
     * @param string $prefix Key prefix to scan
     * @param int $limit Maximum number of keys to return (0 = unlimited)
     * @param bool $keyOnly If true, return only keys without values
     * @return array Array of ['key' => key, 'value' => value] pairs
     */
    public function scanPrefix(string $prefix, int $limit = 0, bool $keyOnly = false): array
    {
        $this->ensureOpen();
        
        $endKey = $this->calculatePrefixEndKey($prefix);
        
        return $this->scan($prefix, $endKey, $limit, $keyOnly);
    }
    
    /**
     * Calculate end key for prefix scan
     * Increments the last byte of the prefix to create an exclusive end key
     */
    private function calculatePrefixEndKey(string $prefix): string
    {
        if ($prefix === '') {
            return '';
        }
        
        $bytes = $prefix;
        $lastByte = ord($bytes[strlen($bytes) - 1]);
        
        // If last byte is 0xFF, we need to handle specially
        if ($lastByte === 255) {
            // Remove trailing 0xFF bytes and increment the next byte
            $trimmed = rtrim($bytes, "\xff");
            if ($trimmed === '') {
                return ''; // All bytes were 0xFF, no upper bound
            }
            $lastByte = ord($trimmed[strlen($trimmed) - 1]);
            return substr($trimmed, 0, -1) . chr($lastByte + 1);
        }
        
        // Simply increment the last byte
        return substr($bytes, 0, -1) . chr($lastByte + 1);
    }
    
    /**
     * Reverse scan a range of keys from TiKV (descending order)
     * 
     * Note: This implementation uses forward scan and reverses the results,
     * as TiKV RawScan with reverse=true may not be supported in all versions.
     * 
     * @param string $startKey Start key (inclusive, upper bound in reverse scan)
     * @param string $endKey End key (exclusive, lower bound in reverse scan), empty string means no lower bound
     * @param int $limit Maximum number of keys to return (0 = unlimited)
     * @param bool $keyOnly If true, return only keys without values
     * @return array Array of ['key' => key, 'value' => value] pairs in reverse order
     */
    public function reverseScan(string $startKey, string $endKey, int $limit = 0, bool $keyOnly = false): array
    {
        $this->ensureOpen();
        
        // For reverse scan from startKey down to endKey:
        // We need to scan [endKey, startKey+] where startKey+ is the next key after startKey
        // to include startKey in the results
        
        // Calculate the end key for forward scan to include startKey
        $scanEndKey = $this->nextKey($startKey);
        
        // Get all results from forward scan [endKey, scanEndKey)
        $results = $this->scan($endKey, $scanEndKey, 0, $keyOnly);
        
        // Reverse the results to get descending order
        $results = array_reverse($results);
        
        // Apply limit if specified
        if ($limit > 0 && count($results) > $limit) {
            $results = array_slice($results, 0, $limit);
        }
        
        return $results;
    }
    
    /**
     * Calculate the next key for range scanning
     * Appends a null byte to include the current key in exclusive range scans
     */
    private function nextKey(string $key): string
    {
        return $key . "\x00";
    }
    
    private function executeScanForRegion(
        array $regionInfo, 
        string $startKey, 
        string $endKey, 
        int $limit, 
        bool $keyOnly,
        bool $reverse
    ): array {
        return $this->executeWithRetry($startKey, function() use ($regionInfo, $startKey, $endKey, $limit, $keyOnly, $reverse) {
            $tikvAddress = $this->getTikvAddress($regionInfo['leader_store_id']);
            
            $request = new RawScanRequest();
            $request->setContext($this->createContext($regionInfo));
            $request->setStartKey($startKey);
            if ($endKey !== '') {
                $request->setEndKey($endKey);
            }
            // Only set limit if > 0, otherwise TiKV returns 0 results
            if ($limit > 0) {
                $request->setLimit($limit);
            }
            $request->setKeyOnly($keyOnly);
            $request->setReverse($reverse);
            
            $response = $this->grpc->call(
                $tikvAddress,
                'tikvpb.Tikv',
                'RawScan',
                $request,
                RawScanResponse::class
            );
            
            // Convert response to array format
            $results = [];
            foreach ($response->getKvs() as $pair) {
                $results[] = [
                    'key' => $pair->getKey(),
                    'value' => $keyOnly ? null : $pair->getValue()
                ];
            }
            
            return $results;
        });
    }
    
    public function close(): void
    {
        if (!$this->closed) {
            $this->grpc->close();
            $this->pdClient->close();
            $this->closed = true;
        }
    }
    
    private function ensureOpen(): void
    {
        if ($this->closed) {
            throw new \RuntimeException('Client is closed');
        }
    }
}
