<?php

use Modules\AcademicPeriods\Public\Contracts\AcademicPeriodReader;
use Modules\AcademicPeriods\Public\DTOs\AcademicPeriodDTO;
use Modules\Subjects\Application\Services\OpenPeriods;
use Modules\Subjects\Domain\Exceptions\InvalidAssignment;

function openPeriodsWith(AcademicPeriodDTO ...$periods): OpenPeriods
{
    return new OpenPeriods(new class($periods) implements AcademicPeriodReader
    {
        /** @param  list<AcademicPeriodDTO>  $periods */
        public function __construct(private readonly array $periods) {}

        public function findForSchool(int $id, int $schoolId): ?AcademicPeriodDTO
        {
            foreach ($this->periods as $period) {
                if ($period->id === $id && $period->schoolId === $schoolId) {
                    return $period;
                }
            }

            return null;
        }

        public function activeForSchool(int $schoolId): ?AcademicPeriodDTO
        {
            return null;
        }

        public function allForSchool(int $schoolId): array
        {
            return [];
        }
    });
}

test('an unknown or another school\'s period is refused, not treated as open', function (int $periodId) {
    $openPeriods = openPeriodsWith(new AcademicPeriodDTO(7, schoolId: 2, name: '2026', isActive: true, startsOn: '2026-01-01', endsOn: '2026-12-31'));

    expect(fn () => $openPeriods->assertOpen($periodId, schoolId: 1))
        ->toThrow(InvalidAssignment::class, "Academic period [{$periodId}] does not belong to the school.");
})->with([
    'unknown' => [99],
    'another school\'s' => [7],
]);
