<?php

namespace Modules\Academic\Infrastructure\Models;

use App\AcademicPeriod\Concerns\BelongsToActivePeriod;
use App\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\OfertaAcademicaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Users\Infrastructure\Models\User;

/**
 * Tenant+period tier. Instantiates one catalog Grado + Seccion within
 * exactly one PeriodoAcademico.
 */
class OfertaAcademica extends Model
{
    /** @use HasFactory<OfertaAcademicaFactory> */
    use BelongsToActivePeriod, BelongsToTenant, HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * Explicit override: the default namespace-convention factory resolver
     * assumes models live under App\Models, which does not apply to models
     * living inside a module's Infrastructure layer.
     */
    protected static function newFactory(): OfertaAcademicaFactory
    {
        return OfertaAcademicaFactory::new();
    }

    /**
     * Explicit override: Spanish plural does not match Laravel's default
     * snake_case pluralization of the class name (would default to
     * `oferta_academicas`).
     */
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
     * @return BelongsTo<Grado, $this>
     */
    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class);
    }

    /**
     * @return BelongsTo<Seccion, $this>
     */
    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }

    /**
     * @return BelongsTo<PeriodoAcademico, $this>
     */
    public function periodoAcademico(): BelongsTo
    {
        return $this->belongsTo(PeriodoAcademico::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return HasMany<Matricula, $this>
     */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }
}
