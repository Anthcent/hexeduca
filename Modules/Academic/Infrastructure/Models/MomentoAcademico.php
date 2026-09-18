<?php

namespace Modules\Academic\Infrastructure\Models;

use Database\Factories\MomentoAcademicoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Academic\Infrastructure\Period\Concerns\BelongsToActivePeriodo;

/**
 * Global-within-period tier: NO school_id column. Tenant isolation is
 * inherited transitively via periodo_academico_id -> periodos_academicos.school_id.
 */
class MomentoAcademico extends Model
{
    /** @use HasFactory<MomentoAcademicoFactory> */
    use BelongsToActivePeriodo, HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * Explicit override: the default namespace-convention factory resolver
     * assumes models live under App\Models, which does not apply to models
     * living inside a module's Infrastructure layer.
     */
    protected static function newFactory(): MomentoAcademicoFactory
    {
        return MomentoAcademicoFactory::new();
    }

    /**
     * Explicit override: Spanish plural does not match Laravel's default
     * snake_case pluralization of the class name (would default to
     * `momento_academicos`).
     */
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
     * @return BelongsTo<PeriodoAcademico, $this>
     */
    public function periodoAcademico(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class);
    }
}
