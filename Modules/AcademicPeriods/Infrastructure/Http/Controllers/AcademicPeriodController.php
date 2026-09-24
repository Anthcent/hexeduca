<?php

namespace Modules\AcademicPeriods\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Modules\AcademicPeriods\Application\DTOs\CreateAcademicPeriodData;
use Modules\AcademicPeriods\Application\UseCases\ActivateAcademicPeriod;
use Modules\AcademicPeriods\Application\UseCases\CreateAcademicPeriod;
use Modules\AcademicPeriods\Infrastructure\Http\Requests\StoreAcademicPeriodRequest;
use Modules\AcademicPeriods\Infrastructure\Models\AcademicPeriod;

class AcademicPeriodController extends Controller
{
    public function index(TenantContext $tenantContext)
    {
        return Inertia::render('AcademicPeriods::Periods', [
            'periods' => AcademicPeriod::where('school_id', $tenantContext->current()->id)
                ->orderByDesc('starts_on')
                ->get(),
        ]);
    }

    public function store(StoreAcademicPeriodRequest $request, CreateAcademicPeriod $useCase, TenantContext $tenantContext): RedirectResponse
    {
        $useCase->handle(new CreateAcademicPeriodData(
            schoolId: $tenantContext->current()->id,
            name: $request->string('name')->toString(),
            startsOn: new \DateTimeImmutable($request->string('starts_on')->toString()),
            endsOn: new \DateTimeImmutable($request->string('ends_on')->toString()),
        ));

        return back()->with('success', 'Período académico creado.');
    }

    public function update(StoreAcademicPeriodRequest $request, AcademicPeriod $academic_period): RedirectResponse
    {
        $academic_period->update($request->validated());

        return back()->with('success', 'Período académico actualizado.');
    }

    public function destroy(AcademicPeriod $academic_period): RedirectResponse
    {
        try {
            $academic_period->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar: este período tiene datos asociados.');
        }

        return back()->with('success', 'Período académico eliminado.');
    }

    public function activate(AcademicPeriod $academic_period, ActivateAcademicPeriod $useCase): RedirectResponse
    {
        $useCase->handle($academic_period);

        return back()->with('success', "\"{$academic_period->name}\" es ahora el período activo.");
    }
}
