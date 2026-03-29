<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Connection;

use CrazyGoat\TiKV\Client\Grpc\GrpcClient;
use CrazyGoat\Proto\Pdpb\GetRegionRequest;
use CrazyGoat\Proto\Pdpb\GetRegionResponse;
use CrazyGoat\Proto\Pdpb\GetStoreRequest;
use CrazyGoat\Proto\Pdpb\GetStoreResponse;
use CrazyGoat\Proto\Pdpb\RequestHeader;
use CrazyGoat\Proto\Pdpb\ScanRegionsRequest;
use CrazyGoat\Proto\Pdpb\ScanRegionsResponse;
use CrazyGoat\Proto\Metapb\Store;

class PdClient
{
    private GrpcClient $grpc;
    private string $pdAddress;
    private ?int $clusterId = null;
    private array $storeCache = [];
    
    public function __construct(string $pdAddress)
    {
        $this->grpc = new GrpcClient();
        $this->pdAddress = $pdAddress;
    }
    
    private function createHeader(): RequestHeader
    {
        $header = new RequestHeader();
        $header->setClusterId($this->clusterId ?? 0);
        return $header;
    }
    
    public function getRegion(string $key): array
    {
        $request = new GetRegionRequest();
        $request->setHeader($this->createHeader());
        $request->setRegionKey($key);
        
        try {
            $response = $this->grpc->call(
                $this->pdAddress,
                'pdpb.PD',
                'GetRegion',
                $request,
                GetRegionResponse::class
            );
            
            // Extract cluster_id from response for future requests
            $respHeader = $response->getHeader();
            if ($respHeader && $this->clusterId === null) {
                $this->clusterId = $respHeader->getClusterId();
            }
            
            $region = $response->getRegion();
            $leader = $response->getLeader();
            $regionEpoch = $region ? $region->getRegionEpoch() : null;
            
            return [
                'region_id' => $region ? $region->getId() : 0,
                'leader_peer_id' => $leader ? $leader->getId() : 0,
                'leader_store_id' => $leader ? $leader->getStoreId() : 1,
                'region_epoch_conf_ver' => $regionEpoch ? $regionEpoch->getConfVer() : 0,
                'region_epoch_version' => $regionEpoch ? $regionEpoch->getVersion() : 0,
            ];
        } catch (\RuntimeException $e) {
            // If we got cluster_id mismatch, extract it from error and retry
            if (strpos($e->getMessage(), 'mismatch cluster id') !== false) {
                preg_match('/need (\d+) but got/', $e->getMessage(), $matches);
                if (isset($matches[1])) {
                    $this->clusterId = (int)$matches[1];
                    // Retry with correct cluster_id
                    return $this->getRegion($key);
                }
            }
            throw $e;
        }
    }
    
    /**
     * Get store information from PD
     * 
     * @param int $storeId Store ID
     * @return Store|null Store information or null if not found
     */
    public function getStore(int $storeId): ?Store
    {
        // Check cache first
        if (isset($this->storeCache[$storeId])) {
            return $this->storeCache[$storeId];
        }
        
        $request = new GetStoreRequest();
        $request->setHeader($this->createHeader());
        $request->setStoreId($storeId);
        
        try {
            $response = $this->grpc->call(
                $this->pdAddress,
                'pdpb.PD',
                'GetStore',
                $request,
                GetStoreResponse::class
            );
            
            // Extract cluster_id from response for future requests
            $respHeader = $response->getHeader();
            if ($respHeader && $this->clusterId === null) {
                $this->clusterId = $respHeader->getClusterId();
            }
            
            $store = $response->getStore();
            if ($store) {
                $this->storeCache[$storeId] = $store;
            }
            
            return $store;
        } catch (\RuntimeException $e) {
            // If we got cluster_id mismatch, extract it from error and retry
            if (strpos($e->getMessage(), 'mismatch cluster id') !== false) {
                preg_match('/need (\d+) but got/', $e->getMessage(), $matches);
                if (isset($matches[1])) {
                    $this->clusterId = (int)$matches[1];
                    // Retry with correct cluster_id
                    return $this->getStore($storeId);
                }
            }
            throw $e;
        }
    }
    
    public function close(): void
    {
        $this->grpc->close();
    }
    
    /**
     * Scan regions in a key range
     * 
     * @param string $startKey Start key of the range
     * @param string $endKey End key of the range (empty means +inf)
     * @param int $limit Maximum number of regions to return (0 = no limit)
     * @return array Array of region info arrays
     */
    public function scanRegions(string $startKey, string $endKey, int $limit = 0): array
    {
        $request = new ScanRegionsRequest();
        $request->setHeader($this->createHeader());
        $request->setStartKey($startKey);
        $request->setEndKey($endKey);
        $request->setLimit($limit);
        
        try {
            $response = $this->grpc->call(
                $this->pdAddress,
                'pdpb.PD',
                'ScanRegions',
                $request,
                ScanRegionsResponse::class
            );
            
            // Extract cluster_id from response for future requests
            $respHeader = $response->getHeader();
            if ($respHeader && $this->clusterId === null) {
                $this->clusterId = $respHeader->getClusterId();
            }
            
            $regions = [];
            $regionMetas = $response->getRegionMetas();
            $leaders = $response->getLeaders();
            
            // Combine region metas with leaders
            foreach ($regionMetas as $index => $region) {
                $leader = $leaders[$index] ?? null;
                $regionEpoch = $region ? $region->getRegionEpoch() : null;
                
                $regions[] = [
                    'region_id' => $region ? $region->getId() : 0,
                    'leader_peer_id' => $leader ? $leader->getId() : 0,
                    'leader_store_id' => $leader ? $leader->getStoreId() : 1,
                    'region_epoch_conf_ver' => $regionEpoch ? $regionEpoch->getConfVer() : 0,
                    'region_epoch_version' => $regionEpoch ? $regionEpoch->getVersion() : 0,
                    'start_key' => $region ? $region->getStartKey() : '',
                    'end_key' => $region ? $region->getEndKey() : '',
                ];
            }
            
            return $regions;
        } catch (\RuntimeException $e) {
            // If we got cluster_id mismatch, extract it from error and retry
            if (strpos($e->getMessage(), 'mismatch cluster id') !== false) {
                preg_match('/need (\d+) but got/', $e->getMessage(), $matches);
                if (isset($matches[1])) {
                    $this->clusterId = (int)$matches[1];
                    // Retry with correct cluster_id
                    return $this->scanRegions($startKey, $endKey, $limit);
                }
            }
            throw $e;
        }
    }
}
