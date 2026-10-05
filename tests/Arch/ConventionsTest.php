<?php

declare(strict_types=1);

use Aybarsm\Podman\Api\Contracts\Hydratable;
use Aybarsm\Podman\Api\Internal\Transport\Result;
use Aybarsm\Podman\Api\Internal\Transport\Transport;
use Aybarsm\Podman\Api\Resources\AbstractResource;
use Psr\Http\Message\ResponseInterface;

/**
 * @return list<class-string>
 */
function packageClasses(): array
{
    $root = dirname(__DIR__, 2).'/src';
    $classes = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $relative = substr($file->getPathname(), strlen($root) + 1, -4);
            $class = 'Aybarsm\\Podman\\Api\\'.str_replace('/', '\\', $relative);
            if (class_exists($class) || interface_exists($class) || enum_exists($class)) {
                $classes[] = $class;
            }
        }
    }
    sort($classes);

    return $classes;
}

/**
 * @return list<string>
 */
function phpSourceFiles(): array
{
    $root = dirname(__DIR__, 2);
    $files = [$root.'/bin/spec', $root.'/dev-tools/deferred-operations.php'];
    foreach (['src', 'dev-tools/src', 'tests'] as $dir) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

test('every PHP file (src, dev-tools, tests, bin) declares strict_types', function (): void {
    $missing = array_filter(
        phpSourceFiles(),
        static fn (string $file): bool => ! str_contains((string) file_get_contents($file), 'declare(strict_types=1);'),
    );

    expect($missing)->toBe([]);
});

test('every class constant is typed', function (): void {
    $untyped = [];
    foreach (packageClasses() as $class) {
        foreach ((new ReflectionClass($class))->getReflectionConstants() as $constant) {
            if ($constant->getDeclaringClass()->getName() !== $class || $constant->isEnumCase()) {
                continue;
            }
            if (! $constant->hasType()) {
                $untyped[] = "{$class}::{$constant->getName()}";
            }
        }
    }

    expect($untyped)->toBe([]);
});

test('resources never expose PSR responses or transport internals', function (): void {
    $forbidden = [ResponseInterface::class, Result::class, Transport::class];
    $violations = [];

    foreach (packageClasses() as $class) {
        if (! is_subclass_of($class, AbstractResource::class)) {
            continue;
        }
        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor()) {
                continue;
            }
            $type = $method->getReturnType();
            $names = match (true) {
                $type instanceof ReflectionNamedType => [$type->getName()],
                $type instanceof ReflectionUnionType, $type instanceof ReflectionIntersectionType => array_map(
                    static fn (ReflectionType $t): string => $t instanceof ReflectionNamedType ? $t->getName() : (string) $t,
                    $type->getTypes(),
                ),
                default => ['<untyped>'],
            };
            foreach ($names as $name) {
                if (in_array($name, $forbidden, true) || $name === '<untyped>') {
                    $violations[] = "{$class}::{$method->getName()}(): {$name}";
                }
            }
        }
    }

    expect($violations)->toBe([]);
});

test('every Hydratable DTO declares fromArray with #[Override]', function (): void {
    $missing = [];
    foreach (packageClasses() as $class) {
        $ref = new ReflectionClass($class);
        if ($ref->isInterface() || ! $ref->implementsInterface(Hydratable::class)) {
            continue;
        }
        if ($ref->getMethod('fromArray')->getAttributes(Override::class) === []) {
            $missing[] = $class;
        }
    }

    expect($missing)->toBe([]);
});
