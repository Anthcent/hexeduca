<?php

namespace Modules\AcademicOffers\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Guards an AcademicOffer's enrollment limit.
 */
final class Capacity
{
    private int $limit;

    public function __construct(int $limit)
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Capacity limit must not be negative.');
        }

        $this->limit = $limit;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function isFullAt(int $enrolledCount): bool
    {
        return $enrolledCount >= $this->limit;
    }

    public function hasRoomAt(int $enrolledCount): bool
    {
        return ! $this->isFullAt($enrolledCount);
    }
}
