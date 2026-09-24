<?php

namespace App\ModulePlatform\Services;

use App\ModulePlatform\Models\ModuleRecord;
use App\ModulePlatform\Models\SchoolModule;
use App\Tenancy\Models\School;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves whether a module is currently usable: `active AND (core OR the
 * given school is entitled to it)`.
 *
 * Only the small "active modules" set is cached (it changes rarely, via
 * ModuleRegistry::sync/enable/disable, and is read on almost every request
 * to build the Inertia nav and gate routes). Per-school entitlement is
 * looked up directly against `school_modules` — that table is already
 * indexed by the `school_id`+`module_key` unique constraint, so it doesn't
 * need its own cache/invalidation bookkeeping.
 */
class ModuleAccess
{
    private const CACHE_KEY = 'module-platform:active-modules';

    public function allows(string $key, ?School $school): bool
    {
        $modules = $this->activeModules();

        if (! isset($modules[$key])) {
            return false;
        }

        if ($modules[$key]['core']) {
            return true;
        }

        if ($school === null) {
            return false;
        }

        return SchoolModule::query()
            ->where('school_id', $school->id)
            ->where('module_key', $key)
            ->where('enabled', true)
            ->exists();
    }

    /**
     * @return list<string>
     */
    public function availableKeys(?School $school): array
    {
        return array_values(array_filter(
            array_keys($this->activeModules()),
            fn (string $key): bool => $this->allows($key, $school),
        ));
    }

    /**
     * Forces the next read to recompute the active-modules set. Called by
     * ModuleRegistry after sync/enable/disable.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array{core: bool}>
     */
    private function activeModules(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return ModuleRecord::query()
                ->where('active', true)
                ->get()
                ->mapWithKeys(fn (ModuleRecord $module): array => [
                    $module->key => ['core' => $module->core],
                ])
                ->all();
        });
    }
}
