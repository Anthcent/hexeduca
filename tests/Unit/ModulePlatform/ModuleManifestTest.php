<?php

// R1.1: every Modules/*/module.json carries the manifest v1 fields. Plain
// PHPUnit TestCase (no Laravel boot needed) — this reads module.json files
// directly from disk.

function moduleManifestFiles(): array
{
    return glob(dirname(__DIR__, 3).'/Modules/*/module.json');
}

function readModuleManifest(string $path): array
{
    return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}

test('every module manifest declares core, maturity, dependencies, and permissions', function () {
    $files = moduleManifestFiles();

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $manifest = readModuleManifest($file);

        expect($manifest)->toHaveKeys(['core', 'maturity', 'dependencies', 'permissions'], "Manifest {$file} is missing a v1 field.")
            ->and($manifest['core'])->toBeBool("core in {$file} must be a bool.")
            ->and($manifest['maturity'])->toBeIn(['skeleton', 'mature'], "maturity in {$file} must be skeleton or mature.")
            ->and($manifest['dependencies'])->toBeArray("dependencies in {$file} must be an array.")
            ->and($manifest['permissions'])->toBeArray("permissions in {$file} must be an array.");
    }
});

test('Users and Admin are the only core modules', function () {
    $core = [];

    foreach (moduleManifestFiles() as $file) {
        $manifest = readModuleManifest($file);

        if ($manifest['core'] === true) {
            $core[] = $manifest['name'];
        }
    }

    sort($core);

    expect($core)->toBe(['Admin', 'Users']);
});

test('Schedule, Files, and Notifications are skeleton maturity', function () {
    $skeletons = [];

    foreach (moduleManifestFiles() as $file) {
        $manifest = readModuleManifest($file);

        if ($manifest['maturity'] === 'skeleton') {
            $skeletons[] = $manifest['name'];
        }
    }

    sort($skeletons);

    expect($skeletons)->toBe(['Files', 'Notifications', 'Schedule']);
});
