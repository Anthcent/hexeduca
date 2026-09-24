<?php

namespace Modules\AcademicOffers\Infrastructure\Http\Controllers;

use App\AcademicPeriod\Context\AcademicPeriodContext;
use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AcademicOffers\Application\DTOs\CreateAcademicOfferData;
use Modules\AcademicOffers\Application\UseCases\CreateAcademicOffer;
use Modules\AcademicOffers\Infrastructure\Http\Requests\StoreAcademicOfferRequest;
use Modules\AcademicOffers\Infrastructure\Persistence\LocalProjectionTeacherReader;
use Modules\GradeLevels\Public\Contracts\GradeLevelReader;
use Modules\Sections\Public\Contracts\SectionReader;

/**
 * Backs the Create Academic Offer screen. Reads sibling-module catalog
 * data (GradeLevels, Sections) through their Public\Contracts readers —
 * see plan §5. Teacher data is read from this module's own
 * `academic_offers_teacher_projection` (LocalProjectionTeacherReader,
 * Fase 8) instead of calling Modules\Users\Public\Contracts\TeacherReader
 * synchronously on every request — see plan §6.
 */
class AcademicOfferController extends Controller
{
    public function __construct(
        private readonly GradeLevelReader $gradeLevelReader,
        private readonly SectionReader $sectionReader,
        private readonly LocalProjectionTeacherReader $teacherReader,
    ) {}

    public function create(TenantContext $tenantContext, AcademicPeriodContext $periodContext): Response
    {
        $schoolId = $tenantContext->current()->id;
        $periodo = $periodContext->current();

        return Inertia::render('AcademicOffers::AcademicOfferCreate', [
            'gradeLevels' => $this->gradeLevelReader->allForSchool($schoolId),
            'sections' => $this->sectionReader->allForSchool($schoolId),
            'teachers' => $this->teacherReader->allForSchool($schoolId),
            'hasActivePeriodo' => $periodContext->hasPeriod(),
            'periodoName' => $periodo?->name,
        ]);
    }

    public function store(
        StoreAcademicOfferRequest $request,
        CreateAcademicOffer $createAcademicOffer,
        TenantContext $tenantContext,
        AcademicPeriodContext $periodContext,
    ): RedirectResponse {
        if (! $periodContext->hasPeriod()) {
            throw ValidationException::withMessages([
                'grade_level_id' => 'There is no active academic period for this school.',
            ]);
        }

        $data = new CreateAcademicOfferData(
            schoolId: $tenantContext->current()->id,
            academicPeriodId: $periodContext->current()->id,
            gradeLevelId: (int) $request->validated('grade_level_id'),
            sectionId: (int) $request->validated('section_id'),
            teacherId: $request->validated('teacher_id') !== null ? (int) $request->validated('teacher_id') : null,
            capacity: (int) $request->validated('capacity'),
        );

        try {
            $createAcademicOffer->handle($data);
        } catch (DomainException $e) {
            $field = match ($e->getMessage()) {
                'The grade level must belong to this school.' => 'grade_level_id',
                'The section must belong to this school.' => 'section_id',
                'The assigned teacher must be a teacher in this school.' => 'teacher_id',
                default => 'grade_level_id',
            };

            throw ValidationException::withMessages([
                $field => $e->getMessage(),
            ]);
        }

        return redirect()->route('academic-offers.create')->with('success', 'Academic offer created successfully.');
    }
}
