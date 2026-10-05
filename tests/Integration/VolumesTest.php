<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Shared\Filters;
use Aybarsm\Podman\Api\Dto\Volume\VolumeCreateRequest;
use Aybarsm\Podman\Api\Enums\ApiVersion;

beforeEach(function (): void {
    $this->podman = integrationClient();
    $this->name = 'podman-api-it-'.bin2hex(random_bytes(4));
});

afterEach(function (): void {
    if (isset($this->podman) && $this->podman->volumes()->exists($this->name)) {
        $this->podman->volumes()->remove($this->name, force: true);
    }
});

it('round-trips a volume', function (): void {
    $volumes = $this->podman->volumes();

    $created = $volumes->create(new VolumeCreateRequest(name: $this->name, labels: ['podman-api' => 'integration']));

    expect($created->name)->toBe($this->name)
        ->and($volumes->exists($this->name))->toBeTrue();

    $inspect = $volumes->inspect($this->name);
    expect($inspect->labels)->toMatchArray(['podman-api' => 'integration'])
        ->and($inspect->mountpoint)->not->toBeEmpty();

    $listed = $volumes->list(Filters::of(['name' => $this->name]));
    expect(array_map(static fn ($v): string => $v->name, $listed))->toBe([$this->name]);

    $volumes->remove($this->name);
    expect($volumes->exists($this->name))->toBeFalse();
});

it('exports and re-imports a volume', function (): void {
    if (! $this->podman->config()->apiVersion->isAtLeast(ApiVersion::V5_6)) {
        $this->markTestSkipped('Volume export/import needs Podman 5.6+.');
    }
    $volumes = $this->podman->volumes();
    $volumes->create(new VolumeCreateRequest(name: $this->name));

    $tar = (string) $volumes->export($this->name);
    $volumes->import($this->name, $tar);

    expect($volumes->exists($this->name))->toBeTrue();
});
