<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Exceptions;

use Throwable;

/**
 * Podman answered with an error status. Carries the spec's ErrorModel fields (message, cause).
 */
abstract class RequestException extends PodmanApiException
{
    final public function __construct(
        public readonly string $operationId,
        public readonly int $statusCode,
        public readonly ?string $apiMessage = null,
        public readonly ?string $apiCause = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('%s failed with HTTP %d: %s', $operationId, $statusCode, $apiMessage ?? 'no error message'),
            $statusCode,
            $previous,
        );
    }

    /**
     * Maps a status code to the matching exception class.
     */
    public static function forStatus(string $operationId, int $statusCode, ?string $message = null, ?string $cause = null): self
    {
        return match (true) {
            $statusCode === 400 => new BadRequestException($operationId, $statusCode, $message, $cause),
            $statusCode === 401 => new UnauthorizedException($operationId, $statusCode, $message, $cause),
            $statusCode === 403 => new ForbiddenException($operationId, $statusCode, $message, $cause),
            $statusCode === 404 => new NotFoundException($operationId, $statusCode, $message, $cause),
            $statusCode === 409 => new ConflictException($operationId, $statusCode, $message, $cause),
            $statusCode >= 500 => new ServerException($operationId, $statusCode, $message, $cause),
            default => new UnexpectedStatusException($operationId, $statusCode, $message, $cause),
        };
    }
}
