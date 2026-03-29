<?php
// GENERATED CODE -- DO NOT EDIT!

namespace Resource_manager;

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
     * @param \Resource_manager\ListResourceGroupsRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Resource_manager\ListResourceGroupsResponse>
     */
    public function ListResourceGroups(\Resource_manager\ListResourceGroupsRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/resource_manager.ResourceManager/ListResourceGroups',
        $argument,
        ['\Resource_manager\ListResourceGroupsResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Resource_manager\GetResourceGroupRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Resource_manager\GetResourceGroupResponse>
     */
    public function GetResourceGroup(\Resource_manager\GetResourceGroupRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/resource_manager.ResourceManager/GetResourceGroup',
        $argument,
        ['\Resource_manager\GetResourceGroupResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Resource_manager\PutResourceGroupRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Resource_manager\PutResourceGroupResponse>
     */
    public function AddResourceGroup(\Resource_manager\PutResourceGroupRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/resource_manager.ResourceManager/AddResourceGroup',
        $argument,
        ['\Resource_manager\PutResourceGroupResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Resource_manager\PutResourceGroupRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Resource_manager\PutResourceGroupResponse>
     */
    public function ModifyResourceGroup(\Resource_manager\PutResourceGroupRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/resource_manager.ResourceManager/ModifyResourceGroup',
        $argument,
        ['\Resource_manager\PutResourceGroupResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param \Resource_manager\DeleteResourceGroupRequest $argument input argument
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\UnaryCall<\Resource_manager\DeleteResourceGroupResponse>
     */
    public function DeleteResourceGroup(\Resource_manager\DeleteResourceGroupRequest $argument,
      $metadata = [], $options = []) {
        return $this->_simpleRequest('/resource_manager.ResourceManager/DeleteResourceGroup',
        $argument,
        ['\Resource_manager\DeleteResourceGroupResponse', 'decode'],
        $metadata, $options);
    }

    /**
     * @param array $metadata metadata
     * @param array $options call options
     * @return \Grpc\BidiStreamingCall
     */
    public function AcquireTokenBuckets($metadata = [], $options = []) {
        return $this->_bidiRequest('/resource_manager.ResourceManager/AcquireTokenBuckets',
        ['\Resource_manager\TokenBucketsResponse','decode'],
        $metadata, $options);
    }

}
