<?php

namespace Modules\Grades\Infrastructure\Http\Controllers;

use App\AcademicPeriod\Context\AcademicPeriodContext;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Grades\Application\DTOs\RecordGradeData;
use Modules\Grades\Application\UseCases\RecordGrade;
use Modules\Grades\Application\UseCases\UpdateGrade;
use Modules\Grades\Infrastructure\Http\Requests\StoreGradeRequest;
use Modules\Grades\Infrastructure\Http\Requests\UpdateGradeRequest;
use Modules\Grades\Infrastructure\Models\Grade;

class GradeController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Grades::GradeCreate');
    }

    public function store(
        StoreGradeRequest $request,
        RecordGrade $recordGrade,
        AcademicPeriodContext $academicPeriodContext,
    ): RedirectResponse {
        $user = $request->user();
        $activePeriod = $academicPeriodContext->current();

        if ($activePeriod === null) {
            abort(403);
        }

        // A teacher may only record a grade under their own teacher_id —
        // staff/admin may record on behalf of any teacher (plan §16).
        $teacherId = $user->hasRole('staff/admin')
            ? (int) ($request->validated('teacher_id') ?? $user->id)
            : $user->id;

        if (! $user->hasRole('staff/admin') && ! $user->hasRole('teacher')) {
            abort(403);
        }

        try {
            $recordGrade->handle(new RecordGradeData(
                academicOfferId: (int) $request->validated('academic_offer_id'),
                studentId: (int) $request->validated('student_id'),
                teacherId: $teacherId,
                value: (float) $request->validated('value'),
                actorId: $user->id,
                actorSchoolId: $user->school_id,
                activeAcademicPeriodId: (int) $activePeriod->id,
                actorCanDelegate: $user->hasRole('staff/admin'),
            ));
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['academic_offer_id' => $e->getMessage()]);
        }

        return redirect()->route('grades.create')->with('success', 'Grade recorded successfully.');
    }

    public function update(UpdateGradeRequest $request, Grade $grade, UpdateGrade $updateGrade): RedirectResponse
    {
        $this->authorize('update', $grade);

        $updateGrade->handle($grade->id, (float) $request->validated('value'));

        return redirect()->route('grades.create')->with('success', 'Grade updated successfully.');
    }

    public function destroy(Grade $grade): RedirectResponse
    {
        $this->authorize('delete', $grade);

        $grade->delete();

        return redirect()->route('grades.create')->with('success', 'Grade removed.');
    }
}
