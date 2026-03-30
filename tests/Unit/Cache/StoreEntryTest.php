<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\Cache;

use CrazyGoat\Proto\Metapb\Store;
use CrazyGoat\TiKV\Client\Cache\StoreEntry;
use PHPUnit\Framework\TestCase;

class StoreEntryTest extends TestCase
{
    public function testConstruction(): void
    {
        $store = new Store();
        $store->setId(1);
        $store->setAddress("127.0.0.1:20160");

        $entry = new StoreEntry($store, 1000);

        $this->assertSame($store, $entry->store);
        $this->assertSame(1000, $entry->expiresAt);
    }
}
