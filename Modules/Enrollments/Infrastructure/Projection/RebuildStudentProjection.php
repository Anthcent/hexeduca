<?php

namespace Modules\Enrollments\Infrastructure\Projection;

use Illuminate\Support\Facades\DB;
use Modules\Users\Public\Contracts\StudentReader;

final class RebuildStudentProjection
{
    public function __construct(
        private readonly StudentReader $students,
    ) {}

    /**
     * @return array{active: int, tombstoned: int}
     */
    public function handle(): array
    {
        return DB::transaction(function (): array {
            $students = $this->students->all();
            $activeIds = array_map(fn ($student): int => $student->id, $students);

            $stale = DB::table('enrollments_student_projection')
                ->where('is_active', true)
                ->when($activeIds !== [], fn ($query) => $query->whereNotIn('source_student_id', $activeIds))
                ->update(['is_active' => false, 'updated_at' => now()]);

            foreach ($students as $student) {
                $version = max(1, (int) DB::table('enrollments_student_projection')
                    ->where('source_student_id', $student->id)
                    ->value('last_event_version'));

                DB::table('enrollments_student_projection')->upsert([
                    'source_student_id' => $student->id,
                    'school_id' => $student->schoolId,
                    'name' => $student->name,
                    'email' => $student->email,
                    'is_active' => true,
                    'source_updated_at' => now(),
                    'last_event_version' => $version,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], ['source_student_id'], [
                    'school_id', 'name', 'email', 'is_active', 'source_updated_at', 'updated_at',
                ]);
            }

            return ['active' => count($students), 'tombstoned' => $stale];
        });
    }
}
