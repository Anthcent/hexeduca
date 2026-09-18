<?php

namespace Modules\Academic\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\GradoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grado extends Model
{
    /** @use HasFactory<GradoFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * Explicit override: the default namespace-convention factory resolver
     * assumes models live under App\Models, which does not apply to models
     * living inside a module's Infrastructure layer.
     */
    protected static function newFactory(): GradoFactory
    {
        return GradoFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'nivel_academico_id',
        'name',
        'order',
    ];

    /**
     * @return BelongsTo<NivelAcademico, $this>
     */
    public function nivelAcademico(): BelongsTo
    {
        return $this->belongsTo(NivelAcademico::class);
    }
}
