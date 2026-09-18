<?php

namespace Modules\Academic\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\PeriodoAcademicoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeriodoAcademico extends Model
{
    /** @use HasFactory<PeriodoAcademicoFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * Explicit override: the default namespace-convention factory resolver
     * assumes models live under App\Models, which does not apply to models
     * living inside a module's Infrastructure layer.
     */
    protected static function newFactory(): PeriodoAcademicoFactory
    {
        return PeriodoAcademicoFactory::new();
    }

    /**
     * Explicit override: Spanish plural does not match Laravel's default
     * snake_case pluralization of the class name.
     */
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
