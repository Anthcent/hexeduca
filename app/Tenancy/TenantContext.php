<?php

namespace App\Tenancy;

use App\Tenancy\Models\School;

/**
 * Holds the currently resolved tenant for the request lifecycle.
 *
 * Bound as a scoped container singleton (see AppServiceProvider /
 * bootstrap) so each HTTP request gets a fresh instance while console
 * commands and queued jobs default to "no tenant" unless they explicitly
 * re-bind one.
 */
final class TenantContext
{
    private ?School $school = null;

    public function set(School $school): void
    {
        $this->school = $school;
    }

    public function current(): ?School
    {
        return $this->school;
    }

    public function hasTenant(): bool
    {
        return $this->school !== null;
    }

    public function forget(): void
    {
        $this->school = null;
    }
}
