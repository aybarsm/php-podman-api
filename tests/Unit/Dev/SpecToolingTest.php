<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Dev\Coverage;
use Aybarsm\Podman\Api\Dev\Generator\OperationEnumGenerator;
use Aybarsm\Podman\Api\Dev\Generator\Scaffolder;
use Aybarsm\Podman\Api\Dev\Spec\DefinitionResolver;
use Aybarsm\Podman\Api\Dev\Spec\SinceCalculator;
use Aybarsm\Podman\Api\Dev\Spec\SpecDiff;
use Aybarsm\Podman\Api\Dev\Spec\SpecOperation;
use Aybarsm\Podman\Api\Dev\Spec\SpecRepository;

beforeEach(function (): void {
    $this->specs = new SpecRepository(dirname(__DIR__, 2).'/Fixtures/specs');
});

it('discovers spec versions in ascending order', function (): void {
    expect($this->specs->versions())->toBe(['1.0', '1.1'])
        ->and($this->specs->latestVersion())->toBe('1.1');
});

it('separates Libpod operations from compat ones', function (): void {
    $ops = $this->specs->get('1.0')->libpodOperations();

    expect(array_keys($ops))->toBe(['WidgetDeleteLibpod', 'WidgetInspectLibpod', 'WidgetListLibpod'])
        ->and($ops['WidgetListLibpod']->caseName())->toBe('WidgetList');
});

it('diffs operations, parameters and definitions between versions', function (): void {
    $diff = SpecDiff::compare($this->specs->get('1.0'), $this->specs->get('1.1'));

    expect(array_map(static fn (SpecOperation $op): string => $op->id, $diff->addedOperations))->toBe(['WidgetExistsLibpod'])
        ->and(array_map(static fn (SpecOperation $op): string => $op->id, $diff->removedOperations))->toBe(['WidgetDeleteLibpod'])
        ->and($diff->changedOperations['WidgetListLibpod'])->toContain('+ param query:filters (query:string)')
        ->and($diff->addedDefinitions)->toBe(['WidgetSummary'])
        ->and($diff->removedDefinitions)->toBe(['OldWidgetSummary'])
        ->and($diff->changedDefinitions['WidgetData'])->toBe([
            '+ Created: string(date-time)',
            '- Labels: map<string>',
            '~ Size: integer → string',
            '~ required: [Id] → []',
        ])
        ->and($diff->render())->toContain('## Added operations (1)');
});

it('ignores compat operations unless asked', function (): void {
    $libpod = SpecDiff::compare($this->specs->get('1.0'), $this->specs->get('1.1'));
    $all = SpecDiff::compare($this->specs->get('1.0'), $this->specs->get('1.1'), libpodOnly: false);

    expect($libpod->isEmpty())->toBeFalse()
        ->and(array_map(static fn (SpecOperation $op): string => $op->id, $all->addedOperations))->toBe(['WidgetExistsLibpod']);
});

it('computes the first version of every operation in the newest spec', function (): void {
    expect(SinceCalculator::compute($this->specs->all()))->toBe([
        'WidgetExistsLibpod' => '1.1',
        'WidgetInspectLibpod' => '1.0',
        'WidgetListLibpod' => '1.0',
    ]);
});

it('computes query parameters introduced after their operation', function (): void {
    expect(SinceCalculator::queryParameters($this->specs->all()))->toBe([
        'WidgetListLibpod' => ['filters' => '1.1'],
    ]);
});

it('falls back to an older spec for degraded (shapeless) definitions, following renames', function (): void {
    $resolver = new DefinitionResolver($this->specs->all());
    $latest = $this->specs->latest();

    expect($resolver->degraded($latest))->toBe(['WidgetSummary']);

    $resolved = $resolver->resolve($latest, 'WidgetSummary');

    expect($resolved)->not->toBeNull()
        ->and($resolved['version'] ?? null)->toBe('1.0')
        ->and($resolved['name'] ?? null)->toBe('OldWidgetSummary');
});

it('scaffolds a readonly DTO with typed hydration', function (): void {
    $code = (new Scaffolder($this->specs->get('1.0'), new DefinitionResolver($this->specs->all())))
        ->dto('WidgetData', 'Widget');

    expect($code)
        ->toContain('final readonly class WidgetData implements Hydratable')
        ->toContain('public string $id,')
        ->toContain("id: Data::string(\$data, 'Id'),")
        ->toContain("labels: Data::stringMap(\$data, 'Labels'),")
        ->toContain('#[Override]');
});

it('names resource methods after Podman CLI verbs', function (string $case, string $expected): void {
    expect(Scaffolder::methodName($case))->toBe($expected);
})->with([
    ['ContainerList', 'list'],
    ['ContainerDelete', 'remove'],
    ['ImageDeleteAll', 'removeMany'],
    ['PodStatsAll', 'statsAll'],
]);

it('converts JSON keys to camelCase property names', function (string $key, string $expected): void {
    expect(Scaffolder::propertyName($key))->toBe($expected);
})->with([
    ['Id', 'id'],
    ['ImageID', 'imageId'],
    ['IPAddress', 'ipAddress'],
    ['cpu_percent', 'cpuPercent'],
    ['HostConfig', 'hostConfig'],
    ['CIDFile', 'cidFile'],
]);

it('reports implemented, deferred and missing operations', function (): void {
    $dir = sys_get_temp_dir().'/podman-api-coverage-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/Widgets.php', '<?php $x = Operation::WidgetList;');

    $coverage = Coverage::compute($this->specs->latest(), $dir, ['WidgetExists' => 'streaming']);

    expect(array_keys($coverage->implemented))->toBe(['WidgetList'])
        ->and(array_keys($coverage->deferred))->toBe(['WidgetExists'])
        ->and(array_keys($coverage->missing))->toBe(['WidgetInspect'])
        ->and($coverage->render())->toContain('1/3 implemented');

    unlink($dir.'/Widgets.php');
    rmdir($dir);
});

it('keeps src/Internal/Operation.php in sync with the newest spec', function (): void {
    $root = dirname(__DIR__, 3);
    $specs = new SpecRepository($root.'/resources/podman');

    $generated = OperationEnumGenerator::generate(
        $specs->latest(),
        SinceCalculator::compute($specs->all()),
        $specs->versions()[0],
        SinceCalculator::queryParameters($specs->all()),
    );

    expect($generated)->toBe(file_get_contents($root.'/'.OperationEnumGenerator::TARGET));
});
