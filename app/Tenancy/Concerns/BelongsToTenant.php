<?php

namespace App\Tenancy\Concerns;

use App\Tenancy\Scopes\TenantScope;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies tenant isolation to an Eloquent model: adds the TenantScope global
 * scope and auto-stamps `school_id` from the current tenant on creation.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            if ($model->school_id !== null) {
                return;
            }

            /** @var TenantContext $context */
            $context = app(TenantContext::class);

            if ($context->hasTenant()) {
                $model->school_id = $context->current()->id;
            }
        });
    }

    /**
     * Explicit landlord bypass — the only sanctioned way to cross tenants.
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
