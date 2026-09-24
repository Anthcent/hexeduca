<?php

namespace App\Console\Commands;

use App\ModulePlatform\Exceptions\ModuleCycleException;
use App\ModulePlatform\Services\ModuleRegistry;
use Illuminate\Console\Command;

/**
 * Thin CLI wrapper over `ModuleRegistry::sync()` — upserts the `modules`
 * table from the `Modules/*\/module.json` manifests and syncs Spatie
 * permissions declared in each manifest.
 */
class ModulesSyncCommand extends Command
{
    protected $signature = 'modules:sync';

    protected $description = 'Sync the modules registry table and permissions from the Modules/*/module.json manifests.';

    public function handle(ModuleRegistry $registry): int
    {
        try {
            $registry->sync();
        } catch (ModuleCycleException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Modules synced.');

        return self::SUCCESS;
    }
}
