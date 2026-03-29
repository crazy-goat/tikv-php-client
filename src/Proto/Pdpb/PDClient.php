<?php
// GENERATED CODE -- DO NOT EDIT!

namespace CrazyGoat\Proto\Pdpb;

/**
 */
class PDClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * GetClusterInfo get the information of this cluster. It does not require
     * the cluster_id in request matchs the id of this cluster.
     * @param \CrazyGoat\Proto\Pdpb\GetClusterInfoRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetClusterInfoResponse>
     */
    public function GetClusterInfo(\CrazyGoat\Proto\Pdpb\GetClusterInfoRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetClusterInfo',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetClusterInfoResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * GetMembers get the member list of this cluster. It does not require
     * the cluster_id in request matchs the id of this cluster.
     * @param \CrazyGoat\Proto\Pdpb\GetMembersRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetMembersResponse>
     */
    public function GetMembers(\CrazyGoat\Proto\Pdpb\GetMembersRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetMembers',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetMembersResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function Tso($metadata = [], $options = []) {
        return $this->_bidiRequest('/pdpb.PD/Tso',
        ['\CrazyGoat\Proto\Pdpb\TsoResponse','decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\BootstrapRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\BootstrapResponse>
     */
    public function Bootstrap(\CrazyGoat\Proto\Pdpb\BootstrapRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/Bootstrap',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\BootstrapResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\IsBootstrappedRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\IsBootstrappedResponse>
     */
    public function IsBootstrapped(\CrazyGoat\Proto\Pdpb\IsBootstrappedRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/IsBootstrapped',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\IsBootstrappedResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\AllocIDRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\AllocIDResponse>
     */
    public function AllocID(\CrazyGoat\Proto\Pdpb\AllocIDRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/AllocID',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\AllocIDResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\IsSnapshotRecoveringRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\IsSnapshotRecoveringResponse>
     */
    public function IsSnapshotRecovering(\CrazyGoat\Proto\Pdpb\IsSnapshotRecoveringRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/IsSnapshotRecovering',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\IsSnapshotRecoveringResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\GetStoreRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetStoreResponse>
     */
    public function GetStore(\CrazyGoat\Proto\Pdpb\GetStoreRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetStore',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetStoreResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\PutStoreRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\PutStoreResponse>
     */
    public function PutStore(\CrazyGoat\Proto\Pdpb\PutStoreRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/PutStore',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\PutStoreResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\GetAllStoresRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetAllStoresResponse>
     */
    public function GetAllStores(\CrazyGoat\Proto\Pdpb\GetAllStoresRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetAllStores',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetAllStoresResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\StoreHeartbeatRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\StoreHeartbeatResponse>
     */
    public function StoreHeartbeat(\CrazyGoat\Proto\Pdpb\StoreHeartbeatRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/StoreHeartbeat',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\StoreHeartbeatResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function RegionHeartbeat($metadata = [], $options = []) {
        return $this->_bidiRequest('/pdpb.PD/RegionHeartbeat',
        ['\CrazyGoat\Proto\Pdpb\RegionHeartbeatResponse','decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\GetRegionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetRegionResponse>
     */
    public function GetRegion(\CrazyGoat\Proto\Pdpb\GetRegionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetRegion',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetRegionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\GetRegionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetRegionResponse>
     */
    public function GetPrevRegion(\CrazyGoat\Proto\Pdpb\GetRegionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetPrevRegion',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetRegionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\GetRegionByIDRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetRegionResponse>
     */
    public function GetRegionByID(\CrazyGoat\Proto\Pdpb\GetRegionByIDRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetRegionByID',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetRegionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function QueryRegion($metadata = [], $options = []) {
        return $this->_bidiRequest('/pdpb.PD/QueryRegion',
        ['\CrazyGoat\Proto\Pdpb\QueryRegionResponse','decode'],
        $metadata, $options);
    }

    /**
     * Deprecated: use BatchScanRegions instead.
     * @param \CrazyGoat\Proto\Pdpb\ScanRegionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\ScanRegionsResponse>
     */
    public function ScanRegions(\CrazyGoat\Proto\Pdpb\ScanRegionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/ScanRegions',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\ScanRegionsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\BatchScanRegionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\BatchScanRegionsResponse>
     */
    public function BatchScanRegions(\CrazyGoat\Proto\Pdpb\BatchScanRegionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/BatchScanRegions',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\BatchScanRegionsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @deprecated
     * @param \CrazyGoat\Proto\Pdpb\AskSplitRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\AskSplitResponse>
     */
    public function AskSplit(\CrazyGoat\Proto\Pdpb\AskSplitRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/AskSplit',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\AskSplitResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @deprecated
     * @param \CrazyGoat\Proto\Pdpb\ReportSplitRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\ReportSplitResponse>
     */
    public function ReportSplit(\CrazyGoat\Proto\Pdpb\ReportSplitRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/ReportSplit',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\ReportSplitResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\AskBatchSplitRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\AskBatchSplitResponse>
     */
    public function AskBatchSplit(\CrazyGoat\Proto\Pdpb\AskBatchSplitRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/AskBatchSplit',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\AskBatchSplitResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\ReportBatchSplitRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\ReportBatchSplitResponse>
     */
    public function ReportBatchSplit(\CrazyGoat\Proto\Pdpb\ReportBatchSplitRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/ReportBatchSplit',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\ReportBatchSplitResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\GetClusterConfigRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetClusterConfigResponse>
     */
    public function GetClusterConfig(\CrazyGoat\Proto\Pdpb\GetClusterConfigRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetClusterConfig',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetClusterConfigResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\PutClusterConfigRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\PutClusterConfigResponse>
     */
    public function PutClusterConfig(\CrazyGoat\Proto\Pdpb\PutClusterConfigRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/PutClusterConfig',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\PutClusterConfigResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\ScatterRegionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\ScatterRegionResponse>
     */
    public function ScatterRegion(\CrazyGoat\Proto\Pdpb\ScatterRegionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/ScatterRegion',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\ScatterRegionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\GetGCSafePointRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetGCSafePointResponse>
     */
    public function GetGCSafePoint(\CrazyGoat\Proto\Pdpb\GetGCSafePointRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetGCSafePoint',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetGCSafePointResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\UpdateGCSafePointRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\UpdateGCSafePointResponse>
     */
    public function UpdateGCSafePoint(\CrazyGoat\Proto\Pdpb\UpdateGCSafePointRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/UpdateGCSafePoint',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\UpdateGCSafePointResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\UpdateServiceGCSafePointRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\UpdateServiceGCSafePointResponse>
     */
    public function UpdateServiceGCSafePoint(\CrazyGoat\Proto\Pdpb\UpdateServiceGCSafePointRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/UpdateServiceGCSafePoint',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\UpdateServiceGCSafePointResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\GetGCSafePointV2Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetGCSafePointV2Response>
     */
    public function GetGCSafePointV2(\CrazyGoat\Proto\Pdpb\GetGCSafePointV2Request $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetGCSafePointV2',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetGCSafePointV2Response', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\WatchGCSafePointV2Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function WatchGCSafePointV2(\CrazyGoat\Proto\Pdpb\WatchGCSafePointV2Request $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/pdpb.PD/WatchGCSafePointV2',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\WatchGCSafePointV2Response', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\UpdateGCSafePointV2Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\UpdateGCSafePointV2Response>
     */
    public function UpdateGCSafePointV2(\CrazyGoat\Proto\Pdpb\UpdateGCSafePointV2Request $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/UpdateGCSafePointV2',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\UpdateGCSafePointV2Response', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\UpdateServiceSafePointV2Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\UpdateServiceSafePointV2Response>
     */
    public function UpdateServiceSafePointV2(\CrazyGoat\Proto\Pdpb\UpdateServiceSafePointV2Request $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/UpdateServiceSafePointV2',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\UpdateServiceSafePointV2Response', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\GetAllGCSafePointV2Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetAllGCSafePointV2Response>
     */
    public function GetAllGCSafePointV2(\CrazyGoat\Proto\Pdpb\GetAllGCSafePointV2Request $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetAllGCSafePointV2',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetAllGCSafePointV2Response', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\AdvanceGCSafePointRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\AdvanceGCSafePointResponse>
     */
    public function AdvanceGCSafePoint(\CrazyGoat\Proto\Pdpb\AdvanceGCSafePointRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/AdvanceGCSafePoint',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\AdvanceGCSafePointResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\AdvanceTxnSafePointRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\AdvanceTxnSafePointResponse>
     */
    public function AdvanceTxnSafePoint(\CrazyGoat\Proto\Pdpb\AdvanceTxnSafePointRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/AdvanceTxnSafePoint',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\AdvanceTxnSafePointResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\SetGCBarrierRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\SetGCBarrierResponse>
     */
    public function SetGCBarrier(\CrazyGoat\Proto\Pdpb\SetGCBarrierRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/SetGCBarrier',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\SetGCBarrierResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\DeleteGCBarrierRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\DeleteGCBarrierResponse>
     */
    public function DeleteGCBarrier(\CrazyGoat\Proto\Pdpb\DeleteGCBarrierRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/DeleteGCBarrier',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\DeleteGCBarrierResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\SetGlobalGCBarrierRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\SetGlobalGCBarrierResponse>
     */
    public function SetGlobalGCBarrier(\CrazyGoat\Proto\Pdpb\SetGlobalGCBarrierRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/SetGlobalGCBarrier',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\SetGlobalGCBarrierResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\DeleteGlobalGCBarrierRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\DeleteGlobalGCBarrierResponse>
     */
    public function DeleteGlobalGCBarrier(\CrazyGoat\Proto\Pdpb\DeleteGlobalGCBarrierRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/DeleteGlobalGCBarrier',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\DeleteGlobalGCBarrierResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\GetGCStateRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetGCStateResponse>
     */
    public function GetGCState(\CrazyGoat\Proto\Pdpb\GetGCStateRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetGCState',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetGCStateResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\GetAllKeyspacesGCStatesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetAllKeyspacesGCStatesResponse>
     */
    public function GetAllKeyspacesGCStates(\CrazyGoat\Proto\Pdpb\GetAllKeyspacesGCStatesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetAllKeyspacesGCStates',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetAllKeyspacesGCStatesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function SyncRegions($metadata = [], $options = []) {
        return $this->_bidiRequest('/pdpb.PD/SyncRegions',
        ['\CrazyGoat\Proto\Pdpb\SyncRegionResponse','decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\GetOperatorRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetOperatorResponse>
     */
    public function GetOperator(\CrazyGoat\Proto\Pdpb\GetOperatorRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetOperator',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetOperatorResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\SyncMaxTSRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\SyncMaxTSResponse>
     */
    public function SyncMaxTS(\CrazyGoat\Proto\Pdpb\SyncMaxTSRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/SyncMaxTS',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\SyncMaxTSResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\SplitRegionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\SplitRegionsResponse>
     */
    public function SplitRegions(\CrazyGoat\Proto\Pdpb\SplitRegionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/SplitRegions',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\SplitRegionsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\SplitAndScatterRegionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\SplitAndScatterRegionsResponse>
     */
    public function SplitAndScatterRegions(\CrazyGoat\Proto\Pdpb\SplitAndScatterRegionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/SplitAndScatterRegions',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\SplitAndScatterRegionsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\GetDCLocationInfoRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetDCLocationInfoResponse>
     */
    public function GetDCLocationInfo(\CrazyGoat\Proto\Pdpb\GetDCLocationInfoRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetDCLocationInfo',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetDCLocationInfoResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\StoreGlobalConfigRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\StoreGlobalConfigResponse>
     */
    public function StoreGlobalConfig(\CrazyGoat\Proto\Pdpb\StoreGlobalConfigRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/StoreGlobalConfig',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\StoreGlobalConfigResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\LoadGlobalConfigRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\LoadGlobalConfigResponse>
     */
    public function LoadGlobalConfig(\CrazyGoat\Proto\Pdpb\LoadGlobalConfigRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/LoadGlobalConfig',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\LoadGlobalConfigResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\WatchGlobalConfigRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function WatchGlobalConfig(\CrazyGoat\Proto\Pdpb\WatchGlobalConfigRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/pdpb.PD/WatchGlobalConfig',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\WatchGlobalConfigResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ClientStreamingCall
     */
    public function ReportBuckets($metadata = [], $options = []) {
        return $this->_clientStreamRequest('/pdpb.PD/ReportBuckets',
        ['\CrazyGoat\Proto\Pdpb\ReportBucketsResponse','decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\ReportMinResolvedTsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\ReportMinResolvedTsResponse>
     */
    public function ReportMinResolvedTS(\CrazyGoat\Proto\Pdpb\ReportMinResolvedTsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/ReportMinResolvedTS',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\ReportMinResolvedTsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\SetExternalTimestampRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\SetExternalTimestampResponse>
     */
    public function SetExternalTimestamp(\CrazyGoat\Proto\Pdpb\SetExternalTimestampRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/SetExternalTimestamp',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\SetExternalTimestampResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Pdpb\GetExternalTimestampRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetExternalTimestampResponse>
     */
    public function GetExternalTimestamp(\CrazyGoat\Proto\Pdpb\GetExternalTimestampRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetExternalTimestamp',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetExternalTimestampResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Get the minimum timestamp across all keyspace groups from API server
     * TODO: Currently, we need to ask API server to get the minimum timestamp.
     * Once we support service discovery, we can remove it.
     * @param \CrazyGoat\Proto\Pdpb\GetMinTSRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Pdpb\GetMinTSResponse>
     */
    public function GetMinTS(\CrazyGoat\Proto\Pdpb\GetMinTSRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetMinTS',
        $argument,
        ['\CrazyGoat\Proto\Pdpb\GetMinTSResponse', 'decode'],
        $metadata, $options);
    }

}
