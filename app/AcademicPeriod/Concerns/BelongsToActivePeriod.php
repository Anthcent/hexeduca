<?php

namespace App\AcademicPeriod\Concerns;

use App\AcademicPeriod\Context\AcademicPeriodContext;
use App\AcademicPeriod\Scopes\AcademicPeriodScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies active-period isolation to an Eloquent model: adds the
 * AcademicPeriodScope global scope and auto-stamps `periodo_academico_id`
 * from the current active period on creation.
 *
 * Structural mirror of App\Tenancy\Concerns\BelongsToTenant. Registers its
 * own scope under the `AcademicPeriodScope::class` key, independent from
 * `TenantScope::class`, so opting out of one never drops the other.
 *
 * NOT YET adopted by Modules\Academic's own models in this phase — see
 * Modules\Academic\Infrastructure\Period\Concerns\BelongsToActivePeriodo
 * for the still-active legacy trait those models use until Phase 2.
 */
trait BelongsToActivePeriod
{
    public static function bootBelongsToActivePeriod(): void
    {
        static::addGlobalScope(new AcademicPeriodScope);

        static::creating(function ($model): void {
            if ($model->periodo_academico_id !== null) {
                return;
            }

            /** @var AcademicPeriodContext $context */
            $context = app(AcademicPeriodContext::class);

            if ($context->hasPeriod()) {
                $model->periodo_academico_id = $context->current()->id;
            }
        });
    }

    /**
     * Explicit cross-period bypass — the only sanctioned way to cross
     * periods. Never call bare `withoutGlobalScopes()`, which would also
     * strip `TenantScope`.
     */
    public function scopeWithoutActivePeriodScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(AcademicPeriodScope::class);
    }
}
