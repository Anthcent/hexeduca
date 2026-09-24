<?php

namespace Modules\Enrollments\Public\Events;

use App\IntegrationEvents\Concerns\HasIntegrationEventEnvelope;
use App\IntegrationEvents\IntegrationEvent;
use DateTimeImmutable;

/**
 * Flat integration event for consumers outside this module. No Eloquent.
 * Reference event for plan §8's outbox example: Enrollments
 * (EnrollmentCreated) -> Outbox -> Queue -> Grades listener ->
 * grades_enrollment_projection. Wired in Fase 7 via
 * App\IntegrationEvents\Outbox\OutboxEventRecorder — CreateEnrollment
 * records this in the outbox inside the same transaction as the write.
 */
final readonly class EnrollmentCreated implements IntegrationEvent
{
    use HasIntegrationEventEnvelope;

    public function __construct(
        public int $enrollmentId,
        public int $schoolId,
        public int $academicPeriodId,
        public int $academicOfferId,
        public int $studentId,
        public string $status,
        int $version = 1,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->initEnvelope($eventId, $occurredAt, $version);
    }

    public function eventName(): string
    {
        return 'enrollment.created';
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
            'academicPeriodId' => $this->academicPeriodId,
            'academicOfferId' => $this->academicOfferId,
            'studentId' => $this->studentId,
            'status' => $this->status,
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
            academicPeriodId: $payload['academicPeriodId'],
            academicOfferId: $payload['academicOfferId'],
            studentId: $payload['studentId'],
            status: $payload['status'],
            version: $payload['version'] ?? 1,
            eventId: $payload['eventId'] ?? null,
            occurredAt: isset($payload['occurredAt']) ? new DateTimeImmutable($payload['occurredAt']) : null,
        );
    }
}
