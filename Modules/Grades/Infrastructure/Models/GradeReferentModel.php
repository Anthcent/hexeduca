<?php

namespace Modules\Grades\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GradeReferentModel extends Model
{
    use BelongsToTenant;

    protected $table = 'grade_plan_referents';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'grade_plan_id',
        'position',
        'topic',
        'technique',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'grade_plan_id' => 'integer',
            'position' => 'integer',
        ];
    }
}
