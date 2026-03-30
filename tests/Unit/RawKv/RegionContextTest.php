<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\RawKv;

use CrazyGoat\Proto\Kvrpcpb\Context;
use CrazyGoat\TiKV\Client\RawKv\Dto\RegionInfo;
use CrazyGoat\TiKV\Client\RawKv\RegionContext;
use PHPUnit\Framework\TestCase;

class RegionContextTest extends TestCase
{
    public function testCreatesContextFromRegionInfo(): void
    {
        $region = new RegionInfo(
            regionId: 42,
            leaderPeerId: 7,
            leaderStoreId: 3,
            epochConfVer: 1,
            epochVersion: 10,
        );

        $ctx = RegionContext::fromRegionInfo($region);

        $this->assertInstanceOf(Context::class, $ctx);
        $this->assertSame(42, $ctx->getRegionId());
        $this->assertNotNull($ctx->getRegionEpoch());
        $this->assertSame(1, $ctx->getRegionEpoch()->getConfVer());
        $this->assertSame(10, $ctx->getRegionEpoch()->getVersion());
        $this->assertNotNull($ctx->getPeer());
        $this->assertSame(7, $ctx->getPeer()->getId());
        $this->assertSame(3, $ctx->getPeer()->getStoreId());
    }
}
