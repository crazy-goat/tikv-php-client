<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Connection;

use CrazyGoat\Proto\Pdpb\RequestHeader;
use CrazyGoat\Proto\Pdpb\TsoRequest;
use CrazyGoat\Proto\Pdpb\TsoResponse;
use CrazyGoat\TiKV\Client\Exception\GrpcException;
use CrazyGoat\TiKV\Client\Exception\InvalidArgumentException;
use CrazyGoat\TiKV\Client\Exception\TiKvException;
use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * PD TSO oracle: batching and low-resolution caching of PD timestamps.
 *
 * TSO timestamps carry an 18-bit logical counter inside one physical
 * millisecond; a single TSO grant of $count timestamps is handed out as
 * the consecutive values base … base + $count - 1 (plain integer
 * addition composes/wraps the logical part correctly).
 */
final class TimestampOracle
{
    /** Cached low-resolution timestamp, or null when not populated. */
    private ?int $lowResCachedTs = null;
    /** Wall-clock milliseconds (per {@see $clock}) at which the cache was filled. */
    private ?int $lowResCachedAtMs = null;
    /** Wall-clock milliseconds source for the low-resolution cache. */
    private readonly \Closure $clock;

    /** Number of logical bits inside one physical millisecond (issue #420). */
    private const LOGICAL_SHIFT = 18;

    /**
     * @param \Closure(): ?int $getClusterId
     * @param \Closure(int): void $setClusterId
     * @param int|null $lowResMaxStalenessMs maximum allowed staleness (ms) of the
     *                                       low-resolution timestamp cache;
     *                                       null = no caching (default), 0 =
     *                                       refetch on every call (only bounds
     *                                       staleness, never returns stale data)
     * @param \Closure(): int $clock wall-clock milliseconds; injectable for tests
     */
    public function __construct(
        private readonly GrpcClientInterface $grpc,
        private readonly string $pdAddress,
        private readonly \Closure $getClusterId,
        private readonly \Closure $setClusterId,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly ?int $lowResMaxStalenessMs = null,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => (int) (microtime(true) * 1000);
    }

    /**
     * Request a monotonically increasing timestamp from PD's TSO service.
     *
     * Fails closed on TSO unavailability: a locally fabricated timestamp
     * would violate TiKV MVCC ordering (snapshot isolation / global ordering),
     * so callers must observe the failure and decide whether to retry or
     * abort the transaction.
     *
     * @param int|null $timeoutMs Optional gRPC call timeout in milliseconds (null = no timeout)
     *
     * @throws TiKvException when the TSO RPC fails or returns an invalid response
     */
    public function getTimestamp(?int $timeoutMs = null): int
    {
        return $this->getTimestampBatch(1, $timeoutMs)[0];
    }

