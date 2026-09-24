<?php

namespace Modules\Users\Application\UseCases;

use App\IntegrationEvents\Outbox\OutboxEventRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Users\Domain\Exceptions\UserCannotBeDeleted;
use Modules\Users\Domain\Repositories\UserRepositoryInterface;
use Modules\Users\Public\Events\UserUpdated;

/**
 * Hard-deletes a user and, in the same transaction, records a role-less
 * `UserUpdated` (roles: []) so every role-qualified projection deactivates
 * its row once the outbox is published.
 *
 * Authorization (tenant ownership, self-deletion, super-admin targets) is
 * the caller's responsibility — see `UserPolicy::delete`.
 */
final class DeleteUser
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly OutboxEventRecorder $outbox,
    ) {}

    /**
     * @throws UserCannotBeDeleted when another module still references the user
     */
    public function handle(int $userId): void
    {
        try {
            DB::transaction(function () use ($userId): void {
                $user = $this->users->findById($userId);

                if ($user === null) {
                    return;
                }

                $this->outbox->record(new UserUpdated(
                    userId: $userId,
                    name: $user->name(),
                    email: (string) $user->email(),
                    schoolId: $user->schoolId(),
                    roles: [],
                    version: $this->nextEventVersion($userId),
                ));

                $this->users->delete($userId);
            });
        } catch (QueryException $e) {
            // SQLSTATE class 23 = integrity constraint violation (restrict FK).
            if (str_starts_with((string) $e->getCode(), '23')) {
                throw UserCannotBeDeleted::becauseOfDependentRecords($userId, $e);
            }

            throw $e;
        }
    }

    /**
     * Same versioning rule as the role-reassignment flow: projections only
     * apply an event whose version is newer than the last one they saw.
     */
    private function nextEventVersion(int $userId): int
    {
        $latestPayload = DB::table('integration_outbox_events')
            ->where('aggregate_type', 'User')
            ->where('aggregate_id', (string) $userId)
            ->whereIn('event_name', ['user.created', 'user.updated'])
            ->orderByDesc('id')
            ->value('payload');

        $latestVersion = $latestPayload === null
            ? 1
            : (int) (json_decode((string) $latestPayload, true)['version'] ?? 1);

        return $latestVersion + 1;
    }
}
