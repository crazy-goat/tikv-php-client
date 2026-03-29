<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Tracepb;

/**
 */
class TraceRecordPubSubClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Subscribe the Trace records generated on this service. The service will periodically (e.g. per minute)
     * publishes Trace records to clients via gRPC stream.
     * @param \Tracepb\TraceRecordRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\ServerStreamingCall
     */
    public function Subscribe(\Tracepb\TraceRecordRequest $argument,
      $metadata = [], $options = []) {
        return $this->_serverStreamRequest('/tracepb.TraceRecordPubSub/Subscribe',
        $argument,
        ['\Tracepb\TraceRecord', 'decode'],
        $metadata, $options);
    }

}
