<?php

namespace App\Console\Commands;

use App\ModulePlatform\Exceptions\ModuleDependencyException;
use App\ModulePlatform\Exceptions\ModuleNotFoundException;
use App\ModulePlatform\Exceptions\ModuleNotReadyException;
use App\ModulePlatform\Services\ModuleRegistry;
use Illuminate\Console\Command;

/**
 * Thin CLI wrapper over `ModuleRegistry::enable()`.
 */
class ModulesEnableCommand extends Command
{
    protected $signature = 'modules:enable {key : The module key, e.g. academic}';

    protected $description = 'Activate a module globally (requires maturity: mature and all dependencies active).';

    public function handle(ModuleRegistry $registry): int
    {
        $key = strtolower($this->argument('key'));

        try {
            $registry->enable($key);
        } catch (ModuleNotFoundException|ModuleNotReadyException|ModuleDependencyException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Module [{$key}] enabled.");

        return self::SUCCESS;
    }
}
