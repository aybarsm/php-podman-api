<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Image\ImageRemoveManyOptions;
use Aybarsm\Podman\Api\Dto\Shared\Filters;

beforeEach(function (): void {
    $this->podman = integrationClient();
    $this->image = PODMAN_TEST_IMAGE;
    $this->repo = 'localhost/podman-api-it-'.bin2hex(random_bytes(4));
});

afterEach(function (): void {
    if (isset($this->podman)) {
        $this->podman->images()->removeMany(new ImageRemoveManyOptions(images: [$this->repo.':it'], ignore: true));
    }
});

it('pulls, inspects, tags, saves and untags an image', function (): void {
    ensureTestImage($this->podman);

    $images = $this->podman->images();

    $pulled = $images->pull($this->image);
    expect($pulled->failed())->toBeFalse()
        ->and($pulled->images)->not->toBeEmpty()
        ->and($images->exists($this->image))->toBeTrue();

    $inspect = $images->inspect($this->image);
    expect($inspect->id)->toBe($pulled->images[0])
        ->and($inspect->repoTags)->toContain($this->image);

    $listed = $images->list(Filters::of(['reference' => $this->image]));
    expect(array_map(static fn ($i): string => $i->id, $listed))->toContain($inspect->id);

    expect($images->history($this->image))->not->toBeEmpty()
        ->and($images->tree($this->image))->toContain('Image Layers');

    $images->tag($this->image, $this->repo, 'it');
    expect($images->exists($this->repo.':it'))->toBeTrue();

    $tar = $images->save($this->repo.':it');
    expect($tar->read(512))->not->toBe('');
    $tar->close();

    $images->untag($this->repo.':it', $this->repo, 'it');
    expect($images->exists($this->repo.':it'))->toBeFalse();
});

it('reports missing images', function (): void {
    expect($this->podman->images()->exists('localhost/podman-api-it-does-not-exist:none'))->toBeFalse();
});
