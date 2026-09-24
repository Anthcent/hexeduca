<?php

namespace Modules\AcademicPeriods\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\PeriodoAcademicoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Points at the existing `periodos_academicos` table — physical table name
 * is not renamed as part of this phase (see plan §3, progressive
 * migration: preserve IDs, no data movement).
 */
class AcademicPeriod extends Model
{
    /** @use HasFactory<PeriodoAcademicoFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Reuses the existing root-level factory (already exercises this exact
     * table/columns) rather than duplicating factory definitions per
     * module.
     */
    protected static function newFactory(): PeriodoAcademicoFactory
    {
        return PeriodoAcademicoFactory::new();
    }

    protected $table = 'periodos_academicos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'starts_on',
        'ends_on',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
