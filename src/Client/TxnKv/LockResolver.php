<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\TxnKv;

use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class LockResolver
{
    public function __construct(
        private GrpcClientInterface $grpc,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Resolve lock for a key.
     * Called when encountering a lock during read/write.
     */
    public function resolveLock(string $key, int $lockTs): void
    {
        $this->logger->debug('Resolving lock', ['key' => $key, 'lockTs' => $lockTs]);
        // TODO: Implement lock resolution
    }

    /**
     * Check for deadlocks.
     * Called periodically in pessimistic mode.
     */
    public function checkDeadlock(string $txnId): bool
    {
        $this->logger->debug('Checking for deadlock', ['txnId' => $txnId]);
        // TODO: Implement deadlock detection
        return false;
    }

    public function getGrpc(): GrpcClientInterface
    {
        return $this->grpc;
    }
}
