<?php

namespace Modules\Academic\Infrastructure\Period\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Modules\Academic\Infrastructure\Period\PeriodoContext;
use Modules\Academic\Infrastructure\Period\Scopes\PeriodoScope;

/**
 * Applies active-period isolation to an Eloquent model: adds the
 * PeriodoScope global scope and auto-stamps `periodo_academico_id` from
 * the current active period on creation.
 *
 * Structural clone of App\Tenancy\Concerns\BelongsToTenant. Registers its
 * own scope under the `PeriodoScope::class` key, independent from
 * `TenantScope::class`, so opting out of one never drops the other.
 */
trait BelongsToActivePeriodo
{
    public static function bootBelongsToActivePeriodo(): void
    {
        static::addGlobalScope(new PeriodoScope);

        static::creating(function ($model): void {
            if ($model->periodo_academico_id !== null) {
                return;
            }

            /** @var PeriodoContext $context */
            $context = app(PeriodoContext::class);

            if ($context->hasPeriodo()) {
                $model->periodo_academico_id = $context->current()->id;
            }
        });
    }

    /**
     * Explicit cross-period bypass — the only sanctioned way to cross
     * periods. Never call bare `withoutGlobalScopes()`, which would also
     * strip `TenantScope`.
     */
    public function scopeWithoutActivePeriodoScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(PeriodoScope::class);
    }
}
