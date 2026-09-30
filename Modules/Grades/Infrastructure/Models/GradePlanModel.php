<?php

namespace Modules\Grades\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GradePlanModel extends Model
{
    use BelongsToTenant;

    protected $table = 'grade_plans';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'academic_period_id',
        'academic_offer_id',
        'academic_moment_id',
        'study_plan_subject_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'academic_period_id' => 'integer',
            'academic_offer_id' => 'integer',
            'academic_moment_id' => 'integer',
            'study_plan_subject_id' => 'integer',
            'created_by' => 'integer',
        ];
    }
}
