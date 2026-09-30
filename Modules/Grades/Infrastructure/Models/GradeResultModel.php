<?php

namespace Modules\Grades\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GradeResultModel extends Model
{
    use BelongsToTenant;

    protected $table = 'grade_results';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'grade_plan_id',
        'student_id',
        'average',
        'extra',
        'final',
        'complete',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'grade_plan_id' => 'integer',
            'student_id' => 'integer',
            'extra' => 'integer',
            'final' => 'integer',
            'average' => 'float',
            'complete' => 'boolean',
        ];
    }
}
