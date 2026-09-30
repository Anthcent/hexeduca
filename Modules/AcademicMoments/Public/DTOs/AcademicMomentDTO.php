<?php

namespace Modules\AcademicMoments\Public\DTOs;

/**
 * A grading cut (lapso / momento) of an academic period. Dates are ISO
 * (Y-m-d). The grading window is null until staff sets it.
 */
final readonly class AcademicMomentDTO
{
    public function __construct(
        public int $id,
        public int $periodId,
        public string $name,
        public int $order,
        public string $startsOn,
        public string $endsOn,
        public ?string $gradingOpensOn,
        public ?string $gradingClosesOn,
    ) {}
}
