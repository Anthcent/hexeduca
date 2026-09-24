<?php

namespace Modules\Enrollments\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AcademicOffers\Public\Contracts\AcademicOfferReader;
use Modules\Enrollments\Application\DTOs\CreateEnrollmentData;
use Modules\Enrollments\Application\UseCases\CreateEnrollment;
use Modules\Enrollments\Infrastructure\Http\Requests\StoreEnrollmentRequest;
use Modules\Enrollments\Infrastructure\Persistence\LocalProjectionStudentReader;

/**
 * Backs the Create Enrollment screen. Reads AcademicOffers through its
 * Public\Contracts reader — see plan §5. Student data is read from this
 * module's own `enrollments_student_projection`
 * (LocalProjectionStudentReader, Fase 8) instead of calling
 * Modules\Users\Public\Contracts\StudentReader synchronously on every
 * request — see plan §6.
 */
class EnrollmentController extends Controller
{
    public function __construct(
        private readonly AcademicOfferReader $academicOfferReader,
        private readonly LocalProjectionStudentReader $studentReader,
    ) {}

    public function create(TenantContext $tenantContext): Response
    {
        return Inertia::render('Enrollments::EnrollmentCreate', [
            'academicOffers' => $this->academicOfferReader->allActiveForSchool($tenantContext->current()->id),
            'students' => $this->studentReader->allForSchool($tenantContext->current()->id),
        ]);
    }

    public function store(StoreEnrollmentRequest $request, CreateEnrollment $createEnrollment): RedirectResponse
    {
        $data = new CreateEnrollmentData(
            academicOfferId: (int) $request->validated('academic_offer_id'),
            studentId: (int) $request->validated('student_id'),
        );

        try {
            $createEnrollment->handle($data);
        } catch (DomainException $e) {
            throw ValidationException::withMessages([
                $e->getMessage() === 'The enrolled user must be a student in the AcademicOffer school.' ? 'student_id' : 'academic_offer_id' => $e->getMessage(),
            ]);
        }

        return redirect()->route('enrollments.create')->with('success', 'Student enrolled successfully.');
    }
}
