<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Connection;

use CrazyGoat\TiKV\Client\Grpc\GrpcClient;
use Pdpb\GetRegionRequest;
use Pdpb\GetRegionResponse;
use Pdpb\RequestHeader;

class PdClient
{
    private GrpcClient $grpc;
    private string $pdAddress;
    private ?int $clusterId = null;
    
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
        $request->setKey($key);
        
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
    
    public function close(): void
    {
        $this->grpc->close();
    }
}
