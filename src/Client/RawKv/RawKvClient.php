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
use CrazyGoat\Proto\Kvrpcpb\RawDeleteRangeRequest;
use CrazyGoat\Proto\Kvrpcpb\RawDeleteRangeResponse;
use CrazyGoat\Proto\Kvrpcpb\RawGetKeyTTLRequest;
use CrazyGoat\Proto\Kvrpcpb\RawGetKeyTTLResponse;
use CrazyGoat\Proto\Kvrpcpb\RawCASRequest;
use CrazyGoat\Proto\Kvrpcpb\RawCASResponse;
use CrazyGoat\Proto\Kvrpcpb\RawChecksumRequest;
use CrazyGoat\Proto\Kvrpcpb\RawChecksumResponse;
use CrazyGoat\Proto\Kvrpcpb\RawBatchScanRequest;
use CrazyGoat\Proto\Kvrpcpb\RawBatchScanResponse;
use CrazyGoat\Proto\Kvrpcpb\ChecksumAlgorithm;
use CrazyGoat\Proto\Kvrpcpb\KeyRange;
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
    
    /**
     * Get the remaining TTL (time-to-live) for a key
     * 
     * @param string $key Key to check
     * @return int|null Remaining TTL in seconds, or null if key not found or has no TTL
     */
    public function getKeyTTL(string $key): ?int
    {
        $this->ensureOpen();
        
        return $this->executeWithRetry($key, function() use ($key) {
            $regionInfo = $this->getRegionInfo($key);
            $tikvAddress = $this->getTikvAddress($regionInfo['leader_store_id']);
            
            $request = new RawGetKeyTTLRequest();
            $request->setContext($this->createContext($regionInfo));
            $request->setKey($key);
            
            $response = $this->grpc->call(
                $tikvAddress,
                'tikvpb.Tikv',
                'RawGetKeyTTL',
                $request,
                RawGetKeyTTLResponse::class
            );
            
            $error = $response->getError();
            if ($error !== '') {
                throw new \RuntimeException("RawGetKeyTTL failed: {$error}");
            }
            
            if ($response->getNotFound()) {
                return null;
            }
            
            $ttl = (int) $response->getTtl();
            
            // TTL of 0 means no expiration set
            return $ttl > 0 ? $ttl : null;
        });
    }
    
    /**
     * Atomic Compare-And-Swap operation
     * 
     * Atomically compares the current value of a key with an expected value,
     * and if they match, replaces it with a new value. This is the fundamental
     * building block for optimistic locking, distributed locks, and atomic counters.
     * 
     * The operation is atomic at the TiKV region level — no other operation can
     * interleave between the comparison and the write.
     * 
     * @param string $key Key to compare-and-swap
     * @param string|null $expectedValue Expected current value, or null if the key should not exist
     * @param string $newValue New value to set if comparison succeeds
     * @param int $ttl Time-to-live in seconds for the new value (0 = no expiration)
     * @return CasResult Contains whether the swap succeeded and the previous value
     */
    public function compareAndSwap(string $key, ?string $expectedValue, string $newValue, int $ttl = 0): CasResult
    {
        $this->ensureOpen();
        
        return $this->executeWithRetry($key, function() use ($key, $expectedValue, $newValue, $ttl) {
            $regionInfo = $this->getRegionInfo($key);
            $tikvAddress = $this->getTikvAddress($regionInfo['leader_store_id']);
            
            $request = new RawCASRequest();
            $request->setContext($this->createContext($regionInfo));
            $request->setKey($key);
            $request->setValue($newValue);
            
            if ($expectedValue === null) {
                // Expect the key to NOT exist (used by putIfAbsent)
                $request->setPreviousNotExist(true);
            } else {
                $request->setPreviousNotExist(false);
                $request->setPreviousValue($expectedValue);
            }
            
            if ($ttl > 0) {
                $request->setTtl($ttl);
            }
            
            /** @var RawCASResponse $response */
            $response = $this->grpc->call(
                $tikvAddress,
                'tikvpb.Tikv',
                'RawCompareAndSwap',
                $request,
                RawCASResponse::class
            );
            
            $error = $response->getError();
            if ($error !== '') {
                throw new \RuntimeException("RawCompareAndSwap failed: {$error}");
            }
            
            $previousValue = $response->getPreviousNotExist()
                ? null
                : $response->getPreviousValue();
            
            return new CasResult(
                swapped: $response->getSucceed(),
                previousValue: $previousValue,
            );
        });
    }
    
    /**
     * Atomically put a value only if the key does not already exist
     * 
     * This is a convenience wrapper around compareAndSwap() with expectedValue=null.
     * It is useful for idempotent initialization, distributed locks, and ensuring
     * exactly-once semantics.
     * 
     * @param string $key Key to conditionally insert
     * @param string $value Value to store if key doesn't exist
     * @param int $ttl Time-to-live in seconds (0 = no expiration)
     * @return string|null null if the key was successfully inserted (didn't exist before),
     *                     or the existing value if the key already existed
     */
    public function putIfAbsent(string $key, string $value, int $ttl = 0): ?string
    {
        $result = $this->compareAndSwap($key, null, $value, $ttl);
        
        if ($result->swapped) {
            return null; // Successfully inserted — key didn't exist
        }
        
        // Key already existed — return the existing value
        return $result->previousValue;
    }
    
    /**
     * Compute a CRC64-XOR checksum over all key-value pairs in a range
     * 
     * The checksum is computed server-side by TiKV. For ranges spanning multiple
     * regions, individual region checksums are XOR-merged (CRC64-XOR is associative
     * and commutative), and key/byte counts are summed.
     * 
     * This is useful for data integrity verification, backup validation, and
     * detecting data drift between replicas or after migration.
     * 
     * @param string $startKey Start key (inclusive)
     * @param string $endKey End key (exclusive), empty string means no upper bound
     * @return ChecksumResult Contains checksum, total key count, and total byte count
     */
    public function checksum(string $startKey, string $endKey): ChecksumResult
    {
        $this->ensureOpen();
        
        // Get all regions covering the range
        $regions = $this->pdClient->scanRegions($startKey, $endKey, 0);
        
        $mergedChecksum = 0;
        $mergedTotalKvs = 0;
        $mergedTotalBytes = 0;
        
        foreach ($regions as $regionInfo) {
            $regionStart = $regionInfo['start_key'];
            $regionEnd = $regionInfo['end_key'];
            
            // Clamp range to region boundaries
            $rangeStart = ($startKey > $regionStart) ? $startKey : $regionStart;
            $rangeEnd = ($endKey === '' || ($regionEnd !== '' && $endKey > $regionEnd)) ? $regionEnd : $endKey;
            
            if ($rangeStart >= $rangeEnd && $rangeEnd !== '') {
                continue;
            }
            
            $regionResult = $this->executeChecksumForRegion($regionInfo, $rangeStart, $rangeEnd);
            
            // XOR-merge checksums (CRC64-XOR is associative and commutative)
            $mergedChecksum ^= $regionResult->checksum;
            $mergedTotalKvs += $regionResult->totalKvs;
            $mergedTotalBytes += $regionResult->totalBytes;
        }
        
        return new ChecksumResult(
            checksum: $mergedChecksum,
            totalKvs: $mergedTotalKvs,
            totalBytes: $mergedTotalBytes,
        );
    }
    
    private function executeChecksumForRegion(array $regionInfo, string $startKey, string $endKey): ChecksumResult
    {
        return $this->executeWithRetry($startKey, function() use ($regionInfo, $startKey, $endKey) {
            $tikvAddress = $this->getTikvAddress($regionInfo['leader_store_id']);
            
            $range = new KeyRange();
            $range->setStartKey($startKey);
            if ($endKey !== '') {
                $range->setEndKey($endKey);
            }
            
            $request = new RawChecksumRequest();
            $request->setContext($this->createContext($regionInfo));
            $request->setAlgorithm(ChecksumAlgorithm::Crc64_Xor);
            $request->setRanges([$range]);
            
            /** @var RawChecksumResponse $response */
            $response = $this->grpc->call(
                $tikvAddress,
                'tikvpb.Tikv',
                'RawChecksum',
                $request,
                RawChecksumResponse::class
            );
            
            $error = $response->getError();
            if ($error !== '') {
                throw new \RuntimeException("RawChecksum failed: {$error}");
            }
            
            return new ChecksumResult(
                checksum: (int) $response->getChecksum(),
                totalKvs: (int) $response->getTotalKvs(),
                totalBytes: (int) $response->getTotalBytes(),
            );
        });
    }
    
    /**
     * Scan multiple non-contiguous key ranges in a single operation
     * 
     * Each range is defined as [startKey, endKey) and scanned independently with
     * its own limit. Results are returned as an array of arrays, one per input range,
     * preserving the input order.
     * 
     * For ranges spanning multiple regions, the request is split per-region and
     * results are merged transparently.
     * 
     * @param array<array{0: string, 1: string}> $ranges Array of [startKey, endKey] pairs
     * @param int $eachLimit Maximum number of key-value pairs to return per range
     * @param bool $keyOnly If true, return only keys without values
     * @return array<array<array{key: string, value: ?string}>> Array of results per range
     */
    public function batchScan(array $ranges, int $eachLimit, bool $keyOnly = false): array
    {
        $this->ensureOpen();
        
        if (empty($ranges)) {
            return [];
        }
        
        if ($eachLimit <= 0) {
            throw new \InvalidArgumentException('eachLimit must be greater than 0');
        }
        
        // For each input range, perform a scan (reusing existing scan logic)
        // This is more robust than trying to group ranges by region and use
        // the RawBatchScan RPC, because:
        // 1. Ranges may span multiple regions
        // 2. The RawBatchScan response returns a flat list without range delimiters
        // 3. The existing scan() method already handles multi-region correctly
        //
        // The RawBatchScan RPC is a single-region operation — it doesn't handle
        // cross-region ranges. So we'd need the same splitting logic anyway.
        // Using scan() per range is simpler and equally correct.
        $results = [];
        foreach ($ranges as $range) {
            if (!is_array($range) || count($range) !== 2) {
                throw new \InvalidArgumentException('Each range must be an array of [startKey, endKey]');
            }
            [$startKey, $endKey] = $range;
            $results[] = $this->scan($startKey, $endKey, $eachLimit, $keyOnly);
        }
        
        return $results;
    }
    
    /**
     * Store a key-value pair in TiKV
     * 
     * @param string $key Key to store
     * @param string $value Value to store
     * @param int $ttl Time-to-live in seconds (0 = no expiration)
     */
    public function put(string $key, string $value, int $ttl = 0): void
    {
        $this->ensureOpen();
        
        $this->executeWithRetry($key, function() use ($key, $value, $ttl) {
            $regionInfo = $this->getRegionInfo($key);
            $tikvAddress = $this->getTikvAddress($regionInfo['leader_store_id']);
            
            $request = new RawPutRequest();
            $request->setContext($this->createContext($regionInfo));
            $request->setKey($key);
            $request->setValue($value);
            if ($ttl > 0) {
                $request->setTtl($ttl);
            }
            
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
     * @param int $ttl Time-to-live in seconds applied to all keys (0 = no expiration)
     * @throws \RuntimeException If any region fails after retries
     */
    public function batchPut(array $keyValuePairs, int $ttl = 0): void
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
            $this->executeBatchPutForRegion($regionData['region_info'], $regionData['pairs'], $ttl);
        }
    }
    
    private function executeBatchPutForRegion(array $regionInfo, array $pairs, int $ttl = 0): void
    {
        $this->executeWithRetry($pairs[0]->getKey(), function() use ($regionInfo, $pairs, $ttl) {
            $tikvAddress = $this->getTikvAddress($regionInfo['leader_store_id']);
            
            $request = new RawBatchPutRequest();
            $request->setContext($this->createContext($regionInfo));
            $request->setPairs($pairs);
            if ($ttl > 0) {
                // Use the new `ttls` field (repeated) with a single value applied to all keys
                $request->setTtls([$ttl]);
            }
            
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
     * Delete all keys in range [startKey, endKey)
     * 
     * Sends RawDeleteRange RPC to each region covering the range.
     * This is an atomic operation per region — within a single region, either
     * all keys in the range are deleted or none are (on error).
     * 
     * @param string $startKey Start key (inclusive)
     * @param string $endKey End key (exclusive)
     * @throws \RuntimeException If any region fails after retries
     */
    public function deleteRange(string $startKey, string $endKey): void
    {
        $this->ensureOpen();
        
        if ($startKey === $endKey) {
            return;
        }
        
        $regions = $this->pdClient->scanRegions($startKey, $endKey, 0);
        
        foreach ($regions as $regionInfo) {
            $regionStart = $regionInfo['start_key'];
            $regionEnd = $regionInfo['end_key'];
            
            // Clamp range to region boundaries
            $rangeStart = ($startKey > $regionStart) ? $startKey : $regionStart;
            $rangeEnd = ($endKey === '' || ($regionEnd !== '' && $endKey > $regionEnd)) ? $regionEnd : $endKey;
            
            if ($rangeStart >= $rangeEnd && $rangeEnd !== '') {
                continue;
            }
            
            $this->executeDeleteRangeForRegion($regionInfo, $rangeStart, $rangeEnd);
        }
    }
    
    /**
     * Delete all keys with the given prefix
     * 
     * Convenience wrapper around deleteRange() that calculates the end key
     * from the prefix. Equivalent to deleteRange(prefix, nextPrefix(prefix)).
     * 
     * @param string $prefix Key prefix — all keys starting with this will be deleted
     * @throws \RuntimeException If any region fails after retries
     */
    public function deletePrefix(string $prefix): void
    {
        $this->ensureOpen();
        
        if ($prefix === '') {
            throw new \InvalidArgumentException('Prefix must not be empty — refusing to delete all keys');
        }
        
        $endKey = $this->calculatePrefixEndKey($prefix);
        $this->deleteRange($prefix, $endKey);
    }
    
    private function executeDeleteRangeForRegion(array $regionInfo, string $startKey, string $endKey): void
    {
        $this->executeWithRetry($startKey, function() use ($regionInfo, $startKey, $endKey) {
            $tikvAddress = $this->getTikvAddress($regionInfo['leader_store_id']);
            
            $request = new RawDeleteRangeRequest();
            $request->setContext($this->createContext($regionInfo));
            $request->setStartKey($startKey);
            $request->setEndKey($endKey);
            
            $response = $this->grpc->call(
                $tikvAddress,
                'tikvpb.Tikv',
                'RawDeleteRange',
                $request,
                RawDeleteRangeResponse::class
            );
            
            $error = $response->getError();
            if ($error !== '') {
                throw new \RuntimeException("RawDeleteRange failed: {$error}");
            }
            
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
     * Uses TiKV's native reverse=true flag with correct key semantics.
     * Per the protobuf contract (kvrpcpb.proto RawScanRequest):
     *   "when scanning backward, it scans [end_key, start_key) in descending order, where end_key < start_key"
     * 
     * So start_key is the upper bound (exclusive) and end_key is the lower bound (inclusive).
     * This matches the Go client (client-go) ReverseScan implementation.
     * 
     * @param string $startKey Upper bound key (exclusive), the cursor starts here going backwards
     * @param string $endKey Lower bound key (inclusive), the cursor stops here
     * @param int $limit Maximum number of keys to return (0 = unlimited)
     * @param bool $keyOnly If true, return only keys without values
     * @return array Array of ['key' => key, 'value' => value] pairs in descending order
     */
    public function reverseScan(string $startKey, string $endKey, int $limit = 0, bool $keyOnly = false): array
    {
        $this->ensureOpen();
        
        // Get all regions covering the range (endKey..startKey since endKey < startKey)
        $regions = $this->pdClient->scanRegions($endKey, $startKey, 0);
        
        $results = [];
        $remainingLimit = $limit;
        
        // Iterate regions in reverse order (from highest to lowest) for reverse scan
        $regions = array_reverse($regions);
        
        foreach ($regions as $regionInfo) {
            $regionStart = $regionInfo['start_key'];
            $regionEnd = $regionInfo['end_key'];
            
            // For reverse scan, start_key is upper bound, end_key is lower bound
            // Clamp to the region boundaries
            $scanStartKey = ($regionEnd === '' || $startKey < $regionEnd) ? $startKey : $regionEnd;
            $scanEndKey = ($endKey > $regionStart) ? $endKey : $regionStart;
            
            // Skip if range is invalid (end must be < start for reverse)
            if ($scanEndKey >= $scanStartKey && $scanEndKey !== '') {
                continue;
            }
            
            $regionLimit = $remainingLimit === 0 ? PHP_INT_MAX : $remainingLimit;
            
            $regionResults = $this->executeScanForRegion(
                $regionInfo,
                $scanStartKey,
                $scanEndKey,
                $regionLimit,
                $keyOnly,
                true // native reverse scan
            );
            
            $results = array_merge($results, $regionResults);
            
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
