<?php

namespace Modules\Users\Infrastructure\Persistence;

use Modules\Users\Domain\Entities\User as UserEntity;
use Modules\Users\Domain\Repositories\UserRepositoryInterface;
use Modules\Users\Domain\ValueObjects\Email;
use Modules\Users\Infrastructure\Models\User as UserModel;

final class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?UserEntity
    {
        $model = UserModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByEmail(Email $email): ?UserEntity
    {
        $model = UserModel::where('email', $email->value())->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(UserEntity $user): UserEntity
    {
        $model = $user->id() ? UserModel::findOrFail($user->id()) : new UserModel;

        $model->name = $user->name();
        $model->email = $user->email()->value();

        if ($user->passwordHash() !== null) {
            $model->password = $user->passwordHash();
        }

        if ($user->schoolId() !== null) {
            $model->school_id = $user->schoolId();
        }

        $model->save();

        return $this->toEntity($model);
    }

    public function assignRole(int $userId, string $role): void
    {
        UserModel::withoutTenantScope()->findOrFail($userId)->assignRole($role);
    }

    public function delete(int $id): void
    {
        UserModel::withoutTenantScope()->findOrFail($id)->delete();
    }

    private function toEntity(UserModel $model): UserEntity
    {
        return new UserEntity(
            id: $model->id,
            name: $model->name,
            email: new Email($model->email),
            passwordHash: $model->password,
            schoolId: $model->school_id,
        );
    }
}
