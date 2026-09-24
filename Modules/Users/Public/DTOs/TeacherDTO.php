<?php

namespace Modules\Users\Public\DTOs;

/**
 * Plain read-only projection of a User acting as a teacher. No Eloquent,
 * no behavior — consumers outside this module may only ever see this
 * shape, never the owning module's Eloquent model.
 */
final readonly class TeacherDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?int $schoolId = null,
    ) {}
}
