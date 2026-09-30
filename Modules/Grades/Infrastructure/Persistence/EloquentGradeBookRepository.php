<?php

namespace Modules\Grades\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Modules\Grades\Domain\Entities\EvaluationPlan;
use Modules\Grades\Domain\Repositories\GradeBookRepositoryInterface;
use Modules\Grades\Domain\ValueObjects\PlanStructure;
use Modules\Grades\Infrastructure\Models\GradeChangeModel;
use Modules\Grades\Infrastructure\Models\GradeCorrectionModel;
use Modules\Grades\Infrastructure\Models\GradeExtraModel;
use Modules\Grades\Infrastructure\Models\GradeIndicatorModel;
use Modules\Grades\Infrastructure\Models\GradePlanModel;
use Modules\Grades\Infrastructure\Models\GradeReferentModel;
use Modules\Grades\Infrastructure\Models\GradeResultModel;
use Modules\Grades\Infrastructure\Models\GradeScoreModel;

final class EloquentGradeBookRepository implements GradeBookRepositoryInterface
{
    public function findPlan(int $planId, int $schoolId): ?EvaluationPlan
    {
        $plan = GradePlanModel::query()->where('school_id', $schoolId)->find($planId);

        return $plan ? $this->toEntity($plan) : null;
    }

    public function findPlanForSlot(int $schoolId, int $offerId, int $subjectId, int $momentId): ?EvaluationPlan
    {
        $plan = GradePlanModel::query()
            ->where('school_id', $schoolId)
            ->where('academic_offer_id', $offerId)
            ->where('study_plan_subject_id', $subjectId)
            ->where('academic_moment_id', $momentId)
            ->first();

        return $plan ? $this->toEntity($plan) : null;
    }

    public function createPlan(int $schoolId, int $periodId, int $offerId, int $subjectId, int $momentId, int $createdBy, PlanStructure $structure): int
    {
        $plan = GradePlanModel::query()->create([
            'school_id' => $schoolId,
            'academic_period_id' => $periodId,
            'academic_offer_id' => $offerId,
            'study_plan_subject_id' => $subjectId,
            'academic_moment_id' => $momentId,
            'created_by' => $createdBy,
        ]);

        $this->writeStructure($plan->id, $schoolId, $structure);

        return $plan->id;
    }

    public function replaceStructure(int $planId, int $schoolId, PlanStructure $structure): void
    {
        GradeIndicatorModel::query()->where('school_id', $schoolId)->where('grade_plan_id', $planId)->delete();
        GradeReferentModel::query()->where('school_id', $schoolId)->where('grade_plan_id', $planId)->delete();
        $this->writeStructure($planId, $schoolId, $structure);
        GradePlanModel::query()->where('school_id', $schoolId)->whereKey($planId)->update(['updated_at' => now()]);
    }

    public function updateTexts(EvaluationPlan $plan, PlanStructure $structure): void
    {
        foreach ($plan->referents as $r => $referent) {
            $new = $structure->referents[$r];

            GradeReferentModel::query()->where('school_id', $plan->schoolId)->whereKey($referent['id'])
                ->update(['topic' => $new['topic'], 'technique' => $new['technique']]);

            foreach ($referent['indicators'] as $i => $indicator) {
                GradeIndicatorModel::query()->where('school_id', $plan->schoolId)->whereKey($indicator['id'])
                    ->update(['description' => $new['indicators'][$i]['description']]);
            }
        }

        GradePlanModel::query()->where('school_id', $plan->schoolId)->whereKey($plan->id)->update(['updated_at' => now()]);
    }

    public function hasScores(int $planId, int $schoolId): bool
    {
        return GradeScoreModel::query()->where('school_id', $schoolId)->where('grade_plan_id', $planId)->exists()
            || GradeExtraModel::query()->where('school_id', $schoolId)->where('grade_plan_id', $planId)->exists();
    }

    public function isEnrolled(int $schoolId, int $offerId, int $studentId): bool
    {
        return DB::table('grades_enrollment_projection')
            ->where('school_id', $schoolId)
            ->where('academic_offer_id', $offerId)
            ->where('student_id', $studentId)
            ->where('status', 'active')
            ->exists();
    }

    public function studentScores(int $planId, int $studentId, int $schoolId): array
    {
        return GradeScoreModel::query()
            ->where('school_id', $schoolId)
            ->where('grade_plan_id', $planId)
            ->where('student_id', $studentId)
            ->pluck('points', 'grade_plan_indicator_id')
            ->map(fn ($points): int => (int) $points)
            ->all();
    }

    public function studentExtra(int $planId, int $studentId, int $schoolId): ?int
    {
        $points = GradeExtraModel::query()
            ->where('school_id', $schoolId)
            ->where('grade_plan_id', $planId)
            ->where('student_id', $studentId)
            ->value('points');

        return $points === null ? null : (int) $points;
    }

    public function setScore(EvaluationPlan $plan, int $indicatorId, int $studentId, ?int $points, int $actorId, ?int $correctionId = null): ?int
    {
        $row = GradeScoreModel::query()
            ->where('school_id', $plan->schoolId)
            ->where('grade_plan_indicator_id', $indicatorId)
            ->where('student_id', $studentId)
            ->lockForUpdate()
            ->first();

        $old = $row?->points;

        if ($old === $points) {
            return $old;
        }

        if ($points === null) {
            $row->delete();
        } elseif ($row) {
            $row->update(['points' => $points, 'recorded_by' => $actorId]);
        } else {
            GradeScoreModel::query()->create([
                'school_id' => $plan->schoolId,
                'grade_plan_id' => $plan->id,
                'grade_plan_indicator_id' => $indicatorId,
                'student_id' => $studentId,
                'points' => $points,
                'recorded_by' => $actorId,
            ]);
        }

        $this->logChange($plan, $studentId, $indicatorId, $old, $points, $actorId, $correctionId);

        return $old;
    }

