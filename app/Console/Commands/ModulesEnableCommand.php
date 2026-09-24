<?php

namespace App\Console\Commands;

use App\ModulePlatform\Exceptions\ModuleDependencyException;
use App\ModulePlatform\Exceptions\ModuleNotFoundException;
use App\ModulePlatform\Exceptions\ModuleNotReadyException;
use App\ModulePlatform\Models\ModuleRecord;
use App\ModulePlatform\Services\ModuleRegistry;
use App\Tenancy\Models\School;
use Illuminate\Console\Command;
use Nwidart\Modules\Facades\Module as NwidartModule;

/**
 * Thin CLI wrapper over `ModuleRegistry::enable()`.
 *
 * `--promote` is the simplest way to take a module from `maturity: skeleton`
 * to `mature`: it writes `maturity: mature` into the module's own
 * `module.json` (the manifest stays the single source of truth) and
 * re-syncs the registry before enabling, instead of adding a separate
 * `modules:promote` command. See sdd/module-developer-platform R4.2.
 *
 * `--all-schools` entitles every existing school to the module right after
 * enabling it. This is opt-in (not the default) so enabling a module never
 * silently grants every school access to new, possibly unfinished,
 * functionality — the operator has to say so explicitly. Individual schools
 * can still be entitled one at a time via `modules:entitle`, and any school
 * created afterwards is auto-entitled by SchoolCacheObserver regardless of
 * this flag.
 */
class ModulesEnableCommand extends Command
{
    protected $signature = 'modules:enable {key : The module key, e.g. academic}
        {--promote : Promote a skeleton module to mature before enabling it}
        {--all-schools : Entitle every existing school to this module once enabled}';

    protected $description = 'Activate a module globally (requires maturity: mature and all dependencies active).';

    public function handle(ModuleRegistry $registry): int
    {
        $key = strtolower($this->argument('key'));

        if ($this->option('promote') && ! $this->promote($key, $registry)) {
            return self::FAILURE;
        }

        try {
            $registry->enable($key);
        } catch (ModuleNotFoundException|ModuleNotReadyException|ModuleDependencyException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Module [{$key}] enabled.");

        if ($this->option('all-schools')) {
            $this->entitleAllSchools($key, $registry);
        }

        return self::SUCCESS;
    }

    private function promote(string $key, ModuleRegistry $registry): bool
    {
        $manifest = NwidartModule::find($key);

        if ($manifest === null) {
            $this->components->error("Module [{$key}] is not registered.");

            return false;
        }

        $manifest->json()->set('maturity', 'mature')->save();

        // The registry's `maturity` column is a cache of the manifest —
        // re-sync so `enable()` sees the promotion immediately.
        $registry->sync();

        return true;
    }

    private function entitleAllSchools(string $key, ModuleRegistry $registry): void
    {
        $module = ModuleRecord::query()->find($key);

        if ($module?->core) {
            // Core modules are already available to every school; nothing to entitle.
            return;
        }

        $schools = School::withoutTenantScope()->get();

        foreach ($schools as $school) {
            $registry->entitle($key, $school);
        }

        $this->components->info("Entitled {$schools->count()} school(s) to [{$key}].");
    }
}
