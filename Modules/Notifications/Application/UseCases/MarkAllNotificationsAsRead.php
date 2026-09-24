<?php

namespace Modules\Notifications\Application\UseCases;

use DateTimeImmutable;
use Modules\Notifications\Domain\Repositories\NotificationRepositoryInterface;

final class MarkAllNotificationsAsRead
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
    ) {}

    public function handle(int $recipientId, int $schoolId): int
    {
        return $this->notifications->markAllAsReadFor($recipientId, $schoolId, new DateTimeImmutable);
    }
}
