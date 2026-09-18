<?php

namespace App\Tenancy\Scopes;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filters tenant-scoped models by the currently bound tenant.
 *
 * No-ops when no tenant is bound (landlord/console context), which is what
 * allows a landlord user (school_id = NULL) to remain visible to itself.
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        /** @var TenantContext $context */
        $context = app(TenantContext::class);

        if (! $context->hasTenant()) {
            return;
        }

        $builder->where($model->qualifyColumn('school_id'), $context->current()->id);
    }
}
