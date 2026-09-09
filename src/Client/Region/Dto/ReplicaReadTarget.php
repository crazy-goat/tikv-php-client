<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Region\Dto;

use CrazyGoat\Proto\Kvrpcpb\Context;

/**
 * Result of replica-aware peer selection (issue #421): the request Context
 * to place on the wire (leader or replica peer, with replica_read /
 * stale_read flags set per policy) and the store id whose address the
 * request must be sent to. Callers resolve the gRPC target with
 * RegionResolver::resolveStoreAddress($target->storeId).
 */
final readonly class ReplicaReadTarget
{
    public function __construct(
        public Context $context,
        public int $storeId,
    ) {
    }
}
