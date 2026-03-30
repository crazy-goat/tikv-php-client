<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Retry;

enum BackoffType
{
    case None;
    case ServerBusy;
    case StaleCmd;
    case RegionMiss;
    case TiKvRpc;
    case NotLeader;

    public function baseMs(): int
    {
        return match ($this) {
            self::None => 0,
            self::ServerBusy => 2000,
            self::StaleCmd => 2,
            self::RegionMiss => 2,
            self::TiKvRpc => 100,
            self::NotLeader => 2,
        };
    }

    public function capMs(): int
    {
        return match ($this) {
            self::None => 0,
            self::ServerBusy => 10000,
            self::StaleCmd => 1000,
            self::RegionMiss => 500,
            self::TiKvRpc => 2000,
            self::NotLeader => 500,
        };
    }

    public function equalJitter(): bool
    {
        return match ($this) {
            self::ServerBusy, self::TiKvRpc => true,
            default => false,
        };
    }

    public function sleepMs(int $attempt): int
    {
        if ($this === self::None) {
            return 0;
        }

        return Backoff::exponential($this->baseMs(), $this->capMs(), $attempt, $this->equalJitter());
    }
}
