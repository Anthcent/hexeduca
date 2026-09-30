<?php

namespace Modules\Grades\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GradeScoreModel extends Model
{
    use BelongsToTenant;

    protected $table = 'grade_scores';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'grade_plan_id',
        'grade_plan_indicator_id',
        'student_id',
        'points',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'grade_plan_id' => 'integer',
            'grade_plan_indicator_id' => 'integer',
            'student_id' => 'integer',
            'points' => 'integer',
            'recorded_by' => 'integer',
        ];
    }
}
