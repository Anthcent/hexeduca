<?php

namespace App\ModulePlatform\Services;

use App\ModulePlatform\Exceptions\ModuleCoreException;
use App\ModulePlatform\Exceptions\ModuleCycleException;
use App\ModulePlatform\Exceptions\ModuleDependencyException;
use App\ModulePlatform\Exceptions\ModuleNotFoundException;
use App\ModulePlatform\Exceptions\ModuleNotReadyException;
use App\ModulePlatform\Models\ModuleRecord;
use App\ModulePlatform\Models\SchoolModule;
use App\Tenancy\Models\School;
use Illuminate\Support\Facades\DB;
use Nwidart\Modules\Facades\Module as NwidartModule;
use Nwidart\Modules\Module as NwidartModuleInstance;
use Spatie\Permission\Models\Permission;

/**
 * Keeps the `modules`/`school_modules` DB tables in sync with the
 * `Modules/*\/module.json` manifests, and enforces the activation rules:
 * dependencies must be active before a module can be enabled, core modules
 * can never be disabled, and a module with active dependents can't be
 * disabled either.
 *
 * Dependencies are intentionally NOT duplicated into the `modules` table —
 * they're read live from the manifests on every check via nwidart's own
 * Module facade, so there is a single source of truth and no drift.
 */
class ModuleRegistry
{
    public function __construct(private readonly ModuleAccess $moduleAccess) {}

    /**
     * Upserts a `modules` row per manifest and syncs Spatie permissions
     * declared in each manifest's `permissions` field. Safe to run
     * repeatedly: existing rows keep their current `active` state, only
     * newly-discovered modules get a default (core modules start active,
     * everything else starts inactive until explicitly enabled).
     */
    public function sync(): void
    {
        $this->assertNoCycles();

        DB::transaction(function (): void {
            foreach (NwidartModule::all() as $module) {
                $this->upsert($module);

                foreach ((array) $module->get('permissions', []) as $permission) {
                    Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
                }
            }
        });

        $this->moduleAccess->forget();
    }

    /**
     * @throws ModuleNotFoundException
     * @throws ModuleNotReadyException
     * @throws ModuleDependencyException
     */
    public function enable(string $key): void
    {
        DB::transaction(function () use ($key): void {
            $module = $this->findOrFail($key);

            if ($module->maturity === 'skeleton') {
                throw new ModuleNotReadyException("Module [{$key}] is still a skeleton and cannot be enabled.");
            }

            foreach ($this->dependencyKeysOf($key) as $dependencyKey) {
                $dependency = ModuleRecord::find($dependencyKey);

                if ($dependency === null || ! $dependency->active) {
                    throw new ModuleDependencyException(
                        "Module [{$key}] requires [{$dependencyKey}] to be active first."
                    );
                }
            }

            $module->update(['active' => true]);
        });

        $this->moduleAccess->forget();
    }

    /**
     * @throws ModuleNotFoundException
     * @throws ModuleCoreException
     * @throws ModuleDependencyException
     */
    public function disable(string $key): void
    {
        DB::transaction(function () use ($key): void {
            $module = $this->findOrFail($key);

            if ($module->core) {
                throw new ModuleCoreException("Module [{$key}] is core and cannot be disabled.");
            }

            $dependents = $this->activeDependentsOf($key);

            if ($dependents !== []) {
                throw new ModuleDependencyException(
                    "Module [{$key}] cannot be disabled while ["
                    .implode(', ', $dependents)
                    .'] depend on it.'
                );
            }

            $module->update(['active' => false]);
        });

        $this->moduleAccess->forget();
    }

    /**
     * Grants a school access to an (already registered) optional module.
     *
     * @throws ModuleNotFoundException
     */
    public function entitle(string $key, School $school): void
    {
        $this->findOrFail($key);

        SchoolModule::query()->updateOrCreate(
            ['school_id' => $school->id, 'module_key' => $key],
            ['enabled' => true],
        );
    }

    /**
     * Revokes a school's access to an optional module.
     */
    public function revoke(string $key, School $school): void
    {
        SchoolModule::query()
            ->where('school_id', $school->id)
            ->where('module_key', $key)
            ->update(['enabled' => false]);
    }

    private function upsert(NwidartModuleInstance $module): void
    {
        $key = $module->getLowerName();
        $core = (bool) $module->get('core', false);
        $maturity = (string) $module->get('maturity', 'mature');

        $existing = ModuleRecord::find($key);

        if ($existing === null) {
            ModuleRecord::query()->create([
                'key' => $key,
                'name' => $module->getName(),
                'core' => $core,
                'maturity' => $maturity,
                // Core modules have no disable path, so they start active.
                // Everything else stays inactive until explicitly enabled.
                'active' => $core,
            ]);

            return;
        }

        $existing->update([
            'name' => $module->getName(),
            'core' => $core,
            'maturity' => $maturity,
        ]);
    }

    /**
     * @throws ModuleNotFoundException
     */
    private function findOrFail(string $key): ModuleRecord
    {
        $module = ModuleRecord::find($key);

        if ($module === null) {
            throw new ModuleNotFoundException("Module [{$key}] is not registered.");
        }

        return $module;
    }

    /**
     * @return list<string>
     */
    private function dependencyKeysOf(string $key): array
    {
        $manifest = NwidartModule::find($key);

        if ($manifest === null) {
            return [];
        }

        return array_map('strtolower', (array) $manifest->get('dependencies', []));
    }

    /**
     * @return list<string>
     */
    private function activeDependentsOf(string $key): array
    {
        $dependents = [];

        foreach (ModuleRecord::query()->where('active', true)->get() as $candidate) {
            if ($candidate->key === $key) {
                continue;
            }

            if (in_array($key, $this->dependencyKeysOf($candidate->key), true)) {
                $dependents[] = $candidate->key;
            }
        }

        return $dependents;
    }

    /**
     * @throws ModuleCycleException
     */
    private function assertNoCycles(): void
    {
        $visiting = [];
        $visited = [];

        foreach (NwidartModule::all() as $module) {
            $this->visit($module->getLowerName(), $visiting, $visited);
        }
    }

    /**
     * @param  array<string, bool>  $visiting
     * @param  array<string, bool>  $visited
     *
     * @throws ModuleCycleException
     */
    private function visit(string $key, array &$visiting, array &$visited): void
    {
        if (isset($visited[$key])) {
            return;
        }

        if (isset($visiting[$key])) {
            throw new ModuleCycleException("Module dependency cycle detected involving [{$key}].");
        }

        $visiting[$key] = true;

        foreach ($this->dependencyKeysOf($key) as $dependencyKey) {
            $this->visit($dependencyKey, $visiting, $visited);
        }

        unset($visiting[$key]);
        $visited[$key] = true;
    }
}
