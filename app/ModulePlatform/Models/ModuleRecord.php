<?php

namespace App\ModulePlatform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Registry row for a `Modules/*` package: mirrors its manifest fields
 * (`core`, `maturity`) plus the app-level `active` flag ModuleRegistry
 * toggles. Named `ModuleRecord` (not `Module`) to avoid colliding with
 * nwidart/laravel-modules' own `Nwidart\Modules\Module` class.
 */
class ModuleRecord extends Model
{
    protected $table = 'modules';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
        'core',
        'maturity',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'core' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
