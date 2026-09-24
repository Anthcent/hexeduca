<?php

namespace Modules\AcademicOffers\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\AcademicOffers\Infrastructure\Projection\RebuildTeacherProjection;

final class RebuildTeacherProjectionCommand extends Command
{
    protected $signature = 'academic-offers:rebuild-teacher-projection';

    protected $description = 'Rebuild the AcademicOffers teacher projection from Users public data';

    public function handle(RebuildTeacherProjection $rebuild): int
    {
        $result = $rebuild->handle();
        $this->info("Teacher projection rebuilt: {$result['active']} active, {$result['tombstoned']} tombstoned.");

        return self::SUCCESS;
    }
}
