<?php

namespace Modules\Enrollments\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Modules\Users\Public\Contracts\StudentReader;
use Modules\Users\Public\DTOs\StudentDTO;

/**
 * Reads Enrollments' own local projection (`enrollments_student_
 * projection`, kept fresh by ProjectStudentListener) instead of calling
 * out to Modules\Users on every request — plan §6/§8. Not bound to the
 * global StudentReader interface key (see
 * Modules\AcademicOffers\Infrastructure\Persistence\
 * LocalProjectionTeacherReader for why); type-hinted by concrete class in
 * EnrollmentController instead.
 */
final class LocalProjectionStudentReader implements StudentReader
{
    public function find(int $id): ?StudentDTO
    {
        $row = DB::table('enrollments_student_projection')
            ->where('source_student_id', $id)
            ->where('is_active', true)
            ->first();

        return $row ? $this->toDTO($row) : null;
    }

    public function findForSchool(int $id, int $schoolId): ?StudentDTO
    {
        $row = DB::table('enrollments_student_projection')
            ->where('source_student_id', $id)
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->first();

        return $row ? $this->toDTO($row) : null;
    }

    public function all(): array
    {
        return DB::table('enrollments_student_projection')
            ->where('is_active', true)
            ->orderBy('source_student_id')
            ->get()
            ->map(fn ($row): StudentDTO => $this->toDTO($row))
            ->all();
    }

    /**
     * @return array<int, StudentDTO>
     */
    public function allForSchool(int $schoolId): array
    {
        return DB::table('enrollments_student_projection')
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($row): StudentDTO => $this->toDTO($row))
            ->all();
    }

    private function toDTO(object $row): StudentDTO
    {
        return new StudentDTO(
            id: (int) $row->source_student_id,
            name: $row->name,
            email: $row->email,
            schoolId: $row->school_id !== null ? (int) $row->school_id : null,
        );
    }
}
