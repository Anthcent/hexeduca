<?php

namespace Modules\Subjects\Application\Queries;

use Modules\AcademicOffers\Public\Contracts\AcademicOfferReader;
use Modules\AcademicOffers\Public\DTOs\AcademicOfferSummary;
use Modules\AcademicPeriods\Public\Contracts\AcademicPeriodReader;
use Modules\AcademicPeriods\Public\DTOs\AcademicPeriodDTO;
use Modules\GradeLevels\Public\Contracts\GradeLevelReader;
use Modules\GradeLevels\Public\DTOs\GradeLevelDTO;
use Modules\Subjects\Domain\Entities\PlanAssignment;
use Modules\Subjects\Domain\Entities\StudyPlan;
use Modules\Subjects\Domain\ValueObjects\AssignmentScope;

/**
 * Turns assignments and plans into display rows (period, scope, target and
 * plan names) for the Inertia pages. Reads each sibling list once per
 * school (and once per period for offers).
 */
final class AssignmentLabeler
{
    /** @var array<int, AcademicPeriodDTO>|null */
    private ?array $periods = null;

    /** @var array<int, GradeLevelDTO>|null */
    private ?array $gradeLevels = null;

    /** @var array<int, array<int, AcademicOfferSummary>> */
    private array $offersByPeriod = [];

    public function __construct(
        private readonly int $schoolId,
        private readonly AcademicPeriodReader $periodReader,
        private readonly GradeLevelReader $gradeLevelReader,
        private readonly AcademicOfferReader $offerReader,
    ) {}

    /**
     * @return array{id: int, code: string, observation: ?string, name: string, archived: bool}
     */
    public static function plan(StudyPlan $plan): array
    {
        return [
            'id' => (int) $plan->id(),
            'code' => $plan->code(),
            'observation' => $plan->observation(),
            'name' => $plan->name(),
            'archived' => $plan->isArchived(),
        ];
    }

    /**
     * @param  array<int, StudyPlan>  $plansById
     * @return array{id: int, periodId: int, periodName: string, scope: string, targetLabel: string, plan: array<string, mixed>|null}
     */
    public function assignment(PlanAssignment $assignment, array $plansById = []): array
    {
        $plan = $plansById[$assignment->planId()] ?? null;

        return [
            'id' => (int) $assignment->id(),
            'periodId' => $assignment->periodId(),
            'periodName' => $this->periods()[$assignment->periodId()]->name ?? '—',
            'scope' => $assignment->scope()->value,
            'targetLabel' => $this->targetLabel($assignment),
            'plan' => $plan ? self::plan($plan) : null,
        ];
    }

    public function targetLabel(PlanAssignment $assignment): string
    {
        return match ($assignment->scope()) {
            AssignmentScope::School => 'Todo el colegio',
            AssignmentScope::GradeLevel => $this->gradeLevels()[(int) $assignment->gradeLevelId()]->name ?? '—',
            AssignmentScope::Offer => $this->offerLabel($this->offers($assignment->periodId())[(int) $assignment->offerId()] ?? null),
        };
    }

    public static function offerLabel(?AcademicOfferSummary $offer): string
    {
        return $offer ? $offer->gradeLevelName.' · Sección '.$offer->sectionName : '—';
    }

    /**
     * @return array<int, AcademicPeriodDTO>
     */
    public function periods(): array
    {
        if ($this->periods === null) {
            $this->periods = [];

            foreach ($this->periodReader->allForSchool($this->schoolId) as $period) {
                $this->periods[$period->id] = $period;
            }
        }

        return $this->periods;
    }

    /**
     * Ordered by the grade level order.
     *
     * @return array<int, GradeLevelDTO>
     */
    public function gradeLevels(): array
    {
        if ($this->gradeLevels === null) {
            $this->gradeLevels = [];

            foreach ($this->gradeLevelReader->allForSchool($this->schoolId) as $gradeLevel) {
                $this->gradeLevels[$gradeLevel->id] = $gradeLevel;
            }
        }

        return $this->gradeLevels;
    }

    /**
     * Ordered by grade level order, then section name.
     *
     * @return array<int, AcademicOfferSummary>
     */
    public function offers(int $periodId): array
    {
        if (! isset($this->offersByPeriod[$periodId])) {
            $order = array_flip(array_keys($this->gradeLevels()));
            $offers = $this->offerReader->allForPeriod($this->schoolId, $periodId);

            usort($offers, fn (AcademicOfferSummary $a, AcademicOfferSummary $b): int => [$order[$a->gradeLevelId] ?? PHP_INT_MAX, $a->sectionName, $a->id]
                <=> [$order[$b->gradeLevelId] ?? PHP_INT_MAX, $b->sectionName, $b->id]);

            $this->offersByPeriod[$periodId] = [];

            foreach ($offers as $offer) {
                $this->offersByPeriod[$periodId][$offer->id] = $offer;
            }
        }

        return $this->offersByPeriod[$periodId];
    }
}
