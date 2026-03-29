<?php
// GENERATED CODE -- DO NOT EDIT!

namespace CrazyGoat\Proto\Tikvpb;

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
     * @param \CrazyGoat\Proto\Kvrpcpb\GetRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\GetResponse>
     */
    public function KvGet(\CrazyGoat\Proto\Kvrpcpb\GetRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvGet',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\GetResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\ScanRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\ScanResponse>
     */
    public function KvScan(\CrazyGoat\Proto\Kvrpcpb\ScanRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvScan',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\ScanResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\PrewriteRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\PrewriteResponse>
     */
    public function KvPrewrite(\CrazyGoat\Proto\Kvrpcpb\PrewriteRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvPrewrite',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\PrewriteResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\PessimisticLockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\PessimisticLockResponse>
     */
    public function KvPessimisticLock(\CrazyGoat\Proto\Kvrpcpb\PessimisticLockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvPessimisticLock',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\PessimisticLockResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\PessimisticRollbackRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\PessimisticRollbackResponse>
     */
    public function KVPessimisticRollback(\CrazyGoat\Proto\Kvrpcpb\PessimisticRollbackRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KVPessimisticRollback',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\PessimisticRollbackResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\TxnHeartBeatRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\TxnHeartBeatResponse>
     */
    public function KvTxnHeartBeat(\CrazyGoat\Proto\Kvrpcpb\TxnHeartBeatRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvTxnHeartBeat',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\TxnHeartBeatResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\CheckTxnStatusRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\CheckTxnStatusResponse>
     */
    public function KvCheckTxnStatus(\CrazyGoat\Proto\Kvrpcpb\CheckTxnStatusRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvCheckTxnStatus',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\CheckTxnStatusResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\CheckSecondaryLocksRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\CheckSecondaryLocksResponse>
     */
    public function KvCheckSecondaryLocks(\CrazyGoat\Proto\Kvrpcpb\CheckSecondaryLocksRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvCheckSecondaryLocks',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\CheckSecondaryLocksResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\CommitRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\CommitResponse>
     */
    public function KvCommit(\CrazyGoat\Proto\Kvrpcpb\CommitRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvCommit',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\CommitResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\ImportRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\ImportResponse>
     */
    public function KvImport(\CrazyGoat\Proto\Kvrpcpb\ImportRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvImport',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\ImportResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\CleanupRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\CleanupResponse>
     */
    public function KvCleanup(\CrazyGoat\Proto\Kvrpcpb\CleanupRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvCleanup',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\CleanupResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\BatchGetRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\BatchGetResponse>
     */
    public function KvBatchGet(\CrazyGoat\Proto\Kvrpcpb\BatchGetRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvBatchGet',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\BatchGetResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\BatchRollbackRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\BatchRollbackResponse>
     */
    public function KvBatchRollback(\CrazyGoat\Proto\Kvrpcpb\BatchRollbackRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvBatchRollback',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\BatchRollbackResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\ScanLockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\ScanLockResponse>
     */
    public function KvScanLock(\CrazyGoat\Proto\Kvrpcpb\ScanLockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvScanLock',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\ScanLockResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\ResolveLockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\ResolveLockResponse>
     */
    public function KvResolveLock(\CrazyGoat\Proto\Kvrpcpb\ResolveLockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvResolveLock',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\ResolveLockResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\GCRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\GCResponse>
     */
    public function KvGC(\CrazyGoat\Proto\Kvrpcpb\GCRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvGC',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\GCResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\DeleteRangeRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\DeleteRangeResponse>
     */
    public function KvDeleteRange(\CrazyGoat\Proto\Kvrpcpb\DeleteRangeRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvDeleteRange',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\DeleteRangeResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\PrepareFlashbackToVersionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\PrepareFlashbackToVersionResponse>
     */
    public function KvPrepareFlashbackToVersion(\CrazyGoat\Proto\Kvrpcpb\PrepareFlashbackToVersionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvPrepareFlashbackToVersion',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\PrepareFlashbackToVersionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\FlashbackToVersionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\FlashbackToVersionResponse>
     */
    public function KvFlashbackToVersion(\CrazyGoat\Proto\Kvrpcpb\FlashbackToVersionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvFlashbackToVersion',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\FlashbackToVersionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\FlushRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\FlushResponse>
     */
    public function KvFlush(\CrazyGoat\Proto\Kvrpcpb\FlushRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvFlush',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\FlushResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\BufferBatchGetRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\BufferBatchGetResponse>
     */
    public function KvBufferBatchGet(\CrazyGoat\Proto\Kvrpcpb\BufferBatchGetRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/KvBufferBatchGet',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\BufferBatchGetResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Raw commands; no transaction support.
     * @param \CrazyGoat\Proto\Kvrpcpb\RawGetRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\RawGetResponse>
     */
    public function RawGet(\CrazyGoat\Proto\Kvrpcpb\RawGetRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawGet',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\RawGetResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\RawBatchGetRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\RawBatchGetResponse>
     */
    public function RawBatchGet(\CrazyGoat\Proto\Kvrpcpb\RawBatchGetRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawBatchGet',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\RawBatchGetResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\RawPutRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\RawPutResponse>
     */
    public function RawPut(\CrazyGoat\Proto\Kvrpcpb\RawPutRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawPut',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\RawPutResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\RawBatchPutRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\RawBatchPutResponse>
     */
    public function RawBatchPut(\CrazyGoat\Proto\Kvrpcpb\RawBatchPutRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawBatchPut',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\RawBatchPutResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\RawDeleteRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\RawDeleteResponse>
     */
    public function RawDelete(\CrazyGoat\Proto\Kvrpcpb\RawDeleteRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawDelete',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\RawDeleteResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\RawBatchDeleteRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\RawBatchDeleteResponse>
     */
    public function RawBatchDelete(\CrazyGoat\Proto\Kvrpcpb\RawBatchDeleteRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawBatchDelete',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\RawBatchDeleteResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\RawScanRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\RawScanResponse>
     */
    public function RawScan(\CrazyGoat\Proto\Kvrpcpb\RawScanRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawScan',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\RawScanResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\RawDeleteRangeRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\RawDeleteRangeResponse>
     */
    public function RawDeleteRange(\CrazyGoat\Proto\Kvrpcpb\RawDeleteRangeRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawDeleteRange',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\RawDeleteRangeResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\RawBatchScanRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\RawBatchScanResponse>
     */
    public function RawBatchScan(\CrazyGoat\Proto\Kvrpcpb\RawBatchScanRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawBatchScan',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\RawBatchScanResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Get TTL of the key. Returns 0 if TTL is not set for the key.
     * @param \CrazyGoat\Proto\Kvrpcpb\RawGetKeyTTLRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\RawGetKeyTTLResponse>
     */
    public function RawGetKeyTTL(\CrazyGoat\Proto\Kvrpcpb\RawGetKeyTTLRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawGetKeyTTL',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\RawGetKeyTTLResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Compare if the value in database equals to `RawCASRequest.previous_value` before putting the new value. If not, this request will have no effect and the value in the database will be returned.
     * @param \CrazyGoat\Proto\Kvrpcpb\RawCASRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\RawCASResponse>
     */
    public function RawCompareAndSwap(\CrazyGoat\Proto\Kvrpcpb\RawCASRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawCompareAndSwap',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\RawCASResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\RawChecksumRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\RawChecksumResponse>
     */
    public function RawChecksum(\CrazyGoat\Proto\Kvrpcpb\RawChecksumRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawChecksum',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\RawChecksumResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Store commands (sent to a each TiKV node in a cluster, rather than a certain region).
     * @param \CrazyGoat\Proto\Kvrpcpb\UnsafeDestroyRangeRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\UnsafeDestroyRangeResponse>
     */
    public function UnsafeDestroyRange(\CrazyGoat\Proto\Kvrpcpb\UnsafeDestroyRangeRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/UnsafeDestroyRange',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\UnsafeDestroyRangeResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\RegisterLockObserverRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\RegisterLockObserverResponse>
     */
    public function RegisterLockObserver(\CrazyGoat\Proto\Kvrpcpb\RegisterLockObserverRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RegisterLockObserver',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\RegisterLockObserverResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\CheckLockObserverRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\CheckLockObserverResponse>
     */
    public function CheckLockObserver(\CrazyGoat\Proto\Kvrpcpb\CheckLockObserverRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/CheckLockObserver',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\CheckLockObserverResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\RemoveLockObserverRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\RemoveLockObserverResponse>
     */
    public function RemoveLockObserver(\CrazyGoat\Proto\Kvrpcpb\RemoveLockObserverRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RemoveLockObserver',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\RemoveLockObserverResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\PhysicalScanLockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\PhysicalScanLockResponse>
     */
    public function PhysicalScanLock(\CrazyGoat\Proto\Kvrpcpb\PhysicalScanLockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/PhysicalScanLock',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\PhysicalScanLockResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Commands for executing SQL in the TiKV coprocessor (i.e., 'pushed down' to TiKV rather than
     * executed in TiDB).
     * @param \CrazyGoat\Proto\Coprocessor\Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Coprocessor\Response>
     */
    public function Coprocessor(\CrazyGoat\Proto\Coprocessor\Request $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/Coprocessor',
        $argument,
        ['\CrazyGoat\Proto\Coprocessor\Response', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Coprocessor\Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function CoprocessorStream(\CrazyGoat\Proto\Coprocessor\Request $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/tikvpb.Tikv/CoprocessorStream',
        $argument,
        ['\CrazyGoat\Proto\Coprocessor\Response', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Coprocessor\BatchRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function BatchCoprocessor(\CrazyGoat\Proto\Coprocessor\BatchRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/tikvpb.Tikv/BatchCoprocessor',
        $argument,
        ['\CrazyGoat\Proto\Coprocessor\BatchResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Command send by remote coprocessor to TiKV for executing coprocessor request.
     * @param \CrazyGoat\Proto\Coprocessor\DelegateRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Coprocessor\DelegateResponse>
     */
    public function DelegateCoprocessor(\CrazyGoat\Proto\Coprocessor\DelegateRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/DelegateCoprocessor',
        $argument,
        ['\CrazyGoat\Proto\Coprocessor\DelegateResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Command for executing custom user requests in TiKV coprocessor_v2.
     * @param \CrazyGoat\Proto\Kvrpcpb\RawCoprocessorRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\RawCoprocessorResponse>
     */
    public function RawCoprocessor(\CrazyGoat\Proto\Kvrpcpb\RawCoprocessorRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/RawCoprocessor',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\RawCoprocessorResponse', 'decode'],
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
        ['\CrazyGoat\Proto\RaftServerpb\Done','decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ClientStreamingCall
     */
    public function BatchRaft($metadata = [], $options = []) {
        return $this->_clientStreamRequest('/tikvpb.Tikv/BatchRaft',
        ['\CrazyGoat\Proto\RaftServerpb\Done','decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ClientStreamingCall
     */
    public function Snapshot($metadata = [], $options = []) {
        return $this->_clientStreamRequest('/tikvpb.Tikv/Snapshot',
        ['\CrazyGoat\Proto\RaftServerpb\Done','decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function TabletSnapshot($metadata = [], $options = []) {
        return $this->_bidiRequest('/tikvpb.Tikv/TabletSnapshot',
        ['\CrazyGoat\Proto\RaftServerpb\TabletSnapshotResponse','decode'],
        $metadata, $options);
    }

    /**
     * Sent from PD or TiDB to a TiKV node.
     * @param \CrazyGoat\Proto\Kvrpcpb\SplitRegionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\SplitRegionResponse>
     */
    public function SplitRegion(\CrazyGoat\Proto\Kvrpcpb\SplitRegionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/SplitRegion',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\SplitRegionResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Sent from TiFlash or TiKV to a TiKV node.
     * @param \CrazyGoat\Proto\Kvrpcpb\ReadIndexRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\ReadIndexResponse>
     */
    public function ReadIndex(\CrazyGoat\Proto\Kvrpcpb\ReadIndexRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/ReadIndex',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\ReadIndexResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Commands for debugging transactions.
     * @param \CrazyGoat\Proto\Kvrpcpb\MvccGetByKeyRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\MvccGetByKeyResponse>
     */
    public function MvccGetByKey(\CrazyGoat\Proto\Kvrpcpb\MvccGetByKeyRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/MvccGetByKey',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\MvccGetByKeyResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Kvrpcpb\MvccGetByStartTsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\MvccGetByStartTsResponse>
     */
    public function MvccGetByStartTs(\CrazyGoat\Proto\Kvrpcpb\MvccGetByStartTsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/MvccGetByStartTs',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\MvccGetByStartTsResponse', 'decode'],
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
        ['\CrazyGoat\Proto\Tikvpb\BatchCommandsResponse','decode'],
        $metadata, $options);
    }

    /**
     * These are for mpp execution.
     * @param \CrazyGoat\Proto\Mpp\DispatchTaskRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Mpp\DispatchTaskResponse>
     */
    public function DispatchMPPTask(\CrazyGoat\Proto\Mpp\DispatchTaskRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/DispatchMPPTask',
        $argument,
        ['\CrazyGoat\Proto\Mpp\DispatchTaskResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Mpp\CancelTaskRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Mpp\CancelTaskResponse>
     */
    public function CancelMPPTask(\CrazyGoat\Proto\Mpp\CancelTaskRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/CancelMPPTask',
        $argument,
        ['\CrazyGoat\Proto\Mpp\CancelTaskResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Mpp\EstablishMPPConnectionRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function EstablishMPPConnection(\CrazyGoat\Proto\Mpp\EstablishMPPConnectionRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/tikvpb.Tikv/EstablishMPPConnection',
        $argument,
        ['\CrazyGoat\Proto\Mpp\MPPDataPacket', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Mpp\IsAliveRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Mpp\IsAliveResponse>
     */
    public function IsAlive(\CrazyGoat\Proto\Mpp\IsAliveRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/IsAlive',
        $argument,
        ['\CrazyGoat\Proto\Mpp\IsAliveResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\Mpp\ReportTaskStatusRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Mpp\ReportTaskStatusResponse>
     */
    public function ReportMPPTaskStatus(\CrazyGoat\Proto\Mpp\ReportTaskStatusRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/ReportMPPTaskStatus',
        $argument,
        ['\CrazyGoat\Proto\Mpp\ReportTaskStatusResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / CheckLeader sends all information (includes region term and epoch) to other stores.
     * / Once a store receives a request, it checks term and epoch for each region, and sends the regions whose
     * / term and epoch match with local information in the store.
     * / After the client collected all responses from all stores, it checks if got a quorum of responses from
     * / other stores for every region, and decides to advance resolved ts from these regions.
     * @param \CrazyGoat\Proto\Kvrpcpb\CheckLeaderRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\CheckLeaderResponse>
     */
    public function CheckLeader(\CrazyGoat\Proto\Kvrpcpb\CheckLeaderRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/CheckLeader',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\CheckLeaderResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Get the minimal `safe_ts` from regions at the store
     * @param \CrazyGoat\Proto\Kvrpcpb\StoreSafeTSRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\StoreSafeTSResponse>
     */
    public function GetStoreSafeTS(\CrazyGoat\Proto\Kvrpcpb\StoreSafeTSRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/GetStoreSafeTS',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\StoreSafeTSResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Get the information about lock waiting from TiKV.
     * @param \CrazyGoat\Proto\Kvrpcpb\GetLockWaitInfoRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\GetLockWaitInfoResponse>
     */
    public function GetLockWaitInfo(\CrazyGoat\Proto\Kvrpcpb\GetLockWaitInfoRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/GetLockWaitInfo',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\GetLockWaitInfoResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Compact a specified key range. This request is not restricted to raft leaders and will not be replicated.
     * / It only compacts data on this node.
     * / TODO: Currently this RPC is designed to be only compatible with TiFlash.
     * / Shall be move out in https://github.com/pingcap/kvproto/issues/912
     * @param \CrazyGoat\Proto\Kvrpcpb\CompactRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\CompactResponse>
     */
    public function Compact(\CrazyGoat\Proto\Kvrpcpb\CompactRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/Compact',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\CompactResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Get the information about history lock waiting from TiKV.
     * @param \CrazyGoat\Proto\Kvrpcpb\GetLockWaitHistoryRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\GetLockWaitHistoryResponse>
     */
    public function GetLockWaitHistory(\CrazyGoat\Proto\Kvrpcpb\GetLockWaitHistoryRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/GetLockWaitHistory',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\GetLockWaitHistoryResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Get system table from TiFlash
     * @param \CrazyGoat\Proto\Kvrpcpb\TiFlashSystemTableRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\TiFlashSystemTableResponse>
     */
    public function GetTiFlashSystemTable(\CrazyGoat\Proto\Kvrpcpb\TiFlashSystemTableRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/GetTiFlashSystemTable',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\TiFlashSystemTableResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * These are for TiFlash disaggregated architecture
     * / Try to lock a S3 object, atomically
     * @param \CrazyGoat\Proto\Disaggregated\TryAddLockRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Disaggregated\TryAddLockResponse>
     */
    public function tryAddLock(\CrazyGoat\Proto\Disaggregated\TryAddLockRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/tryAddLock',
        $argument,
        ['\CrazyGoat\Proto\Disaggregated\TryAddLockResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Try to delete a S3 object, atomically
     * @param \CrazyGoat\Proto\Disaggregated\TryMarkDeleteRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Disaggregated\TryMarkDeleteResponse>
     */
    public function tryMarkDelete(\CrazyGoat\Proto\Disaggregated\TryMarkDeleteRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/tryMarkDelete',
        $argument,
        ['\CrazyGoat\Proto\Disaggregated\TryMarkDeleteResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Build the disaggregated task on TiFlash write node
     * @param \CrazyGoat\Proto\Disaggregated\EstablishDisaggTaskRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Disaggregated\EstablishDisaggTaskResponse>
     */
    public function EstablishDisaggTask(\CrazyGoat\Proto\Disaggregated\EstablishDisaggTaskRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/EstablishDisaggTask',
        $argument,
        ['\CrazyGoat\Proto\Disaggregated\EstablishDisaggTaskResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Cancel the disaggregated task on TiFlash write node
     * @param \CrazyGoat\Proto\Disaggregated\CancelDisaggTaskRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Disaggregated\CancelDisaggTaskResponse>
     */
    public function CancelDisaggTask(\CrazyGoat\Proto\Disaggregated\CancelDisaggTaskRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/CancelDisaggTask',
        $argument,
        ['\CrazyGoat\Proto\Disaggregated\CancelDisaggTaskResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Exchange page data between TiFlash write node and compute node
     * @param \CrazyGoat\Proto\Disaggregated\FetchDisaggPagesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function FetchDisaggPages(\CrazyGoat\Proto\Disaggregated\FetchDisaggPagesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/tikvpb.Tikv/FetchDisaggPages',
        $argument,
        ['\CrazyGoat\Proto\Disaggregated\PagesPacket', 'decode'],
        $metadata, $options);
    }

    /**
     * / Compute node get configuration from Write node
     * @param \CrazyGoat\Proto\Disaggregated\GetDisaggConfigRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Disaggregated\GetDisaggConfigResponse>
     */
    public function GetDisaggConfig(\CrazyGoat\Proto\Disaggregated\GetDisaggConfigRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/GetDisaggConfig',
        $argument,
        ['\CrazyGoat\Proto\Disaggregated\GetDisaggConfigResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Get health feedback info from the TiKV node.
     * @param \CrazyGoat\Proto\Kvrpcpb\GetHealthFeedbackRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\GetHealthFeedbackResponse>
     */
    public function GetHealthFeedback(\CrazyGoat\Proto\Kvrpcpb\GetHealthFeedbackRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/GetHealthFeedback',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\GetHealthFeedbackResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * / Broadcast the transaction status to all TiKV nodes
     * @param \CrazyGoat\Proto\Kvrpcpb\BroadcastTxnStatusRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Kvrpcpb\BroadcastTxnStatusResponse>
     */
    public function BroadcastTxnStatus(\CrazyGoat\Proto\Kvrpcpb\BroadcastTxnStatusRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.Tikv/BroadcastTxnStatus',
        $argument,
        ['\CrazyGoat\Proto\Kvrpcpb\BroadcastTxnStatusResponse', 'decode'],
        $metadata, $options);
    }

}
