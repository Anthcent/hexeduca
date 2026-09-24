<?php

namespace Modules\Notifications\Application\UseCases;

use DateTimeImmutable;
use Modules\Notifications\Domain\Repositories\NotificationRepositoryInterface;

final class MarkNotificationAsRead
{
    public function __construct(
        private readonly NotificationRepositoryInterface $notifications,
    ) {}

    /**
     * Returns false when the notification does not exist for this recipient
     * in this school, so callers can answer 404 without leaking whether it
     * belongs to someone else.
     */
    public function handle(int $notificationId, int $recipientId, int $schoolId): bool
    {
        $notification = $this->notifications->findForRecipient($notificationId, $recipientId, $schoolId);

        if ($notification === null) {
            return false;
        }

        if (! $notification->isRead()) {
            $notification->markAsRead(new DateTimeImmutable);
            $this->notifications->save($notification);
        }

        return true;
    }
}
