<?php

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Domain\Entities\OfertaAcademica;

/**
 * Framework-agnostic domain event. No Eloquent, no framework dependencies.
 */
final class OfertaAcademicaCreated
{
    public function __construct(
        public readonly OfertaAcademica $ofertaAcademica,
    ) {}
}
