<?php

namespace Modules\Grades\Infrastructure\Queries;

use Illuminate\Support\Facades\DB;
use Modules\Users\Public\Contracts\StudentReader;
use Modules\Users\Public\DTOs\StudentDTO;

/**
 * The students actively enrolled in an offer, by name.
 */
final class OfferRoster
{
    public function __construct(private readonly StudentReader $students) {}

    /**
     * @return list<array{id: int, name: string}>
     */
    public function forOffer(int $schoolId, int $offerId): array
    {
        $names = [];

        foreach ($this->students->allForSchool($schoolId) as $student) {
            /** @var StudentDTO $student */
            $names[$student->id] = $student->name;
        }

        $roster = DB::table('grades_enrollment_projection')
            ->where('school_id', $schoolId)
            ->where('academic_offer_id', $offerId)
            ->where('status', 'active')
            ->pluck('student_id')
            ->map(fn ($id): array => ['id' => (int) $id, 'name' => $names[(int) $id] ?? "Estudiante {$id}"])
            ->all();

        usort($roster, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $roster;
    }
}
