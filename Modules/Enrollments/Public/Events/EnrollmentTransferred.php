<?php

namespace Modules\Enrollments\Public\Events;

use App\IntegrationEvents\Concerns\HasIntegrationEventEnvelope;
use App\IntegrationEvents\IntegrationEvent;
use DateTimeImmutable;

/**
 * Not dispatched yet — no transfer use case exists in the legacy Matricula
 * behavior being ported. Defined now per plan §7/§15 (full lifecycle, not
 * just Created) so consumers can register a listener ahead of the feature
 * landing.
 */
final readonly class EnrollmentTransferred implements IntegrationEvent
{
    use HasIntegrationEventEnvelope;

    public function __construct(
        public int $enrollmentId,
        public int $schoolId,
        public int $fromAcademicOfferId,
        public int $toAcademicOfferId,
        int $version = 1,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->initEnvelope($eventId, $occurredAt, $version);
    }

    public function eventName(): string
    {
        return 'enrollment.transferred';
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
            'fromAcademicOfferId' => $this->fromAcademicOfferId,
            'toAcademicOfferId' => $this->toAcademicOfferId,
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
            fromAcademicOfferId: $payload['fromAcademicOfferId'],
            toAcademicOfferId: $payload['toAcademicOfferId'],
            version: $payload['version'] ?? 1,
            eventId: $payload['eventId'] ?? null,
            occurredAt: isset($payload['occurredAt']) ? new DateTimeImmutable($payload['occurredAt']) : null,
        );
    }
}
