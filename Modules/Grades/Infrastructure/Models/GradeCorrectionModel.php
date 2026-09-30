<?php

namespace Modules\Grades\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class GradeCorrectionModel extends Model
{
    use BelongsToTenant;

    protected $table = 'grade_corrections';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'grade_plan_id',
        'opened_by',
        'reason',
        'expires_at',
        'closed_at',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'grade_plan_id' => 'integer',
            'opened_by' => 'integer',
            'closed_by' => 'integer',
            'expires_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
