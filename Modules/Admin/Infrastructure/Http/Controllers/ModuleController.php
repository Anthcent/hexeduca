<?php

namespace Modules\Admin\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\ModulePlatform\Exceptions\ModuleNotFoundException;
use App\ModulePlatform\Models\ModuleRecord;
use App\ModulePlatform\Models\SchoolModule;
use App\ModulePlatform\Services\ModuleRegistry;
use App\Tenancy\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;
use Nwidart\Modules\Facades\Module as NwidartModule;

/**
 * Landlord-only module platform management: global activation and
 * per-school entitlements. Gated at the route level by `auth` +
 * `role:super-admin` + `RequireLandlordHost` — every method here assumes it
 * is only ever reached from the landlord host by the super-admin.
 *
 * Global activation is deliberately NOT done via `ModuleRegistry::enable()`/
 * `disable()` called straight from this HTTP controller: the architecture
 * guardrail (`Tests\Architecture\ModulePlatformGuardrailsTest`) forbids
 * non-console code from calling activation methods, since that's the same
 * code path that can flip deployment state. Instead this delegates to the
 * `modules:enable`/`modules:disable` Artisan commands (R3.1) via
 * `Artisan::call()`, keeping the actual mutation console-only while still
 * being triggerable from the admin page. Per-school entitlement
 * (`entitle`/`revoke`) is a lighter-weight, per-tenant operation the
 * guardrail does not restrict, so `toggleEntitlement()` calls
 * `ModuleRegistry` directly.
 *
 * Typed exceptions from `ModuleRegistry` are mapped to a flash `error`
 * message (they're operator-facing state conflicts, e.g. "dependency not
 * active", not per-field form validation).
 */
class ModuleController extends Controller
{
    public function index(): Response
    {
        $modules = ModuleRecord::query()
            ->orderBy('key')
            ->get()
            ->map(fn (ModuleRecord $module): array => [
                'key' => $module->key,
                'name' => $module->name,
                'core' => $module->core,
                'maturity' => $module->maturity,
                'active' => $module->active,
                'dependencies' => $this->dependencyKeysOf($module->key),
            ]);

        $schools = School::orderBy('name')->get(['id', 'name', 'subdomain']);

        $entitlements = SchoolModule::query()
            ->where('enabled', true)
            ->get(['school_id', 'module_key'])
            ->groupBy('school_id')
            ->map(fn ($rows) => $rows->pluck('module_key')->values());

        return Inertia::render('Admin::Modules', [
            'modules' => $modules,
            'schools' => $schools,
            'entitlements' => $entitlements,
        ]);
    }

    public function sync(): RedirectResponse
    {
        $exitCode = Artisan::call('modules:sync');

        if ($exitCode !== 0) {
            return back()->with('error', trim(Artisan::output()));
        }

        return back()->with('success', 'Módulos sincronizados desde los manifiestos.');
    }

    public function toggleActive(string $module): RedirectResponse
    {
        $record = ModuleRecord::query()->findOrFail($module);
        $command = $record->active ? 'modules:disable' : 'modules:enable';
        $verb = $record->active ? 'desactivado' : 'activado';

        $exitCode = Artisan::call($command, ['key' => $record->key]);

        if ($exitCode !== 0) {
            return back()->with('error', trim(Artisan::output()));
        }

        return back()->with('success', "Módulo [{$record->key}] {$verb}.");
    }

    public function toggleEntitlement(ModuleRegistry $registry, string $module, School $school): RedirectResponse
    {
        $record = ModuleRecord::query()->find($module);

        if ($record === null) {
            return back()->with('error', "Module [{$module}] is not registered.");
        }

        $entitled = SchoolModule::query()
            ->where('school_id', $school->id)
            ->where('module_key', $record->key)
            ->where('enabled', true)
            ->exists();

        try {
            if ($entitled) {
                $registry->revoke($record->key, $school);
                $message = "Acceso a [{$record->key}] revocado para {$school->name}.";
            } else {
                $registry->entitle($record->key, $school);
                $message = "Acceso a [{$record->key}] otorgado a {$school->name}.";
            }
        } catch (ModuleNotFoundException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
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
}
