<?php

namespace Modules\Notifications\Domain\Entities;

use DateTimeImmutable;

/**
 * One announcement delivered to one recipient. Marking it as read is
 * idempotent: the first read time wins.
 */
final class Notification
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $schoolId,
        private readonly int $recipientId,
        private readonly ?int $senderId,
        private readonly string $senderName,
        private readonly string $title,
        private readonly string $body,
        private ?DateTimeImmutable $readAt = null,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function schoolId(): int
    {
        return $this->schoolId;
    }

    public function recipientId(): int
    {
        return $this->recipientId;
    }

    public function senderId(): ?int
    {
        return $this->senderId;
    }

    public function senderName(): string
    {
        return $this->senderName;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function readAt(): ?DateTimeImmutable
    {
        return $this->readAt;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function markAsRead(DateTimeImmutable $at): void
    {
        $this->readAt ??= $at;
    }
}
