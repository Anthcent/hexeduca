<?php

namespace Modules\Academic\Infrastructure\Period;

use Modules\Academic\Infrastructure\Models\PeriodoAcademico;

/**
 * Holds the currently resolved active academic period for the request
 * lifecycle.
 *
 * Structural clone of App\Tenancy\TenantContext. Bound as a scoped
 * container singleton (see AcademicServiceProvider::register()) so each
 * HTTP request gets a fresh instance while console commands and queued
 * jobs default to "no period" unless they explicitly re-bind one.
 */
final class PeriodoContext
{
    private ?PeriodoAcademico $periodo = null;

    public function set(PeriodoAcademico $periodo): void
    {
        $this->periodo = $periodo;
    }

    public function current(): ?PeriodoAcademico
    {
        return $this->periodo;
    }

    public function hasPeriodo(): bool
    {
        return $this->periodo !== null;
    }

    public function forget(): void
    {
        $this->periodo = null;
    }
}
