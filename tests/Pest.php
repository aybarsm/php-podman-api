<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Enums\ApiVersion;
use Aybarsm\Podman\Api\PodmanClient;
use Aybarsm\Podman\Api\Tests\Support\MockPodman;

/*
 * Unit tests run against MockPodman (Guzzle MockHandler as the PSR-18 client).
 * Integration tests need a live socket: PODMAN_SOCKET=/path/to/podman.sock composer test:integration
 */

function mockPodman(): MockPodman
{
    return new MockPodman();
}

function podmanSocket(): ?string
{
    $socket = getenv('PODMAN_SOCKET');

    return is_string($socket) && $socket !== '' ? $socket : null;
}

/** Small public image used by integration tests that need one. */
const PODMAN_TEST_IMAGE = 'quay.io/libpod/alpine:latest';

/**
 * A client for the live socket, targeting the server's own API version (so version-gated operations are usable).
 * Skips the current test when PODMAN_SOCKET is not set.
 */
function integrationClient(): PodmanClient
{
    $socket = podmanSocket() ?? test()->markTestSkipped('Set PODMAN_SOCKET to run integration tests.');
    $probe = PodmanClient::unixSocket($socket, ApiVersion::minimum());
    $version = ApiVersion::fromServerVersion($probe->system()->version()->version)
        ?? ApiVersion::minimum();

    return PodmanClient::unixSocket($socket, $version);
}

/**
 * Makes sure PODMAN_TEST_IMAGE is present, pulling it when PODMAN_PULL=1; skips the test otherwise.
 */
function ensureTestImage(PodmanClient $podman): string
{
    if (! $podman->images()->exists(PODMAN_TEST_IMAGE)) {
        if (getenv('PODMAN_PULL') === false) {
            test()->markTestSkipped(PODMAN_TEST_IMAGE.' is not present locally; set PODMAN_PULL=1 to allow pulling it.');
        }
        $podman->images()->pull(PODMAN_TEST_IMAGE);
    }

    return PODMAN_TEST_IMAGE;
}
