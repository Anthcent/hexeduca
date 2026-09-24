<?php

namespace Modules\Notifications\Application\DTOs;

final readonly class SendAnnouncementData
{
    public function __construct(
        public int $schoolId,
        public int $senderId,
        public string $senderName,
        public string $title,
        public string $body,
        public string $audience,
    ) {}
}
