<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Region;

/**
 * Replica read preference (issue #421), mirroring client-go's
 * kv.ReplicaReadType: which peer of a region serves a read request.
 *
 * - Leader (default): current behaviour, every read goes to the region leader.
 * - Follower: a follower replica serves the read (Context.replica_read = true).
 * - Mixed: any replica (leader or follower) may serve the read.
 * - PreferLeader: the leader is used when eligible, a follower otherwise.
 *
 * Writes always target the leader regardless of the configured mode.
 */
enum ReplicaReadMode: string
{
    case Leader = 'leader';
    case Follower = 'follower';
    case Mixed = 'mixed';
    case PreferLeader = 'prefer_leader';
}
