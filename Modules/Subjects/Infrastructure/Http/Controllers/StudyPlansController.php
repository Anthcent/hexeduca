<?php

namespace Modules\Subjects\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AcademicPeriods\Public\Contracts\AcademicPeriodReader;
use Modules\Subjects\Application\DTOs\StudyPlanData;
use Modules\Subjects\Application\Queries\AssignmentLabeler;
use Modules\Subjects\Application\Services\OpenPeriods;
use Modules\Subjects\Application\UseCases\ArchiveStudyPlan;
use Modules\Subjects\Application\UseCases\CreateStudyPlan;
use Modules\Subjects\Application\UseCases\DeleteStudyPlan;
use Modules\Subjects\Application\UseCases\ReactivateStudyPlan;
use Modules\Subjects\Application\UseCases\ReviewStudyPlanReactivation;
use Modules\Subjects\Application\UseCases\UpdateStudyPlan;
use Modules\Subjects\Domain\Entities\PlanAssignment;
use Modules\Subjects\Domain\Entities\StudyPlan;
use Modules\Subjects\Domain\Entities\Subject;
use Modules\Subjects\Domain\Repositories\PlanAssignmentRepositoryInterface;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Domain\Repositories\SubjectRepositoryInterface;
use Modules\Subjects\Domain\ValueObjects\RecordStatus;
use Modules\Subjects\Domain\ValueObjects\SlotRestoration;
use Modules\Subjects\Infrastructure\Http\Controllers\Concerns\HandlesDomainErrors;
use Modules\Subjects\Infrastructure\Http\Requests\ReactivateStudyPlanRequest;
use Modules\Subjects\Infrastructure\Http\Requests\StudyPlanRequest;
use Modules\Subjects\Infrastructure\Models\StudyPlanModel;
use Modules\Subjects\Infrastructure\Models\SubjectModel;

class StudyPlansController extends Controller
{
    use HandlesDomainErrors;

    private const PER_PAGE = 15;

