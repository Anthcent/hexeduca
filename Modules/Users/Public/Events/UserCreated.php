<?php

namespace Modules\Users\Public\Events;

use App\IntegrationEvents\Concerns\HasIntegrationEventEnvelope;
use App\IntegrationEvents\IntegrationEvent;
use DateTimeImmutable;

/**
 * Public integration event, distinct from the older
 * Modules\Users\Domain\Events\UserRegistered (domain-internal, still fired
 * for in-process listeners). This one is recorded to the outbox by
 * RegisterUser so cross-module consumers (e.g. AcademicOffers/Enrollments'
 * teacher/student projections, Fase 8) can react without importing
 * Modules\Users\Infrastructure\Models\User.
 */
final readonly class UserCreated implements IntegrationEvent
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
        return 'user.created';
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
