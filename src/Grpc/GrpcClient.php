<?php
declare(strict_types=1);

namespace TiKvPhp\Grpc;

use Grpc\Channel;
use Grpc\ChannelCredentials;
use Grpc\Call;
use Grpc\Timeval;
use Google\Protobuf\Internal\Message;

class GrpcClient
{
    private array $channels = [];
    
    public function call(string $address, string $service, string $method, Message $request, string $responseClass): Message
    {
        if (!isset($this->channels[$address])) {
            $this->channels[$address] = new Channel($address, [
                'credentials' => ChannelCredentials::createInsecure(),
            ]);
        }
        
        $channel = $this->channels[$address];
        $call = new Call(
            $channel,
            '/' . $service . '/' . $method,
            Timeval::infFuture()
        );
        
        $serialized = $request->serializeToString();
        $metadata = [];
        
        $call->startBatch([
            \Grpc\OP_SEND_INITIAL_METADATA => $metadata,
            \Grpc\OP_SEND_MESSAGE => ['message' => $serialized],
            \Grpc\OP_SEND_CLOSE_FROM_CLIENT => true,
        ]);
        
        $event = $call->startBatch([
            \Grpc\OP_RECV_INITIAL_METADATA => true,
            \Grpc\OP_RECV_MESSAGE => true,
            \Grpc\OP_RECV_STATUS_ON_CLIENT => true,
        ]);
        
        // Debug: print raw response
        error_log("Raw event: " . print_r($event, true));
        
        // Convert object to array if needed
        if (is_object($event)) {
            $event = (array) $event;
        }
        
        $status = $event['status'] ?? null;
        if (is_object($status)) {
            $status = (array) $status;
        }
        
        $code = $status['code'] ?? 0;
        $details = $status['details'] ?? '';
        
        if ($code !== \Grpc\STATUS_OK) {
            throw new \RuntimeException(
                'gRPC error: ' . $details,
                $code
            );
        }
        
        $message = $event['message'] ?? null;
        
        // Debug: print message
        error_log("Raw message (hex): " . bin2hex($message ?? ''));
        
        $response = new $responseClass();
        
        if ($message !== null && $message !== '') {
            try {
                $response->mergeFromString($message);
            } catch (\Exception $e) {
                error_log("Parse error: " . $e->getMessage());
                error_log("Message length: " . strlen($message));
                throw $e;
            }
        }
        
        return $response;
    }
    
    public function close(): void
    {
        foreach ($this->channels as $channel) {
            $channel->close();
        }
        $this->channels = [];
    }
}
