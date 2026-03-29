<?php
// GENERATED CODE -- DO NOT EDIT!

namespace CrazyGoat\Proto\Deadlock;

/**
 */
class DeadlockClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * Get local wait for entries, should be handle by every node.
     * The owner should sent this request to all members to build the complete wait for graph.
     * @param \CrazyGoat\Proto\Deadlock\WaitForEntriesRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\Deadlock\WaitForEntriesResponse>
     */
    public function GetWaitForEntries(\CrazyGoat\Proto\Deadlock\WaitForEntriesRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/deadlock.Deadlock/GetWaitForEntries',
        $argument,
        ['\CrazyGoat\Proto\Deadlock\WaitForEntriesResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * Detect should only sent to the owner. only be handled by the owner.
     * The DeadlockResponse is sent back only if there is deadlock detected.
     * CleanUpWaitFor and CleanUp doesn't return responses.
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function Detect($metadata = [], $options = []) {
        return $this->_bidiRequest('/deadlock.Deadlock/Detect',
        ['\CrazyGoat\Proto\Deadlock\DeadlockResponse','decode'],
        $metadata, $options);
    }

}
