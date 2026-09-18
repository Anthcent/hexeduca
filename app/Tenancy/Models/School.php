<?php

namespace App\Tenancy\Models;

use App\Tenancy\Observers\SchoolCacheObserver;
use App\Tenancy\Scopes\TenantScope;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant/institution, identified by a unique subdomain.
 *
 * The `schools` table itself is never tenant-scoped (there is no "tenant of
 * a tenant"), so this model does not use BelongsToTenant.
 */
#[ObservedBy(SchoolCacheObserver::class)]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'subdomain',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): SchoolFactory
    {
        return SchoolFactory::new();
    }

    /**
     * Explicit landlord bypass helper for models using BelongsToTenant.
     * Thin wrapper over `withoutGlobalScope(TenantScope::class)`.
     */
    public static function withoutTenantScope(): Builder
    {
        return static::query()->withoutGlobalScope(TenantScope::class);
    }

    /**
     * This school's own subdomain login URL, e.g.
     * "https://schoolA.app.com/login". Scheme is derived from the current
     * request rather than hardcoded, so local dev over plain HTTP resolves
     * correctly. Reuses the same subdomain/base_domain convention
     * ResolveTenant::subdomainLabel() already relies on in reverse.
     */
    public function loginUrl(): string
    {
        $scheme = request()->getScheme();
        $baseDomain = config('tenancy.base_domain');

        return "{$scheme}://{$this->subdomain}.{$baseDomain}/login";
    }
}
