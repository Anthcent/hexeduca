<?php

namespace Modules\Academic\Domain\Events;

use Modules\Academic\Domain\Entities\Matricula;

/**
 * Framework-agnostic domain event. No Eloquent, no framework dependencies.
 */
final class EstudianteMatriculado
{
    public function __construct(
        public readonly Matricula $matricula,
    ) {}
}
