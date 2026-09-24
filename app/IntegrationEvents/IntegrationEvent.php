<?php

namespace App\IntegrationEvents;

use DateTimeImmutable;

/**
 * Contract every Public/Events/* integration event implements so the
 * platform-neutral Outbox (app/IntegrationEvents/Outbox/*) can record,
 * persist and later rehydrate/redispatch them without importing anything
 * from the owning module beyond this interface. See plan §8/§9.
 */
interface IntegrationEvent
{
    public function eventId(): string;

    public function eventName(): string;

    public function aggregateType(): string;

    public function aggregateId(): string;

    public function version(): int;

    public function occurredAt(): DateTimeImmutable;

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self;
}
