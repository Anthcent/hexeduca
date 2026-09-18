<?php

namespace Modules\Academic\Infrastructure\Period\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Modules\Academic\Infrastructure\Period\PeriodoContext;

/**
 * Filters period-scoped models by the currently bound active academic
 * period.
 *
 * Structural clone of App\Tenancy\Scopes\TenantScope. No-ops when no
 * period is bound (console/queue context or a request with no active
 * period), which prevents an unbound period from silently hiding every
 * row.
 */
final class PeriodoScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var PeriodoContext $context */
        $context = app(PeriodoContext::class);

        if (! $context->hasPeriodo()) {
            return;
        }

        $builder->where($model->qualifyColumn('periodo_academico_id'), $context->current()->id);
    }
}
