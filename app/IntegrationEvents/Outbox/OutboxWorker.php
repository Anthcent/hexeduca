<?php

namespace App\IntegrationEvents\Outbox;

use App\IntegrationEvents\IntegrationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Drains pending rows from `integration_outbox_events`, rehydrates each
 * event via its own `fromPayload()`, and hands it to Laravel's normal event
 * bus (`event($instance)`) — the ShouldQueue listeners already registered
 * per module take it from there onto the real queue. Rows are atomically
 * claimed, retried with bounded backoff, and terminal failures do not stop
 * the rest of the batch (plan §9/§11).
 */
final class OutboxWorker
{
    public const MAX_ATTEMPTS = 5;

    /** @var list<int> */
    public const BACKOFF_SECONDS = [10, 30, 60, 300];

    public const CLAIM_TIMEOUT_SECONDS = 300;

    public function run(int $limit = 100): array
    {
        $published = 0;
        $retried = 0;
        $terminalFailed = 0;
        $ownershipLost = 0;

        $rows = DB::table('integration_outbox_events')
            ->where(function ($query): void {
                $query->where(function ($pending): void {
                    $pending->where('status', 'pending')
                        ->where(function ($available): void {
                            $available->whereNull('available_at')->orWhere('available_at', '<=', now());
                        });
                })->orWhere(function ($stale): void {
                    $stale->where('status', 'processing')
                        ->where('claimed_at', '<=', now()->subSeconds(self::CLAIM_TIMEOUT_SECONDS));
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            $claimToken = (string) Str::uuid();
            $claimed = DB::table('integration_outbox_events')
                ->where('id', $row->id)
                ->where(function ($query): void {
                    $query->where(function ($pending): void {
                        $pending->where('status', 'pending')
                            ->where(function ($available): void {
                                $available->whereNull('available_at')->orWhere('available_at', '<=', now());
                            });
                    })->orWhere(function ($stale): void {
                        $stale->where('status', 'processing')
                            ->where('claimed_at', '<=', now()->subSeconds(self::CLAIM_TIMEOUT_SECONDS));
                    });
                })
                ->update([
                    'status' => 'processing',
                    'claim_token' => $claimToken,
                    'claimed_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($claimed !== 1) {
                continue;
            }

            try {
                /** @var class-string<IntegrationEvent> $class */
                $class = $row->event_class;
                $payload = json_decode((string) $row->payload, true, flags: JSON_THROW_ON_ERROR);

                $event = $class::fromPayload($payload);

                event($event);

                $finalized = DB::table('integration_outbox_events')
                    ->where('id', $row->id)
                    ->where('status', 'processing')
                    ->where('claim_token', $claimToken)
                    ->update([
                        'status' => 'published',
                        'published_at' => now(),
                        'available_at' => null,
                        'failed_reason' => null,
                        'claim_token' => null,
                        'claimed_at' => null,
                        'updated_at' => now(),
                    ]);

                if ($finalized === 1) {
                    $published++;
                } else {
                    $ownershipLost++;
                }
            } catch (Throwable $e) {
                $attempts = $row->attempts + 1;
                $terminal = $attempts >= self::MAX_ATTEMPTS;
                $backoffIndex = min($attempts - 1, count(self::BACKOFF_SECONDS) - 1);

                $finalized = DB::table('integration_outbox_events')
                    ->where('id', $row->id)
                    ->where('status', 'processing')
                    ->where('claim_token', $claimToken)
                    ->update([
                        'status' => $terminal ? 'failed' : 'pending',
                        'failed_reason' => mb_substr($e->getMessage(), 0, 255),
                        'attempts' => $attempts,
                        'available_at' => $terminal ? null : now()->addSeconds(self::BACKOFF_SECONDS[$backoffIndex]),
                        'claim_token' => null,
                        'claimed_at' => null,
                        'updated_at' => now(),
                    ]);

                if ($finalized !== 1) {
                    $ownershipLost++;

                    continue;
                }

                if ($terminal) {
                    $terminalFailed++;
                    Log::channel(config('integration-events.terminal_log_channel', 'integration-events'))
                        ->critical('Integration outbox event exhausted retries', [
                            'outbox_id' => $row->id,
                            'event_name' => $row->event_name,
                            'aggregate_type' => $row->aggregate_type,
                            'aggregate_id' => $row->aggregate_id,
                            'attempts' => $attempts,
                            'event_id' => json_decode((string) $row->payload, true)['eventId'] ?? null,
                            'error' => $e->getMessage(),
                        ]);
                } else {
                    $retried++;
                }
            }
        }

        $outstandingTerminalFailed = DB::table('integration_outbox_events')
            ->where('status', 'failed')
            ->count();

        return [
            'published' => $published,
            'retried' => $retried,
            'newly_terminal_failed' => $terminalFailed,
            'outstanding_terminal_failed' => $outstandingTerminalFailed,
            'ownership_lost' => $ownershipLost,
            'failed' => $retried + $terminalFailed,
        ];
    }
}
