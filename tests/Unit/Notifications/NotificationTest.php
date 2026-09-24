<?php

use Modules\Notifications\Domain\Entities\Notification;

function unreadNotification(): Notification
{
    return new Notification(
        id: 1,
        schoolId: 1,
        recipientId: 2,
        senderId: 3,
        senderName: 'Ana Staff',
        title: 'Title',
        body: 'Body',
    );
}

test('a new notification is unread', function () {
    expect(unreadNotification()->isRead())->toBeFalse()
        ->and(unreadNotification()->readAt())->toBeNull();
});

test('marking a notification as read records when it was read', function () {
    $notification = unreadNotification();
    $at = new DateTimeImmutable('2026-09-24 10:00:00');

    $notification->markAsRead($at);

    expect($notification->isRead())->toBeTrue()
        ->and($notification->readAt())->toBe($at);
});

test('marking as read is idempotent: the first read time is kept', function () {
    $notification = unreadNotification();
    $first = new DateTimeImmutable('2026-09-24 10:00:00');

    $notification->markAsRead($first);
    $notification->markAsRead(new DateTimeImmutable('2026-09-25 12:00:00'));

    expect($notification->readAt())->toBe($first);
});
