<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\RawKv;

/**
 * Result of a Compare-And-Swap (CAS) operation.
 *
 * Contains the outcome of the atomic comparison and the previous value
 * that was stored in TiKV at the time of the operation, regardless of
 * whether the swap succeeded.
 *
 * @see RawKvClient::compareAndSwap()
 */
final readonly class CasResult
{
    /**
     * @param bool $swapped Whether the swap succeeded (comparison matched)
     * @param string|null $previousValue The value that was in TiKV before the operation,
     *                                   or null if the key did not exist
     */
    public function __construct(
        public bool $swapped,
        public ?string $previousValue,
    ) {
    }
}
