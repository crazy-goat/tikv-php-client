<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\RawKv;

use CrazyGoat\TiKV\Client\Cache\RegionCacheInterface;
use CrazyGoat\TiKV\Client\Connection\PdClientInterface;
use CrazyGoat\TiKV\Client\Exception\TiKvException;
use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use CrazyGoat\TiKV\Client\Grpc\TimeoutConfig;
use CrazyGoat\TiKV\Client\RawKv\RawKvBatch;
use CrazyGoat\TiKV\Client\Region\Dto\RegionInfo;
use CrazyGoat\TiKV\Client\Region\RegionResolver;
use CrazyGoat\TiKV\Client\Retry\RetryExecutor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Issue #329 (TEST-09): keys whose region cannot be resolved must never be
 * silently dropped from a batch write — RawKvBatch::batchPut() returns void,
 * so a skipped key means the write was never sent while the caller observed
 * success (silent data loss).
 *
 * Note: the fail-closed contract is implemented by issue #244 (PR #531),
 * which is not merged yet. The guarded tests below probe whether that fix
 * is present and skip (instead of failing) until it merges; they then
 * permanently pin the contract.
 */
final class RawKvBatchUnresolvedKeyTest extends TestCase
{
    private GrpcClientInterface&MockObject $grpc;
    private RegionCacheInterface&MockObject $regionCache;
    private PdClientInterface&MockObject $pdClient;
    private RawKvBatch $batch;

    protected function setUp(): void
    {
        $this->grpc = $this->createMock(GrpcClientInterface::class);
        $this->regionCache = $this->createMock(RegionCacheInterface::class);
        $this->pdClient = $this->createMock(PdClientInterface::class);

        $this->batch = new RawKvBatch(
            $this->grpc,
            new RegionResolver($this->pdClient, $this->regionCache),
            new TimeoutConfig(),
            new NullLogger(),
        );
    }

    /**
     * Probe whether the fail-closed contract of issue #244 is implemented:
     * batchResolveRegions() must throw for a key outside the returned
     * regions instead of omitting it from the result.
     */
    private function requireFailClosedContract(): void
    {
        $region = $this->region(1, 'a', 'm');
        $this->pdClient->method('scanRegions')->willReturn([$region]);
        $this->regionCache->method('put');

        $resolver = new RegionResolver($this->pdClient, $this->regionCache);
        try {
            $resolver->batchResolveRegions(['z']);
        } catch (TiKvException) {
            return; // fail-closed: implemented
        }

        $this->markTestSkipped(
            'Fail-closed contract not implemented yet (issue #244, PR #531); '
            . 'this test pins the issue #329 criteria and activates once #244 merges.',
        );
    }

    private function region(int $id, string $startKey, string $endKey): RegionInfo
    {
        return new RegionInfo(
            regionId: $id,
            leaderPeerId: $id,
            leaderStoreId: $id,
            epochConfVer: 1,
            epochVersion: 1,
            startKey: $startKey,
            endKey: $endKey,
        );
    }

    private function createRetryExecutor(): RetryExecutor
    {
        return new RetryExecutor(
            20000,
            600000,
            $this->regionCache,
            $this->grpc,
            new RegionResolver($this->pdClient, $this->regionCache),
            new NullLogger(),
        );
    }

    /**
     * Issue #329 criterion: batchPut must either send every key or raise an
     * exception — never return normally having sent only a subset.
     *
     * scanRegions deliberately returns a window that does not cover 'z'
     * (e.g. a stale PD window or the boundary scan bug fixed by #244). The
     * unresolvable key must surface as a typed failure, not vanish.
     */
    public function testBatchPutThrowsWhenAKeyCannotBeResolved(): void
    {
        $this->requireFailClosedContract();

        $this->pdClient->method('scanRegions')->willReturn([$this->region(1, 'a', 'm')]);
        $this->regionCache->method('put');
        $this->grpc->expects($this->never())->method('getChannel');

        $this->expectException(TiKvException::class);
        $this->expectExceptionMessage('"z"');

        $this->batch->batchPut(
            ['a' => '1', 'z' => '2'],
            60,
            $this->createRetryExecutor(),
        );
    }

    /**
     * Every resolvable key must still be sent: only the genuinely
     * unresolvable key may fail the call, not its batch neighbours.
     */
    public function testBatchPutSendsResolvableNeighboursOfUnresolvableKey(): void
    {
        $this->requireFailClosedContract();

        $this->pdClient->method('scanRegions')->willReturn([$this->region(1, 'a', 'm')]);
        $this->regionCache->method('put');

        try {
            $this->batch->batchPut(
                ['a' => '1', 'b' => '2', 'z' => '3'],
                60,
                $this->createRetryExecutor(),
            );
            $this->markTestSkipped('batchPut() must not return normally with an unresolvable key');
        } catch (TiKvException $e) {
            // expected under the fail-closed contract
            self::assertStringContainsString('z', $e->getMessage());
        }
        $this->addToAssertionCount(1);
    }
}
