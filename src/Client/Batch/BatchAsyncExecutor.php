<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Batch;

use CrazyGoat\TiKV\Client\Exception\BatchPartialFailureException;
use CrazyGoat\TiKV\Client\Exception\TiKvException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class BatchAsyncExecutor
{
    public function __construct(
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Execute multiple callables concurrently and return results.
     *
     * @template T
     * @param array<int, callable(): T> $regionCalls Array of regionId => callable returning GrpcFuture
     * @return array<int, T> Array of regionId => result
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
                if (is_object($future) && method_exists($future, 'wait')) {
                    $results[$regionId] = $future->wait();
                } else {
                    $results[$regionId] = $future;
                }
                $this->logger->debug('Region completed successfully', ['regionId' => $regionId]);
            } catch (TiKvException $e) {
                $errors[$regionId] = $e;
                $this->logger->warning('Region failed', [
                    'regionId' => $regionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($errors)) {
            throw new BatchPartialFailureException($errors, $totalRegions);
        }

        $this->logger->debug('Parallel batch execution completed', [
            'totalRegions' => $totalRegions,
        ]);

        return $results;
    }
}
