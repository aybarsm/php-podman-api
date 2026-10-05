<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Secret\SecretCreateOptions;
use Aybarsm\Podman\Api\Dto\Shared\Filters;

beforeEach(function (): void {
    $this->podman = integrationClient();
    $this->name = 'podman-api-it-'.bin2hex(random_bytes(4));
});

afterEach(function (): void {
    if (isset($this->podman) && $this->podman->secrets()->exists($this->name)) {
        $this->podman->secrets()->remove($this->name);
    }
});

it('round-trips a secret', function (): void {
    $secrets = $this->podman->secrets();

    $id = $secrets->create($this->name, 's3cret-value', new SecretCreateOptions(labels: ['podman-api' => 'integration']));

    expect($secrets->exists($this->name))->toBeTrue();

    $inspect = $secrets->inspect($this->name, showSecret: true);
    expect($inspect->id)->toBe($id)
        ->and($inspect->name())->toBe($this->name)
        ->and($inspect->secretData)->toBe('s3cret-value')
        ->and($inspect->spec?->labels)->toMatchArray(['podman-api' => 'integration']);

    $listed = $secrets->list(Filters::of(['id' => $id]));
    expect(array_map(static fn ($s): string => $s->id, $listed))->toBe([$id]);

    $secrets->remove($id);
    expect($secrets->exists($this->name))->toBeFalse();
});
