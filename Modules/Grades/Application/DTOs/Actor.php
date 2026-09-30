<?php

namespace Modules\Grades\Application\DTOs;

/**
 * Who is acting: staff may manage every subject; a teacher only the ones
 * they are assigned to.
 */
final readonly class Actor
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public bool $isStaff,
    ) {}
}
