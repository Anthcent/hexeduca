<?php

namespace Modules\GradeLevels\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Modules\AcademicLevels\Public\Contracts\AcademicLevelReader;
use Modules\GradeLevels\Application\DTOs\CreateGradeLevelData;
use Modules\GradeLevels\Application\UseCases\CreateGradeLevel;
use Modules\GradeLevels\Application\UseCases\UpdateGradeLevel;
use Modules\GradeLevels\Domain\Entities\GradeLevel as GradeLevelEntity;
use Modules\GradeLevels\Infrastructure\Http\Requests\StoreGradeLevelRequest;
use Modules\GradeLevels\Infrastructure\Models\GradeLevel;

class GradeLevelController extends Controller
{
    public function __construct(
        private readonly AcademicLevelReader $academicLevelReader,
    ) {}

    /**
     * Reference example of the sibling-module contract pattern (plan §5):
     * this module never touches Modules\AcademicLevels\Infrastructure\*,
     * only the DTOs handed back by AcademicLevelReader.
     */
    public function index(TenantContext $tenantContext)
    {
        $schoolId = $tenantContext->current()->id;

        $gradeLevels = GradeLevel::where('school_id', $schoolId)->orderBy('order')->get();
        $academicLevels = $this->academicLevelReader->allForSchool($schoolId);
        $academicLevelNames = collect($academicLevels)->keyBy('id')->map(fn ($dto) => $dto->name);

        return Inertia::render('GradeLevels::GradeLevels', [
            'gradeLevels' => $gradeLevels->map(fn (GradeLevel $gradeLevel) => [
                'id' => $gradeLevel->id,
                'name' => $gradeLevel->name,
                'order' => $gradeLevel->order,
                'academic_level_id' => $gradeLevel->nivel_academico_id,
                'academic_level_name' => $academicLevelNames->get($gradeLevel->nivel_academico_id, '—'),
            ])->values(),
            'academicLevels' => $academicLevels,
        ]);
    }

    public function store(StoreGradeLevelRequest $request, CreateGradeLevel $useCase, TenantContext $tenantContext): RedirectResponse
    {
        $useCase->handle(new CreateGradeLevelData(
            schoolId: $tenantContext->current()->id,
            academicLevelId: (int) $request->input('academic_level_id'),
            name: $request->string('name')->toString(),
            order: (int) $request->input('order'),
        ));

        return back()->with('success', 'Grado creado.');
    }

    public function update(StoreGradeLevelRequest $request, GradeLevel $grade_level, UpdateGradeLevel $useCase): RedirectResponse
    {
        $entity = new GradeLevelEntity(
            id: $grade_level->id,
            schoolId: $grade_level->school_id,
            academicLevelId: $grade_level->nivel_academico_id,
            name: $grade_level->name,
            order: $grade_level->order,
        );

        $useCase->handle($entity, $request->string('name')->toString(), (int) $request->input('order'));

        return back()->with('success', 'Grado actualizado.');
    }

    public function destroy(GradeLevel $grade_level): RedirectResponse
    {
        try {
            $grade_level->delete();
        } catch (QueryException) {
            return back()->with('error', 'No se puede eliminar: este grado está en uso.');
        }

        return back()->with('success', 'Grado eliminado.');
    }
}
