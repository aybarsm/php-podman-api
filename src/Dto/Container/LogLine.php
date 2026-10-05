<?php

declare(strict_types=1);

namespace Aybarsm\Podman\Api\Dto\Container;

use Aybarsm\Podman\Api\Enums\LogStream;
use Aybarsm\Podman\Api\Internal\Support\Data;
use DateTimeImmutable;

/**
 * One line of container output, decoded from the multiplexed log stream.
 *
 * @see resources/podman/swagger-v5.8.yaml#/paths/~1libpod~1containers~1{name}~1attach/post (stream format)
 */
final readonly class LogLine
{
    public function __construct(
        public LogStream $stream,
        /** The line without its trailing newline */
        public string $text,
        /** Only when requested with ContainerLogsOptions::$timestamps */
        public ?DateTimeImmutable $timestamp = null,
    ) {}

    /**
     * Splits a frame payload into lines; with $timestamps, strips and parses the leading RFC 3339 timestamp.
     *
     * @return list<self>
     */
    public static function fromFrame(LogStream $stream, string $payload, bool $timestamps): array
    {
        $lines = [];
        foreach (explode("\n", rtrim($payload, "\n")) as $line) {
            $line = rtrim($line, "\r");
            $timestamp = null;
            if ($timestamps && preg_match('/^(\S+) (.*)$/s', $line, $m) === 1) {
                $timestamp = Data::dateTimeOrNull(['t' => $m[1]], 't');
                $line = $m[2];
            }
            $lines[] = new self($stream, $line, $timestamp);
        }

        return $lines;
    }
}
