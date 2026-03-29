<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Grpc;

use CrazyGoat\TiKV\Client\Exception\GrpcException;
use Google\Protobuf\Internal\Message;
use Grpc\Call;
use Grpc\Channel;
use Grpc\ChannelCredentials;
use Grpc\Timeval;

final class GrpcClient implements GrpcClientInterface
{
    /** @var array<string, Channel> */
    private array $channels = [];

    public function call(
        string $address,
        string $service,
        string $method,
        Message $request,
        string $responseClass,
    ): Message {
        $channel = $this->getChannel($address);

        $call = new Call(
            $channel,
            "/{$service}/{$method}",
            Timeval::infFuture(),
        );

        $call->startBatch([
            \Grpc\OP_SEND_INITIAL_METADATA => [],
            \Grpc\OP_SEND_MESSAGE => ['message' => $request->serializeToString()],
            \Grpc\OP_SEND_CLOSE_FROM_CLIENT => true,
        ]);

        $event = $call->startBatch([
            \Grpc\OP_RECV_INITIAL_METADATA => true,
            \Grpc\OP_RECV_MESSAGE => true,
            \Grpc\OP_RECV_STATUS_ON_CLIENT => true,
        ]);

        $status = $this->extractStatus($event);

        if ($status['code'] !== \Grpc\STATUS_OK) {
            throw new GrpcException(
                details: $status['details'],
                grpcStatusCode: $status['code'],
            );
        }

        return $this->deserializeResponse($event, $responseClass);
    }

    public function close(): void
    {
        foreach ($this->channels as $channel) {
            $channel->close();
        }
        $this->channels = [];
    }

    private function getChannel(string $address): Channel
    {
        if (!isset($this->channels[$address])) {
            $this->channels[$address] = new Channel($address, [
                'credentials' => ChannelCredentials::createInsecure(),
            ]);
        }

        return $this->channels[$address];
    }

    /**
     * @return array{code: int, details: string}
     */
    private function extractStatus(mixed $event): array
    {
        if (is_object($event)) {
            $event = (array) $event;
        }

        $status = $event['status'] ?? null;
        if (is_object($status)) {
            $status = (array) $status;
        }

        return [
            'code' => (int) ($status['code'] ?? 0),
            'details' => (string) ($status['details'] ?? ''),
        ];
    }

    /**
     * @template T of Message
     * @param class-string<T> $responseClass
     * @return T
     */
    private function deserializeResponse(mixed $event, string $responseClass): Message
    {
        if (is_object($event)) {
            $event = (array) $event;
        }

        $message = $event['message'] ?? null;

        /** @var T $response */
        $response = new $responseClass();

        if ($message !== null && $message !== '') {
            $response->mergeFromString($message);
        }

        return $response;
    }
}
