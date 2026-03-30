<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\TxnKv;

use CrazyGoat\TiKV\Client\Cache\RegionCache;
use CrazyGoat\TiKV\Client\Cache\RegionCacheInterface;
use CrazyGoat\TiKV\Client\Connection\PdClientInterface;
use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class TxnKvClient
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Begin a new transaction.
     *
     * @param array{
     *   pessimistic?: bool,
     *   priority?: int,
     * } $options
     */
    public function begin(array $options = []): Transaction
    {
        $pessimistic = $options['pessimistic'] ?? true;
        $priority = $options['priority'] ?? 0;

        // TODO: Get timestamp from PD
        $startTs = time() * 1000; // Placeholder

        $txnId = uniqid('txn-', true);

        $this->logger->info('Transaction started', [
            'txnId' => $txnId,
            'startTs' => $startTs,
            'pessimistic' => $pessimistic,
        ]);

        return new Transaction(
            txnId: $txnId,
            startTs: $startTs,
            pessimistic: $pessimistic,
            priority: $priority,
        );
    }
}
