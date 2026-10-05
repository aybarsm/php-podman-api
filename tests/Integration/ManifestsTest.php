<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Manifest\ManifestModifyRequest;
use Aybarsm\Podman\Api\Enums\ManifestOperation;

beforeEach(function (): void {
    $this->podman = integrationClient();
    $this->image = PODMAN_TEST_IMAGE;
    $this->list = 'localhost/podman-api-it-list-'.bin2hex(random_bytes(4));
});

afterEach(function (): void {
    if (isset($this->podman) && $this->podman->manifests()->exists($this->list)) {
        $this->podman->manifests()->remove($this->list, ignore: true);
    }
});

it('creates, inspects, annotates and removes a manifest list', function (): void {
    ensureTestImage($this->podman);

    $manifests = $this->podman->manifests();

    $id = $manifests->create($this->list, [$this->image]);
    expect($id)->not->toBe('')
        ->and($manifests->exists($this->list))->toBeTrue();

    $list = $manifests->inspect($this->list);
    expect($list->manifests)->not->toBeEmpty();

    $digest = $list->manifests[0]->digest;
    $report = $manifests->modify($this->list, new ManifestModifyRequest(
        operation: ManifestOperation::Annotate,
        images: [$digest],
        annotations: ['podman-api' => 'integration'],
    ));
    expect($report->hasErrors())->toBeFalse();

    $removed = $manifests->remove($this->list);
    expect($removed->deleted)->not->toBeEmpty()
        ->and($manifests->exists($this->list))->toBeFalse();
});

it('reports missing manifest lists', function (): void {
    expect($this->podman->manifests()->exists('localhost/podman-api-it-no-such-list'))->toBeFalse();
});
