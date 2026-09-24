<?php

test('installed modules have parseable manifests and root PSR-4 mappings', function () {
    $violations = [];

    foreach (glob(dirname(__DIR__, 2).'/Modules/*', GLOB_ONLYDIR) ?: [] as $directory) {
        $module = basename($directory);
        $manifest = json_decode(file_get_contents($directory.'/module.json'), true);
        $composer = json_decode(file_get_contents($directory.'/composer.json'), true);

        if (! is_array($manifest)) {
            $violations[] = "{$module}/module.json -> invalid JSON";
        }

        if (($composer['autoload']['psr-4']["Modules\\{$module}\\"] ?? null) !== './') {
            $violations[] = "{$module}/composer.json -> missing PSR-4 mapping to ./";
        }
    }

    expect($violations)->toBe([]);
});
