<?php

namespace Modules\Enrollments\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\Enrollments\Infrastructure\Projection\RebuildStudentProjection;

final class RebuildStudentProjectionCommand extends Command
{
    protected $signature = 'enrollments:rebuild-student-projection';

    protected $description = 'Rebuild the Enrollments student projection from Users public data';

    public function handle(RebuildStudentProjection $rebuild): int
    {
        $result = $rebuild->handle();
        $this->info("Student projection rebuilt: {$result['active']} active, {$result['tombstoned']} tombstoned.");

        return self::SUCCESS;
    }
}
