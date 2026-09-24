<?php

namespace Modules\Users\Domain\Repositories;

use Modules\Users\Domain\Entities\User;
use Modules\Users\Domain\ValueObjects\Email;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function save(User $user): User;

    public function assignRole(int $userId, string $role): void;
}
