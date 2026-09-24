<?php

namespace Modules\Notifications\Infrastructure\Persistence;

use DateTimeImmutable;
use Modules\Notifications\Domain\Entities\Notification;
use Modules\Notifications\Domain\Repositories\NotificationRepositoryInterface;
use Modules\Notifications\Infrastructure\Models\SchoolNotification;

final class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    /**
     * Rows per INSERT, kept well below SQLite's bound-parameter limit.
     */
    private const INSERT_CHUNK = 100;

    public function saveMany(array $notifications): void
    {
        $now = now();

        $rows = array_map(fn (Notification $notification): array => [
            'school_id' => $notification->schoolId(),
            'recipient_id' => $notification->recipientId(),
            'sender_id' => $notification->senderId(),
            'sender_name' => $notification->senderName(),
            'title' => $notification->title(),
            'body' => $notification->body(),
            'read_at' => $notification->readAt(),
            'created_at' => $now,
            'updated_at' => $now,
        ], $notifications);

        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            SchoolNotification::query()->insert($chunk);
        }
    }

    public function save(Notification $notification): Notification
    {
        $model = $notification->id()
            ? SchoolNotification::query()->findOrFail($notification->id())
            : new SchoolNotification;

        $model->fill([
            'school_id' => $notification->schoolId(),
            'recipient_id' => $notification->recipientId(),
            'sender_id' => $notification->senderId(),
            'sender_name' => $notification->senderName(),
            'title' => $notification->title(),
            'body' => $notification->body(),
            'read_at' => $notification->readAt(),
        ])->save();

        return $this->toEntity($model);
    }

    public function findForRecipient(int $id, int $recipientId, int $schoolId): ?Notification
    {
        $model = SchoolNotification::query()->forRecipient($recipientId, $schoolId)->find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function markAllAsReadFor(int $recipientId, int $schoolId, DateTimeImmutable $at): int
    {
        return SchoolNotification::query()
            ->forRecipient($recipientId, $schoolId)
            ->unread()
            ->update(['read_at' => $at, 'updated_at' => now()]);
    }

    public function unreadCountFor(int $recipientId, int $schoolId): int
    {
        return SchoolNotification::query()->forRecipient($recipientId, $schoolId)->unread()->count();
    }

    private function toEntity(SchoolNotification $model): Notification
    {
        return new Notification(
            id: $model->id,
            schoolId: $model->school_id,
            recipientId: $model->recipient_id,
            senderId: $model->sender_id,
            senderName: $model->sender_name,
            title: $model->title,
            body: $model->body,
            readAt: $model->read_at,
        );
    }
}
