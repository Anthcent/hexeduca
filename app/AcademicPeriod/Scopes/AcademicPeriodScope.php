<?php

namespace App\AcademicPeriod\Scopes;

use App\AcademicPeriod\Context\AcademicPeriodContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filters period-scoped models by the currently bound active academic
 * period.
 *
 * Structural mirror of App\Tenancy\Scopes\TenantScope. No-ops when no
 * period is bound (console/queue context or a request with no active
 * period), which prevents an unbound period from silently hiding every row.
 *
 * The filtered column is `periodo_academico_id` — the physical column name
 * on the existing tables is not renamed as part of this phase; only the
 * PHP-level ownership of the scoping concern moves to the platform layer.
 */
final class AcademicPeriodScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var AcademicPeriodContext $context */
        $context = app(AcademicPeriodContext::class);

        if (! $context->hasPeriod()) {
            return;
        }

        $builder->where($model->qualifyColumn('periodo_academico_id'), $context->current()->id);
    }
}
