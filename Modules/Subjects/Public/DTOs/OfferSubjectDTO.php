<?php

namespace Modules\Subjects\Public\DTOs;

/**
 * A subject in force for one academic offer: it belongs to the offer's
 * effective study plan and grade level, is active, and is not excluded.
 */
final readonly class OfferSubjectDTO
{
    public function __construct(
        public int $id,
        public int $planId,
        public int $gradeLevelId,
        public string $name,
        public ?string $code,
        public ?int $weeklyHours,
    ) {}
}
