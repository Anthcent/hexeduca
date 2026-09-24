<?php

namespace Modules\AcademicOffers\Public\Events;

use App\IntegrationEvents\Concerns\HasIntegrationEventEnvelope;
use App\IntegrationEvents\IntegrationEvent;
use DateTimeImmutable;

final readonly class AcademicOfferUpdated implements IntegrationEvent
{
    use HasIntegrationEventEnvelope;

    public function __construct(
        public int $academicOfferId,
        public int $schoolId,
        public int $academicPeriodId,
        int $version = 1,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->initEnvelope($eventId, $occurredAt, $version);
    }

    public function eventName(): string
    {
        return 'academic_offer.updated';
    }

    public function aggregateType(): string
    {
        return 'AcademicOffer';
    }

    public function aggregateId(): string
    {
        return (string) $this->academicOfferId;
    }

    public function toPayload(): array
    {
        return [
            'academicOfferId' => $this->academicOfferId,
            'schoolId' => $this->schoolId,
            'academicPeriodId' => $this->academicPeriodId,
            'version' => $this->version,
            'eventId' => $this->eventId,
            'occurredAt' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            academicOfferId: $payload['academicOfferId'],
            schoolId: $payload['schoolId'],
            academicPeriodId: $payload['academicPeriodId'],
            version: $payload['version'] ?? 1,
            eventId: $payload['eventId'] ?? null,
            occurredAt: isset($payload['occurredAt']) ? new DateTimeImmutable($payload['occurredAt']) : null,
        );
    }
}
