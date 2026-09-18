<?php

namespace Modules\Academic\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Infrastructure\Http\Requests\StorePeriodoAcademicoRequest;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;

class PeriodoAcademicoController extends Controller
{
    public function store(StorePeriodoAcademicoRequest $request): RedirectResponse
    {
        PeriodoAcademico::create([
            ...$request->validated(),
            'is_active' => false,
        ]);

        return back()->with('success', 'Período académico creado.');
    }

    public function update(StorePeriodoAcademicoRequest $request, PeriodoAcademico $periodo): RedirectResponse
    {
        $periodo->update($request->validated());

        return back()->with('success', 'Período académico actualizado.');
    }

    public function destroy(PeriodoAcademico $periodo): RedirectResponse
    {
        try {
            $periodo->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar: este período tiene datos asociados.');
        }

        return back()->with('success', 'Período académico eliminado.');
    }

    /**
     * Marks this period as the school's single active period, deactivating
     * any other active period in the same transaction — only one period
     * may be active per school (see the migration's
     * `[school_id, is_active]` index and `PeriodoContext::current()`).
     */
    public function activate(PeriodoAcademico $periodo, TenantContext $tenantContext): RedirectResponse
    {
        DB::transaction(function () use ($periodo, $tenantContext): void {
            PeriodoAcademico::where('school_id', $tenantContext->current()->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $periodo->update(['is_active' => true]);
        });

        return back()->with('success', "\"{$periodo->name}\" es ahora el período activo.");
    }
}
