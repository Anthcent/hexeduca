<?php

namespace Modules\Enrollments\Infrastructure\Models;

use App\AcademicPeriod\Concerns\BelongsToActivePeriod;
use App\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\MatriculaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Points at the existing `matriculas` table — physical table/column names
 * are not renamed as part of this phase (see plan §3).
 *
 * MUST NOT reference GradeLevels'/Sections' Infrastructure\Models
 * directly — enrollment always targets an AcademicOffer (mirrors
 * tests/Architecture/Academic/EnrollmentTargetsOfertaOnlyTest's invariant
 * for the legacy Matricula).
 *
 * Deliberately has NO `belongsTo(AcademicOffer)` relation: AcademicOffers
 * is a sibling module, not a vertical dependency (unlike
 * AcademicMoments -> AcademicPeriods). `oferta_academica_id` is kept as a
 * plain foreign id; capacity/existence reads at write-time go through
 * Modules\AcademicOffers\Public\Contracts\AcademicOfferProjectionSource
 * (see Application/UseCases/CreateEnrollment).
 *
 * Same treatment for `student_id`: no `belongsTo(User)` — Users is a
 * sibling module, reads go through
 * Modules\Users\Public\Contracts\StudentReader (Fase 6). The column stays
 * as a platform-level FK to `users` (plan §3); only the code-level
 * coupling is removed.
 */
class Enrollment extends Model
{
    /** @use HasFactory<MatriculaFactory> */
    use BelongsToActivePeriod, BelongsToTenant, HasFactory;

    protected static function newFactory(): MatriculaFactory
    {
        return MatriculaFactory::new();
    }

    protected $table = 'matriculas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'periodo_academico_id',
        'oferta_academica_id',
        'student_id',
        'status',
        'source_version',
        'enrolled_at',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'source_version' => 'integer',
        ];
    }
}
