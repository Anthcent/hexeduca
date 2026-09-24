<?php

namespace App\AcademicPeriod\Contracts;

/**
 * Immutable read model for the tenant's active academic period.
 *
 * Deliberately not an Eloquent model: this is the shape the platform layer
 * (middleware, context, scope) is allowed to know about. The owning module
 * (Modules\AcademicPeriods, or Modules\Academic during the Phase 1 bridge)
 * maps its own entity into this DTO at the boundary.
 */
final readonly class ActivePeriod
{
    public function __construct(
        public int|string $id,
        public string $name,
        public \DateTimeInterface $startsOn,
        public \DateTimeInterface $endsOn,
    ) {}
}
