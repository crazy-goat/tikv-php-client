<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Tikvpb;

/**
 * Key/value store API for TiKV.
 */
class TikvClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Commands using a transactional interface.
     * @param \Kvrpcpb\GetRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\GetResponse>
     */
    public function KvGet(\Kvrpcpb\GetRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvGet',
        $argument,
        ['\Kvrpcpb\GetResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\ScanRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\ScanResponse>
     */
    public function KvScan(\Kvrpcpb\ScanRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvScan',
        $argument,
        ['\Kvrpcpb\ScanResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\PrewriteRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\PrewriteResponse>
     */
    public function KvPrewrite(\Kvrpcpb\PrewriteRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvPrewrite',
        $argument,
        ['\Kvrpcpb\PrewriteResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\PessimisticLockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\PessimisticLockResponse>
     */
    public function KvPessimisticLock(\Kvrpcpb\PessimisticLockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvPessimisticLock',
        $argument,
        ['\Kvrpcpb\PessimisticLockResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\PessimisticRollbackRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\PessimisticRollbackResponse>
     */
    public function KVPessimisticRollback(\Kvrpcpb\PessimisticRollbackRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KVPessimisticRollback',
        $argument,
        ['\Kvrpcpb\PessimisticRollbackResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\TxnHeartBeatRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\TxnHeartBeatResponse>
     */
    public function KvTxnHeartBeat(\Kvrpcpb\TxnHeartBeatRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvTxnHeartBeat',
        $argument,
        ['\Kvrpcpb\TxnHeartBeatResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\CheckTxnStatusRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\CheckTxnStatusResponse>
     */
    public function KvCheckTxnStatus(\Kvrpcpb\CheckTxnStatusRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvCheckTxnStatus',
        $argument,
        ['\Kvrpcpb\CheckTxnStatusResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\CheckSecondaryLocksRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\CheckSecondaryLocksResponse>
     */
    public function KvCheckSecondaryLocks(\Kvrpcpb\CheckSecondaryLocksRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvCheckSecondaryLocks',
        $argument,
        ['\Kvrpcpb\CheckSecondaryLocksResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\CommitRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\CommitResponse>
     */
    public function KvCommit(\Kvrpcpb\CommitRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvCommit',
        $argument,
        ['\Kvrpcpb\CommitResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\ImportRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\ImportResponse>
     */
    public function KvImport(\Kvrpcpb\ImportRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvImport',
        $argument,
        ['\Kvrpcpb\ImportResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\CleanupRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\CleanupResponse>
     */
    public function KvCleanup(\Kvrpcpb\CleanupRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvCleanup',
        $argument,
        ['\Kvrpcpb\CleanupResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\BatchGetRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\BatchGetResponse>
     */
    public function KvBatchGet(\Kvrpcpb\BatchGetRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvBatchGet',
        $argument,
        ['\Kvrpcpb\BatchGetResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\BatchRollbackRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\BatchRollbackResponse>
     */
    public function KvBatchRollback(\Kvrpcpb\BatchRollbackRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvBatchRollback',
        $argument,
        ['\Kvrpcpb\BatchRollbackResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\ScanLockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\ScanLockResponse>
     */
    public function KvScanLock(\Kvrpcpb\ScanLockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvScanLock',
        $argument,
        ['\Kvrpcpb\ScanLockResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\ResolveLockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\ResolveLockResponse>
     */
    public function KvResolveLock(\Kvrpcpb\ResolveLockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvResolveLock',
        $argument,
        ['\Kvrpcpb\ResolveLockResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\GCRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\GCResponse>
     */
    public function KvGC(\Kvrpcpb\GCRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvGC',
        $argument,
        ['\Kvrpcpb\GCResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\DeleteRangeRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\DeleteRangeResponse>
     */
    public function KvDeleteRange(\Kvrpcpb\DeleteRangeRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvDeleteRange',
        $argument,
        ['\Kvrpcpb\DeleteRangeResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\PrepareFlashbackToVersionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\PrepareFlashbackToVersionResponse>
     */
    public function KvPrepareFlashbackToVersion(\Kvrpcpb\PrepareFlashbackToVersionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvPrepareFlashbackToVersion',
        $argument,
        ['\Kvrpcpb\PrepareFlashbackToVersionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\FlashbackToVersionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\FlashbackToVersionResponse>
     */
    public function KvFlashbackToVersion(\Kvrpcpb\FlashbackToVersionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvFlashbackToVersion',
        $argument,
        ['\Kvrpcpb\FlashbackToVersionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\FlushRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\FlushResponse>
     */
    public function KvFlush(\Kvrpcpb\FlushRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvFlush',
        $argument,
        ['\Kvrpcpb\FlushResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\BufferBatchGetRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\BufferBatchGetResponse>
     */
    public function KvBufferBatchGet(\Kvrpcpb\BufferBatchGetRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvBufferBatchGet',
        $argument,
        ['\Kvrpcpb\BufferBatchGetResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Raw commands; no transaction support.
     * @param \Kvrpcpb\RawGetRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\RawGetResponse>
     */
    public function RawGet(\Kvrpcpb\RawGetRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawGet',
        $argument,
        ['\Kvrpcpb\RawGetResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\RawBatchGetRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\RawBatchGetResponse>
     */
    public function RawBatchGet(\Kvrpcpb\RawBatchGetRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawBatchGet',
        $argument,
        ['\Kvrpcpb\RawBatchGetResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\RawPutRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\RawPutResponse>
     */
    public function RawPut(\Kvrpcpb\RawPutRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawPut',
        $argument,
        ['\Kvrpcpb\RawPutResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\RawBatchPutRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\RawBatchPutResponse>
     */
    public function RawBatchPut(\Kvrpcpb\RawBatchPutRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawBatchPut',
        $argument,
        ['\Kvrpcpb\RawBatchPutResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\RawDeleteRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\RawDeleteResponse>
     */
    public function RawDelete(\Kvrpcpb\RawDeleteRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawDelete',
        $argument,
        ['\Kvrpcpb\RawDeleteResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\RawBatchDeleteRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\RawBatchDeleteResponse>
     */
    public function RawBatchDelete(\Kvrpcpb\RawBatchDeleteRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawBatchDelete',
        $argument,
        ['\Kvrpcpb\RawBatchDeleteResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\RawScanRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\RawScanResponse>
     */
    public function RawScan(\Kvrpcpb\RawScanRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawScan',
        $argument,
        ['\Kvrpcpb\RawScanResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\RawDeleteRangeRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\RawDeleteRangeResponse>
     */
    public function RawDeleteRange(\Kvrpcpb\RawDeleteRangeRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawDeleteRange',
        $argument,
        ['\Kvrpcpb\RawDeleteRangeResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\RawBatchScanRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\RawBatchScanResponse>
     */
    public function RawBatchScan(\Kvrpcpb\RawBatchScanRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawBatchScan',
        $argument,
        ['\Kvrpcpb\RawBatchScanResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Get TTL of the key. Returns 0 if TTL is not set for the key.
     * @param \Kvrpcpb\RawGetKeyTTLRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\RawGetKeyTTLResponse>
     */
    public function RawGetKeyTTL(\Kvrpcpb\RawGetKeyTTLRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawGetKeyTTL',
        $argument,
        ['\Kvrpcpb\RawGetKeyTTLResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Compare if the value in database equals to `RawCASRequest.previous_value` before putting the new value. If not, this request will have no effect and the value in the database will be returned.
     * @param \Kvrpcpb\RawCASRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\RawCASResponse>
     */
    public function RawCompareAndSwap(\Kvrpcpb\RawCASRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawCompareAndSwap',
        $argument,
        ['\Kvrpcpb\RawCASResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\RawChecksumRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\RawChecksumResponse>
     */
    public function RawChecksum(\Kvrpcpb\RawChecksumRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawChecksum',
        $argument,
        ['\Kvrpcpb\RawChecksumResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Store commands (sent to a each TiKV node in a cluster, rather than a certain region).
     * @param \Kvrpcpb\UnsafeDestroyRangeRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\UnsafeDestroyRangeResponse>
     */
    public function UnsafeDestroyRange(\Kvrpcpb\UnsafeDestroyRangeRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/UnsafeDestroyRange',
        $argument,
        ['\Kvrpcpb\UnsafeDestroyRangeResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\RegisterLockObserverRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\RegisterLockObserverResponse>
     */
    public function RegisterLockObserver(\Kvrpcpb\RegisterLockObserverRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RegisterLockObserver',
        $argument,
        ['\Kvrpcpb\RegisterLockObserverResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\CheckLockObserverRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\CheckLockObserverResponse>
     */
    public function CheckLockObserver(\Kvrpcpb\CheckLockObserverRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/CheckLockObserver',
        $argument,
        ['\Kvrpcpb\CheckLockObserverResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\RemoveLockObserverRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\RemoveLockObserverResponse>
     */
    public function RemoveLockObserver(\Kvrpcpb\RemoveLockObserverRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RemoveLockObserver',
        $argument,
        ['\Kvrpcpb\RemoveLockObserverResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\PhysicalScanLockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\PhysicalScanLockResponse>
     */
    public function PhysicalScanLock(\Kvrpcpb\PhysicalScanLockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/PhysicalScanLock',
        $argument,
        ['\Kvrpcpb\PhysicalScanLockResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Commands for executing SQL in the TiKV coprocessor (i.e., 'pushed down' to TiKV rather than
     * executed in TiDB).
     * @param \Coprocessor\Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Coprocessor\Response>
     */
    public function Coprocessor(\Coprocessor\Request $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/Coprocessor',
        $argument,
        ['\Coprocessor\Response', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Coprocessor\Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function CoprocessorStream(\Coprocessor\Request $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/tikvpb.Tikv/CoprocessorStream',
        $argument,
        ['\Coprocessor\Response', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Coprocessor\BatchRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function BatchCoprocessor(\Coprocessor\BatchRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/tikvpb.Tikv/BatchCoprocessor',
        $argument,
        ['\Coprocessor\BatchResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Command send by remote coprocessor to TiKV for executing coprocessor request.
     * @param \Coprocessor\DelegateRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Coprocessor\DelegateResponse>
     */
    public function DelegateCoprocessor(\Coprocessor\DelegateRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/DelegateCoprocessor',
        $argument,
        ['\Coprocessor\DelegateResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Command for executing custom user requests in TiKV coprocessor_v2.
     * @param \Kvrpcpb\RawCoprocessorRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\RawCoprocessorResponse>
     */
    public function RawCoprocessor(\Kvrpcpb\RawCoprocessorRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawCoprocessor',
        $argument,
        ['\Kvrpcpb\RawCoprocessorResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Raft commands (sent between TiKV nodes).
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ClientStreamingCall
     */
    public function Raft($metadata = [], $options = []) {
        return $this->_clientStreamRequest('/tikvpb.Tikv/Raft',
        ['\Raft_serverpb\Done','decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ClientStreamingCall
     */
    public function BatchRaft($metadata = [], $options = []) {
        return $this->_clientStreamRequest('/tikvpb.Tikv/BatchRaft',
        ['\Raft_serverpb\Done','decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ClientStreamingCall
     */
    public function Snapshot($metadata = [], $options = []) {
        return $this->_clientStreamRequest('/tikvpb.Tikv/Snapshot',
        ['\Raft_serverpb\Done','decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function TabletSnapshot($metadata = [], $options = []) {
        return $this->_bidiRequest('/tikvpb.Tikv/TabletSnapshot',
        ['\Raft_serverpb\TabletSnapshotResponse','decode'],
        $metadata, $options);
    }

    /**
     * Sent from PD or TiDB to a TiKV node.
     * @param \Kvrpcpb\SplitRegionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\SplitRegionResponse>
     */
    public function SplitRegion(\Kvrpcpb\SplitRegionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/SplitRegion',
        $argument,
        ['\Kvrpcpb\SplitRegionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Sent from TiFlash or TiKV to a TiKV node.
     * @param \Kvrpcpb\ReadIndexRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\ReadIndexResponse>
     */
    public function ReadIndex(\Kvrpcpb\ReadIndexRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/ReadIndex',
        $argument,
        ['\Kvrpcpb\ReadIndexResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Commands for debugging transactions.
     * @param \Kvrpcpb\MvccGetByKeyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\MvccGetByKeyResponse>
     */
    public function MvccGetByKey(\Kvrpcpb\MvccGetByKeyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/MvccGetByKey',
        $argument,
        ['\Kvrpcpb\MvccGetByKeyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Kvrpcpb\MvccGetByStartTsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\MvccGetByStartTsResponse>
     */
    public function MvccGetByStartTs(\Kvrpcpb\MvccGetByStartTsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/MvccGetByStartTs',
        $argument,
        ['\Kvrpcpb\MvccGetByStartTsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Batched commands.
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function BatchCommands($metadata = [], $options = []) {
        return $this->_bidiRequest('/tikvpb.Tikv/BatchCommands',
        ['\Tikvpb\BatchCommandsResponse','decode'],
        $metadata, $options);
    }

    /**
     * These are for mpp execution.
     * @param \Mpp\DispatchTaskRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Mpp\DispatchTaskResponse>
     */
    public function DispatchMPPTask(\Mpp\DispatchTaskRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/DispatchMPPTask',
        $argument,
        ['\Mpp\DispatchTaskResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Mpp\CancelTaskRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Mpp\CancelTaskResponse>
     */
    public function CancelMPPTask(\Mpp\CancelTaskRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/CancelMPPTask',
        $argument,
        ['\Mpp\CancelTaskResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Mpp\EstablishMPPConnectionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function EstablishMPPConnection(\Mpp\EstablishMPPConnectionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/tikvpb.Tikv/EstablishMPPConnection',
        $argument,
        ['\Mpp\MPPDataPacket', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Mpp\IsAliveRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Mpp\IsAliveResponse>
     */
    public function IsAlive(\Mpp\IsAliveRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/IsAlive',
        $argument,
        ['\Mpp\IsAliveResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Mpp\ReportTaskStatusRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Mpp\ReportTaskStatusResponse>
     */
    public function ReportMPPTaskStatus(\Mpp\ReportTaskStatusRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/ReportMPPTaskStatus',
        $argument,
        ['\Mpp\ReportTaskStatusResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / CheckLeader sends all information (includes region term and epoch) to other stores.
     * / Once a store receives a request, it checks term and epoch for each region, and sends the regions whose
     * / term and epoch match with local information in the store.
     * / After the client collected all responses from all stores, it checks if got a quorum of responses from
     * / other stores for every region, and decides to advance resolved ts from these regions.
     * @param \Kvrpcpb\CheckLeaderRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\CheckLeaderResponse>
     */
    public function CheckLeader(\Kvrpcpb\CheckLeaderRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/CheckLeader',
        $argument,
        ['\Kvrpcpb\CheckLeaderResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Get the minimal `safe_ts` from regions at the store
     * @param \Kvrpcpb\StoreSafeTSRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\StoreSafeTSResponse>
     */
    public function GetStoreSafeTS(\Kvrpcpb\StoreSafeTSRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/GetStoreSafeTS',
        $argument,
        ['\Kvrpcpb\StoreSafeTSResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Get the information about lock waiting from TiKV.
     * @param \Kvrpcpb\GetLockWaitInfoRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\GetLockWaitInfoResponse>
     */
    public function GetLockWaitInfo(\Kvrpcpb\GetLockWaitInfoRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/GetLockWaitInfo',
        $argument,
        ['\Kvrpcpb\GetLockWaitInfoResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Compact a specified key range. This request is not restricted to raft leaders and will not be replicated.
     * / It only compacts data on this node.
     * / TODO: Currently this RPC is designed to be only compatible with TiFlash.
     * / Shall be move out in https://github.com/pingcap/kvproto/issues/912
     * @param \Kvrpcpb\CompactRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\CompactResponse>
     */
    public function Compact(\Kvrpcpb\CompactRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/Compact',
        $argument,
        ['\Kvrpcpb\CompactResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Get the information about history lock waiting from TiKV.
     * @param \Kvrpcpb\GetLockWaitHistoryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\GetLockWaitHistoryResponse>
     */
    public function GetLockWaitHistory(\Kvrpcpb\GetLockWaitHistoryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/GetLockWaitHistory',
        $argument,
        ['\Kvrpcpb\GetLockWaitHistoryResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Get system table from TiFlash
     * @param \Kvrpcpb\TiFlashSystemTableRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\TiFlashSystemTableResponse>
     */
    public function GetTiFlashSystemTable(\Kvrpcpb\TiFlashSystemTableRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/GetTiFlashSystemTable',
        $argument,
        ['\Kvrpcpb\TiFlashSystemTableResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * These are for TiFlash disaggregated architecture
     * / Try to lock a S3 object, atomically
     * @param \Disaggregated\TryAddLockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Disaggregated\TryAddLockResponse>
     */
    public function tryAddLock(\Disaggregated\TryAddLockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/tryAddLock',
        $argument,
        ['\Disaggregated\TryAddLockResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Try to delete a S3 object, atomically
     * @param \Disaggregated\TryMarkDeleteRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Disaggregated\TryMarkDeleteResponse>
     */
    public function tryMarkDelete(\Disaggregated\TryMarkDeleteRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/tryMarkDelete',
        $argument,
        ['\Disaggregated\TryMarkDeleteResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Build the disaggregated task on TiFlash write node
     * @param \Disaggregated\EstablishDisaggTaskRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Disaggregated\EstablishDisaggTaskResponse>
     */
    public function EstablishDisaggTask(\Disaggregated\EstablishDisaggTaskRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/EstablishDisaggTask',
        $argument,
        ['\Disaggregated\EstablishDisaggTaskResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Cancel the disaggregated task on TiFlash write node
     * @param \Disaggregated\CancelDisaggTaskRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Disaggregated\CancelDisaggTaskResponse>
     */
    public function CancelDisaggTask(\Disaggregated\CancelDisaggTaskRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/CancelDisaggTask',
        $argument,
        ['\Disaggregated\CancelDisaggTaskResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Exchange page data between TiFlash write node and compute node
     * @param \Disaggregated\FetchDisaggPagesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function FetchDisaggPages(\Disaggregated\FetchDisaggPagesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/tikvpb.Tikv/FetchDisaggPages',
        $argument,
        ['\Disaggregated\PagesPacket', 'decode'],
        $metadata, $options);
    }

    /**
     * / Compute node get configuration from Write node
     * @param \Disaggregated\GetDisaggConfigRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Disaggregated\GetDisaggConfigResponse>
     */
    public function GetDisaggConfig(\Disaggregated\GetDisaggConfigRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/GetDisaggConfig',
        $argument,
        ['\Disaggregated\GetDisaggConfigResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Get health feedback info from the TiKV node.
     * @param \Kvrpcpb\GetHealthFeedbackRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\GetHealthFeedbackResponse>
     */
    public function GetHealthFeedback(\Kvrpcpb\GetHealthFeedbackRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/GetHealthFeedback',
        $argument,
        ['\Kvrpcpb\GetHealthFeedbackResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Broadcast the transaction status to all TiKV nodes
     * @param \Kvrpcpb\BroadcastTxnStatusRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Kvrpcpb\BroadcastTxnStatusResponse>
     */
    public function BroadcastTxnStatus(\Kvrpcpb\BroadcastTxnStatusRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/BroadcastTxnStatus',
        $argument,
        ['\Kvrpcpb\BroadcastTxnStatusResponse', 'decode'],
        $metadata, $options);
    }

}
