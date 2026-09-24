<?php

namespace Modules\Admin\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\ModulePlatform\Exceptions\ModuleCoreException;
use App\ModulePlatform\Exceptions\ModuleDependencyException;
use App\ModulePlatform\Exceptions\ModuleNotFoundException;
use App\ModulePlatform\Exceptions\ModuleNotReadyException;
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
 * Both `toggleActive()` and `toggleEntitlement()` call `ModuleRegistry`
 * directly (constructor/method injection). The architecture guardrail
 * (`Tests\Architecture\ModulePlatformGuardrailsTest`) allowlists calls made
 * on a parameter typed `ModuleRegistry` specifically, so it still flags any
 * nwidart file-activator/`Module` facade `enable()`/`disable()` call made
 * from HTTP code, without needing a controller-side workaround. See
 * sdd/module-developer-platform R4.5.
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

    public function toggleActive(ModuleRegistry $registry, string $module): RedirectResponse
    {
        $record = ModuleRecord::query()->findOrFail($module);
        $verb = $record->active ? 'desactivado' : 'activado';

        try {
            $record->active ? $registry->disable($record->key) : $registry->enable($record->key);
        } catch (ModuleNotFoundException|ModuleNotReadyException|ModuleCoreException|ModuleDependencyException $e) {
            return back()->with('error', $e->getMessage());
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
