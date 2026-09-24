<?php

namespace App\ModulePlatform\Models;

use App\Tenancy\Models\School;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-school entitlement for an optional module.
 *
 * Deliberately does NOT use `App\Tenancy\Concerns\BelongsToTenant`: entitlement
 * lookups (see ModuleAccess) are always performed for an explicitly passed
 * `School`, not the "current" tenant, so an automatic TenantScope would only
 * get in the way (e.g. landlord/console contexts have no current tenant).
 */
class SchoolModule extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'module_key',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(ModuleRecord::class, 'module_key', 'key');
    }
}
