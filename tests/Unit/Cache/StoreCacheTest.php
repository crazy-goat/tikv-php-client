<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Cache;

use CrazyGoat\Proto\Metapb\Store;
use CrazyGoat\TiKV\Client\Cache\StoreCache;
use PHPUnit\Framework\TestCase;

class StoreCacheTest extends TestCase
{
    public function testGetCacheMiss(): void
    {
        $cache = new StoreCache();
        $this->assertNull($cache->get(1));
    }

    public function testPutAndGet(): void
    {
        $cache = new StoreCache();

        $store = new Store();
        $store->setId(1);
        $store->setAddress("127.0.0.1:20160");

        $cache->put($store);

        $cached = $cache->get(1);
        $this->assertNotNull($cached);
        $this->assertSame("127.0.0.1:20160", $cached->getAddress());
    }

    public function testInvalidate(): void
    {
        $cache = new StoreCache();

        $store = new Store();
        $store->setId(1);
        $store->setAddress("127.0.0.1:20160");

        $cache->put($store);
        $this->assertNotNull($cache->get(1));

        $cache->invalidate(1);
        $this->assertNull($cache->get(1));
    }

    public function testClear(): void
    {
        $cache = new StoreCache();

        $store1 = new Store();
        $store1->setId(1);
        $store1->setAddress("127.0.0.1:20160");

        $store2 = new Store();
        $store2->setId(2);
        $store2->setAddress("127.0.0.1:20161");

        $cache->put($store1);
        $cache->put($store2);

        $cache->clear();

        $this->assertNull($cache->get(1));
        $this->assertNull($cache->get(2));
    }

    public function testTtlExpiration(): void
    {
        $cache = new class extends StoreCache {
            protected function now(): int {
                return 1000;
            }
        };

        $store = new Store();
        $store->setId(1);
        $store->setAddress("127.0.0.1:20160");

        $cache->put($store);
        $this->assertNotNull($cache->get(1));

        // Simulate time passing beyond TTL (600s default + up to 60s jitter = 1660)
        $cache = new class(600, 0) extends StoreCache {
            protected function now(): int {
                return 2000;
            }
        };

        $this->assertNull($cache->get(1));
    }

    public function testOverwriteExisting(): void
    {
        $cache = new StoreCache();

        $store1 = new Store();
        $store1->setId(1);
        $store1->setAddress("127.0.0.1:20160");

        $store2 = new Store();
        $store2->setId(1);
        $store2->setAddress("127.0.0.1:20161");

        $cache->put($store1);
        $cache->put($store2);

        $cached = $cache->get(1);
        $this->assertNotNull($cached);
        $this->assertSame("127.0.0.1:20161", $cached->getAddress());
    }
}