    /**
     * Request a batch of monotonically increasing timestamps from PD's
     * TSO service in a single RPC (issue #420, GAP-06).
     *
     * A single `Tso` request with `count = $count` costs one round trip
     * and yields a consecutive range of timestamps; the surplus
     * timestamps beyond the first are handed out in order by plain
     * integer increment, which composes and wraps the 18-bit logical
     * counter inside the physical millisecond correctly.
     *
     * @param int $count number of timestamps to request (>= 1)
     * @param int|null $timeoutMs Optional gRPC call timeout in milliseconds (null = no timeout)
     *
     * @return list<int> at most $count monotonically increasing timestamps
     *                   (PD may grant fewer than requested; never fewer than 1)
     *
     * @throws InvalidArgumentException when $count is < 1
     * @throws TiKvException when the TSO RPC fails or returns an invalid response
     */
    public function getTimestampBatch(int $count, ?int $timeoutMs = null): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException('Timestamp batch count must be >= 1');
        }

        $request = new TsoRequest();
        $request->setHeader($this->createHeader());
        $request->setCount($count);

        try {
            $response = $this->callTso($request, $timeoutMs);

            return $this->extractTimestampRange($response, $count);
        } catch (GrpcException $e) {
            $this->logger->error('TSO request failed; refusing to fabricate a local timestamp', [
                'error' => $e->getMessage(),
                'grpcStatusCode' => $e->grpcStatusCode,
            ]);

            throw new TiKvException(
                sprintf('TSO request failed: %s', $e->getMessage()),
                $e->grpcStatusCode,
                $e,
            );
        }
    }

    /**
     * Return a timestamp that is at most $lowResMaxStalenessMs old
     * (issue #420, GAP-06 low-resolution cache).
     *
     * With no staleness bound configured (default) this is equivalent to
     * {@see getTimestamp()}: every call performs a fresh TSO RPC. With a
     * bound set, repeated calls within the bound reuse the cached
     * timestamp and save the PD round trip — suitable for
     * staleness-tolerant consumers such as lock resolution
     * (`CheckTxnStatus.current_ts`), never for start/commit timestamps.
     *
     * @param int|null $timeoutMs Optional gRPC call timeout in milliseconds (null = no timeout)
     *
     * @throws TiKvException when the TSO RPC fails or returns an invalid response
     */
    public function getLowResolutionTimestamp(?int $timeoutMs = null): int
    {
        if ($this->lowResMaxStalenessMs === null) {
            return $this->getTimestamp($timeoutMs);
        }

        $cachedTs = $this->lowResCachedTs;
        $cachedAtMs = $this->lowResCachedAtMs;
        if ($cachedTs !== null && $cachedAtMs !== null) {
            $ageMs = ($this->clock)() - $cachedAtMs;
            if ($ageMs >= 0 && $ageMs <= $this->lowResMaxStalenessMs) {
                return $cachedTs;
            }
        }

        $ts = $this->getTimestamp($timeoutMs);
        $this->lowResCachedTs = $ts;
        $this->lowResCachedAtMs = ($this->clock)();

        return $ts;
    }

    private function createHeader(): RequestHeader
    {
        $header = new RequestHeader();
        $clusterId = ($this->getClusterId)();
        if ($clusterId !== null) {
            $header->setClusterId($clusterId);
        }

        return $header;
    }

    /**
     * Issue the TSO RPC, retrying once on cluster-id mismatch.
     *
     * Mirrors `PdClient::callWithClusterIdRetry()` so the oracle benefits
     * from the same first-connect cluster-id discovery as the other PD
     * RPCs. The retry only fires for the "mismatch cluster id" error;
     * any other gRPC failure propagates immediately so the caller can
     * fail closed.
     */
    private function callTso(TsoRequest $request, ?int $timeoutMs): TsoResponse
    {
        try {
            $response = $this->grpc->call(
                $this->pdAddress,
                'pdpb.PD',
                'Tso',
                $request,
                TsoResponse::class,
                $timeoutMs,
            );

            $this->learnClusterId($response);

            return $response;
        } catch (GrpcException $e) {
            $extractedId = $this->extractClusterIdFromError($e->getMessage());
            if ($extractedId === null) {
                throw $e;
            }

            $this->logger->warning(
                'Cluster ID mismatch on TSO, retrying',
                ['clusterId' => $extractedId],
            );
            ($this->setClusterId)($extractedId);
            $request->setHeader($this->createHeader());

            $response = $this->grpc->call(
                $this->pdAddress,
                'pdpb.PD',
                'Tso',
                $request,
                TsoResponse::class,
                $timeoutMs,
            );

            $this->learnClusterId($response);

            return $response;
        }
    }

    private function learnClusterId(TsoResponse $response): void
    {
        if (($this->getClusterId)() !== null) {
            return;
        }

        $header = $response->getHeader();
        if ($header !== null) {
            ($this->setClusterId)((int) $header->getClusterId());
            $this->logger->info('Learned cluster ID', ['clusterId' => $header->getClusterId()]);
        }
    }

    private function extractClusterIdFromError(string $message): ?int
    {
        if (!str_contains($message, 'mismatch cluster id')) {
            return null;
        }
        if (preg_match('/need (\d+) but got/', $message, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Turn a TSO grant into exactly $count consecutive timestamps.
     *
     * PD may grant fewer timestamps than requested (`TsoResponse.count`);
     * the handout never exceeds the granted count so we never fabricate
     * timestamps outside the grant.
     *
     * @return list<int>
     */
    private function extractTimestampRange(TsoResponse $response, int $requested): array
    {
        $ts = $response->getTimestamp();
        if ($ts === null) {
            throw new TiKvException('TSO response missing timestamp');
        }

        $physical = (int) $ts->getPhysical();
        $logical = (int) $ts->getLogical();
        $base = ($physical << self::LOGICAL_SHIFT) + $logical;

        $granted = (int) $response->getCount();
        if ($granted < 1) {
            $this->logger->warning('TSO response missing count, assuming single timestamp', [
                'count' => $granted,
            ]);
            $granted = 1;
        }
        if ($granted < $requested) {
            $this->logger->warning('TSO grant smaller than requested', [
                'requested' => $requested,
                'granted' => $granted,
            ]);
        }

        $range = [];
        for ($i = 0; $i < min($granted, $requested); $i++) {
            $range[] = $base + $i;
        }

        return $range;
    }
}
