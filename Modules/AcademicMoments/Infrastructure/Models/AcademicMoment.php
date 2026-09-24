<?php

namespace Modules\AcademicMoments\Infrastructure\Models;

use App\AcademicPeriod\Concerns\BelongsToActivePeriod;
use Database\Factories\MomentoAcademicoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AcademicPeriods\Infrastructure\Models\AcademicPeriod;

/**
 * Points at the existing `momentos_academicos` table — physical table/
 * column names are not renamed as part of this phase (see plan §3).
 *
 * Global-within-period tier: NO school_id column. Tenant isolation is
 * inherited transitively via periodo_academico_id -> periodos_academicos.school_id.
 *
 * The `belongsTo(AcademicPeriod)` relation below crosses into
 * Modules\AcademicPeriods\Infrastructure\Models directly — this is
 * intentional and allowed: Tenant -> Period is the one sanctioned vertical
 * dependency chain (plan §1), not a sibling-module coupling.
 */
class AcademicMoment extends Model
{
    /** @use HasFactory<MomentoAcademicoFactory> */
    use BelongsToActivePeriod, HasFactory;

    protected static function newFactory(): MomentoAcademicoFactory
    {
        return MomentoAcademicoFactory::new();
    }

    protected $table = 'momentos_academicos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'periodo_academico_id',
        'name',
        'order',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<AcademicPeriod, $this>
     */
    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'periodo_academico_id');
    }
}
