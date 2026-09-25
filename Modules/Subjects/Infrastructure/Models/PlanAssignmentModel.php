<?php

namespace Modules\Subjects\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Subjects\Domain\Entities\PlanAssignment;
use Modules\Subjects\Domain\ValueObjects\AssignmentScope;

/**
 * Deliberately NOT BelongsToActivePeriod: assignments are read per explicit
 * period (the screen has a period selector), never through the request's
 * active period.
 */
class PlanAssignmentModel extends Model
{
    use BelongsToTenant;

    protected $table = 'study_plan_assignments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'academic_period_id',
        'study_plan_id',
        'scope',
        'grade_level_id',
        'academic_offer_id',
        'target_id',
        'replaced_at',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'academic_period_id' => 'integer',
            'study_plan_id' => 'integer',
            'grade_level_id' => 'integer',
            'academic_offer_id' => 'integer',
            'target_id' => 'integer',
            'replaced_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<PlanAssignmentModel>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('replaced_at');
    }

    public function toEntity(): PlanAssignment
    {
        return PlanAssignment::fromStorage(
            id: $this->id,
            schoolId: $this->school_id,
            periodId: $this->academic_period_id,
            planId: $this->study_plan_id,
            scope: AssignmentScope::from($this->scope),
            gradeLevelId: $this->grade_level_id,
            offerId: $this->academic_offer_id,
            replaced: $this->replaced_at !== null,
        );
    }
}
