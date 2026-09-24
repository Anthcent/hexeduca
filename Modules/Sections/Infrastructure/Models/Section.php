<?php

namespace Modules\Sections\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\SeccionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Points at the existing `secciones` table — physical table name is not
 * renamed as part of this phase (see plan §3).
 */
class Section extends Model
{
    /** @use HasFactory<SeccionFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): SeccionFactory
    {
        return SeccionFactory::new();
    }

    protected $table = 'secciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'name',
    ];
}
