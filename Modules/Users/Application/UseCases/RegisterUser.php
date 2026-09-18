<?php

namespace Modules\Users\Application\UseCases;

use Modules\Users\Application\DTOs\UserData;
use Modules\Users\Domain\Entities\User;
use Modules\Users\Domain\Events\UserRegistered;
use Modules\Users\Domain\Repositories\UserRepositoryInterface;
use Modules\Users\Domain\ValueObjects\Email;

final class RegisterUser
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function handle(UserData $data): User
    {
        $user = new User(
            id: null,
            name: $data->name,
            email: new Email($data->email),
        );

        $saved = $this->users->save($user);

        event(new UserRegistered($saved));

        return $saved;
    }
}
