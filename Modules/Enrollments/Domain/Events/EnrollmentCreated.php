<?php

namespace Modules\Enrollments\Domain\Events;

use Modules\Enrollments\Domain\Entities\Enrollment;

/**
 * Framework-agnostic domain event. No Eloquent, no framework dependencies.
 */
final class EnrollmentCreated
{
    public function __construct(
        public readonly Enrollment $enrollment,
    ) {}
}