    public function setExtra(EvaluationPlan $plan, int $studentId, ?int $points, int $actorId, ?int $correctionId = null): ?int
    {
        $row = GradeExtraModel::query()
            ->where('school_id', $plan->schoolId)
            ->where('grade_plan_id', $plan->id)
            ->where('student_id', $studentId)
            ->lockForUpdate()
            ->first();

        $old = $row?->points;

        if ($old === $points) {
            return $old;
        }

        if ($points === null) {
            $row->delete();
        } elseif ($row) {
            $row->update(['points' => $points, 'recorded_by' => $actorId]);
        } else {
            GradeExtraModel::query()->create([
                'school_id' => $plan->schoolId,
                'grade_plan_id' => $plan->id,
                'student_id' => $studentId,
                'points' => $points,
                'recorded_by' => $actorId,
            ]);
        }

        $this->logChange($plan, $studentId, null, $old, $points, $actorId, $correctionId);

        return $old;
    }

    public function saveResult(EvaluationPlan $plan, int $studentId, float $average, int $extra, int $final, bool $complete): void
    {
        GradeResultModel::query()->updateOrCreate(
            ['school_id' => $plan->schoolId, 'grade_plan_id' => $plan->id, 'student_id' => $studentId],
            ['average' => $average, 'extra' => $extra, 'final' => $final, 'complete' => $complete],
        );
    }

    public function activeCorrection(int $planId, int $schoolId): ?array
    {
        $row = GradeCorrectionModel::query()
            ->where('school_id', $schoolId)
            ->where('grade_plan_id', $planId)
            ->whereNull('closed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        return $row ? [
            'id' => $row->id,
            'reason' => $row->reason,
            'expiresAt' => $row->expires_at->toIso8601String(),
            'openedBy' => $row->opened_by,
        ] : null;
    }

    public function openCorrection(EvaluationPlan $plan, int $openedBy, string $reason, string $expiresAt): int
    {
        $this->closeCorrection($plan->id, $plan->schoolId, $openedBy);

        return GradeCorrectionModel::query()->create([
            'school_id' => $plan->schoolId,
            'grade_plan_id' => $plan->id,
            'opened_by' => $openedBy,
            'reason' => $reason,
            'expires_at' => $expiresAt,
        ])->id;
    }

    public function closeCorrection(int $planId, int $schoolId, int $closedBy): bool
    {
        return GradeCorrectionModel::query()
            ->where('school_id', $schoolId)
            ->where('grade_plan_id', $planId)
            ->whereNull('closed_at')
            ->update(['closed_at' => now(), 'closed_by' => $closedBy]) > 0;
    }

    private function logChange(EvaluationPlan $plan, int $studentId, ?int $indicatorId, ?int $old, ?int $new, int $actorId, ?int $correctionId): void
    {
        GradeChangeModel::query()->create([
            'school_id' => $plan->schoolId,
            'grade_plan_id' => $plan->id,
            'student_id' => $studentId,
            'grade_plan_indicator_id' => $indicatorId,
            'old_points' => $old,
            'new_points' => $new,
            'changed_by' => $actorId,
            'grade_correction_id' => $correctionId,
        ]);
    }

    private function writeStructure(int $planId, int $schoolId, PlanStructure $structure): void
    {
        foreach ($structure->referents as $r => $referent) {
            $row = GradeReferentModel::query()->create([
                'school_id' => $schoolId,
                'grade_plan_id' => $planId,
                'position' => $r + 1,
                'topic' => $referent['topic'],
                'technique' => $referent['technique'],
            ]);

            foreach ($referent['indicators'] as $i => $indicator) {
                GradeIndicatorModel::query()->create([
                    'school_id' => $schoolId,
                    'grade_plan_id' => $planId,
                    'grade_plan_referent_id' => $row->id,
                    'letter' => PlanStructure::letter($i),
                    'description' => $indicator['description'],
                    'max_points' => $indicator['maxPoints'],
                ]);
            }
        }
    }

    private function toEntity(GradePlanModel $plan): EvaluationPlan
    {
        $indicators = GradeIndicatorModel::query()
            ->where('school_id', $plan->school_id)
            ->where('grade_plan_id', $plan->id)
            ->orderBy('letter')
            ->get()
            ->groupBy('grade_plan_referent_id');

        $referents = GradeReferentModel::query()
            ->where('school_id', $plan->school_id)
            ->where('grade_plan_id', $plan->id)
            ->orderBy('position')
            ->get()
            ->map(fn (GradeReferentModel $referent): array => [
                'id' => $referent->id,
                'position' => $referent->position,
                'topic' => $referent->topic,
                'technique' => $referent->technique,
                'indicators' => ($indicators[$referent->id] ?? collect())->map(fn (GradeIndicatorModel $indicator): array => [
                    'id' => $indicator->id,
                    'letter' => $indicator->letter,
                    'description' => $indicator->description,
                    'maxPoints' => $indicator->max_points,
                ])->values()->all(),
            ])
            ->all();

        return new EvaluationPlan(
            id: $plan->id,
            schoolId: $plan->school_id,
            periodId: $plan->academic_period_id,
            offerId: $plan->academic_offer_id,
            subjectId: $plan->study_plan_subject_id,
            momentId: $plan->academic_moment_id,
            referents: $referents,
        );
    }
}
