<?php

namespace Modules\Subjects\Domain\ValueObjects;

/**
 * Lifecycle of a study plan or a subject. An archived record is frozen:
 * read-only and out of circulation, but its history is kept.
 */
enum RecordStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
