<?php

namespace Modules\Grades\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GradeIndicatorModel extends Model
{
    use BelongsToTenant;

    protected $table = 'grade_plan_indicators';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'grade_plan_id',
        'grade_plan_referent_id',
        'letter',
        'description',
        'max_points',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'grade_plan_id' => 'integer',
            'grade_plan_referent_id' => 'integer',
            'max_points' => 'integer',
        ];
    }
}
