<?php

namespace Modules\AcademicLevels\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\NivelAcademicoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Points at the existing `niveles_academicos` table — physical table name
 * is not renamed as part of this phase (see plan §3).
 */
class AcademicLevel extends Model
{
    /** @use HasFactory<NivelAcademicoFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Reuses the existing root-level factory (already exercises this exact
     * table/columns) rather than duplicating factory definitions per module.
     */
    protected static function newFactory(): NivelAcademicoFactory
    {
        return NivelAcademicoFactory::new();
    }

    protected $table = 'niveles_academicos';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'name',
    ];
}
