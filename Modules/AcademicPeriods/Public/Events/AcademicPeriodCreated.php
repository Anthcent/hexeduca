<?php

namespace Modules\AcademicPeriods\Public\Events;

use App\IntegrationEvents\Concerns\HasIntegrationEventEnvelope;
use App\IntegrationEvents\IntegrationEvent;
use DateTimeImmutable;

/**
 * Plain integration event for cross-module consumers. No Eloquent, no
 * dependency on this module's internal Domain/Infrastructure classes —
 * other modules may import this class, and only this class, from
 * Modules\AcademicPeriods. Wired into the outbox in Fase 7.
 */
final readonly class AcademicPeriodCreated implements IntegrationEvent
{
    use HasIntegrationEventEnvelope;

    public function __construct(
        public int $academicPeriodId,
        public int $schoolId,
        public string $name,
        public DateTimeImmutable $startsOn,
        public DateTimeImmutable $endsOn,
        int $version = 1,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->initEnvelope($eventId, $occurredAt, $version);
    }

    public function eventName(): string
    {
        return 'academic_period.created';
    }

    public function aggregateType(): string
    {
        return 'AcademicPeriod';
    }

    public function aggregateId(): string
    {
        return (string) $this->academicPeriodId;
    }

    public function toPayload(): array
    {
        return [
            'academicPeriodId' => $this->academicPeriodId,
            'schoolId' => $this->schoolId,
            'name' => $this->name,
            'startsOn' => $this->startsOn->format(DATE_ATOM),
            'endsOn' => $this->endsOn->format(DATE_ATOM),
            'version' => $this->version,
            'eventId' => $this->eventId,
            'occurredAt' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            academicPeriodId: $payload['academicPeriodId'],
            schoolId: $payload['schoolId'],
            name: $payload['name'],
            startsOn: new DateTimeImmutable($payload['startsOn']),
            endsOn: new DateTimeImmutable($payload['endsOn']),
            version: $payload['version'] ?? 1,
            eventId: $payload['eventId'] ?? null,
            occurredAt: isset($payload['occurredAt']) ? new DateTimeImmutable($payload['occurredAt']) : null,
        );
    }
}
