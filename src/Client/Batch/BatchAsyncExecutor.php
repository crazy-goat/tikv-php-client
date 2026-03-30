<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Batch;

use CrazyGoat\TiKV\Client\Batch\GrpcFuture;
use CrazyGoat\TiKV\Client\Exception\BatchPartialFailureException;
use CrazyGoat\TiKV\Client\Exception\TiKvException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class BatchAsyncExecutor
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Execute multiple callables concurrently and return results.
     *
     * @param array<int, callable(): mixed> $regionCalls Array of regionId => callable returning GrpcFuture or direct value
     * @return array<int, mixed> Array of regionId => result
     * @throws BatchPartialFailureException If any region fails
     */
    public function executeParallel(array $regionCalls): array
    {
        $totalRegions = count($regionCalls);

        $this->logger->debug('Starting parallel batch execution', [
            'totalRegions' => $totalRegions,
        ]);

        // Start all calls - they return GrpcFuture objects
        $futures = [];
        $errors = [];
        foreach ($regionCalls as $regionId => $callable) {
            try {
                $futures[$regionId] = $callable();
            } catch (TiKvException $e) {
                $errors[$regionId] = $e;
                $this->logger->warning('Region failed during call', [
                    'regionId' => $regionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Collect all results
        $results = [];

        foreach ($futures as $regionId => $future) {
            try {
                // Handle both GrpcFuture objects and direct values
                $results[$regionId] = $future instanceof GrpcFuture ? $future->wait() : $future;
                $this->logger->debug('Region completed successfully', ['regionId' => $regionId]);
            } catch (TiKvException $e) {
                $errors[$regionId] = $e;
                $this->logger->warning('Region failed', [
                    'regionId' => $regionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($errors !== []) {
            throw new BatchPartialFailureException($errors, $totalRegions);
        }

        $this->logger->debug('Parallel batch execution completed', [
            'totalRegions' => $totalRegions,
        ]);

        return $results;
    }
}
