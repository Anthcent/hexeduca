<?php

namespace Modules\Users\Infrastructure\Persistence;

use Modules\Users\Infrastructure\Models\User;
use Modules\Users\Public\Contracts\TeacherReader;
use Modules\Users\Public\DTOs\TeacherDTO;

final class EloquentTeacherReader implements TeacherReader
{
    public function find(int $id): ?TeacherDTO
    {
        $model = User::withoutTenantScope()->role('teacher')->find($id);

        return $model ? $this->toDTO($model) : null;
    }

    public function findForSchool(int $id, int $schoolId): ?TeacherDTO
    {
        $model = User::withoutTenantScope()
            ->role('teacher')
            ->where('school_id', $schoolId)
            ->find($id);

        return $model ? $this->toDTO($model) : null;
    }

    public function all(): array
    {
        return User::withoutTenantScope()->role('teacher')
            ->orderBy('id')
            ->get()
            ->map(fn (User $model): TeacherDTO => $this->toDTO($model))
            ->all();
    }

    /**
     * @return array<int, TeacherDTO>
     */
    public function allForSchool(int $schoolId): array
    {
        return User::withoutTenantScope()->role('teacher')
            ->where('school_id', $schoolId)
            ->orderBy('name')
            ->get()
            ->map(fn (User $model): TeacherDTO => $this->toDTO($model))
            ->all();
    }

    private function toDTO(User $model): TeacherDTO
    {
        return new TeacherDTO(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            schoolId: $model->school_id,
        );
    }
}
