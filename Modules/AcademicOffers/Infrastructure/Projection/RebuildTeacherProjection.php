<?php

namespace Modules\AcademicOffers\Infrastructure\Projection;

use Illuminate\Support\Facades\DB;
use Modules\Users\Public\Contracts\TeacherReader;

final class RebuildTeacherProjection
{
    public function __construct(
        private readonly TeacherReader $teachers,
    ) {}

    /**
     * @return array{active: int, tombstoned: int}
     */
    public function handle(): array
    {
        return DB::transaction(function (): array {
            $teachers = $this->teachers->all();
            $activeIds = array_map(fn ($teacher): int => $teacher->id, $teachers);

            $stale = DB::table('academic_offers_teacher_projection')
                ->where('is_active', true)
                ->when($activeIds !== [], fn ($query) => $query->whereNotIn('source_teacher_id', $activeIds))
                ->update(['is_active' => false, 'updated_at' => now()]);

            foreach ($teachers as $teacher) {
                $version = max(1, (int) DB::table('academic_offers_teacher_projection')
                    ->where('source_teacher_id', $teacher->id)
                    ->value('last_event_version'));

                DB::table('academic_offers_teacher_projection')->upsert([
                    'source_teacher_id' => $teacher->id,
                    'school_id' => $teacher->schoolId,
                    'name' => $teacher->name,
                    'email' => $teacher->email,
                    'is_active' => true,
                    'source_updated_at' => now(),
                    'last_event_version' => $version,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], ['source_teacher_id'], [
                    'school_id', 'name', 'email', 'is_active', 'source_updated_at', 'updated_at',
                ]);
            }

            return ['active' => count($teachers), 'tombstoned' => $stale];
        });
    }
}
