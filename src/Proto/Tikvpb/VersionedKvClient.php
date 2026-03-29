<?php
// GENERATED CODE -- DO NOT EDIT!

namespace CrazyGoat\Proto\Tikvpb;

/**
 * VersionedKv provides versioned coprocessor APIs for TiCI lookup.
 *
 * Invariants:
 * - For `VersionedCoprocessor`, callers should fill `coprocessor.Request.versioned_ranges`
 *   (each `VersionedKeyRange.range` must be a point range) and keep `coprocessor.Request.ranges` empty.
 */
class VersionedKvClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \CrazyGoat\Proto\Coprocessor\Request $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Coprocessor\Response>
     */
    public function VersionedCoprocessor(\CrazyGoat\Proto\Coprocessor\Request $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/tikvpb.VersionedKv/VersionedCoprocessor',
        $argument,
        ['\CrazyGoat\Proto\Coprocessor\Response', 'decode'],
        $metadata, $options);
    }

}
