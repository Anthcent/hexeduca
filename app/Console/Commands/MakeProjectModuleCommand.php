<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Generators\ModuleGenerator;

/**
 * Scaffolds a new module with the project's Domain/Application/Infrastructure/
 * Public layout, guarded routes, MODULE.md, and manifest v1 fields.
 *
 * Deliberately does NOT call `module:make` (nwidart/laravel-modules'
 * command). `module:make` unconditionally shells out to
 * `composer dump-autoload` with a hardcoded 60s timeout after generating,
 * regardless of any flag. In this environment `composer` on PATH resolves
 * an older PHP (8.0.30) than the one required by this project (>= 8.3), so
 * that step reliably times out or fails — see
 * sdd/module-developer-platform R2.0. Instead, this command drives the same
 * `ModuleGenerator` pipeline directly (same as `module:make` does
 * internally, and the same approach tests/Feature/ModulePlatform/
 * ModuleGeneratorTest.php already uses) and then refreshes the autoloader
 * itself using PHP_BINARY (whatever PHP is running this command) plus an
 * explicitly located composer.phar, bypassing PATH's `composer` resolution
 * entirely.
 */
class MakeProjectModuleCommand extends Command
{
    protected $signature = 'make:project-module {name : The module name, e.g. Billing}';

    protected $description = 'Scaffold a new module with the project\'s Domain/Application/Infrastructure/Public layout, guarded routes, MODULE.md, and manifest v1 fields.';

    public function handle(): int
    {
        $name = $this->argument('name');

        $code = (new ModuleGenerator($name))
            ->setFilesystem($this->laravel['files'])
            ->setModule($this->laravel['modules'])
            ->setConfig($this->laravel['config'])
            ->setActivator($this->laravel[ActivatorInterface::class])
            ->setConsole($this)
            ->setComponent($this->components)
            ->setType('web')
            ->setInertia(true)
            ->setAuthor(null, null)
            // New modules start disabled in the file activator and as
            // maturity: skeleton in module.json. They are not registered
            // or entitled anywhere until an operator runs `modules:sync`
            // and explicitly enables them.
            ->setActive(false)
            ->generate();

        if ($code === E_ERROR) {
            $this->components->error("Failed to generate module [{$name}].");

            return self::FAILURE;
        }

        $this->refreshAutoloader();

        $this->components->info("Module [{$name}] generated. Run `composer dump-autoload` yourself if the warning above fired, then `php artisan modules:sync` once R3 lands it.");

        return self::SUCCESS;
    }

    private function refreshAutoloader(): void
    {
        $composerPhar = $this->locateComposerPhar();

        if ($composerPhar === null) {
            $this->components->warn(
                'Could not locate composer.phar automatically. Run "composer dump-autoload" '
                .'manually (with PHP >= 8.3) so the new module\'s service provider and classes '
                .'are discovered.'
            );

            return;
        }

        $result = Process::path(base_path())
            ->timeout(180)
            ->run([PHP_BINARY, $composerPhar, 'dump-autoload']);

        if (! $result->successful()) {
            $this->components->warn(
                'Module scaffolded, but "composer dump-autoload" failed. Run it manually: '
                .PHP_BINARY." {$composerPhar} dump-autoload"
            );
            $this->line($result->errorOutput());
        }
    }

    private function locateComposerPhar(): ?string
    {
        $candidates = array_filter([
            getenv('COMPOSER_BINARY') ?: null,
            'C:\\composer\\composer.phar',
            base_path('composer.phar'),
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
