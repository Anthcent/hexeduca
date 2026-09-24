<?php

use Modules\Notifications\Domain\Exceptions\InvalidAnnouncement;
use Modules\Notifications\Domain\ValueObjects\Announcement;

test('an announcement keeps a trimmed title and body', function () {
    $announcement = new Announcement('  Reunión de padres  ', "  El viernes a las 18 h.\n");

    expect($announcement->title)->toBe('Reunión de padres')
        ->and($announcement->body)->toBe('El viernes a las 18 h.');
});

test('the title is required', function (string $title) {
    new Announcement($title, 'Body');
})->with(['empty' => '', 'whitespace only' => '   '])
    ->throws(InvalidAnnouncement::class, 'The announcement title is required.');

test('the title accepts exactly 120 characters, counted as characters not bytes', function () {
    $title = str_repeat('ñ', 120);

    expect((new Announcement($title, 'Body'))->title)->toBe($title);
});

test('the title may not exceed 120 characters', function () {
    new Announcement(str_repeat('a', 121), 'Body');
})->throws(InvalidAnnouncement::class, 'The announcement title may not be longer than 120 characters.');

test('the body is required', function (string $body) {
    new Announcement('Title', $body);
})->with(['empty' => '', 'whitespace only' => "  \n "])
    ->throws(InvalidAnnouncement::class, 'The announcement body is required.');

test('the body accepts exactly 2000 characters', function () {
    $body = str_repeat('é', 2000);

    expect((new Announcement('Title', $body))->body)->toBe($body);
});

test('the body may not exceed 2000 characters', function () {
    new Announcement('Title', str_repeat('a', 2001));
})->throws(InvalidAnnouncement::class, 'The announcement body may not be longer than 2000 characters.');

test('delivering an announcement creates one unread notification per distinct recipient', function () {
    $notifications = (new Announcement('Title', 'Body'))->deliverTo([3, 5, 3, 8], schoolId: 1, senderId: 9, senderName: 'Ana Staff');

    expect($notifications)->toHaveCount(3)
        ->and(array_map(fn ($notification) => $notification->recipientId(), $notifications))->toBe([3, 5, 8]);

    foreach ($notifications as $notification) {
        expect($notification->id())->toBeNull()
            ->and($notification->schoolId())->toBe(1)
            ->and($notification->senderId())->toBe(9)
            ->and($notification->senderName())->toBe('Ana Staff')
            ->and($notification->title())->toBe('Title')
            ->and($notification->body())->toBe('Body')
            ->and($notification->isRead())->toBeFalse();
    }
});

test('delivering to nobody creates no notifications', function () {
    expect((new Announcement('Title', 'Body'))->deliverTo([], 1, 9, 'Ana Staff'))->toBe([]);
});
