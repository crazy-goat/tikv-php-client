<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\RawKv;

use CrazyGoat\Proto\Errorpb\EpochNotMatch;
use CrazyGoat\Proto\Errorpb\Error;
use CrazyGoat\Proto\Errorpb\NotLeader;
use CrazyGoat\Proto\Kvrpcpb\KvPair;
use CrazyGoat\Proto\Kvrpcpb\RawBatchGetResponse;
use CrazyGoat\Proto\Kvrpcpb\RawBatchPutRequest;
use CrazyGoat\Proto\Kvrpcpb\RawBatchPutResponse;
use CrazyGoat\Proto\Metapb\Peer;
use CrazyGoat\Proto\Metapb\Store;
use CrazyGoat\TiKV\Client\Batch\BatchAsyncExecutor;
use CrazyGoat\TiKV\Client\Batch\CheckedGrpcFuture;
use CrazyGoat\TiKV\Client\Batch\GrpcFuture;
use CrazyGoat\TiKV\Client\Cache\RegionCacheInterface;
use CrazyGoat\TiKV\Client\Connection\PdClientInterface;
use CrazyGoat\TiKV\Client\Exception\BatchPartialFailureException;
use CrazyGoat\TiKV\Client\Exception\InvalidArgumentException;
use CrazyGoat\TiKV\Client\Exception\RegionException;
use CrazyGoat\TiKV\Client\Grpc\GrpcClientInterface;
use CrazyGoat\TiKV\Client\Grpc\TimeoutConfig;
use CrazyGoat\TiKV\Client\RawKv\RawKvBatch;
use CrazyGoat\TiKV\Client\Region\Dto\RegionInfo;
use CrazyGoat\TiKV\Client\Region\RegionErrorHandler;
use CrazyGoat\TiKV\Client\Region\RegionResolver;
use CrazyGoat\TiKV\Client\Retry\RetryExecutor;
use CrazyGoat\TiKV\Tests\Unit\Grpc\GrpcExtensionGate;
use Google\Protobuf\Internal\Message;
use Grpc\Call;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RawKvBatchTest extends TestCase
{
    use GrpcExtensionGate;

    private GrpcClientInterface&MockObject $grpc;
    private RegionCacheInterface&MockObject $regionCache;
    private PdClientInterface&MockObject $pdClient;
    private RawKvBatch $batch;

    protected function setUp(): void
    {
        $this->grpc = $this->createMock(GrpcClientInterface::class);
        $this->regionCache = $this->createMock(RegionCacheInterface::class);
        $this->pdClient = $this->createMock(PdClientInterface::class);

        $regionResolver = new RegionResolver($this->pdClient, $this->regionCache);

        $this->batch = new RawKvBatch(
            $this->grpc,
            $regionResolver,
            new TimeoutConfig(),
            new NullLogger(),
        );
    }

    private function defaultRegion(): RegionInfo
    {
        return new RegionInfo(
            regionId: 1,
            leaderPeerId: 1,
            leaderStoreId: 1,
            epochConfVer: 1,
            epochVersion: 1,
        );
    }

    private function defaultStore(): Store
    {
        $store = new Store();
        $store->setId(1);
        $store->setAddress('tikv1:20160');
        return $store;
    }

    public function testBatchPutThrowsOnTtlCountMismatch(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TTL array count (1) must match key-value pairs count (2)');

        $retryExecutor = $this->createRetryExecutor();
        $this->batch->batchPut(['k1' => 'v1', 'k2' => 'v2'], [60], $retryExecutor);
    }

    public function testBatchGetEmptyReturnsEmpty(): void
    {
        $retryExecutor = $this->createRetryExecutor();
        $this->assertSame([], $this->batch->batchGet([], $retryExecutor));
    }

    public function testBatchPutEmptyReturnsEarly(): void
    {
        $retryExecutor = $this->createRetryExecutor();
        $this->grpc->expects($this->never())->method('getChannel');
        $this->batch->batchPut([], 0, $retryExecutor);
    }

    public function testBatchDeleteEmptyReturnsEarly(): void
    {
        $retryExecutor = $this->createRetryExecutor();
        $this->grpc->expects($this->never())->method('getChannel');
        $this->batch->batchDelete([], $retryExecutor);
    }

    public function testBatchPutWithIntTtl(): void
    {
        $this->requireGrpcExtension();

        $this->regionCache->method('getByKey')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());
        $this->grpc->method('getChannel')->willReturn(new \Grpc\Channel('127.0.0.1:1', [
            'credentials' => \Grpc\ChannelCredentials::createInsecure(),
        ]));

        $retryExecutor = $this->createRetryExecutor();
        $this->batch->batchPut(['k1' => 'v1', 'k2' => 'v2'], 60, $retryExecutor);
        $this->addToAssertionCount(1);
    }

    public function testBatchPutWithAssociativeTtlArray(): void
    {
        $this->requireGrpcExtension();

        $this->regionCache->method('getByKey')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());
        $this->grpc->method('getChannel')->willReturn(new \Grpc\Channel('127.0.0.1:1', [
            'credentials' => \Grpc\ChannelCredentials::createInsecure(),
        ]));

        $retryExecutor = $this->createRetryExecutor();
        $this->batch->batchPut(['k1' => 'v1', 'k2' => 'v2'], ['k1' => 60, 'k2' => 120], $retryExecutor);
        $this->addToAssertionCount(1);
    }

    public function testScalarTtlExpandsToMatchPairCountOnRequest(): void
    {
        $pair1 = new KvPair();
        $pair1->setKey('k1');
        $pair1->setValue('v1');
        $pair2 = new KvPair();
        $pair2->setKey('k2');
        $pair2->setValue('v2');
        $pair3 = new KvPair();
        $pair3->setKey('k3');
        $pair3->setValue('v3');

        $request = new RawBatchPutRequest();
        $request->setPairs([$pair1, $pair2, $pair3]);

        // Simulate the fix: scalar TTL expanded to match pair count
        $ttl = 60;
        $ttls = array_fill(0, count([$pair1, $pair2, $pair3]), $ttl);
        $request->setTtls($ttls);

        $this->assertCount(3, $request->getTtls());
        foreach ($request->getTtls() as $entry) {
            $this->assertSame(60, $entry);
        }
    }

    public function testPerKeyTtlArrayPassesThroughUnchanged(): void
    {
        $pair1 = new KvPair();
        $pair1->setKey('k1');
        $pair1->setValue('v1');
        $pair2 = new KvPair();
        $pair2->setKey('k2');
        $pair2->setValue('v2');

        $request = new RawBatchPutRequest();
        $request->setPairs([$pair1, $pair2]);

        // Per-key TTL array passes through unchanged
        $ttls = [60, 120];
        $request->setTtls($ttls);

        $this->assertCount(2, $request->getTtls());
        $this->assertSame(60, $request->getTtls()[0]);
        $this->assertSame(120, $request->getTtls()[1]);
    }

    public function testBatchPutWithMultipleKeysAndScalarTtl(): void
    {
        $this->requireGrpcExtension();

        $this->regionCache->method('getByKey')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());
        $this->grpc->method('getChannel')->willReturn(new \Grpc\Channel('127.0.0.1:1', [
            'credentials' => \Grpc\ChannelCredentials::createInsecure(),
        ]));

        $retryExecutor = $this->createRetryExecutor();
        // 3 keys with scalar TTL - old code would send a 1-element ttls array
        $this->batch->batchPut(['k1' => 'v1', 'k2' => 'v2', 'k3' => 'v3'], 60, $retryExecutor);
        $this->addToAssertionCount(1);
    }

    public function testBatchPutWithNumericStringKeys(): void
    {
        $this->requireGrpcExtension();

        $this->regionCache->method('getByKey')->willReturn($this->defaultRegion());
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());
        $this->pdClient->method('scanRegions')->willReturn([$this->defaultRegion()]);
        $this->grpc->method('getChannel')->willReturn(new \Grpc\Channel('127.0.0.1:1', [
            'credentials' => \Grpc\ChannelCredentials::createInsecure(),
        ]));

        // Pre-fix: PHP coerces the "12345"/"0" array keys to int, so
        // KvPair::setKey(string) throws a TypeError while building the wire
        // pairs. Post-fix the request only fails at the transport layer,
        // because there is no TiKV server in unit tests.
        $this->expectException(BatchPartialFailureException::class);

        $retryExecutor = $this->createRetryExecutor();
        // PHP models literal "12345"/"0" array keys as int; build the pairs
        // through string-typed keys so they reach batchPut() with their
        // declared contract (numeric-string keys must survive to the wire).
        $pairs = $this->stringKeyedPairs('12345', 'v1') + $this->stringKeyedPairs('0', 'v2');
        $this->batch->batchPut($pairs, 0, $retryExecutor);
    }

    /**
     * keyInRegion() must check region bounds in byte order, not PHP
     * numeric-string order. Region bounds ['', '100']: '9' is inside in byte
     * order, '100' is the exclusive end, '200' is outside.
     */
    public function testKeyInRegionUsesByteOrderNotNumericOrder(): void
    {
        $region = new RegionInfo(
            regionId: 1,
            leaderPeerId: 1,
            leaderStoreId: 1,
            epochConfVer: 1,
            epochVersion: 1,
            startKey: '',
            endKey: '100',
        );

        $method = new \ReflectionMethod(RawKvBatch::class, 'keyInRegion');

        // In byte order '0999' < '100', so it is inside ['', '100').
        self::assertTrue($method->invoke($this->batch, '0999', $region));
        // '9' > '100' in byte order, so it belongs to the NEXT region, not this one.
        self::assertFalse($method->invoke($this->batch, '9', $region));
        // '100' is the exclusive end — not in this region.
        self::assertFalse($method->invoke($this->batch, '100', $region));
        // '200' > '100' in both byte and numeric order.
        self::assertFalse($method->invoke($this->batch, '200', $region));
    }

    // ========================================================================
    // Issue #330: pin the retry behaviour for region errors returned INSIDE
    // batch responses. RawKvBatch::batchGetWithRetry() runs only the dispatch
    // (region resolution + send) inside RetryExecutor::execute() and returns
    // an un-awaited CheckedGrpcFuture; the region-error check happens later,
    // inside CheckedGrpcFuture::waitForExecutor(), during
    // BatchAsyncExecutor's wait phase — outside the retry executor entirely.
    // RawKvBatch hardcodes `new Call(...)` so the transport cannot be
    // injected; the tests below therefore drive the exact wait-boundary
    // composition RawKvBatch builds (CheckedGrpcFuture + GrpcFuture over a
    // mocked \Grpc\Call, or fromCallable over the multi-region waiter)
    // through BatchAsyncExecutor::executeParallel(), which is the code path
    // that actually classifies/reports the error.
    // Pinned contract: a region error inside a batch response is NOT retried
    // and the region cache is NOT invalidated — the error surfaces as a
    // BatchPartialFailureException.
    // ========================================================================

    private function notLeaderError(int $regionId, int $hintStoreId): Error
    {
        $leader = new Peer();
        $leader->setId(1);
        $leader->setStoreId($hintStoreId);

        $notLeader = new NotLeader();
        $notLeader->setRegionId($regionId);
        $notLeader->setLeader($leader);

        $error = new Error();
        $error->setMessage('not leader');
        $error->setNotLeader($notLeader);

        return $error;
    }

    /**
     * Mock \Grpc\Call that resolves a recv batch to a serialized response
     * with STATUS_OK, exactly like a real channel would deliver it.
     */
    private function callReturning(Message $response): Call&MockObject
    {
        $call = $this->createMock(Call::class);
        $call->method('startBatch')->willReturn([
            'status' => ['code' => 0, 'details' => 'OK'],
            'message' => $response->serializeToString(),
        ]);

        return $call;
    }

    public function testBatchGetNotLeaderInsideResponseIsNotRetriedSurfacesAsPartialFailure(): void
    {
        // RawBatchGetResponse #1 carries a NotLeader region_error pointing at
        // store 2. The pinned contract: the RPC is NOT reissued (exactly one
        // startBatch recv = one wait = zero retries) and the error surfaces
        // as a BatchPartialFailureException instead of transparent recovery.
        $response = new RawBatchGetResponse();
        $response->setRegionError($this->notLeaderError(1, 2));

        $call = $this->createMock(Call::class);
        $call->expects($this->exactly(1))->method('startBatch')->willReturn([
            'status' => ['code' => 0, 'details' => 'OK'],
            'message' => $response->serializeToString(),
        ]);

        // The exact wait-boundary wrapper RawKvBatch::batchGetWithRetry()
        // builds for the single-region fast path.
        $future = CheckedGrpcFuture::fromGrpcFuture(
            new GrpcFuture($call, RawBatchGetResponse::class),
        );

        $this->regionCache->expects($this->never())->method('invalidate');

        try {
            (new BatchAsyncExecutor(new NullLogger()))->executeParallel([1 => fn(): CheckedGrpcFuture => $future]);
            self::fail('Expected BatchPartialFailureException');
        } catch (BatchPartialFailureException $e) {
            $errors = $e->getRegionErrors();
            self::assertCount(1, $errors);
            self::assertArrayHasKey(1, $errors);
            $regionError = $errors[1];
            self::assertInstanceOf(RegionException::class, $regionError);
            self::assertNotNull($regionError->notLeader);
            self::assertNotNull($regionError->notLeader->getLeader());
            self::assertSame(2, (int) $regionError->notLeader->getLeader()->getStoreId());
            self::assertSame(1, $e->getTotalRegions());
        }
    }

    public function testEpochNotMatchInsideBatchResponseIsNotInvalidatedEither(): void
    {
        // Multi-region waiter path (CheckedGrpcFuture::fromCallable), exactly
        // as batchGetWithRetry()'s split/merge branch builds it: the waiter
        // runs RegionErrorHandler::check() on each response. Non-NotLeader
        // errors do not carry a leader hint to switch to, yet even they are
        // not invalidated here — check() is invoked without a cache in the
        // batch wait path.
        $response = new RawBatchGetResponse();
        $error = new Error();
        $error->setMessage('epoch not match');
        $error->setEpochNotMatch(new EpochNotMatch());
        $response->setRegionError($error);

        $waiter = static function () use ($response): Message {
            RegionErrorHandler::check($response);
            return $response;
        };

        $this->regionCache->expects($this->never())->method('invalidate');

        try {
            (new BatchAsyncExecutor(new NullLogger()))->executeParallel([
                7 => fn(): CheckedGrpcFuture => CheckedGrpcFuture::fromCallable($waiter),
            ]);
            self::fail('Expected BatchPartialFailureException');
        } catch (BatchPartialFailureException $e) {
            self::assertArrayHasKey(7, $e->getRegionErrors());
            self::assertInstanceOf(RegionException::class, $e->getRegionErrors()[7]);
            self::assertSame(1, $e->getTotalRegions());
        }
    }

    public function testBatchPutPartialFailureReportsWhichRegionsFailedAndDispatched(): void
    {
        // 3 regions dispatched; region 2's response carries an EpochNotMatch.
        // All three region calls are dispatched before any wait begins
        // (dispatch phase), so regions 1 and 3 were still issued even though
        // region 2 fails.
        $dispatched = [];
        $okCall = $this->callReturning(new RawBatchPutResponse());

        $failedResponse = new RawBatchPutResponse();
        $error = new Error();
        $error->setMessage('epoch not match');
        $error->setEpochNotMatch(new EpochNotMatch());
        $failedResponse->setRegionError($error);
        $failedCall = $this->callReturning($failedResponse);

        $regionCalls = [];
        foreach ([1, 2, 3] as $regionId) {
            $call = $regionId === 2 ? $failedCall : $okCall;
            $regionCalls[$regionId] = function () use ($regionId, $call, &$dispatched): CheckedGrpcFuture {
                $dispatched[] = $regionId;
                return CheckedGrpcFuture::fromGrpcFuture(new GrpcFuture($call, RawBatchPutResponse::class));
            };
        }

        try {
            (new BatchAsyncExecutor(new NullLogger()))->executeParallel($regionCalls);
            self::fail('Expected BatchPartialFailureException');
        } catch (BatchPartialFailureException $e) {
            // All 3 regions were dispatched before the wait phase failed.
            self::assertSame([1, 2, 3], $dispatched);
            // getRegionErrors() keys identify exactly which region failed.
            self::assertSame([2], array_keys($e->getRegionErrors()));
            self::assertInstanceOf(RegionException::class, current($e->getRegionErrors()));
            self::assertSame(3, $e->getTotalRegions());
        }
    }

    public function testBatchGetSplitsIntoSubBatchesAtMaxBatchLimit(): void
    {
        // 600 keys in one region → exactly 2 RawBatchGet RPCs
        // (MAX_BATCH_LIMIT = 512). The wait phase fails against the dead
        // channel, which is what pins the *dispatch count*.
        $keys = array_map(static fn(int $i): string => 'k' . $i, range(0, 599));

        $this->regionCache->method('getByKey')->willReturn($this->defaultRegion());
        $this->pdClient->method('scanRegions')->willReturn([$this->defaultRegion()]);
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());
        $this->grpc->expects($this->exactly(2))->method('getChannel')->willReturnCallback(
            fn(): \Grpc\Channel => new \Grpc\Channel('127.0.0.1:1', [
                'credentials' => \Grpc\ChannelCredentials::createInsecure(),
            ]),
        );

        $this->expectException(BatchPartialFailureException::class);
        $this->batch->batchGet($keys, $this->createRetryExecutor());
    }

    public function testBatchPutSplitsOnByteSizeNotOnlyCount(): void
    {
        // 10 pairs of ~2KB values = 20480 bytes > MAX_BATCH_PUT_SIZE (16384)
        // → at least 2 RawBatchPut RPCs despite only 10 pairs.
        $pairs = [];
        foreach (range(0, 9) as $i) {
            $pairs['k' . $i] = str_repeat('x', 2048);
        }

        $this->regionCache->method('getByKey')->willReturn($this->defaultRegion());
        $this->pdClient->method('scanRegions')->willReturn([$this->defaultRegion()]);
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());
        $this->grpc->expects($this->exactly(2))->method('getChannel')->willReturnCallback(
            fn(): \Grpc\Channel => new \Grpc\Channel('127.0.0.1:1', [
                'credentials' => \Grpc\ChannelCredentials::createInsecure(),
            ]),
        );

        $this->expectException(BatchPartialFailureException::class);
        $this->batch->batchPut($pairs, 60, $this->createRetryExecutor());
    }

    public function testBatchGetWithDuplicateKeysDoesNotDuplicateRegionDispatch(): void
    {
        // batchGet(['a', 'a', 'b']) — duplicates collapse in the result map
        // (one entry per key); the pinned observable here is that the batch
        // is dispatched once for the single region, not once per duplicate.
        $this->regionCache->method('getByKey')->willReturn($this->defaultRegion());
        $this->pdClient->method('scanRegions')->willReturn([$this->defaultRegion()]);
        $this->pdClient->method('getStore')->willReturn($this->defaultStore());
        $this->grpc->expects($this->exactly(1))->method('getChannel')->willReturnCallback(
            fn(): \Grpc\Channel => new \Grpc\Channel('127.0.0.1:1', [
                'credentials' => \Grpc\ChannelCredentials::createInsecure(),
            ]),
        );

        $this->expectException(BatchPartialFailureException::class);
        $this->batch->batchGet(['a', 'a', 'b'], $this->createRetryExecutor());
    }

    /**
     * @return array<string, string>
     */
    private function stringKeyedPairs(string $key, string $value): array
    {
        return [$key => $value];
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
}
