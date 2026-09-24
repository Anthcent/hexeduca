<?php

namespace Modules\AcademicOffers\Infrastructure\Persistence;

use Illuminate\Support\Facades\DB;
use Modules\Users\Public\Contracts\TeacherReader;
use Modules\Users\Public\DTOs\TeacherDTO;

/**
 * Reads AcademicOffers' own local projection (`academic_offers_teacher_
 * projection`, kept fresh by ProjectTeacherListener) instead of calling out
 * to Modules\Users on every request — plan §6/§8.
 *
 * Deliberately NOT bound to the Users\Public\Contracts\TeacherReader
 * interface in the global container (that binding belongs to
 * UsersServiceProvider and must stay Users' own EloquentTeacherReader).
 * This class is type-hinted directly by its concrete name in
 * AcademicOfferController, so Laravel's container auto-resolves it without
 * any provider-order-dependent rebind of a shared interface key.
 */
final class LocalProjectionTeacherReader implements TeacherReader
{
    public function find(int $id): ?TeacherDTO
    {
        $row = DB::table('academic_offers_teacher_projection')
            ->where('source_teacher_id', $id)
            ->where('is_active', true)
            ->first();

        return $row ? $this->toDTO($row) : null;
    }

    public function findForSchool(int $id, int $schoolId): ?TeacherDTO
    {
        $row = DB::table('academic_offers_teacher_projection')
            ->where('source_teacher_id', $id)
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->first();

        return $row ? $this->toDTO($row) : null;
    }

    public function all(): array
    {
        return DB::table('academic_offers_teacher_projection')
            ->where('is_active', true)
            ->orderBy('source_teacher_id')
            ->get()
            ->map(fn ($row): TeacherDTO => $this->toDTO($row))
            ->all();
    }

    /**
     * @return array<int, TeacherDTO>
     */
    public function allForSchool(int $schoolId): array
    {
        return DB::table('academic_offers_teacher_projection')
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn ($row): TeacherDTO => $this->toDTO($row))
            ->all();
    }

    private function toDTO(object $row): TeacherDTO
    {
        return new TeacherDTO(
            id: (int) $row->source_teacher_id,
            name: $row->name,
            email: $row->email,
            schoolId: $row->school_id !== null ? (int) $row->school_id : null,
        );
    }
}
