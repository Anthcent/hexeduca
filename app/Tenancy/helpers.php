<?php

use App\Tenancy\Models\School;
use App\Tenancy\TenantContext;

if (! function_exists('current_tenant')) {
    /**
     * Resolve the currently bound tenant, or null under landlord/console
     * context (no tenant bound).
     */
    function current_tenant(): ?School
    {
        return app(TenantContext::class)->current();
    }
}
