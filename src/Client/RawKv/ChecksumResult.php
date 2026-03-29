<?php
declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\RawKv;

/**
 * Result of a Checksum operation over a key range.
 *
 * The checksum is computed server-side using CRC64-XOR over all key-value pairs
 * in the specified range. For multi-region ranges, individual region checksums
 * are XOR-merged (CRC64-XOR is associative and commutative).
 *
 * @see RawKvClient::checksum()
 */
final readonly class ChecksumResult
{
    /**
     * @param int $checksum CRC64-XOR checksum of all key-value pairs in the range
     * @param int $totalKvs Total number of key-value pairs in the range
     * @param int $totalBytes Total bytes (keys + values) in the range
     */
    public function __construct(
        public int $checksum,
        public int $totalKvs,
        public int $totalBytes,
    ) {
    }
}
