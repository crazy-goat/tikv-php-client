<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Grpc;

use CrazyGoat\TiKV\Client\Exception\GrpcException;
use Google\Protobuf\Internal\Message;

interface GrpcClientInterface
{
    /**
     * Execute a unary gRPC call.
     *
     * @template T of Message
     *
     * @param string $address  Target host:port
     * @param string $service  Fully-qualified service name (e.g. "tikvpb.Tikv")
     * @param string $method   RPC method name (e.g. "RawGet")
     * @param Message $request Serializable protobuf request
     * @param class-string<T> $responseClass FQCN of the expected response message
     *
     * @return T Deserialized response
     *
     * @throws GrpcException On transport or protocol error
     */
    public function call(
        string $address,
        string $service,
        string $method,
        Message $request,
        string $responseClass,
    ): Message;

    /**
     * Close all open channels and release resources.
     */
    public function close(): void;
}
