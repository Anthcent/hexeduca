<?php

namespace Modules\Academic\Application\DTOs;

use DateTimeImmutable;

final readonly class MatricularEstudianteData
{
    public function __construct(
        public int $ofertaAcademicaId,
        public int $studentId,
        public string $status = 'active',
        public ?DateTimeImmutable $enrolledAt = null,
    ) {}
}
