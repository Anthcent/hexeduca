<?php

namespace Modules\Users\Infrastructure\Persistence;

use Modules\Users\Infrastructure\Models\User;
use Modules\Users\Public\Contracts\StudentReader;
use Modules\Users\Public\DTOs\StudentDTO;
use Modules\Users\Public\Enums\UserType;

final class EloquentStudentReader implements StudentReader
{
    public function find(int $id): ?StudentDTO
    {
        $model = User::withoutTenantScope()->where('type', UserType::Student)->find($id);

        return $model ? $this->toDTO($model) : null;
    }

    public function findForSchool(int $id, int $schoolId): ?StudentDTO
    {
        $model = User::withoutTenantScope()
            ->where('type', UserType::Student)
            ->where('school_id', $schoolId)
            ->find($id);

        return $model ? $this->toDTO($model) : null;
    }

    public function all(): array
    {
        return User::withoutTenantScope()->where('type', UserType::Student)
            ->orderBy('id')
            ->get()
            ->map(fn (User $model): StudentDTO => $this->toDTO($model))
            ->all();
    }

    /**
     * @return array<int, StudentDTO>
     */
    public function allForSchool(int $schoolId): array
    {
        return User::withoutTenantScope()->where('type', UserType::Student)
            ->where('school_id', $schoolId)
            ->orderBy('name')
            ->get()
            ->map(fn (User $model): StudentDTO => $this->toDTO($model))
            ->all();
    }

    private function toDTO(User $model): StudentDTO
    {
        return new StudentDTO(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            schoolId: $model->school_id,
        );
    }
}
