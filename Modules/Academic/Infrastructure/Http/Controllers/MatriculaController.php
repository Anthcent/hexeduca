<?php

namespace Modules\Academic\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Academic\Application\DTOs\MatricularEstudianteData;
use Modules\Academic\Application\UseCases\MatricularEstudiante;
use Modules\Academic\Infrastructure\Http\Requests\StoreMatriculaRequest;
use Modules\Academic\Infrastructure\Models\OfertaAcademica;
use Modules\Users\Infrastructure\Models\User;

/**
 * Backs the Matricular Estudiante screen. Only maps request input into
 * `MatricularEstudianteData` and invokes the use case — `school_id` and
 * `periodo_academico_id` are derived from the selected OfertaAcademica by
 * the use case itself, never from user input.
 */
class MatriculaController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Academic::MatriculaCreate', [
            'ofertas' => OfertaAcademica::with(['grado', 'seccion'])->get(['id', 'grado_id', 'seccion_id']),
            'students' => User::role('student')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreMatriculaRequest $request, MatricularEstudiante $matricularEstudiante): RedirectResponse
    {
        $data = new MatricularEstudianteData(
            ofertaAcademicaId: (int) $request->validated('oferta_academica_id'),
            studentId: (int) $request->validated('student_id'),
        );

        try {
            $matricularEstudiante->handle($data);
        } catch (DomainException $e) {
            throw ValidationException::withMessages([
                'oferta_academica_id' => $e->getMessage(),
            ]);
        }

        return redirect()->route('academic.matriculas.create')->with('success', 'Student enrolled successfully.');
    }
}
