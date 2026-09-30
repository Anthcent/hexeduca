<?php

namespace Modules\Grades\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GradeConductModel extends Model
{
    use BelongsToTenant;

    protected $table = 'grade_conduct';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'academic_offer_id',
        'academic_moment_id',
        'student_id',
        'letter',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'academic_offer_id' => 'integer',
            'academic_moment_id' => 'integer',
            'student_id' => 'integer',
            'recorded_by' => 'integer',
        ];
    }
}
