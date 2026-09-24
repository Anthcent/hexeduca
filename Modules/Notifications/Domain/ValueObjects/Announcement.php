<?php

namespace Modules\Notifications\Domain\ValueObjects;

use Modules\Notifications\Domain\Entities\Notification;
use Modules\Notifications\Domain\Exceptions\InvalidAnnouncement;

/**
 * The content staff publish: a required title (max 120 characters) and a
 * required body (max 2000 characters). Surrounding whitespace is trimmed
 * before the rules are checked.
 */
final readonly class Announcement
{
    public const TITLE_MAX_LENGTH = 120;

    public const BODY_MAX_LENGTH = 2000;

    public string $title;

    public string $body;

    public function __construct(string $title, string $body)
    {
        $title = trim($title);
        $body = trim($body);

        if ($title === '') {
            throw InvalidAnnouncement::titleRequired();
        }

        if (mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            throw InvalidAnnouncement::titleTooLong(self::TITLE_MAX_LENGTH);
        }

        if ($body === '') {
            throw InvalidAnnouncement::bodyRequired();
        }

        if (mb_strlen($body) > self::BODY_MAX_LENGTH) {
            throw InvalidAnnouncement::bodyTooLong(self::BODY_MAX_LENGTH);
        }

        $this->title = $title;
        $this->body = $body;
    }

    /**
     * Fans the announcement out into one unread notification per distinct
     * recipient.
     *
     * @param  iterable<int>  $recipientIds
     * @return list<Notification>
     */
    public function deliverTo(iterable $recipientIds, int $schoolId, ?int $senderId, string $senderName): array
    {
        $notifications = [];

        foreach ($recipientIds as $recipientId) {
            $notifications[$recipientId] ??= new Notification(
                id: null,
                schoolId: $schoolId,
                recipientId: $recipientId,
                senderId: $senderId,
                senderName: $senderName,
                title: $this->title,
                body: $this->body,
            );
        }

        return array_values($notifications);
    }
}
