<?php

namespace Database\Seeders;

use App\ModulePlatform\Exceptions\ModuleDependencyException;
use App\ModulePlatform\Models\ModuleRecord;
use App\ModulePlatform\Services\ModuleRegistry;
use App\Tenancy\Models\School;
use Illuminate\Database\Seeder;

/**
 * Syncs the module registry from the `Modules/*\/module.json` manifests,
 * activates every mature optional module, and entitles all existing
 * schools to them. Idempotent: safe to run on every `migrate --seed` and
 * against a database that already has schools/modules.
 */
class ModulePlatformSeeder extends Seeder
{
    public function run(ModuleRegistry $registry): void
    {
        $registry->sync();

        $keys = $this->enableMatureOptionalModules($registry);

        $schools = School::withoutTenantScope()->get();

        foreach ($keys as $key) {
            foreach ($schools as $school) {
                $registry->entitle($key, $school);
            }
        }
    }

    /**
     * Enables every mature, non-core module, respecting dependency order.
     * Uses a simple fixed-point loop instead of a full topological sort:
     * dependency depth in this project is shallow, and ModuleRegistry::sync()
     * already guarantees the manifest graph has no cycles.
     *
     * @return list<string> keys of the modules that ended up active
     */
    private function enableMatureOptionalModules(ModuleRegistry $registry): array
    {
        $pending = ModuleRecord::query()
            ->where('maturity', 'mature')
            ->where('core', false)
            ->where('active', false)
            ->pluck('key')
            ->all();

        do {
            $progressed = false;

            foreach ($pending as $index => $key) {
                try {
                    $registry->enable($key);
                    unset($pending[$index]);
                    $progressed = true;
                } catch (ModuleDependencyException) {
                    // A dependency isn't active yet — retry in a later pass.
                }
            }
        } while ($progressed && $pending !== []);

        return ModuleRecord::query()
            ->where('maturity', 'mature')
            ->where('core', false)
            ->where('active', true)
            ->pluck('key')
            ->all();
    }
}
