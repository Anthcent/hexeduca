<?php

namespace App\IntegrationEvents\Outbox;

use App\IntegrationEvents\IntegrationEvent;
use Illuminate\Support\Facades\DB;

/**
 * Records an integration event durably in `integration_outbox_events`. Call
 * this INSIDE the same DB transaction that persists the business write, so
 * a crash between COMMIT and dispatch can never lose the event (plan §9).
 * Uses the query builder, not Eloquent, so this class stays owned by no
 * module and every module can depend on it without crossing a module
 * boundary.
 */
final class OutboxEventRecorder
{
    public function record(IntegrationEvent $event): void
    {
        DB::table('integration_outbox_events')->insert([
            'event_name' => $event->eventName(),
            'event_class' => $event::class,
            'aggregate_type' => $event->aggregateType(),
            'aggregate_id' => $event->aggregateId(),
            'payload' => json_encode($event->toPayload()),
            'occurred_at' => $event->occurredAt(),
            'status' => 'pending',
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
