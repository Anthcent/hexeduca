<?php

namespace Modules\Subjects\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A subject unchecked on one assignment: it is not studied by the offers
 * that take their plan from that assignment.
 */
class SubjectExclusionModel extends Model
{
    use BelongsToTenant;

    protected $table = 'study_plan_subject_exclusions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'study_plan_assignment_id',
        'study_plan_subject_id',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'study_plan_assignment_id' => 'integer',
            'study_plan_subject_id' => 'integer',
        ];
    }
}
