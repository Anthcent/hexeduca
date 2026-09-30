<?php

namespace Modules\Grades\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only: a row is written for every change and never updated.
 */
class GradeChangeModel extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'grade_changes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'grade_plan_id',
        'student_id',
        'grade_plan_indicator_id',
        'old_points',
        'new_points',
        'changed_by',
        'grade_correction_id',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'grade_plan_id' => 'integer',
            'student_id' => 'integer',
            'grade_plan_indicator_id' => 'integer',
            'old_points' => 'integer',
            'new_points' => 'integer',
            'changed_by' => 'integer',
            'grade_correction_id' => 'integer',
        ];
    }
}
