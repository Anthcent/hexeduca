<?php

namespace Modules\AcademicMoments\Infrastructure\Http\Controllers;

use App\AcademicPeriod\Context\AcademicPeriodContext;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Modules\AcademicMoments\Infrastructure\Http\Requests\StoreAcademicMomentRequest;
use Modules\AcademicMoments\Infrastructure\Models\AcademicMoment;

class AcademicMomentController extends Controller
{
    public function index(AcademicPeriodContext $periodContext)
    {
        return Inertia::render('AcademicMoments::AcademicMoments', [
            'moments' => AcademicMoment::orderBy('order')->get(),
            'activePeriod' => $periodContext->current(),
        ]);
    }

    public function store(StoreAcademicMomentRequest $request): RedirectResponse
    {
        AcademicMoment::create([
            'periodo_academico_id' => $request->input('academic_period_id'),
            'name' => $request->string('name')->toString(),
            'order' => $request->input('order'),
            'starts_on' => $request->input('starts_on'),
            'ends_on' => $request->input('ends_on'),
        ]);

        return back()->with('success', 'Momento académico creado.');
    }

    public function update(StoreAcademicMomentRequest $request, AcademicMoment $academic_moment): RedirectResponse
    {
        $academic_moment->update([
            'name' => $request->string('name')->toString(),
            'order' => $request->input('order'),
            'starts_on' => $request->input('starts_on'),
            'ends_on' => $request->input('ends_on'),
        ]);

        return back()->with('success', 'Momento académico actualizado.');
    }

    public function destroy(AcademicMoment $academic_moment): RedirectResponse
    {
        try {
            $academic_moment->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar: este momento tiene datos asociados.');
        }

        return back()->with('success', 'Momento académico eliminado.');
    }
}
