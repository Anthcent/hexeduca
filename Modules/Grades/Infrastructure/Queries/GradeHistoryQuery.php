<?php

namespace Modules\Grades\Infrastructure\Queries;

use Modules\Grades\Domain\Entities\EvaluationPlan;
use Modules\Grades\Infrastructure\Models\GradeChangeModel;
use Modules\Grades\Infrastructure\Models\GradeCorrectionModel;
use Modules\Users\Public\Contracts\UserDirectory;

/**
 * The change log of one plan, newest first, with names instead of ids:
 * who changed which cell of which student, from what to what, and under
 * which correction.
 */
final class GradeHistoryQuery
{
    private const LIMIT = 500;

    public function __construct(private readonly UserDirectory $users) {}

    /**
     * @return array{changes: list<array<string, mixed>>, truncated: bool}
     */
    public function forPlan(EvaluationPlan $plan): array
    {
        $rows = GradeChangeModel::query()
            ->where('school_id', $plan->schoolId)
            ->where('grade_plan_id', $plan->id)
            ->orderByDesc('id')
            ->limit(self::LIMIT + 1)
            ->get();

        $truncated = $rows->count() > self::LIMIT;
        $rows = $rows->take(self::LIMIT);

        $corrections = GradeCorrectionModel::query()
            ->where('school_id', $plan->schoolId)
            ->whereIn('id', $rows->pluck('grade_correction_id')->filter()->unique()->all())
            ->pluck('reason', 'id');

        $names = $this->users->namesFor($plan->schoolId, $rows->pluck('changed_by')->merge($rows->pluck('student_id'))->unique()->values()->all());
        $cells = $this->cellLabels($plan);

        return [
            'changes' => $rows->map(fn (GradeChangeModel $row): array => [
                'id' => $row->id,
                'at' => $row->created_at?->toIso8601String(),
                'student' => $names[$row->student_id]['name'] ?? "Estudiante {$row->student_id}",
                'cell' => $row->grade_plan_indicator_id === null ? 'Extra' : ($cells[$row->grade_plan_indicator_id] ?? '—'),
                'old' => $row->old_points,
                'new' => $row->new_points,
                'by' => $names[$row->changed_by]['name'] ?? "Usuario {$row->changed_by}",
                'role' => $names[$row->changed_by]['role'] ?? null,
                'correction' => $row->grade_correction_id ? ($corrections[$row->grade_correction_id] ?? '') : null,
            ])->values()->all(),
            'truncated' => $truncated,
        ];
    }

    /**
     * @return array<int, string> "R1-A" keyed by indicator id
     */
    private function cellLabels(EvaluationPlan $plan): array
    {
        $labels = [];

        foreach ($plan->referents as $r => $referent) {
            foreach ($referent['indicators'] as $indicator) {
                $labels[$indicator['id']] = 'R'.($r + 1).'-'.$indicator['letter'];
            }
        }

        return $labels;
    }
}
