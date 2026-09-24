<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Thin wrapper around `module:make` (nwidart/laravel-modules).
 *
 * Scaffolding itself is entirely stub-driven — see config/modules.php
 * `generator`/`stubs` and stubs/modules/. This command only fixes the
 * flags a new project module always needs, so nobody has to remember
 * `--inertia --disabled` by hand. See sdd/module-developer-platform R1.2.
 */
class MakeProjectModuleCommand extends Command
{
    protected $signature = 'make:project-module {name : The module name, e.g. Billing}';

    protected $description = 'Scaffold a new module with the project\'s Domain/Application/Infrastructure/Public layout, guarded routes, MODULE.md, and manifest v1 fields.';

    public function handle(): int
    {
        return $this->call('module:make', [
            'name' => [$this->argument('name')],
            '--inertia' => true,
            // New modules start disabled in the file activator and as
            // maturity: skeleton in module.json. They are not registered
            // or entitled anywhere until R2's module registry exists.
            '--disabled' => true,
        ]);
    }
}
