<?php

namespace Modules\Subjects\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Subjects\Application\DTOs\SubjectData;
use Modules\Subjects\Application\Queries\AssignmentLabeler;
use Modules\Subjects\Application\Services\OpenPeriods;
use Modules\Subjects\Application\UseCases\ChangeSubjectStatus;
use Modules\Subjects\Application\UseCases\CreateSubject;
use Modules\Subjects\Application\UseCases\CreateSubjects;
use Modules\Subjects\Application\UseCases\DeleteSubject;
use Modules\Subjects\Application\UseCases\UpdateSubject;
use Modules\Subjects\Domain\Entities\PlanAssignment;
use Modules\Subjects\Domain\Repositories\PlanAssignmentRepositoryInterface;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Domain\Repositories\SubjectRepositoryInterface;
use Modules\Subjects\Infrastructure\Http\Controllers\Concerns\HandlesDomainErrors;
use Modules\Subjects\Infrastructure\Http\Requests\SubjectBatchRequest;
use Modules\Subjects\Infrastructure\Http\Requests\SubjectRequest;

class SubjectsController extends Controller
{
    use HandlesDomainErrors;

    public function store(int $plan, SubjectRequest $request, CreateSubject $useCase, TenantContext $tenantContext): RedirectResponse
    {
        return $this->attempt(fn () => $useCase->handle($this->subjectData($plan, $request, $tenantContext)), 'Asignatura agregada.');
    }

    public function storeMany(int $plan, SubjectBatchRequest $request, CreateSubjects $useCase, TenantContext $tenantContext): RedirectResponse
    {
        $count = count($request->names());

        return $this->attempt(
            fn () => $useCase->handle($tenantContext->current()->id, $plan, $request->integer('grade_level_id'), $request->names()),
            $count === 1 ? 'Asignatura agregada.' : "{$count} asignaturas agregadas.",
        );
    }

    public function update(int $plan, int $subject, SubjectRequest $request, UpdateSubject $useCase, TenantContext $tenantContext): RedirectResponse
    {
        return $this->attempt(fn () => $useCase->handle($subject, $this->subjectData($plan, $request, $tenantContext)), 'Asignatura actualizada.');
    }

    public function destroy(int $plan, int $subject, DeleteSubject $useCase, TenantContext $tenantContext): RedirectResponse
    {
        return $this->attempt(fn () => $useCase->handle($subject, $plan, $tenantContext->current()->id), 'Asignatura eliminada.');
    }

    public function archive(int $plan, int $subject, ChangeSubjectStatus $useCase, TenantContext $tenantContext): RedirectResponse
    {
        return $this->attempt(fn () => $useCase->archive($subject, $plan, $tenantContext->current()->id), 'Asignatura archivada.');
    }

    /**
     * A subject holds no slots, so its reactivation has no conflicts: the
     * review shows where it would be active again and asks to confirm.
     */
    public function reviewReactivation(
        int $plan,
        int $subject,
        TenantContext $tenantContext,
        StudyPlanRepositoryInterface $plans,
        SubjectRepositoryInterface $subjects,
        PlanAssignmentRepositoryInterface $assignments,
        OpenPeriods $openPeriods,
    ): Response|RedirectResponse {
        $schoolId = $tenantContext->current()->id;
        $studyPlan = $plans->findInSchool($plan, $schoolId) ?? abort(404);
        $planSubject = $subjects->findInPlan($subject, $plan, $schoolId) ?? abort(404);

        if (! $planSubject->isArchived()) {
            return redirect()->route('subjects.plans.show', $plan);
        }

        $labels = app()->make(AssignmentLabeler::class, ['schoolId' => $schoolId]);
        $openPeriodIds = array_flip($openPeriods->idsForSchool($schoolId));
        $covering = array_values(array_filter(
            $assignments->currentForPlan($plan, $schoolId),
            fn (PlanAssignment $a): bool => isset($openPeriodIds[$a->periodId()]) && $a->coversGradeLevel($planSubject->gradeLevelId()),
        ));
        $excluded = $assignments->excludedSubjectIdsFor(array_map(fn (PlanAssignment $a): int => (int) $a->id(), $covering), $schoolId);

        return Inertia::render('Subjects::Reactivation', [
            'kind' => 'subject',
            'plan' => AssignmentLabeler::plan($studyPlan),
            'subject' => [
                'id' => (int) $planSubject->id(),
                'name' => $planSubject->name(),
                'code' => $planSubject->code(),
                'weeklyHours' => $planSubject->weeklyHours(),
                'gradeLevelName' => $labels->gradeLevels()[$planSubject->gradeLevelId()]->name ?? '—',
            ],
            'summary' => null,
            'restorations' => [],
            'conflictIds' => [],
            'coverage' => array_map(fn (PlanAssignment $a): array => $labels->assignment($a) + [
                'excluded' => in_array($planSubject->id(), $excluded[(int) $a->id()] ?? [], true),
            ], $covering),
            'blockedReason' => $studyPlan->isArchived() ? 'plan-archived' : null,
        ]);
    }

    public function reactivate(int $plan, int $subject, ChangeSubjectStatus $useCase, TenantContext $tenantContext): RedirectResponse
    {
        return $this->attempt(function () use ($plan, $subject, $useCase, $tenantContext) {
            $useCase->reactivate($subject, $plan, $tenantContext->current()->id);

            return redirect()->route('subjects.plans.show', $plan);
        }, 'Asignatura reactivada.');
    }

    private function subjectData(int $plan, SubjectRequest $request, TenantContext $tenantContext): SubjectData
    {
        return new SubjectData(
            schoolId: $tenantContext->current()->id,
            planId: $plan,
            gradeLevelId: $request->integer('grade_level_id'),
            name: $request->string('name')->toString(),
            code: $request->input('code'),
            weeklyHours: $request->filled('weekly_hours') ? $request->integer('weekly_hours') : null,
        );
    }
}
