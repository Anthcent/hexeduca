<?php

namespace Modules\AcademicPeriods\Public\Events;

use App\IntegrationEvents\Concerns\HasIntegrationEventEnvelope;
use App\IntegrationEvents\IntegrationEvent;
use DateTimeImmutable;

final readonly class AcademicPeriodActivated implements IntegrationEvent
{
    use HasIntegrationEventEnvelope;

    public function __construct(
        public int $academicPeriodId,
        public int $schoolId,
        public string $name,
        int $version = 1,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->initEnvelope($eventId, $occurredAt, $version);
    }

    public function eventName(): string
    {
        return 'academic_period.activated';
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
            version: $payload['version'] ?? 1,
            eventId: $payload['eventId'] ?? null,
            occurredAt: isset($payload['occurredAt']) ? new DateTimeImmutable($payload['occurredAt']) : null,
        );
    }
}
