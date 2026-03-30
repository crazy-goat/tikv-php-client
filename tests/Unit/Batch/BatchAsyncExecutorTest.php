<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Batch;

use CrazyGoat\TiKV\Client\Batch\BatchAsyncExecutor;
use CrazyGoat\TiKV\Client\Exception\BatchPartialFailureException;
use CrazyGoat\TiKV\Client\Exception\TiKvException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class BatchAsyncExecutorTest extends TestCase
{
    public function testExecuteParallelWithSuccessfulCalls(): void
    {
        $executor = new BatchAsyncExecutor(new NullLogger());

        $calls = [
            1 => fn() => 'result1',
            2 => fn() => 'result2',
        ];

        $results = $executor->executeParallel($calls);

        $this->assertSame([1 => 'result1', 2 => 'result2'], $results);
    }

    public function testExecuteParallelWithPartialFailure(): void
    {
        $executor = new BatchAsyncExecutor(new NullLogger());

        $calls = [
            1 => fn() => 'result1',
            2 => fn() => throw new TiKvException('Region 2 failed'),
        ];

        $this->expectException(BatchPartialFailureException::class);
        $this->expectExceptionMessage('Batch operation partially failed: 1 of 2 regions failed');

        $executor->executeParallel($calls);
    }

    public function testExecuteParallelWithAllFailure(): void
    {
        $executor = new BatchAsyncExecutor(new NullLogger());

        $calls = [
            1 => fn() => throw new TiKvException('Region 1 failed'),
            2 => fn() => throw new TiKvException('Region 2 failed'),
        ];

        try {
            $executor->executeParallel($calls);
            $this->fail('Expected exception');
        } catch (BatchPartialFailureException $e) {
            $this->assertCount(2, $e->getRegionErrors());
            $this->assertSame(2, $e->getTotalRegions());
        }
    }
}
