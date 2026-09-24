<?php

namespace App\Http\Middleware;

use App\ModulePlatform\Services\ModuleAccess;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Middleware;
use Nwidart\Modules\Facades\Module as NwidartModule;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'auth' => [
                'user' => fn () => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->getRoleNames()->first(),
                ] : null,
            ],
            // Module keys the current request may use, so the frontend nav
            // (see DashboardLayout.vue) can hide links to modules that are
            // inactive or the current school isn't entitled to.
            'modules' => fn () => app(ModuleAccess::class)->availableKeys(
                app(TenantContext::class)->current()
            ),
            // Manifest-declared nav entries (module.json `navigation`) for
            // every module the current request may use. DashboardLayout.vue
            // renders these after its hardcoded items. See
            // sdd/module-developer-platform R4.3.
            'moduleNav' => fn () => $this->moduleNavigation($request),
        ]);
    }

    /**
     * @return list<array{key: string, label: string, icon: ?string, href: string}>
     */
    private function moduleNavigation(Request $request): array
    {
        $keys = app(ModuleAccess::class)->availableKeys(app(TenantContext::class)->current());
        $user = $request->user();

        $entries = [];

        foreach ($keys as $key) {
            $manifest = NwidartModule::find($key);

            if ($manifest === null) {
                continue;
            }

            foreach ((array) $manifest->get('navigation', []) as $item) {
                if (! isset($item['label'], $item['route']) || ! Route::has($item['route'])) {
                    continue;
                }

                if (isset($item['permission']) && ($user === null || ! $user->can($item['permission']))) {
                    continue;
                }

                $entries[] = [
                    'key' => $key.':'.$item['route'],
                    'label' => $item['label'],
                    'icon' => $item['icon'] ?? null,
                    'href' => route($item['route']),
                ];
            }
        }

        return $entries;
    }
}
