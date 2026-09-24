<?php

namespace Modules\GradeLevels\Public\Events;

use App\IntegrationEvents\Concerns\HasIntegrationEventEnvelope;
use App\IntegrationEvents\IntegrationEvent;
use DateTimeImmutable;

final readonly class GradeLevelCreated implements IntegrationEvent
{
    use HasIntegrationEventEnvelope;

    public function __construct(
        public int $gradeLevelId,
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
        return 'grade_level.created';
    }

    public function aggregateType(): string
    {
        return 'GradeLevel';
    }

    public function aggregateId(): string
    {
        return (string) $this->gradeLevelId;
    }

    public function toPayload(): array
    {
        return [
            'gradeLevelId' => $this->gradeLevelId,
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
            gradeLevelId: $payload['gradeLevelId'],
            schoolId: $payload['schoolId'],
            name: $payload['name'],
            version: $payload['version'] ?? 1,
            eventId: $payload['eventId'] ?? null,
            occurredAt: isset($payload['occurredAt']) ? new DateTimeImmutable($payload['occurredAt']) : null,
        );
    }
}
