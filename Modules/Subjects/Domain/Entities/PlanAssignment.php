<?php

namespace Modules\Subjects\Domain\Entities;

use Modules\Subjects\Domain\ValueObjects\AssignmentScope;

/**
 * A study plan assigned to one slot of an academic period: the whole
 * school, one grade level, or one offer (grade level + section). At most
 * one CURRENT assignment holds a slot. A replaced assignment is history:
 * it keeps its row (marked replaced) so a reactivated plan can restore it.
 *
 * An offer-scope assignment also carries the offer's grade level, so every
 * assignment answers "which grade levels do I cover?" on its own.
 */
final class PlanAssignment
{
    private function __construct(
        private readonly ?int $id,
        private readonly int $schoolId,
        private readonly int $periodId,
        private readonly int $planId,
        private readonly AssignmentScope $scope,
        private readonly ?int $gradeLevelId,
        private readonly ?int $offerId,
        private readonly bool $replaced,
    ) {}

    public static function forSchool(int $schoolId, int $periodId, int $planId): self
    {
        return new self(null, $schoolId, $periodId, $planId, AssignmentScope::School, null, null, false);
    }

    public static function forGradeLevel(int $schoolId, int $periodId, int $planId, int $gradeLevelId): self
    {
        return new self(null, $schoolId, $periodId, $planId, AssignmentScope::GradeLevel, $gradeLevelId, null, false);
    }

    public static function forOffer(int $schoolId, int $periodId, int $planId, int $offerId, int $offerGradeLevelId): self
    {
        return new self(null, $schoolId, $periodId, $planId, AssignmentScope::Offer, $offerGradeLevelId, $offerId, false);
    }

    /**
     * Rebuilds a stored assignment.
     */
    public static function fromStorage(
        int $id,
        int $schoolId,
        int $periodId,
        int $planId,
        AssignmentScope $scope,
        ?int $gradeLevelId,
        ?int $offerId,
        bool $replaced,
    ): self {
        return new self($id, $schoolId, $periodId, $planId, $scope, $gradeLevelId, $offerId, $replaced);
    }

    /**
     * The normalized slot target: 0 for the whole school, the grade level
     * id, or the offer id. Never NULL, so the unique index compares it.
     */
    public function targetId(): int
    {
        return match ($this->scope) {
            AssignmentScope::School => 0,
            AssignmentScope::GradeLevel => (int) $this->gradeLevelId,
            AssignmentScope::Offer => (int) $this->offerId,
        };
    }

    public function slotKey(): string
    {
        return self::slotKeyOf($this->periodId, $this->scope, $this->targetId());
    }

    public static function slotKeyOf(int $periodId, AssignmentScope $scope, int $targetId): string
    {
        return $periodId.':'.$scope->value.':'.$targetId;
    }

    public function coversOffer(int $offerId, int $offerGradeLevelId): bool
    {
        return match ($this->scope) {
            AssignmentScope::School => true,
            AssignmentScope::GradeLevel => $this->gradeLevelId === $offerGradeLevelId,
            AssignmentScope::Offer => $this->offerId === $offerId,
        };
    }

    /**
     * Whether an offer of this grade level may take its subjects from this
     * assignment.
     */
    public function coversGradeLevel(int $gradeLevelId): bool
    {
        return $this->scope === AssignmentScope::School || $this->gradeLevelId === $gradeLevelId;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function schoolId(): int
    {
        return $this->schoolId;
    }

    public function periodId(): int
    {
        return $this->periodId;
    }

    public function planId(): int
    {
        return $this->planId;
    }

    public function scope(): AssignmentScope
    {
        return $this->scope;
    }

    public function gradeLevelId(): ?int
    {
        return $this->gradeLevelId;
    }

    public function offerId(): ?int
    {
        return $this->offerId;
    }

    public function isReplaced(): bool
    {
        return $this->replaced;
    }
}
