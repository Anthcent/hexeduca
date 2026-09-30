<?php

namespace Modules\Grades\Infrastructure\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\AcademicPeriods\Public\Contracts\AcademicPeriodReader;
use Modules\AcademicPeriods\Public\DTOs\AcademicPeriodDTO;
use Modules\Grades\Application\DTOs\Actor;
use Modules\Grades\Application\Services\GradeAccess;
use Modules\Grades\Application\UseCases\RecordGrade;
use Modules\Grades\Application\UseCases\SaveEvaluationPlan;
use Modules\Grades\Domain\Exceptions\GradingRefused;
use Modules\Grades\Domain\Exceptions\InvalidPlan;
use Modules\Grades\Domain\Repositories\GradeBookRepositoryInterface;
use Modules\Grades\Domain\ValueObjects\PlanStructure;
use Modules\Grades\Infrastructure\Http\Requests\RecordGradeRequest;
use Modules\Grades\Infrastructure\Http\Requests\SavePlanRequest;
use Modules\Grades\Infrastructure\Queries\GradeSheetQuery;
use Modules\Grades\Infrastructure\Queries\MySubjectsQuery;

class GradesController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext, AcademicPeriodReader $periods, MySubjectsQuery $query): Response
    {
        $actor = $this->actor($request, $tenantContext);
        $all = $periods->allForSchool($actor->schoolId);
        usort($all, fn (AcademicPeriodDTO $a, AcademicPeriodDTO $b): int => strcmp($b->startsOn, $a->startsOn));

        $requested = (int) $request->query('period', 0);
        $selected = array_values(array_filter($all, fn (AcademicPeriodDTO $p): bool => $p->id === $requested))[0]
            ?? array_values(array_filter($all, fn (AcademicPeriodDTO $p): bool => $p->isActive))[0]
            ?? ($all[0] ?? null);

        return Inertia::render('Grades::Index', [
            'periods' => array_map(fn (AcademicPeriodDTO $p): array => ['id' => $p->id, 'name' => $p->name, 'isActive' => $p->isActive], $all),
            'period' => $selected ? ['id' => $selected->id, 'name' => $selected->name] : null,
            'isStaff' => $actor->isStaff,
            'cards' => $selected ? $query->forPeriod($actor, $selected->id) : [],
        ]);
    }

    public function plan(Request $request, TenantContext $tenantContext, GradeAccess $access, GradeBookRepositoryInterface $book): Response
    {
        $actor = $this->actor($request, $tenantContext);
        $offerId = (int) $request->query('offer');
        $subjectId = (int) $request->query('subject');
        $momentId = (int) $request->query('moment');

        $slot = $this->slotOrFail($access, $actor, $offerId, $subjectId, $momentId);
        $plan = $book->findPlanForSlot($actor->schoolId, $offerId, $subjectId, $momentId);

        return Inertia::render('Grades::Plan', [
            'context' => $this->slotProps($slot),
            'plan' => $plan ? ['id' => $plan->id, 'referents' => $plan->referents] : null,
            'locked' => $plan !== null && $book->hasScores($plan->id, $actor->schoolId),
            'periodOpen' => $access->periodOpen($slot['moment']->periodId, $actor->schoolId),
            'limits' => ['referents' => PlanStructure::MAX_REFERENTS, 'indicators' => PlanStructure::MAX_INDICATORS, 'points' => PlanStructure::REFERENT_POINTS],
        ]);
    }

    public function savePlan(SavePlanRequest $request, TenantContext $tenantContext, SaveEvaluationPlan $useCase): RedirectResponse
    {
        $actor = $this->actor($request, $tenantContext);

        try {
            $planId = $useCase->handle(
                $actor,
                $request->integer('offer_id'),
                $request->integer('subject_id'),
                $request->integer('moment_id'),
                PlanStructure::fromArray($request->referents()),
            );
        } catch (InvalidPlan $e) {
            throw ValidationException::withMessages(['referents' => $e->getMessage()]);
        } catch (GradingRefused $e) {
            abort_if($e->status === 403, 403, $e->getMessage());

            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('grades.sheet', $planId)->with('success', 'Plan de evaluación guardado.');
    }

    public function sheet(int $plan, Request $request, TenantContext $tenantContext, GradeAccess $access, GradeBookRepositoryInterface $book, GradeSheetQuery $query): Response
    {
        $actor = $this->actor($request, $tenantContext);
        $evaluationPlan = $book->findPlan($plan, $actor->schoolId) ?? abort(404);
        $slot = $this->slotOrFail($access, $actor, $evaluationPlan->offerId, $evaluationPlan->subjectId, $evaluationPlan->momentId);

        return Inertia::render('Grades::Sheet', [
            'context' => $this->slotProps($slot),
            'plan' => ['id' => $evaluationPlan->id, 'referents' => $evaluationPlan->referents],
            'sheet' => $query->forPlan($actor, $evaluationPlan),
        ]);
    }

    public function record(int $plan, RecordGradeRequest $request, TenantContext $tenantContext, RecordGrade $useCase): JsonResponse
    {
        $actor = $this->actor($request, $tenantContext);

        try {
            $row = $useCase->handle(
                $actor,
                $plan,
                $request->integer('student_id'),
                $request->filled('indicator_id') ? $request->integer('indicator_id') : null,
                $request->input('points') === null ? null : $request->integer('points'),
            );
        } catch (GradingRefused $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json(['row' => $row]);
    }

    private function actor(Request $request, TenantContext $tenantContext): Actor
    {
        $user = $request->user();

        return new Actor(
            id: (int) $user->id,
            schoolId: $tenantContext->current()->id,
            isStaff: $user->hasRole('staff/admin'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function slotOrFail(GradeAccess $access, Actor $actor, int $offerId, int $subjectId, int $momentId): array
    {
        try {
            $slot = $access->slot($actor->schoolId, $offerId, $subjectId, $momentId);
        } catch (GradingRefused) {
            abort(404);
        }

        abort_unless($access->canManage($actor, $offerId, $subjectId), 403);

        return $slot;
    }

    /**
     * @param  array<string, mixed>  $slot
     * @return array<string, mixed>
     */
    private function slotProps(array $slot): array
    {
        return [
            'offerId' => $slot['offer']->id,
            'offerLabel' => "{$slot['offer']->gradeLevelName} · Sección {$slot['offer']->sectionName}",
            'subjectId' => $slot['subject']->id,
            'subjectName' => $slot['subject']->name,
            'momentId' => $slot['moment']->id,
            'momentName' => $slot['moment']->name,
        ];
    }
}
