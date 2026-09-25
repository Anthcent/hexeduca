<?php

namespace Modules\Subjects\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Modules\Subjects\Domain\Entities\Subject;
use Modules\Subjects\Domain\ValueObjects\RecordStatus;

class SubjectModel extends Model
{
    use BelongsToTenant;

    protected $table = 'study_plan_subjects';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'study_plan_id',
        'grade_level_id',
        'name',
        'code',
        'weekly_hours',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'study_plan_id' => 'integer',
            'grade_level_id' => 'integer',
            'weekly_hours' => 'integer',
        ];
    }

    public function toEntity(): Subject
    {
        return new Subject(
            id: $this->id,
            schoolId: $this->school_id,
            planId: $this->study_plan_id,
            gradeLevelId: $this->grade_level_id,
            name: $this->name,
            code: $this->code,
            weeklyHours: $this->weekly_hours,
            status: RecordStatus::from($this->status),
        );
    }
}
