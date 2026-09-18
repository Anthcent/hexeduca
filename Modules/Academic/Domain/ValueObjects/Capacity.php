<?php

namespace Modules\Academic\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Guards an OfertaAcademica's enrollment limit.
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

    /**
     * True when the given enrollment count has already reached the limit —
     * no more Matricula records may target the offering.
     */
    public function isFullAt(int $enrolledCount): bool
    {
        return $enrolledCount >= $this->limit;
    }

    /**
     * True when the given enrollment count still has room for one more.
     */
    public function hasRoomAt(int $enrolledCount): bool
    {
        return ! $this->isFullAt($enrolledCount);
    }
}
