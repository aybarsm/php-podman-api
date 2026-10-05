<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Internal\Support;

use Aybarsm\Podman\Api\Enums\LogStream;
use Aybarsm\Podman\Api\Exceptions\HydrationException;

/**
 * Decodes the multiplexed stream format documented on ContainerAttachLibpod: frames of an 8-byte header
 * [STREAM_TYPE, 0, 0, 0, SIZE1..SIZE4 (uint32, big endian)] followed by SIZE bytes of payload.
 *
 * @internal
 */
final class MultiplexedStream
{
    private const int HEADER_LENGTH = 8;

    /**
     * @return list<array{0: LogStream, 1: string}> [stream, payload] per frame
     */
    public static function frames(string $body): array
    {
        $frames = [];
        $offset = 0;
        $length = strlen($body);

        while ($offset < $length) {
            if ($length - $offset < self::HEADER_LENGTH) {
                throw HydrationException::unexpectedType('multiplexed stream', 'an 8-byte frame header', substr($body, $offset));
            }
            /** @var array{type: int, size: int} $header */
            $header = unpack('Ctype/x3/Nsize', $body, $offset);
            $offset += self::HEADER_LENGTH;
            if ($length - $offset < $header['size']) {
                throw HydrationException::unexpectedType('multiplexed stream', "a {$header['size']}-byte payload", substr($body, $offset));
            }
            $frames[] = [LogStream::fromFrameType($header['type']), substr($body, $offset, $header['size'])];
            $offset += $header['size'];
        }

        return $frames;
    }
}
