<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Enums\ApiVersion;

beforeEach(function (): void {
    $this->podman = integrationClient();
});

it('pings, reads info and version from a live server', function (): void {
    $ping = $this->podman->system()->ping();
    $version = $this->podman->system()->version();
    $info = $this->podman->system()->info();

    expect($ping->isPodman())->toBeTrue()
        ->and(ApiVersion::fromServerVersion($version->version))->not->toBeNull()
        ->and($info->version->version)->toBe($version->version)
        ->and($info->store->graphRoot)->not->toBeEmpty();
});

it('reads disk usage', function (): void {
    expect($this->podman->system()->diskUsage()->imagesSize)->toBeInt();
});
