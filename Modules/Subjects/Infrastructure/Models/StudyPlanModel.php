<?php

namespace Modules\Subjects\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Subjects\Domain\Entities\StudyPlan;
use Modules\Subjects\Domain\ValueObjects\RecordStatus;

class StudyPlanModel extends Model
{
    use BelongsToTenant;

    protected $table = 'study_plans';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'code',
        'name',
        'observation',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<SubjectModel, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(SubjectModel::class, 'study_plan_id');
    }

    /**
     * @return HasMany<PlanAssignmentModel, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(PlanAssignmentModel::class, 'study_plan_id');
    }

    public function toEntity(): StudyPlan
    {
        return new StudyPlan(
            id: $this->id,
            schoolId: $this->school_id,
            code: $this->code,
            name: $this->name,
            observation: $this->observation,
            status: RecordStatus::from($this->status),
        );
    }
}
