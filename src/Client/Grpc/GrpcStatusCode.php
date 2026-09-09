<?php

declare(strict_types=1);

namespace CrazyGoat\TiKV\Client\Grpc;

/**
 * Typed wrapper for the gRPC status codes carried on {@see \CrazyGoat\TiKV\Client\Exception\GrpcException}.
 *
 * Mirrors the codes defined by the grpc extension (\Grpc\STATUS_*), declared
 * locally so classification does not require the extension to be loaded.
 * Values follow the canonical gRPC status code numbering.
 */
enum GrpcStatusCode: int
{
    case OK = 0;
    case Cancelled = 1;
    case Unknown = 2;
    case InvalidArgument = 3;
    case DeadlineExceeded = 4;
    case NotFound = 5;
    case AlreadyExists = 6;
    case PermissionDenied = 7;
    case ResourceExhausted = 8;
    case FailedPrecondition = 9;
    case Aborted = 10;
    case OutOfRange = 11;
    case Unimplemented = 12;
    case Internal = 13;
    case Unavailable = 14;
    case DataLoss = 15;
    case Unauthenticated = 16;
}
