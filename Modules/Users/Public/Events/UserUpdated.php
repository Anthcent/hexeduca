<?php

namespace Modules\Users\Public\Events;

use App\IntegrationEvents\Concerns\HasIntegrationEventEnvelope;
use App\IntegrationEvents\IntegrationEvent;
use DateTimeImmutable;

/**
 * Recorded when user data or roles change so role-qualified projections can
 * remove stale rows and upsert rows for every role in the current role set.
 */
final readonly class UserUpdated implements IntegrationEvent
{
    use HasIntegrationEventEnvelope;

    public function __construct(
        public int $userId,
        public string $name,
        public string $email,
        public ?int $schoolId = null,
        /** @var list<string>|null Null means a legacy role-less payload. */
        public ?array $roles = null,
        int $version = 1,
        ?string $eventId = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        $this->initEnvelope($eventId, $occurredAt, $version);
    }

    public function eventName(): string
    {
        return 'user.updated';
    }

    public function aggregateType(): string
    {
        return 'User';
    }

    public function aggregateId(): string
    {
        return (string) $this->userId;
    }

    public function toPayload(): array
    {
        return [
            'userId' => $this->userId,
            'name' => $this->name,
            'email' => $this->email,
            'schoolId' => $this->schoolId,
            'roles' => $this->roles,
            'version' => $this->version,
            'eventId' => $this->eventId,
            'occurredAt' => $this->occurredAt->format(DATE_ATOM),
        ];
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            userId: $payload['userId'],
            name: $payload['name'],
            email: $payload['email'],
            schoolId: $payload['schoolId'] ?? null,
            roles: array_key_exists('roles', $payload) ? $payload['roles'] : null,
            version: $payload['version'] ?? 1,
            eventId: $payload['eventId'] ?? null,
            occurredAt: isset($payload['occurredAt']) ? new DateTimeImmutable($payload['occurredAt']) : null,
        );
    }
}