    public function index(
        Request $request,
        TenantContext $tenantContext,
        AcademicPeriodReader $periods,
        PlanAssignmentRepositoryInterface $assignments,
    ): Response {
        $schoolId = $tenantContext->current()->id;
        $status = $request->query('status') === RecordStatus::Archived->value ? RecordStatus::Archived : RecordStatus::Active;
        $search = trim((string) $request->query('search', ''));
        $activePeriod = $periods->activeForSchool($schoolId);

        $plans = StudyPlanModel::query()
            ->where('school_id', $schoolId)
            ->where('status', $status->value)
            // Accent- and case-insensitive on every database: both sides are
            // normalized in PHP (see StudyPlanModel::searchText).
            // `%`, `_` and `\` in the term match literally; SQLite has no default
            // LIKE escape character, so it is declared explicitly.
            ->when($search !== '', fn ($query) => $query->whereRaw("search_text LIKE ? ESCAPE '\\'", [
                '%'.addcslashes(StudyPlanModel::searchText($search), '\\%_').'%',
            ]))
            ->withCount(['subjects' => fn ($query) => $query->where('status', RecordStatus::Active->value)])
            // Plans in use in the active period come first.
            ->when($activePeriod !== null, fn ($query) => $query
                ->withExists(['assignments as in_use' => fn ($assigned) => $assigned
                    ->where('academic_period_id', $activePeriod->id)
                    ->whereNull('replaced_at')])
                ->orderByDesc('in_use'))
            ->orderBy('code')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            // The page links keep the filters: PaginationBar builds them from `path`.
            ->withPath($request->fullUrlWithoutQuery(['page']));

        $planIds = $plans->getCollection()->pluck('id')->all();
        $coverage = $this->gradeCoverage($schoolId, $planIds);
        $usage = $activePeriod ? $this->usageInPeriod($schoolId, $activePeriod->id, $planIds, $assignments) : [];

        $plans->through(fn (StudyPlanModel $plan): array => [
            'id' => $plan->id,
            'code' => $plan->code,
            'observation' => $plan->observation,
            'name' => $plan->name,
            'status' => $plan->status,
            'subjectCount' => $plan->subjects_count,
            'weeklyHours' => array_sum(array_column($coverage[$plan->id] ?? [], 'weeklyHours')),
            'grades' => $coverage[$plan->id] ?? [],
            'usage' => $usage[$plan->id] ?? [],
        ]);

        $counts = StudyPlanModel::query()->where('school_id', $schoolId)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Subjects::Index', [
            'plans' => $plans,
            'filters' => ['search' => $search, 'status' => $status->value],
            'counts' => [
                'active' => (int) ($counts[RecordStatus::Active->value] ?? 0),
                'archived' => (int) ($counts[RecordStatus::Archived->value] ?? 0),
            ],
            'planCodes' => $this->planCodes($schoolId),
            'activePeriod' => $activePeriod ? ['id' => $activePeriod->id, 'name' => $activePeriod->name] : null,
        ]);
    }

    public function store(StudyPlanRequest $request, CreateStudyPlan $useCase, TenantContext $tenantContext): RedirectResponse
    {
        return $this->attempt(
            fn () => redirect()->route('subjects.plans.show', $useCase->handle($this->planData($request, $tenantContext))->id()),
            'Plan de estudio creado.',
        );
    }

    public function show(
        int $plan,
        TenantContext $tenantContext,
        StudyPlanRepositoryInterface $plans,
        SubjectRepositoryInterface $subjects,
        PlanAssignmentRepositoryInterface $assignments,
        OpenPeriods $openPeriods,
    ): Response {
        $schoolId = $tenantContext->current()->id;
        $studyPlan = $plans->findInSchool($plan, $schoolId) ?? abort(404);
        $labels = $this->labeler($schoolId);

        $planSubjects = $subjects->forPlan($plan, $schoolId);
        $openPeriodIds = $openPeriods->idsForSchool($schoolId);
        $excludedSubjectIds = array_flip($subjects->excludedInPeriods(
            array_map(fn (Subject $s): int => (int) $s->id(), $planSubjects),
            $schoolId,
            $openPeriodIds,
        ));

        $coveredGrades = [];
        $gradeLevels = $labels->gradeLevels();

        foreach ($gradeLevels as $gradeLevel) {
            $coveredGrades[$gradeLevel->id] = $assignments->planCoversGradeLevelInPeriods($plan, $gradeLevel->id, $schoolId, $openPeriodIds);
        }

        $currentAssignments = $assignments->currentForPlan($plan, $schoolId);
        $activePeriod = array_values(array_filter($labels->periods(), fn ($period): bool => $period->isActive))[0] ?? null;

        return Inertia::render('Subjects::Show', [
            'plan' => AssignmentLabeler::plan($studyPlan),
            'canDelete' => StudyPlan::canBeDeleted(count($planSubjects), $assignments->countForPlan($plan, $schoolId)),
            'gradeLevels' => array_values(array_map(fn ($g): array => ['id' => $g->id, 'name' => $g->name], $gradeLevels)),
            'subjects' => array_map(fn (Subject $subject): array => [
                'id' => (int) $subject->id(),
                'gradeLevelId' => $subject->gradeLevelId(),
                'name' => $subject->name(),
                'code' => $subject->code(),
                'weeklyHours' => $subject->weeklyHours(),
                'status' => $subject->status()->value,
                'canDelete' => Subject::canBeDeleted(
                    $coveredGrades[$subject->gradeLevelId()] ?? $assignments->planCoversGradeLevelInPeriods($plan, $subject->gradeLevelId(), $schoolId, $openPeriodIds),
                    isset($excludedSubjectIds[$subject->id()]),
                ),
            ], $planSubjects),
            'assignments' => array_map(fn (PlanAssignment $a): array => $labels->assignment($a), $currentAssignments),
            'planCodes' => $this->planCodes($schoolId),
            'activePeriod' => $activePeriod ? ['id' => $activePeriod->id, 'name' => $activePeriod->name] : null,
        ]);
    }

    public function update(int $plan, StudyPlanRequest $request, UpdateStudyPlan $useCase, TenantContext $tenantContext): RedirectResponse
    {
        return $this->attempt(fn () => $useCase->handle($plan, $this->planData($request, $tenantContext)), 'Plan de estudio actualizado.');
    }

    public function destroy(int $plan, DeleteStudyPlan $useCase, TenantContext $tenantContext): RedirectResponse
    {
        return $this->attempt(function () use ($plan, $useCase, $tenantContext) {
            $useCase->handle($plan, $tenantContext->current()->id);

            return redirect()->route('subjects.index');
        }, 'Plan de estudio eliminado.');
    }

    public function archive(int $plan, ArchiveStudyPlan $useCase, TenantContext $tenantContext): RedirectResponse
    {
        return $this->attempt(fn () => $useCase->handle($plan, $tenantContext->current()->id), 'Plan de estudio archivado.');
    }

    public function reviewReactivation(
        int $plan,
        ReviewStudyPlanReactivation $review,
        TenantContext $tenantContext,
        StudyPlanRepositoryInterface $plans,
        SubjectRepositoryInterface $subjects,
        PlanAssignmentRepositoryInterface $assignments,
    ): Response|RedirectResponse {
        $schoolId = $tenantContext->current()->id;
        $studyPlan = $plans->findInSchool($plan, $schoolId) ?? abort(404);

        if (! $studyPlan->isArchived()) {
            return redirect()->route('subjects.plans.show', $plan);
        }

        try {
            $impact = $review->handle($plan, $schoolId);
        } catch (DomainException $e) {
            return $this->domainErrorResponse($e);
        }

        $labels = $this->labeler($schoolId);
        $allPlans = $plans->allInSchool($schoolId);
        $planSubjects = $subjects->forPlan($plan, $schoolId);

        return Inertia::render('Subjects::Reactivation', [
            'kind' => 'plan',
            'plan' => AssignmentLabeler::plan($studyPlan),
            'subject' => null,
            'summary' => [
                'subjectCount' => count($planSubjects),
                'archivedSubjectCount' => count(array_filter($planSubjects, fn (Subject $s): bool => $s->isArchived())),
                'assignments' => array_map(fn (PlanAssignment $a): array => $labels->assignment($a), $assignments->currentForPlan($plan, $schoolId)),
            ],
            'restorations' => array_map(fn (SlotRestoration $r): array => [
                'restored' => $labels->assignment($r->restored),
                'holder' => $r->holder ? $labels->assignment($r->holder, $allPlans) : null,
            ], $impact->restorations),
            'conflictIds' => $impact->replacedHolderIds(),
            'coverage' => [],
            'blockedReason' => null,
        ]);
    }

    public function reactivate(int $plan, ReactivateStudyPlanRequest $request, ReactivateStudyPlan $useCase, TenantContext $tenantContext): RedirectResponse
    {
        return $this->attempt(function () use ($plan, $request, $useCase, $tenantContext) {
            $useCase->handle($plan, $tenantContext->current()->id, $request->approvedAssignmentIds());

            return redirect()->route('subjects.plans.show', $plan);
        }, 'Plan de estudio reactivado.');
    }

    private function planData(StudyPlanRequest $request, TenantContext $tenantContext): StudyPlanData
    {
        return new StudyPlanData(
            schoolId: $tenantContext->current()->id,
            code: $request->string('code')->toString(),
            name: $request->string('name')->toString(),
            observation: $request->input('observation'),
        );
    }

    /**
     * Every plan code of the school (active and archived), so the form can
     * warn about a repeated code before saving.
     *
     * @return list<array{id: int, code: string}>
     */
    private function planCodes(int $schoolId): array
    {
        return StudyPlanModel::query()
            ->where('school_id', $schoolId)
            ->get(['id', 'code'])
            ->map(fn (StudyPlanModel $plan): array => ['id' => $plan->id, 'code' => $plan->code])
            ->all();
    }

    /**
     * Active subjects per grade level for each plan, in grade level order.
     *
     * @param  list<int>  $planIds
     * @return array<int, list<array{gradeLevelId: int, name: string, subjectCount: int, weeklyHours: int}>>
     */
    private function gradeCoverage(int $schoolId, array $planIds): array
    {
        if ($planIds === []) {
            return [];
        }

        $gradeLevels = $this->labeler($schoolId)->gradeLevels();
        $order = array_flip(array_keys($gradeLevels));
        $coverage = [];

        $rows = SubjectModel::query()
            ->where('school_id', $schoolId)
            ->whereIn('study_plan_id', $planIds)
            ->where('status', RecordStatus::Active->value)
            ->selectRaw('study_plan_id, grade_level_id, COUNT(*) as total, COALESCE(SUM(weekly_hours), 0) as hours')
            ->groupBy('study_plan_id', 'grade_level_id')
            ->get();

        foreach ($rows as $row) {
            $coverage[(int) $row->study_plan_id][] = [
                'gradeLevelId' => (int) $row->grade_level_id,
                'name' => $gradeLevels[(int) $row->grade_level_id]->name ?? '—',
                'subjectCount' => (int) $row->total,
                'weeklyHours' => (int) $row->hours,
            ];
        }

        foreach ($coverage as &$grades) {
            usort($grades, fn (array $a, array $b): int => ($order[$a['gradeLevelId']] ?? PHP_INT_MAX) <=> ($order[$b['gradeLevelId']] ?? PHP_INT_MAX));
        }

        return $coverage;
    }

    /**
     * Where each plan is assigned in the given period: scope and target label.
     *
     * @param  list<int>  $planIds
     * @return array<int, list<array{scope: string, targetLabel: string}>>
     */
    private function usageInPeriod(int $schoolId, int $periodId, array $planIds, PlanAssignmentRepositoryInterface $assignments): array
    {
        $wanted = array_flip($planIds);
        $labels = $this->labeler($schoolId);
        $usage = [];

        foreach ($assignments->currentForPeriod($schoolId, $periodId) as $assignment) {
            if (isset($wanted[$assignment->planId()])) {
                $usage[$assignment->planId()][] = [
                    'scope' => $assignment->scope()->value,
                    'targetLabel' => $labels->targetLabel($assignment),
                ];
            }
        }

        return $usage;
    }

    private function labeler(int $schoolId): AssignmentLabeler
    {
        return app()->make(AssignmentLabeler::class, ['schoolId' => $schoolId]);
    }
}
