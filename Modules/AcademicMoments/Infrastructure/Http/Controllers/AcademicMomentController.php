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
            // Moments have no school_id: without an active period the period
            // scope filters nothing, so list none rather than every school's.
            'moments' => $periodContext->hasPeriod() ? AcademicMoment::orderBy('order')->get() : [],
            'activePeriod' => $periodContext->current(),
        ]);
    }

    public function store(StoreAcademicMomentRequest $request, AcademicPeriodContext $periodContext): RedirectResponse
    {
        // Always the school's active period: a period id from the request
        // could belong to another school.
        if (! $periodContext->hasPeriod()) {
            return back()->with('error', 'No hay un período académico activo.');
        }

        AcademicMoment::create([
            'periodo_academico_id' => $periodContext->current()->id,
            'name' => $request->string('name')->toString(),
            'order' => $request->input('order'),
            'starts_on' => $request->input('starts_on'),
            'ends_on' => $request->input('ends_on'),
            'grading_opens_on' => $request->input('grading_opens_on'),
            'grading_closes_on' => $request->input('grading_closes_on'),
        ]);

        return back()->with('success', 'Momento académico creado.');
    }

    public function update(StoreAcademicMomentRequest $request, AcademicMoment $academic_moment, AcademicPeriodContext $periodContext): RedirectResponse
    {
        $this->abortUnlessInActivePeriod($academic_moment, $periodContext);

        $academic_moment->update([
            'name' => $request->string('name')->toString(),
            'order' => $request->input('order'),
            'starts_on' => $request->input('starts_on'),
            'ends_on' => $request->input('ends_on'),
            'grading_opens_on' => $request->input('grading_opens_on'),
            'grading_closes_on' => $request->input('grading_closes_on'),
        ]);

        return back()->with('success', 'Momento académico actualizado.');
    }

    public function destroy(AcademicMoment $academic_moment, AcademicPeriodContext $periodContext): RedirectResponse
    {
        $this->abortUnlessInActivePeriod($academic_moment, $periodContext);

        try {
            $academic_moment->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar: este momento tiene datos asociados.');
        }

        return back()->with('success', 'Momento académico eliminado.');
    }

    /**
     * Route binding relies on the active-period scope, which filters nothing
     * when the school has no active period: the moment could be another
     * school's.
     */
    private function abortUnlessInActivePeriod(AcademicMoment $moment, AcademicPeriodContext $periodContext): void
    {
        abort_unless(
            $periodContext->hasPeriod() && (int) $moment->periodo_academico_id === $periodContext->current()->id,
            404,
        );
    }
}
