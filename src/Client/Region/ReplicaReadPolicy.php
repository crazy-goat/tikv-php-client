<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Region;

/**
 * Read-preference policy for replica reads and stale reads (issue #421),
 * the equivalent of client-go's SetReplicaRead() + SetMatchStoreLabels()
 * + SetIsStalenessReadOnly() and client-java's ReplicaRead configuration.
 *
 * matchStoreLabels restricts replica selection to stores carrying all of
 * the given labels (e.g. ['zone' => 'az1'] to keep reads in the client's
 * availability zone, mirroring client-go's SetMatchStoreLabels). When no
 * non-leader replica satisfies the labels, selection falls back to the
 * leader so a read never fails because of a label policy alone.
 *
 * staleRead enables TiKV's stale-read path (Context.stale_read = true):
 * any replica whose safe_ts covers the read timestamp may serve the read
 * locally. Applies to leader-targeted reads too (leader stale reads).
 *
 * Writes are never affected by this policy — they always target the leader.
 *
 * @phpstan-type StoreLabels array<string, string>
 */
final readonly class ReplicaReadPolicy
{
    /**
     * @param array<string, string> $matchStoreLabels label key => value pairs
     *     a candidate store must carry (all of them) to be selectable
     */
    public function __construct(
        public ReplicaReadMode $mode = ReplicaReadMode::Leader,
        public array $matchStoreLabels = [],
        public bool $staleRead = false,
    ) {
    }
}
