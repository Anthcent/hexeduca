<?php

namespace Modules\Admin\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\Models\School;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Infrastructure\Http\Requests\StoreSchoolRequest;

/**
 * Landlord-only institution management. Gated at the route level by
 * `auth` + `role:super-admin` + `RequireLandlordHost` — every method here
 * assumes it is only ever reached from the landlord host by the
 * super-admin.
 */
class SchoolController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin::Schools', [
            'schools' => School::orderBy('name')->get(),
        ]);
    }

    public function store(StoreSchoolRequest $request): RedirectResponse
    {
        School::create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return back()->with('success', 'Institución creada.');
    }

    public function update(StoreSchoolRequest $request, School $school): RedirectResponse
    {
        $school->update($request->validated());

        return back()->with('success', 'Institución actualizada.');
    }

    /**
     * Toggles `is_active`. Never hard-deletes a School: every tenant-owned
     * table has `restrictOnDelete()` on `school_id`, and `ResolveTenant`
     * already fails closed (404) for an inactive subdomain — deactivating
     * is the correct "remove access" action, not destroying data.
     */
    public function toggleActive(School $school): RedirectResponse
    {
        $school->update(['is_active' => ! $school->is_active]);

        $message = $school->is_active ? 'Institución activada.' : 'Institución desactivada.';

        return back()->with('success', $message);
    }
}
