<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Exception;

final class GrpcException extends TiKvException
{
    public function __construct(
        string $details,
        public readonly int $grpcStatusCode,
    ) {
        parent::__construct("gRPC error: {$details}", $grpcStatusCode);
    }
}
