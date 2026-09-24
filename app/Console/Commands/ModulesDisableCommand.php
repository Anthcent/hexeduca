<?php

namespace App\Console\Commands;

use App\ModulePlatform\Exceptions\ModuleCoreException;
use App\ModulePlatform\Exceptions\ModuleDependencyException;
use App\ModulePlatform\Exceptions\ModuleNotFoundException;
use App\ModulePlatform\Services\ModuleRegistry;
use Illuminate\Console\Command;

/**
 * Thin CLI wrapper over `ModuleRegistry::disable()`.
 */
class ModulesDisableCommand extends Command
{
    protected $signature = 'modules:disable {key : The module key, e.g. academic}';

    protected $description = 'Deactivate a module globally (rejects core modules and modules with active dependents).';

    public function handle(ModuleRegistry $registry): int
    {
        $key = strtolower($this->argument('key'));

        try {
            $registry->disable($key);
        } catch (ModuleNotFoundException|ModuleCoreException|ModuleDependencyException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Module [{$key}] disabled.");

        return self::SUCCESS;
    }
}
