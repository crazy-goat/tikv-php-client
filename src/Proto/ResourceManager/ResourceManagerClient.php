<?php
// GENERATED CODE -- DO NOT EDIT!

namespace CrazyGoat\Proto\ResourceManager;

/**
 */
class ResourceManagerClient extends \Grpc\BaseStub {

    /**
     * @param string $hostname hostname
     * @param array $opts channel options
     * @param \Grpc\Channel $channel (optional) re-use channel object
     */
    public function __construct($hostname, $opts, $channel = null) {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \CrazyGoat\Proto\ResourceManager\ListResourceGroupsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\ResourceManager\ListResourceGroupsResponse>
     */
    public function ListResourceGroups(\CrazyGoat\Proto\ResourceManager\ListResourceGroupsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/resource_manager.ResourceManager/ListResourceGroups',
        $argument,
        ['\CrazyGoat\Proto\ResourceManager\ListResourceGroupsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\ResourceManager\GetResourceGroupRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\ResourceManager\GetResourceGroupResponse>
     */
    public function GetResourceGroup(\CrazyGoat\Proto\ResourceManager\GetResourceGroupRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/resource_manager.ResourceManager/GetResourceGroup',
        $argument,
        ['\CrazyGoat\Proto\ResourceManager\GetResourceGroupResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\ResourceManager\PutResourceGroupRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\ResourceManager\PutResourceGroupResponse>
     */
    public function AddResourceGroup(\CrazyGoat\Proto\ResourceManager\PutResourceGroupRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/resource_manager.ResourceManager/AddResourceGroup',
        $argument,
        ['\CrazyGoat\Proto\ResourceManager\PutResourceGroupResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\ResourceManager\PutResourceGroupRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\ResourceManager\PutResourceGroupResponse>
     */
    public function ModifyResourceGroup(\CrazyGoat\Proto\ResourceManager\PutResourceGroupRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/resource_manager.ResourceManager/ModifyResourceGroup',
        $argument,
        ['\CrazyGoat\Proto\ResourceManager\PutResourceGroupResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \CrazyGoat\Proto\ResourceManager\DeleteResourceGroupRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\CrazyGoat\Proto\ResourceManager\DeleteResourceGroupResponse>
     */
    public function DeleteResourceGroup(\CrazyGoat\Proto\ResourceManager\DeleteResourceGroupRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/resource_manager.ResourceManager/DeleteResourceGroup',
        $argument,
        ['\CrazyGoat\Proto\ResourceManager\DeleteResourceGroupResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function AcquireTokenBuckets($metadata = [], $options = []) {
        return $this->_bidiRequest('/resource_manager.ResourceManager/AcquireTokenBuckets',
        ['\CrazyGoat\Proto\ResourceManager\TokenBucketsResponse','decode'],
        $metadata, $options);
    }

}
