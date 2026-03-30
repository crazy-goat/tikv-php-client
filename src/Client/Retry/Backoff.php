<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Retry;

final class Backoff
{
    public static function exponential(int $baseMs, int $capMs, int $attempt, bool $equalJitter = false): int
    {
        $expo = (int) min($capMs, $baseMs * (2 ** $attempt));

        if (!$equalJitter) {
            return $expo;
        }

        $half = intdiv($expo, 2);
        return $half + random_int(0, $half);
    }
}
