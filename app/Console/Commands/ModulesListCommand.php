<?php

namespace App\Console\Commands;

use App\ModulePlatform\Models\ModuleRecord;
use Illuminate\Console\Command;
use Nwidart\Modules\Facades\Module as NwidartModule;

/**
 * Lists every registered module with its key, core/maturity flags, active
 * state, and live dependency keys (read from the manifest, same as
 * `ModuleRegistry` — never duplicated into the DB).
 */
class ModulesListCommand extends Command
{
    protected $signature = 'modules:list';

    protected $description = 'List registered modules with key, core, maturity, active state, and dependencies.';

    public function handle(): int
    {
        $rows = ModuleRecord::query()
            ->orderBy('key')
            ->get()
            ->map(function (ModuleRecord $module): array {
                $manifest = NwidartModule::find($module->key);
                $dependencies = $manifest === null
                    ? []
                    : array_map('strtolower', (array) $manifest->get('dependencies', []));

                return [
                    $module->key,
                    $module->core ? 'yes' : 'no',
                    $module->maturity,
                    $module->active ? 'active' : 'inactive',
                    $dependencies === [] ? '-' : implode(', ', $dependencies),
                ];
            });

        if ($rows->isEmpty()) {
            $this->components->warn('No modules registered yet. Run `modules:sync` first.');

            return self::SUCCESS;
        }

        $this->table(['Key', 'Core', 'Maturity', 'Status', 'Dependencies'], $rows);

        return self::SUCCESS;
    }
}
