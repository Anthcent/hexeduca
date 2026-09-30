<?php

namespace Modules\TeachingAssignments\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\TeachingAssignments\Domain\Entities\TeachingAssignment;
use Modules\TeachingAssignments\Domain\ValueObjects\TeachingRole;

/**
 * Deliberately NOT BelongsToActivePeriod: assignments are read per explicit
 * period (the screen has a period selector).
 */
class TeachingAssignmentModel extends Model
{
    use BelongsToTenant;

    protected $table = 'teaching_assignments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'academic_period_id',
        'academic_offer_id',
        'study_plan_subject_id',
        'teacher_id',
        'role',
        'started_on',
        'ended_on',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'academic_period_id' => 'integer',
            'academic_offer_id' => 'integer',
            'study_plan_subject_id' => 'integer',
            'teacher_id' => 'integer',
            'started_on' => 'date:Y-m-d',
            'ended_on' => 'date:Y-m-d',
        ];
    }

    public function toEntity(): TeachingAssignment
    {
        return new TeachingAssignment(
            id: $this->id,
            schoolId: $this->school_id,
            periodId: $this->academic_period_id,
            offerId: $this->academic_offer_id,
            subjectId: $this->study_plan_subject_id,
            teacherId: $this->teacher_id,
            role: TeachingRole::from($this->role),
            startedOn: $this->started_on->toDateString(),
            endedOn: $this->ended_on?->toDateString(),
        );
    }
}
