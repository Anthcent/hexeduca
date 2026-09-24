<?php

namespace Modules\Academic\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Modules\Academic\Infrastructure\Http\Requests\StorePeriodoAcademicoRequest;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\AcademicPeriods\Application\UseCases\ActivateAcademicPeriod;
use Modules\AcademicPeriods\Infrastructure\Models\AcademicPeriod;

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
     * Backward-compatible route adapter. Activation policy, serialization,
     * outbox recording and domain event dispatch belong to AcademicPeriods.
     */
    public function activate(PeriodoAcademico $periodo, ActivateAcademicPeriod $useCase): RedirectResponse
    {
        $authoritativePeriod = AcademicPeriod::findOrFail($periodo->id);
        $useCase->handle($authoritativePeriod);

        return back()->with('success', "\"{$periodo->name}\" es ahora el período activo.");
    }
}
