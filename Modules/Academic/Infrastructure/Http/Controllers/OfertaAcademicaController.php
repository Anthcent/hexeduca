<?php

namespace Modules\Academic\Infrastructure\Http\Controllers;

use App\AcademicPeriod\Context\AcademicPeriodContext;
use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Academic\Application\DTOs\CreateOfertaAcademicaData;
use Modules\Academic\Application\UseCases\CreateOfertaAcademica;
use Modules\Academic\Infrastructure\Http\Requests\StoreOfertaRequest;
use Modules\Academic\Infrastructure\Models\Grado;
use Modules\Academic\Infrastructure\Models\Seccion;
use Modules\Users\Infrastructure\Models\User;

/**
 * Backs the Create Oferta Académica screen. Only maps request input into
 * `CreateOfertaAcademicaData`, invokes the use case, and translates
 * `DomainException` into a validation-friendly redirect — no
 * capacity/uniqueness rules are re-implemented here.
 */
class OfertaAcademicaController extends Controller
{
    public function create(TenantContext $tenantContext, AcademicPeriodContext $periodContext): Response
    {
        $periodo = $periodContext->current();

        return Inertia::render('Academic::OfertaCreate', [
            'grados' => Grado::orderBy('name')->get(['id', 'name']),
            'secciones' => Seccion::orderBy('name')->get(['id', 'name']),
            'teachers' => User::role('teacher')->orderBy('name')->get(['id', 'name']),
            'hasActivePeriodo' => $periodContext->hasPeriod(),
            'periodoName' => $periodo?->name,
        ]);
    }

    public function store(
        StoreOfertaRequest $request,
        CreateOfertaAcademica $createOfertaAcademica,
        TenantContext $tenantContext,
        AcademicPeriodContext $periodContext,
    ): RedirectResponse {
        if (! $periodContext->hasPeriod()) {
            throw ValidationException::withMessages([
                'grado_id' => 'There is no active academic period for this school.',
            ]);
        }

        $data = new CreateOfertaAcademicaData(
            schoolId: $tenantContext->current()->id,
            periodoAcademicoId: $periodContext->current()->id,
            gradoId: (int) $request->validated('grado_id'),
            seccionId: (int) $request->validated('seccion_id'),
            teacherId: $request->validated('teacher_id') !== null ? (int) $request->validated('teacher_id') : null,
            capacity: (int) $request->validated('capacity'),
        );

        try {
            $createOfertaAcademica->handle($data);
        } catch (DomainException $e) {
            $field = match ($e->getMessage()) {
                'The grade level must belong to this school.' => 'grado_id',
                'The section must belong to this school.' => 'seccion_id',
                'The assigned teacher must be a teacher in this school.' => 'teacher_id',
                default => 'grado_id',
            };

            throw ValidationException::withMessages([
                $field => $e->getMessage(),
            ]);
        }

        return redirect()->route('academic.ofertas.create')->with('success', 'Oferta académica created successfully.');
    }
}
