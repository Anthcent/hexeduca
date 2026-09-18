<?php

namespace Modules\Academic\Infrastructure\Models;

use App\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\SeccionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seccion extends Model
{
    /** @use HasFactory<SeccionFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * Explicit override: the default namespace-convention factory resolver
     * assumes models live under App\Models, which does not apply to models
     * living inside a module's Infrastructure layer.
     */
    protected static function newFactory(): SeccionFactory
    {
        return SeccionFactory::new();
    }

    /**
     * Explicit override: Spanish plural does not match Laravel's default
     * snake_case pluralization of the class name.
     */
    protected $table = 'secciones';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'name',
    ];
}
