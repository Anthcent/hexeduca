<?php

namespace App\IntegrationEvents\Console\Commands;

use App\IntegrationEvents\Outbox\OutboxWorker;
use Illuminate\Console\Command;

class PublishOutboxEvents extends Command
{
    protected $signature = 'integration-events:publish-outbox {--limit=100}';

    protected $description = 'Publish pending rows from integration_outbox_events onto the event bus.';

    public function handle(OutboxWorker $worker): int
    {
        $result = $worker->run((int) $this->option('limit'));

        $this->info(
            "Outbox: {$result['published']} published, {$result['retried']} retried, "
            ."{$result['newly_terminal_failed']} newly terminal, "
            ."{$result['outstanding_terminal_failed']} outstanding terminal, "
            ."{$result['ownership_lost']} ownership lost."
        );

        return $result['outstanding_terminal_failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
