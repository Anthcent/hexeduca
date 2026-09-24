<?php

namespace Modules\AcademicLevels\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Modules\AcademicLevels\Infrastructure\Http\Requests\StoreAcademicLevelRequest;
use Modules\AcademicLevels\Infrastructure\Models\AcademicLevel;

class AcademicLevelController extends Controller
{
    public function index(TenantContext $tenantContext)
    {
        return Inertia::render('AcademicLevels::Levels', [
            'levels' => AcademicLevel::where('school_id', $tenantContext->current()->id)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreAcademicLevelRequest $request): RedirectResponse
    {
        AcademicLevel::create($request->validated());

        return back()->with('success', 'Nivel académico creado.');
    }

    public function update(StoreAcademicLevelRequest $request, AcademicLevel $academic_level): RedirectResponse
    {
        $academic_level->update($request->validated());

        return back()->with('success', 'Nivel académico actualizado.');
    }

    public function destroy(AcademicLevel $academic_level): RedirectResponse
    {
        try {
            $academic_level->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar: hay grados que dependen de este nivel.');
        }

        return back()->with('success', 'Nivel académico eliminado.');
    }
}
