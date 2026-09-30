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
use Modules\AcademicMoments\Public\Contracts\AcademicMomentReader;
use Modules\AcademicOffers\Public\Contracts\AcademicOfferReader;
use Modules\AcademicOffers\Public\DTOs\AcademicOfferSummary;
use Modules\AcademicPeriods\Public\Contracts\AcademicPeriodReader;
use Modules\AcademicPeriods\Public\DTOs\AcademicPeriodDTO;
use Modules\Grades\Application\DTOs\Actor;
use Modules\Grades\Application\Services\GradeAccess;
use Modules\Grades\Application\UseCases\ManageCorrection;
use Modules\Grades\Application\UseCases\RecordConduct;
use Modules\Grades\Application\UseCases\RecordGrade;
use Modules\Grades\Application\UseCases\SaveEvaluationPlan;
use Modules\Grades\Domain\Exceptions\GradingRefused;
use Modules\Grades\Domain\Exceptions\InvalidPlan;
use Modules\Grades\Domain\Repositories\ConductBookRepositoryInterface;
use Modules\Grades\Domain\Repositories\GradeBookRepositoryInterface;
use Modules\Grades\Domain\Services\MomentGrade;
use Modules\Grades\Domain\Services\YearOutcome;
use Modules\Grades\Domain\ValueObjects\PlanStructure;
use Modules\Grades\Infrastructure\Http\Requests\RecordGradeRequest;
use Modules\Grades\Infrastructure\Http\Requests\SavePlanRequest;
use Modules\Grades\Infrastructure\Queries\GradeHistoryQuery;
use Modules\Grades\Infrastructure\Queries\GradeMonitorQuery;
use Modules\Grades\Infrastructure\Queries\GradeSheetQuery;
use Modules\Grades\Infrastructure\Queries\MySubjectsQuery;
use Modules\Grades\Infrastructure\Queries\OfferRoster;
use Modules\Grades\Infrastructure\Queries\YearSummaryQuery;
use Modules\Subjects\Public\Contracts\OfferSubjectsReader;

