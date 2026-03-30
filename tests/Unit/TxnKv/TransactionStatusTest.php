<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Tests\Unit\TxnKv;

use CrazyGoat\TiKV\Client\TxnKv\TransactionStatus;
use PHPUnit\Framework\TestCase;

class TransactionStatusTest extends TestCase
{
    public function testEnumCases(): void
    {
        $this->assertSame('Active', TransactionStatus::Active->name);
        $this->assertSame('Committed', TransactionStatus::Committed->name);
        $this->assertSame('RolledBack', TransactionStatus::RolledBack->name);
    }
}
