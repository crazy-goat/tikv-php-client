<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Retry;

use CrazyGoat\TiKV\Client\Retry\Backoff;
use PHPUnit\Framework\TestCase;

class BackoffTest extends TestCase
{
    public function testExponentialAttemptZeroReturnsBase(): void
    {
        $this->assertSame(100, Backoff::exponential(100, 2000, 0));
    }

    public function testExponentialGrowsExponentially(): void
    {
        $this->assertSame(100, Backoff::exponential(100, 2000, 0));
        $this->assertSame(200, Backoff::exponential(100, 2000, 1));
        $this->assertSame(400, Backoff::exponential(100, 2000, 2));
        $this->assertSame(800, Backoff::exponential(100, 2000, 3));
        $this->assertSame(1600, Backoff::exponential(100, 2000, 4));
    }

    public function testExponentialCapsAtMax(): void
    {
        $this->assertSame(2000, Backoff::exponential(100, 2000, 5));
        $this->assertSame(2000, Backoff::exponential(100, 2000, 10));
        $this->assertSame(2000, Backoff::exponential(100, 2000, 100));
    }

    public function testExponentialWithEqualJitterInRange(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $result = Backoff::exponential(100, 2000, 0, true);
            $this->assertGreaterThanOrEqual(50, $result);
            $this->assertLessThanOrEqual(100, $result);
        }
    }

    public function testExponentialWithEqualJitterCapped(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $result = Backoff::exponential(100, 2000, 10, true);
            $this->assertGreaterThanOrEqual(1000, $result);
            $this->assertLessThanOrEqual(2000, $result);
        }
    }

    public function testExponentialSmallBaseAndCap(): void
    {
        $this->assertSame(2, Backoff::exponential(2, 500, 0));
        $this->assertSame(4, Backoff::exponential(2, 500, 1));
        $this->assertSame(256, Backoff::exponential(2, 500, 7));
        $this->assertSame(500, Backoff::exponential(2, 500, 8));
        $this->assertSame(500, Backoff::exponential(2, 500, 20));
    }
}
