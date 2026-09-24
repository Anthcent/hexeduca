<?php

use Symfony\Component\Yaml\Yaml;

test('CI structurally fails closed and separates SQLite from production services', function () {
    $workflow = dirname(__DIR__, 2).'/.github/workflows/ci.yml';

    expect($workflow)->toBeFile();

    $config = Yaml::parseFile($workflow);
    $quality = $config['jobs']['quality'];
    $production = $config['jobs']['production-like'];
    $allSteps = [...$quality['steps'], ...$production['steps']];
    $actionReferences = array_values(array_filter(array_column($allSteps, 'uses')));
    $jobTimeouts = array_column($config['jobs'], 'timeout-minutes');
    $checkoutSteps = array_values(array_filter($allSteps, fn (array $step): bool => str_starts_with($step['uses'] ?? '', 'actions/checkout@')));
    $pestCommands = array_values(array_filter(array_column($allSteps, 'run'), fn (string $run): bool => str_contains($run, 'artisan test')));
    $suiteCommand = collect($quality['steps'])->firstWhere('name', 'PHP suites')['run'] ?? '';

    expect($config['permissions'])->toBe(['contents' => 'read'])
        ->and($checkoutSteps)->toHaveCount(2)
        ->and(array_column(array_column($checkoutSteps, 'with'), 'persist-credentials'))->each->toBeFalse()
        ->and($actionReferences)->not->toBeEmpty()
        ->and($actionReferences)->each->toMatch('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+@[a-f0-9]{40}$/')
        ->and($jobTimeouts)->toHaveCount(count($config['jobs']))
        ->and($quality['env']['DB_CONNECTION'])->toBe('sqlite')
        ->and($production['env']['DB_CONNECTION'])->toBe('pgsql')
        ->and($production['env']['CACHE_STORE'])->toBe('redis')
        ->and($production['services']['postgres']['image'])->toBe('postgres:16')
        ->and($production['services']['redis']['image'])->toBe('redis:7-alpine')
        ->and($pestCommands)->not->toBeEmpty()
        ->and($pestCommands)->each->toContain('--ci')
        ->and($suiteCommand)->toContain('--list-tests', 'test_count', 'exit 1');

    foreach ($jobTimeouts as $timeout) {
        expect($timeout)->toBeInt()->toBeGreaterThan(0)->toBeLessThanOrEqual(30);
    }

    $phpunit = simplexml_load_file(dirname(__DIR__, 2).'/phpunit.xml');
    $databaseEnv = $phpunit->xpath('/phpunit/php/env[@name="DB_CONNECTION"]')[0] ?? null;
    expect($databaseEnv)->not->toBeNull()
        ->and((string) $databaseEnv['force'])->not->toBe('true');
});
