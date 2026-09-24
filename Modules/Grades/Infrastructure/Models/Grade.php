<?php

namespace Modules\Grades\Infrastructure\Models;

use App\AcademicPeriod\Concerns\BelongsToActivePeriod;
use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Deliberately has NO `belongsTo` to AcademicOffers'/Enrollments'/Users'
 * Infrastructure\Models — those are sibling modules. Enrollment membership
 * is checked against `grades_enrollment_projection` (this module's own
 * local copy) in Application\UseCases\RecordGrade, never a live
 * cross-module query — plan §6/§8.
 */
class Grade extends Model
{
    use BelongsToActivePeriod, BelongsToTenant;

    protected $table = 'grades';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'periodo_academico_id',
        'academic_offer_id',
        'student_id',
        'teacher_id',
        'value',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'recorded_at' => 'datetime',
        ];
    }
}
