<?php

namespace Modules\Grades\Application\DTOs;

/**
 * Who is acting and which grade permissions they hold: `seesAllSections`
 * (`grades.scope.all`) manages every subject, otherwise only the assigned
 * ones; `canCorrect` (`grades.correction`) runs correction windows and
 * records Convivir after its window closed.
 */
final readonly class Actor
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public bool $seesAllSections,
        public bool $canCorrect,
    ) {}
}
