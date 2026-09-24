<?php

namespace App\IntegrationEvents\Concerns;

use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Shared bookkeeping (eventId/occurredAt/version) for Public/Events/*
 * classes implementing App\IntegrationEvents\IntegrationEvent. Every event
 * still writes its own eventName()/aggregateType()/aggregateId()/
 * toPayload()/fromPayload() — those are what make each event distinct.
 */
trait HasIntegrationEventEnvelope
{
    public readonly string $eventId;

    public readonly DateTimeImmutable $occurredAt;

    public readonly int $version;

    private function initEnvelope(?string $eventId, ?DateTimeImmutable $occurredAt, int $version): void
    {
        $this->eventId = $eventId ?? (string) Str::uuid();
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable;
        $this->version = $version;
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function version(): int
    {
        return $this->version;
    }
}
