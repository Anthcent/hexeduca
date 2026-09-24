<?php

namespace Modules\Academic\Infrastructure\Models;

use App\AcademicPeriod\Concerns\BelongsToActivePeriod;
use App\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\MatriculaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Users\Infrastructure\Models\User;

/**
 * Tenant+period tier. Enrolls a student into exactly one OfertaAcademica —
 * MUST NOT reference a catalog Grado/Seccion directly.
 */
class Matricula extends Model
{
    /** @use HasFactory<MatriculaFactory> */
    use BelongsToActivePeriod, BelongsToTenant, HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * Explicit override: the default namespace-convention factory resolver
     * assumes models live under App\Models, which does not apply to models
     * living inside a module's Infrastructure layer.
     */
    protected static function newFactory(): MatriculaFactory
    {
        return MatriculaFactory::new();
    }

    /**
     * No `$table` override needed: Laravel's default snake_case
     * pluralization of `Matricula` already resolves to `matriculas`
     * (verified against Str::plural, unlike OfertaAcademica/MomentoAcademico).
     */

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

    /**
     * @return BelongsTo<OfertaAcademica, $this>
     */
    public function ofertaAcademica(): BelongsTo
    {
        return $this->belongsTo(OfertaAcademica::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
