<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dto\Kube\KubeGenerateSystemdOptions;
use Aybarsm\Podman\Api\Dto\Pod\PodCreateSpec;

beforeEach(function (): void {
    $this->podman = integrationClient();
    $this->name = 'podman-api-it-kube-'.bin2hex(random_bytes(4));
    $this->yaml = <<<YAML
        apiVersion: v1
        kind: Pod
        metadata:
          name: {$this->name}
        spec:
          containers:
            - name: app
              image: quay.io/libpod/alpine:latest
              command: ["sleep", "300"]
        YAML;
});

afterEach(function (): void {
    if (isset($this->podman) && $this->podman->pods()->exists($this->name)) {
        $this->podman->pods()->remove($this->name, force: true);
    }
});

it('generates systemd units for a pod', function (): void {
    $this->podman->pods()->create(new PodCreateSpec(name: $this->name));

    $units = $this->podman->kube()->generateSystemd($this->name, new KubeGenerateSystemdOptions(useName: true));

    expect($units)->not->toBeEmpty()
        ->and(implode("\n", $units))->toContain('[Unit]');
});

it('plays and generates Kubernetes YAML', function (): void {
    ensureTestImage($this->podman);

    $kube = $this->podman->kube();

    $played = $kube->play($this->yaml);
    expect($played->pods)->toHaveCount(1)
        ->and($this->podman->pods()->exists($this->name))->toBeTrue();

    expect($kube->generate([$this->name]))->toContain('name: '.$this->name);

});
