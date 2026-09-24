<?php

namespace Modules\AcademicOffers\Infrastructure\Models;

use App\AcademicPeriod\Concerns\BelongsToActivePeriod;
use App\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\OfertaAcademicaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AcademicPeriods\Infrastructure\Models\AcademicPeriod;

/**
 * Points at the existing `ofertas_academicas` table — physical table/
 * column names are not renamed as part of this phase (see plan §3).
 *
 * Deliberately has NO `belongsTo` to GradeLevels', Sections' or Users'
 * Infrastructure\Models — those are sibling modules, reads go through
 * their Public\Contracts readers instead (GradeLevelReader,
 * SectionReader, TeacherReader — see
 * Infrastructure/Http/Controllers/AcademicOfferController). The
 * `teacher_id` column stays as a platform-level FK to `users` (plan §3);
 * only the code-level coupling is removed (Fase 6).
 */
class AcademicOffer extends Model
{
    /** @use HasFactory<OfertaAcademicaFactory> */
    use BelongsToActivePeriod, BelongsToTenant, HasFactory;

    protected static function newFactory(): OfertaAcademicaFactory
    {
        return OfertaAcademicaFactory::new();
    }

    protected $table = 'ofertas_academicas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'periodo_academico_id',
        'grado_id',
        'seccion_id',
        'teacher_id',
        'capacity',
    ];

    /**
     * @return BelongsTo<AcademicPeriod, $this>
     */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'periodo_academico_id');
    }
}
