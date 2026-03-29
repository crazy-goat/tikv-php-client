<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Pdpb;

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
     * @param \Pdpb\GetClusterInfoRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetClusterInfoResponse>
     */
    public function GetClusterInfo(\Pdpb\GetClusterInfoRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetClusterInfo',
        $argument,
        ['\Pdpb\GetClusterInfoResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * GetMembers get the member list of this cluster. It does not require
     * the cluster_id in request matchs the id of this cluster.
     * @param \Pdpb\GetMembersRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetMembersResponse>
     */
    public function GetMembers(\Pdpb\GetMembersRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetMembers',
        $argument,
        ['\Pdpb\GetMembersResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function Tso($metadata = [], $options = []) {
        return $this->_bidiRequest('/pdpb.PD/Tso',
        ['\Pdpb\TsoResponse','decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\BootstrapRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\BootstrapResponse>
     */
    public function Bootstrap(\Pdpb\BootstrapRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/Bootstrap',
        $argument,
        ['\Pdpb\BootstrapResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\IsBootstrappedRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\IsBootstrappedResponse>
     */
    public function IsBootstrapped(\Pdpb\IsBootstrappedRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/IsBootstrapped',
        $argument,
        ['\Pdpb\IsBootstrappedResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\AllocIDRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\AllocIDResponse>
     */
    public function AllocID(\Pdpb\AllocIDRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/AllocID',
        $argument,
        ['\Pdpb\AllocIDResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\IsSnapshotRecoveringRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\IsSnapshotRecoveringResponse>
     */
    public function IsSnapshotRecovering(\Pdpb\IsSnapshotRecoveringRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/IsSnapshotRecovering',
        $argument,
        ['\Pdpb\IsSnapshotRecoveringResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\GetStoreRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetStoreResponse>
     */
    public function GetStore(\Pdpb\GetStoreRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetStore',
        $argument,
        ['\Pdpb\GetStoreResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\PutStoreRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\PutStoreResponse>
     */
    public function PutStore(\Pdpb\PutStoreRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/PutStore',
        $argument,
        ['\Pdpb\PutStoreResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\GetAllStoresRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetAllStoresResponse>
     */
    public function GetAllStores(\Pdpb\GetAllStoresRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetAllStores',
        $argument,
        ['\Pdpb\GetAllStoresResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\StoreHeartbeatRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\StoreHeartbeatResponse>
     */
    public function StoreHeartbeat(\Pdpb\StoreHeartbeatRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/StoreHeartbeat',
        $argument,
        ['\Pdpb\StoreHeartbeatResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function RegionHeartbeat($metadata = [], $options = []) {
        return $this->_bidiRequest('/pdpb.PD/RegionHeartbeat',
        ['\Pdpb\RegionHeartbeatResponse','decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\GetRegionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetRegionResponse>
     */
    public function GetRegion(\Pdpb\GetRegionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetRegion',
        $argument,
        ['\Pdpb\GetRegionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\GetRegionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetRegionResponse>
     */
    public function GetPrevRegion(\Pdpb\GetRegionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetPrevRegion',
        $argument,
        ['\Pdpb\GetRegionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\GetRegionByIDRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetRegionResponse>
     */
    public function GetRegionByID(\Pdpb\GetRegionByIDRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetRegionByID',
        $argument,
        ['\Pdpb\GetRegionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function QueryRegion($metadata = [], $options = []) {
        return $this->_bidiRequest('/pdpb.PD/QueryRegion',
        ['\Pdpb\QueryRegionResponse','decode'],
        $metadata, $options);
    }

    /**
     * Deprecated: use BatchScanRegions instead.
     * @param \Pdpb\ScanRegionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\ScanRegionsResponse>
     */
    public function ScanRegions(\Pdpb\ScanRegionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/ScanRegions',
        $argument,
        ['\Pdpb\ScanRegionsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\BatchScanRegionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\BatchScanRegionsResponse>
     */
    public function BatchScanRegions(\Pdpb\BatchScanRegionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/BatchScanRegions',
        $argument,
        ['\Pdpb\BatchScanRegionsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @deprecated
     * @param \Pdpb\AskSplitRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\AskSplitResponse>
     */
    public function AskSplit(\Pdpb\AskSplitRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/AskSplit',
        $argument,
        ['\Pdpb\AskSplitResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @deprecated
     * @param \Pdpb\ReportSplitRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\ReportSplitResponse>
     */
    public function ReportSplit(\Pdpb\ReportSplitRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/ReportSplit',
        $argument,
        ['\Pdpb\ReportSplitResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\AskBatchSplitRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\AskBatchSplitResponse>
     */
    public function AskBatchSplit(\Pdpb\AskBatchSplitRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/AskBatchSplit',
        $argument,
        ['\Pdpb\AskBatchSplitResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\ReportBatchSplitRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\ReportBatchSplitResponse>
     */
    public function ReportBatchSplit(\Pdpb\ReportBatchSplitRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/ReportBatchSplit',
        $argument,
        ['\Pdpb\ReportBatchSplitResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\GetClusterConfigRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetClusterConfigResponse>
     */
    public function GetClusterConfig(\Pdpb\GetClusterConfigRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetClusterConfig',
        $argument,
        ['\Pdpb\GetClusterConfigResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\PutClusterConfigRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\PutClusterConfigResponse>
     */
    public function PutClusterConfig(\Pdpb\PutClusterConfigRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/PutClusterConfig',
        $argument,
        ['\Pdpb\PutClusterConfigResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\ScatterRegionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\ScatterRegionResponse>
     */
    public function ScatterRegion(\Pdpb\ScatterRegionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/ScatterRegion',
        $argument,
        ['\Pdpb\ScatterRegionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\GetGCSafePointRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetGCSafePointResponse>
     */
    public function GetGCSafePoint(\Pdpb\GetGCSafePointRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetGCSafePoint',
        $argument,
        ['\Pdpb\GetGCSafePointResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\UpdateGCSafePointRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\UpdateGCSafePointResponse>
     */
    public function UpdateGCSafePoint(\Pdpb\UpdateGCSafePointRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/UpdateGCSafePoint',
        $argument,
        ['\Pdpb\UpdateGCSafePointResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\UpdateServiceGCSafePointRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\UpdateServiceGCSafePointResponse>
     */
    public function UpdateServiceGCSafePoint(\Pdpb\UpdateServiceGCSafePointRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/UpdateServiceGCSafePoint',
        $argument,
        ['\Pdpb\UpdateServiceGCSafePointResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\GetGCSafePointV2Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetGCSafePointV2Response>
     */
    public function GetGCSafePointV2(\Pdpb\GetGCSafePointV2Request $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetGCSafePointV2',
        $argument,
        ['\Pdpb\GetGCSafePointV2Response', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\WatchGCSafePointV2Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function WatchGCSafePointV2(\Pdpb\WatchGCSafePointV2Request $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/pdpb.PD/WatchGCSafePointV2',
        $argument,
        ['\Pdpb\WatchGCSafePointV2Response', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\UpdateGCSafePointV2Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\UpdateGCSafePointV2Response>
     */
    public function UpdateGCSafePointV2(\Pdpb\UpdateGCSafePointV2Request $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/UpdateGCSafePointV2',
        $argument,
        ['\Pdpb\UpdateGCSafePointV2Response', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\UpdateServiceSafePointV2Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\UpdateServiceSafePointV2Response>
     */
    public function UpdateServiceSafePointV2(\Pdpb\UpdateServiceSafePointV2Request $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/UpdateServiceSafePointV2',
        $argument,
        ['\Pdpb\UpdateServiceSafePointV2Response', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\GetAllGCSafePointV2Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetAllGCSafePointV2Response>
     */
    public function GetAllGCSafePointV2(\Pdpb\GetAllGCSafePointV2Request $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetAllGCSafePointV2',
        $argument,
        ['\Pdpb\GetAllGCSafePointV2Response', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\AdvanceGCSafePointRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\AdvanceGCSafePointResponse>
     */
    public function AdvanceGCSafePoint(\Pdpb\AdvanceGCSafePointRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/AdvanceGCSafePoint',
        $argument,
        ['\Pdpb\AdvanceGCSafePointResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\AdvanceTxnSafePointRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\AdvanceTxnSafePointResponse>
     */
    public function AdvanceTxnSafePoint(\Pdpb\AdvanceTxnSafePointRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/AdvanceTxnSafePoint',
        $argument,
        ['\Pdpb\AdvanceTxnSafePointResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\SetGCBarrierRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\SetGCBarrierResponse>
     */
    public function SetGCBarrier(\Pdpb\SetGCBarrierRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/SetGCBarrier',
        $argument,
        ['\Pdpb\SetGCBarrierResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\DeleteGCBarrierRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\DeleteGCBarrierResponse>
     */
    public function DeleteGCBarrier(\Pdpb\DeleteGCBarrierRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/DeleteGCBarrier',
        $argument,
        ['\Pdpb\DeleteGCBarrierResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\SetGlobalGCBarrierRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\SetGlobalGCBarrierResponse>
     */
    public function SetGlobalGCBarrier(\Pdpb\SetGlobalGCBarrierRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/SetGlobalGCBarrier',
        $argument,
        ['\Pdpb\SetGlobalGCBarrierResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\DeleteGlobalGCBarrierRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\DeleteGlobalGCBarrierResponse>
     */
    public function DeleteGlobalGCBarrier(\Pdpb\DeleteGlobalGCBarrierRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/DeleteGlobalGCBarrier',
        $argument,
        ['\Pdpb\DeleteGlobalGCBarrierResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\GetGCStateRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetGCStateResponse>
     */
    public function GetGCState(\Pdpb\GetGCStateRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetGCState',
        $argument,
        ['\Pdpb\GetGCStateResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\GetAllKeyspacesGCStatesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetAllKeyspacesGCStatesResponse>
     */
    public function GetAllKeyspacesGCStates(\Pdpb\GetAllKeyspacesGCStatesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetAllKeyspacesGCStates',
        $argument,
        ['\Pdpb\GetAllKeyspacesGCStatesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function SyncRegions($metadata = [], $options = []) {
        return $this->_bidiRequest('/pdpb.PD/SyncRegions',
        ['\Pdpb\SyncRegionResponse','decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\GetOperatorRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetOperatorResponse>
     */
    public function GetOperator(\Pdpb\GetOperatorRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetOperator',
        $argument,
        ['\Pdpb\GetOperatorResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\SyncMaxTSRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\SyncMaxTSResponse>
     */
    public function SyncMaxTS(\Pdpb\SyncMaxTSRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/SyncMaxTS',
        $argument,
        ['\Pdpb\SyncMaxTSResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\SplitRegionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\SplitRegionsResponse>
     */
    public function SplitRegions(\Pdpb\SplitRegionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/SplitRegions',
        $argument,
        ['\Pdpb\SplitRegionsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\SplitAndScatterRegionsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\SplitAndScatterRegionsResponse>
     */
    public function SplitAndScatterRegions(\Pdpb\SplitAndScatterRegionsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/SplitAndScatterRegions',
        $argument,
        ['\Pdpb\SplitAndScatterRegionsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\GetDCLocationInfoRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetDCLocationInfoResponse>
     */
    public function GetDCLocationInfo(\Pdpb\GetDCLocationInfoRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetDCLocationInfo',
        $argument,
        ['\Pdpb\GetDCLocationInfoResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\StoreGlobalConfigRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\StoreGlobalConfigResponse>
     */
    public function StoreGlobalConfig(\Pdpb\StoreGlobalConfigRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/StoreGlobalConfig',
        $argument,
        ['\Pdpb\StoreGlobalConfigResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\LoadGlobalConfigRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\LoadGlobalConfigResponse>
     */
    public function LoadGlobalConfig(\Pdpb\LoadGlobalConfigRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/LoadGlobalConfig',
        $argument,
        ['\Pdpb\LoadGlobalConfigResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\WatchGlobalConfigRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function WatchGlobalConfig(\Pdpb\WatchGlobalConfigRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/pdpb.PD/WatchGlobalConfig',
        $argument,
        ['\Pdpb\WatchGlobalConfigResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ClientStreamingCall
     */
    public function ReportBuckets($metadata = [], $options = []) {
        return $this->_clientStreamRequest('/pdpb.PD/ReportBuckets',
        ['\Pdpb\ReportBucketsResponse','decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\ReportMinResolvedTsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\ReportMinResolvedTsResponse>
     */
    public function ReportMinResolvedTS(\Pdpb\ReportMinResolvedTsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/ReportMinResolvedTS',
        $argument,
        ['\Pdpb\ReportMinResolvedTsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\SetExternalTimestampRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\SetExternalTimestampResponse>
     */
    public function SetExternalTimestamp(\Pdpb\SetExternalTimestampRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/SetExternalTimestamp',
        $argument,
        ['\Pdpb\SetExternalTimestampResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Pdpb\GetExternalTimestampRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetExternalTimestampResponse>
     */
    public function GetExternalTimestamp(\Pdpb\GetExternalTimestampRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetExternalTimestamp',
        $argument,
        ['\Pdpb\GetExternalTimestampResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Get the minimum timestamp across all keyspace groups from API server
     * TODO: Currently, we need to ask API server to get the minimum timestamp.
     * Once we support service discovery, we can remove it.
     * @param \Pdpb\GetMinTSRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Pdpb\GetMinTSResponse>
     */
    public function GetMinTS(\Pdpb\GetMinTSRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/pdpb.PD/GetMinTS',
        $argument,
        ['\Pdpb\GetMinTSResponse', 'decode'],
        $metadata, $options);
    }

}
