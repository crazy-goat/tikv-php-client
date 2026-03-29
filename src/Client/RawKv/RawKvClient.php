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
