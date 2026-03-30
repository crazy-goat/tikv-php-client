<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Batch;

use CrazyGoat\TiKV\Client\Exception\GrpcException;
use Google\Protobuf\Internal\Message;
use Grpc\Call;

final class GrpcFuture
{
    private bool $completed = false;
    private ?Message $result = null;
    private ?GrpcException $error = null;

    public function __construct(
        private readonly Call $call,
        private readonly string $responseClass,
    ) {
    }

    public function wait(): Message
    {
        if ($this->completed) {
            if ($this->error !== null) {
                throw $this->error;
            }
            if ($this->result === null) {
                throw new GrpcException('Unexpected null result', \Grpc\STATUS_INTERNAL);
            }
            return $this->result;
        }

        $event = $this->call->startBatch([
            \Grpc\OP_RECV_INITIAL_METADATA => true,
            \Grpc\OP_RECV_MESSAGE => true,
            \Grpc\OP_RECV_STATUS_ON_CLIENT => true,
        ]);

        $status = $this->extractStatus($event);

        if ($status['code'] !== \Grpc\STATUS_OK) {
            $this->error = new GrpcException($status['details'], $status['code']);
            $this->completed = true;
            throw $this->error;
        }

        $this->result = $this->deserializeResponse($event);
        $this->completed = true;

        return $this->result;
    }

    /**
     * @return array{code: int, details: string}
     */
    private function extractStatus(mixed $event): array
    {
        if (is_object($event)) {
            $event = (array) $event;
        }

        /** @var array<string, mixed> $eventArray */
        $eventArray = $event;
        $status = $eventArray['status'] ?? null;
        if (is_object($status)) {
            $status = (array) $status;
        }

        /** @var array<string, mixed> $statusArray */
        $statusArray = is_array($status) ? $status : [];

        $code = $statusArray['code'] ?? 0;
        $details = $statusArray['details'] ?? '';

        return [
            'code' => is_int($code) ? $code : (is_string($code) ? (int) $code : 0),
            'details' => is_string($details) ? $details : (is_scalar($details) ? (string) $details : ''),
        ];
    }

    /**
     * @return Message
     */
    private function deserializeResponse(mixed $event): Message
    {
        if (is_object($event)) {
            $event = (array) $event;
        }

        /** @var array<string, mixed> $eventArray */
        $eventArray = $event;
        $message = $eventArray['message'] ?? null;

        /** @var Message $response */
        $response = new $this->responseClass();

        if ($message !== null && $message !== '' && is_string($message)) {
            $response->mergeFromString($message);
        }

        return $response;
    }
}
