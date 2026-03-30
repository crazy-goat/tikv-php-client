<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\RawKv\Dto;

use CrazyGoat\TiKV\Client\RawKv\Dto\KeyValue;
use PHPUnit\Framework\TestCase;

class KeyValueTest extends TestCase
{
    public function testConstructionWithValue(): void
    {
        $kv = new KeyValue(key: 'my-key', value: 'my-value');

        $this->assertSame('my-key', $kv->key);
        $this->assertSame('my-value', $kv->value);
    }

    public function testConstructionKeyOnly(): void
    {
        $kv = new KeyValue(key: 'my-key', value: null);

        $this->assertSame('my-key', $kv->key);
        $this->assertNull($kv->value);
    }

    public function testIsReadonly(): void
    {
        $ref = new \ReflectionClass(KeyValue::class);
        $this->assertTrue($ref->isReadonly());
    }
}
