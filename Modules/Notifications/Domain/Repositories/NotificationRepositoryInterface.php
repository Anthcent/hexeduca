<?php

namespace Modules\Notifications\Domain\Repositories;

use DateTimeImmutable;
use Modules\Notifications\Domain\Entities\Notification;

interface NotificationRepositoryInterface
{
    /**
     * @param  list<Notification>  $notifications
     */
    public function saveMany(array $notifications): void;

    public function save(Notification $notification): Notification;

    public function findForRecipient(int $id, int $recipientId, int $schoolId): ?Notification;

    /**
     * Marks every unread notification of the recipient as read and returns
     * how many changed.
     */
    public function markAllAsReadFor(int $recipientId, int $schoolId, DateTimeImmutable $at): int;

    public function unreadCountFor(int $recipientId, int $schoolId): int;
}