class GradesController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext, AcademicPeriodReader $periods, AcademicOfferReader $offers, MySubjectsQuery $query): Response
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
            // Sections whose Convivir the teacher gives, as their orientador.
            'homerooms' => $selected && ! $actor->isStaff ? array_values(array_map(
                fn (AcademicOfferSummary $o): array => ['id' => $o->id, 'label' => "{$o->gradeLevelName} · Sección {$o->sectionName}"],
                array_filter($offers->allForPeriod($actor->schoolId, $selected->id), fn (AcademicOfferSummary $o): bool => $o->teacherId === $actor->id),
            )) : [],
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

    public function history(int $plan, Request $request, TenantContext $tenantContext, GradeAccess $access, GradeBookRepositoryInterface $book, GradeHistoryQuery $query): JsonResponse
    {
        $actor = $this->actor($request, $tenantContext);
        $evaluationPlan = $book->findPlan($plan, $actor->schoolId) ?? abort(404);
        abort_unless($access->canManage($actor, $evaluationPlan->offerId, $evaluationPlan->subjectId), 403);

        return response()->json($query->forPlan($evaluationPlan));
    }

    public function openCorrection(int $plan, Request $request, TenantContext $tenantContext, ManageCorrection $useCase): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'expires_on' => ['required', 'date_format:Y-m-d'],
        ], [
            'reason.required' => 'Indica el motivo de la corrección.',
            'reason.max' => 'El motivo puede tener hasta 500 caracteres.',
            'expires_on.*' => 'Indica hasta qué día estará abierta la corrección.',
        ]);

        return $this->correction(fn () => $useCase->open($this->actor($request, $tenantContext), $plan, $validated['reason'], $validated['expires_on']), 'Corrección abierta.');
    }

    public function closeCorrection(int $plan, Request $request, TenantContext $tenantContext, ManageCorrection $useCase): RedirectResponse
    {
        return $this->correction(fn () => $useCase->close($this->actor($request, $tenantContext), $plan), 'Corrección cerrada.');
    }

    public function monitor(Request $request, TenantContext $tenantContext, AcademicPeriodReader $periods, AcademicMomentReader $moments, GradeAccess $access, GradeMonitorQuery $query): Response
    {
        $actor = $this->actor($request, $tenantContext);
        abort_unless($actor->isStaff, 403);

        $period = $periods->activeForSchool($actor->schoolId);
        $all = $period ? $moments->forPeriod($actor->schoolId, $period->id) : [];
        $requested = (int) $request->query('moment', 0);
        $moment = array_values(array_filter($all, fn ($m): bool => $m->id === $requested))[0] ?? null;

        // Default: the moment whose grading window is open, else the last one.
        $moment ??= array_values(array_filter($all, fn ($m): bool => $access->windowOpen($m)))[0] ?? (end($all) ?: null);

        return Inertia::render('Grades::Monitor', [
            'period' => $period ? ['id' => $period->id, 'name' => $period->name] : null,
            'moments' => array_map(fn ($m): array => ['id' => $m->id, 'name' => $m->name, 'gradingOpensOn' => $m->gradingOpensOn, 'gradingClosesOn' => $m->gradingClosesOn], $all),
            'moment' => $moment ? ['id' => $moment->id, 'name' => $moment->name, 'windowOpen' => $access->windowOpen($moment)] : null,
            'rows' => $moment ? $query->forMoment($actor->schoolId, $moment) : [],
        ]);
    }

    public function year(Request $request, TenantContext $tenantContext, AcademicPeriodReader $periods, GradeAccess $access, YearSummaryQuery $query): Response
    {
        $actor = $this->actor($request, $tenantContext);
        $period = $periods->findForSchool((int) $request->query('period'), $actor->schoolId) ?? abort(404);
        $offerId = (int) $request->query('offer');
        $subjectId = (int) $request->query('subject');

        try {
            ['offer' => $offer, 'subject' => $subject] = $access->offerSubject($actor->schoolId, $period->id, $offerId, $subjectId);
        } catch (GradingRefused) {
            abort(404);
        }

        abort_unless($access->canManage($actor, $offerId, $subjectId), 403);

        return Inertia::render('Grades::Year', [
            'context' => [
                'periodId' => $period->id,
                'periodName' => $period->name,
                'offerLabel' => "{$offer->gradeLevelName} · Sección {$offer->sectionName}",
                'subjectName' => $subject->name,
            ],
            'summary' => $query->forSubject($actor->schoolId, $period->id, $offerId, $subjectId),
            'pass' => MomentGrade::PASS,
        ]);
    }

    public function results(Request $request, TenantContext $tenantContext, AcademicPeriodReader $periods, AcademicOfferReader $offers, OfferSubjectsReader $offerSubjects, YearSummaryQuery $query): Response
    {
        $actor = $this->actor($request, $tenantContext);
        abort_unless($actor->isStaff, 403);

        $period = $periods->findForSchool((int) $request->query('period'), $actor->schoolId) ?? abort(404);
        $all = $offers->allForPeriod($actor->schoolId, $period->id);
        usort($all, fn (AcademicOfferSummary $a, AcademicOfferSummary $b): int => [$a->gradeLevelName, $a->sectionName] <=> [$b->gradeLevelName, $b->sectionName]);

        $requested = (int) $request->query('offer', 0);
        $offer = array_values(array_filter($all, fn (AcademicOfferSummary $o): bool => $o->id === $requested))[0] ?? null;
        abort_if($requested !== 0 && $offer === null, 404);
        $offer ??= $all[0] ?? null;

        $subjects = $offer ? $offerSubjects->forOffer($actor->schoolId, $period->id, $offer->id) : [];
        usort($subjects, fn ($a, $b): int => strcasecmp($a->name, $b->name));

        return Inertia::render('Grades::Results', [
            'period' => ['id' => $period->id, 'name' => $period->name],
            'offers' => array_map(fn (AcademicOfferSummary $o): array => ['id' => $o->id, 'label' => "{$o->gradeLevelName} · Sección {$o->sectionName}"], $all),
            'offerId' => $offer?->id,
            'subjects' => array_map(fn ($s): array => ['id' => $s->id, 'name' => $s->name, 'code' => $s->code], $subjects),
            'rows' => $offer ? $query->forOffer($actor->schoolId, $period->id, $offer->id, $subjects) : [],
            'pass' => MomentGrade::PASS,
            'maxPending' => YearOutcome::MAX_PENDING,
        ]);
    }

    public function conduct(Request $request, TenantContext $tenantContext, AcademicPeriodReader $periods, AcademicOfferReader $offers, AcademicMomentReader $moments, GradeAccess $access, ConductBookRepositoryInterface $conduct, OfferRoster $roster): Response
    {
        $actor = $this->actor($request, $tenantContext);
        $period = $request->filled('period')
            ? ($periods->findForSchool($request->integer('period'), $actor->schoolId) ?? abort(404))
            : ($periods->activeForSchool($actor->schoolId) ?? abort(404));

        $all = $offers->allForPeriod($actor->schoolId, $period->id);
        $mine = array_values(array_filter($all, fn (AcademicOfferSummary $o): bool => $access->canManageConduct($actor, $o)));
        usort($mine, fn (AcademicOfferSummary $a, AcademicOfferSummary $b): int => [$a->gradeLevelName, $a->sectionName] <=> [$b->gradeLevelName, $b->sectionName]);

        $offer = $mine[0] ?? null;

        if ($request->filled('offer')) {
            $requested = array_values(array_filter($all, fn (AcademicOfferSummary $o): bool => $o->id === $request->integer('offer')))[0] ?? abort(404);
            abort_unless($access->canManageConduct($actor, $requested), 403);
            $offer = $requested;
        }

        abort_if($offer === null, 403, 'No eres docente orientador de ninguna sección del periodo.');

        $allMoments = $moments->forPeriod($actor->schoolId, $period->id);
        $moment = array_values(array_filter($allMoments, fn ($m): bool => $m->id === $request->integer('moment')))[0]
            ?? array_values(array_filter($allMoments, fn ($m): bool => $access->windowOpen($m)))[0]
            ?? (end($allMoments) ?: null);

        $letters = $moment ? $conduct->letters($actor->schoolId, $offer->id, $moment->id) : [];
        $windowOpen = $moment !== null && $access->windowOpen($moment);
        $periodOpen = $access->periodOpen($period->id, $actor->schoolId);

        return Inertia::render('Grades::Conduct', [
            'period' => ['id' => $period->id, 'name' => $period->name],
            'offers' => array_map(fn (AcademicOfferSummary $o): array => ['id' => $o->id, 'label' => "{$o->gradeLevelName} · Sección {$o->sectionName}"], $mine),
            'offerId' => $offer->id,
            'moments' => array_map(fn ($m): array => ['id' => $m->id, 'name' => $m->name], $allMoments),
            'moment' => $moment ? ['id' => $moment->id, 'name' => $moment->name, 'windowOpen' => $windowOpen] : null,
            'periodOpen' => $periodOpen,
            'canEdit' => $moment !== null && ($actor->isStaff || ($windowOpen && $periodOpen)),
            'isStaff' => $actor->isStaff,
            'scale' => array_map(fn (string $letter, string $label): array => ['letter' => $letter, 'label' => $label], array_keys($this->conductScale()), $this->conductScale()),
            'rows' => array_map(fn (array $student): array => $student + ['letter' => $letters[$student['id']] ?? null], $roster->forOffer($actor->schoolId, $offer->id)),
            'edited' => $moment ? $conduct->editedStudents($actor->schoolId, $offer->id, $moment->id) : [],
        ]);
    }

    public function recordConduct(Request $request, TenantContext $tenantContext, RecordConduct $useCase): JsonResponse
    {
        $validated = $request->validate([
            'offer_id' => ['required', 'integer'],
            'moment_id' => ['required', 'integer'],
            'student_id' => ['required', 'integer'],
            'letter' => ['nullable', 'string', 'max:2'],
        ]);

        try {
            $edited = $useCase->handle(
                $this->actor($request, $tenantContext),
                (int) $validated['offer_id'],
                (int) $validated['moment_id'],
                (int) $validated['student_id'],
                $validated['letter'] ?? null,
                $this->conductScale(),
            );
        } catch (GradingRefused $e) {
            return response()->json(['message' => $e->getMessage()], $e->status);
        }

        return response()->json(['letter' => $validated['letter'] ?? null, 'edited' => $edited]);
    }

    /**
     * @return array<string, string>
     */
    private function conductScale(): array
    {
        return config('grades.conduct_scale', []);
    }

    /**
     * @param  \Closure(): mixed  $action
     */
    private function correction(\Closure $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (GradingRefused $e) {
            abort_if($e->status === 403 || $e->status === 404, $e->status, $e->getMessage());

            throw ValidationException::withMessages(['correction' => $e->getMessage()]);
        }

        return back()->with('success', $success);
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
