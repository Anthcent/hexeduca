<?php

namespace Modules\Subjects\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AcademicPeriods\Public\DTOs\AcademicPeriodDTO;
use Modules\Subjects\Application\DTOs\AssignStudyPlanData;
use Modules\Subjects\Application\Queries\AssignmentBoard;
use Modules\Subjects\Application\Queries\AssignmentLabeler;
use Modules\Subjects\Application\UseCases\AssignStudyPlan;
use Modules\Subjects\Application\UseCases\OverrideOfferSubject;
use Modules\Subjects\Application\UseCases\RemovePlanAssignment;
use Modules\Subjects\Application\UseCases\SetSubjectExclusion;
use Modules\Subjects\Domain\Entities\StudyPlan;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Domain\ValueObjects\AssignmentScope;
use Modules\Subjects\Infrastructure\Http\Controllers\Concerns\HandlesDomainErrors;
use Modules\Subjects\Infrastructure\Http\Requests\AssignStudyPlanRequest;
use Modules\Subjects\Infrastructure\Http\Requests\SubjectExclusionRequest;

class PlanAssignmentsController extends Controller
{
    use HandlesDomainErrors;

    /**
     * One period at a time: `?period=` or, by default, the active period
     * (else the newest one).
     */
    public function index(Request $request, TenantContext $tenantContext, AssignmentBoard $board, StudyPlanRepositoryInterface $plans): Response
    {
        $schoolId = $tenantContext->current()->id;
        $labels = app()->make(AssignmentLabeler::class, ['schoolId' => $schoolId]);
        $periods = $labels->periods();

        $requested = $request->integer('period');
        $period = $periods[$requested] ?? null;
        $period ??= collect($periods)->first(fn (AcademicPeriodDTO $p): bool => $p->isActive) ?? (reset($periods) ?: null);

        $activePlans = array_values(array_filter($plans->allInSchool($schoolId), fn (StudyPlan $plan): bool => ! $plan->isArchived()));
        usort($activePlans, fn (StudyPlan $a, StudyPlan $b): int => [$a->code(), $a->id()] <=> [$b->code(), $b->id()]);

        return Inertia::render('Subjects::Assignments', [
            'periods' => array_values(array_map(fn (AcademicPeriodDTO $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'isActive' => $p->isActive,
            ], $periods)),
            'period' => $period ? ['id' => $period->id, 'name' => $period->name, 'isActive' => $period->isActive] : null,
            'plans' => array_map(fn (StudyPlan $plan): array => AssignmentLabeler::plan($plan), $activePlans),
            'gradeLevels' => array_values(array_map(fn ($g): array => ['id' => $g->id, 'name' => $g->name], $labels->gradeLevels())),
            'board' => $period ? $board->build($schoolId, $period->id, $labels) : ['assignments' => ['school' => null, 'gradeLevels' => [], 'offers' => []], 'offers' => []],
        ]);
    }

    public function store(AssignStudyPlanRequest $request, AssignStudyPlan $useCase, TenantContext $tenantContext): RedirectResponse
    {
        return $this->attempt(fn () => $useCase->handle(new AssignStudyPlanData(
            schoolId: $tenantContext->current()->id,
            periodId: $request->integer('period_id'),
            planId: $request->integer('plan_id'),
            scope: AssignmentScope::from($request->string('scope')->toString()),
            gradeLevelId: $request->filled('grade_level_id') ? $request->integer('grade_level_id') : null,
            offerId: $request->filled('offer_id') ? $request->integer('offer_id') : null,
        )), 'Plan asignado.');
    }

    public function destroy(int $assignment, RemovePlanAssignment $useCase, TenantContext $tenantContext): RedirectResponse
    {
        return $this->attempt(fn () => $useCase->handle($assignment, $tenantContext->current()->id), 'Asignación quitada.');
    }

    public function setExclusion(int $assignment, SubjectExclusionRequest $request, SetSubjectExclusion $useCase, TenantContext $tenantContext): RedirectResponse
    {
        $excluded = $request->boolean('excluded');

        return $this->attempt(
            fn () => $useCase->handle($assignment, $request->integer('subject_id'), $excluded, $tenantContext->current()->id),
            $excluded ? 'Asignatura excluida.' : 'Asignatura incluida.',
        );
    }

    public function overrideOffer(SubjectExclusionRequest $request, OverrideOfferSubject $useCase, TenantContext $tenantContext): RedirectResponse
    {
        return $this->attempt(fn () => $useCase->handle(
            $tenantContext->current()->id,
            $request->integer('period_id'),
            $request->integer('offer_id'),
            $request->integer('subject_id'),
            $request->boolean('excluded'),
        ), 'Se aplicó el cambio solo a esta sección.');
    }
}
