<?php

namespace Modules\Grades\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GradeConductChangeModel extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $table = 'grade_conduct_changes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'academic_offer_id',
        'academic_moment_id',
        'student_id',
        'old_letter',
        'new_letter',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'academic_offer_id' => 'integer',
            'academic_moment_id' => 'integer',
            'student_id' => 'integer',
            'changed_by' => 'integer',
        ];
    }
}
