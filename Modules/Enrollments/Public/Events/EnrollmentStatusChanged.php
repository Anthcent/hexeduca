<?php

namespace Modules\Enrollments\Public\Events;

use App\IntegrationEvents\Concerns\HasIntegrationEventEnvelope;
use App\IntegrationEvents\IntegrationEvent;
use DateTimeImmutable;

final readonly class EnrollmentStatusChanged implements IntegrationEvent
{
    use HasIntegrationEventEnvelope;

    public function __construct(
        public int $enrollmentId,
        public int $schoolId,
        public string $previousStatus,
        public string $newStatus,
        int $version = 1,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->initEnvelope($eventId, $occurredAt, $version);
    }

    public function eventName(): string
    {
        return 'enrollment.status_changed';
    }

    public function aggregateType(): string
    {
        return 'Enrollment';
    }

    public function aggregateId(): string
    {
        return (string) $this->enrollmentId;
    }

    public function toPayload(): array
    {
        return [
            'enrollmentId' => $this->enrollmentId,
            'schoolId' => $this->schoolId,
            'previousStatus' => $this->previousStatus,
            'newStatus' => $this->newStatus,
            'version' => $this->version,
            'eventId' => $this->eventId,
            'occurredAt' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            enrollmentId: $payload['enrollmentId'],
            schoolId: $payload['schoolId'],
            previousStatus: $payload['previousStatus'],
            newStatus: $payload['newStatus'],
            version: $payload['version'] ?? 1,
            eventId: $payload['eventId'] ?? null,
            occurredAt: isset($payload['occurredAt']) ? new DateTimeImmutable($payload['occurredAt']) : null,
        );
    }
}
