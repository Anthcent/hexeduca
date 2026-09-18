<?php

namespace Modules\Academic\Application\DTOs;

final readonly class CreateOfertaAcademicaData
{
    public function __construct(
        public int $schoolId,
        public int $periodoAcademicoId,
        public int $gradoId,
        public int $seccionId,
        public ?int $teacherId,
        public int $capacity,
    ) {}
}
