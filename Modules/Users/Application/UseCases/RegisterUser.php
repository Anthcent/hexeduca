<?php

namespace Modules\Users\Application\UseCases;

use App\IntegrationEvents\Outbox\OutboxEventRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Users\Application\DTOs\UserData;
use Modules\Users\Domain\Entities\User;
use Modules\Users\Domain\Events\UserRegistered;
use Modules\Users\Domain\Repositories\UserRepositoryInterface;
use Modules\Users\Domain\ValueObjects\Email;
use Modules\Users\Public\Events\UserCreated as IntegrationUserCreated;

final class RegisterUser
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly OutboxEventRecorder $outbox,
    ) {}

    public function handle(UserData $data): User
    {
        $user = new User(
            id: null,
            name: $data->name,
            email: new Email($data->email),
            passwordHash: Hash::make($data->password),
            schoolId: $data->schoolId,
        );

        $saved = DB::transaction(function () use ($user, $data) {
            $saved = $this->users->save($user);
            $this->users->assignRole($saved->id(), $data->role);

            $this->outbox->record(new IntegrationUserCreated(
                userId: $saved->id(),
                name: $saved->name(),
                email: (string) $saved->email(),
                schoolId: $saved->schoolId(),
                roles: [$data->role],
            ));

            return $saved;
        });

        event(new UserRegistered($saved));

        return $saved;
    }
}
