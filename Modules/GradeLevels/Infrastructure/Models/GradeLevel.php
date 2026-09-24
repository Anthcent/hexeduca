<?php

namespace Modules\GradeLevels\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\GradoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Points at the existing `grados` table — physical table/column names are
 * not renamed as part of this phase (see plan §3).
 *
 * Deliberately has NO `belongsTo(AcademicLevel)` relation: AcademicLevels
 * is a sibling module, not a vertical dependency. Reads go through
 * Modules\AcademicLevels\Public\Contracts\AcademicLevelReader instead
 * (see Infrastructure/Http/Controllers/GradeLevelController).
 */
class GradeLevel extends Model
{
    /** @use HasFactory<GradoFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): GradoFactory
    {
        return GradoFactory::new();
    }

    protected $table = 'grados';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'nivel_academico_id',
        'name',
        'order',
    ];
}
