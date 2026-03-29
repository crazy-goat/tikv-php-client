<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\RawKv;

use CrazyGoat\TiKV\Client\Connection\PdClient;
use CrazyGoat\TiKV\Client\Grpc\GrpcClient;
use Kvrpcpb\Context;
use Kvrpcpb\RawGetRequest;
use Kvrpcpb\RawGetResponse;
use Kvrpcpb\RawPutRequest;
use Kvrpcpb\RawPutResponse;
use Kvrpcpb\RawDeleteRequest;
use Kvrpcpb\RawDeleteResponse;
use Metapb\Peer;
use Metapb\RegionEpoch;

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
