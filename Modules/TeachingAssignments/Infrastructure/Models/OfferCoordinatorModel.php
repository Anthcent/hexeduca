<?php

namespace Modules\TeachingAssignments\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class OfferCoordinatorModel extends Model
{
    use BelongsToTenant;

    protected $table = 'teaching_offer_coordinators';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'academic_period_id',
        'academic_offer_id',
        'teacher_id',
    ];

    protected function casts(): array
    {
        return [
            'school_id' => 'integer',
            'academic_period_id' => 'integer',
            'academic_offer_id' => 'integer',
            'teacher_id' => 'integer',
        ];
    }
}
