<?php

use App\IntegrationEvents\Outbox\OutboxEventRecorder;
use App\IntegrationEvents\Outbox\OutboxWorker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\AcademicOffers\Infrastructure\Listeners\ProjectTeacherListener;
use Modules\Enrollments\Infrastructure\Listeners\ProjectStudentListener;
use Modules\Grades\Infrastructure\Listeners\ProjectEnrollmentListener;
use Modules\Sections\Public\Events\SectionCreated;

uses(RefreshDatabase::class);

test('record() inserts a pending row with the event payload', function () {
    app(OutboxEventRecorder::class)->record(new SectionCreated(
        sectionId: 1,
        schoolId: 2,
        name: 'A',
    ));

    $row = DB::table('integration_outbox_events')->sole();

    expect($row->event_name)->toBe('section.created')
        ->and($row->event_class)->toBe(SectionCreated::class)
        ->and($row->aggregate_type)->toBe('Section')
        ->and($row->aggregate_id)->toBe('1')
        ->and($row->status)->toBe('pending')
        ->and($row->attempts)->toBe(0);

    $payload = json_decode($row->payload, true);
    expect($payload['sectionId'])->toBe(1)
        ->and($payload['name'])->toBe('A');
});

test('a rollback of the enclosing transaction leaves no outbox row', function () {
    try {
        DB::transaction(function () {
            app(OutboxEventRecorder::class)->record(new SectionCreated(1, 2, 'A'));

            throw new RuntimeException('force rollback');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect(DB::table('integration_outbox_events')->count())->toBe(0);
});

test('OutboxWorker publishes pending rows and marks them published', function () {
    Event::fake([SectionCreated::class]);

    app(OutboxEventRecorder::class)->record(new SectionCreated(1, 2, 'A'));

    $result = app(OutboxWorker::class)->run();

    expect($result)->toBe([
        'published' => 1,
        'retried' => 0,
        'newly_terminal_failed' => 0,
        'outstanding_terminal_failed' => 0,
        'ownership_lost' => 0,
        'failed' => 0,
    ]);

    $row = DB::table('integration_outbox_events')->sole();
    expect($row->status)->toBe('published')
        ->and($row->published_at)->not->toBeNull();

    Event::assertDispatched(SectionCreated::class, fn (SectionCreated $e) => $e->sectionId === 1);
});

test('a broken event schedules a bounded retry without stopping the rest of the batch', function () {
    Event::fake([SectionCreated::class]);

    DB::table('integration_outbox_events')->insert([
        'event_name' => 'broken.event',
        'event_class' => 'App\\Nonexistent\\ClassThatDoesNotExist',
        'aggregate_type' => 'Broken',
        'aggregate_id' => '1',
        'payload' => json_encode([]),
        'occurred_at' => now(),
        'status' => 'pending',
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(OutboxEventRecorder::class)->record(new SectionCreated(2, 2, 'B'));

    $result = app(OutboxWorker::class)->run();

    expect($result)->toMatchArray(['published' => 1, 'retried' => 1, 'newly_terminal_failed' => 0, 'outstanding_terminal_failed' => 0, 'failed' => 1]);

    $broken = DB::table('integration_outbox_events')->where('event_name', 'broken.event')->sole();
    expect($broken->status)->toBe('pending')
        ->and($broken->attempts)->toBe(1)
        ->and($broken->available_at)->not->toBeNull()
        ->and($broken->failed_reason)->not->toBeNull();

    $good = DB::table('integration_outbox_events')->where('event_name', 'section.created')->sole();
    expect($good->status)->toBe('published');
});

test('a transient outbox failure succeeds after its scheduled backoff', function () {
    DB::table('integration_outbox_events')->insert([
        'event_name' => 'section.created',
        'event_class' => 'App\\Nonexistent\\TemporaryFailure',
        'aggregate_type' => 'Section',
        'aggregate_id' => '1',
        'payload' => json_encode((new SectionCreated(1, 2, 'A'))->toPayload()),
        'occurred_at' => now(),
        'status' => 'pending',
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(app(OutboxWorker::class)->run())->toMatchArray(['published' => 0, 'retried' => 1, 'newly_terminal_failed' => 0, 'outstanding_terminal_failed' => 0, 'failed' => 1]);
    expect(app(OutboxWorker::class)->run())->toMatchArray(['published' => 0, 'retried' => 0, 'newly_terminal_failed' => 0, 'outstanding_terminal_failed' => 0, 'failed' => 0]);

    DB::table('integration_outbox_events')->update(['event_class' => SectionCreated::class]);
    $this->travel(OutboxWorker::BACKOFF_SECONDS[0] + 1)->seconds();
    Event::fake([SectionCreated::class]);

    expect(app(OutboxWorker::class)->run())->toMatchArray(['published' => 1, 'retried' => 0, 'newly_terminal_failed' => 0, 'outstanding_terminal_failed' => 0, 'failed' => 0]);
    $row = DB::table('integration_outbox_events')->sole();
    expect($row->status)->toBe('published')
        ->and($row->attempts)->toBe(1)
        ->and($row->failed_reason)->toBeNull();
});

test('a permanent outbox failure becomes terminal after exactly the maximum attempts', function () {
    Log::shouldReceive('channel')->once()->with('integration-events')->andReturnSelf();
    Log::shouldReceive('critical')->once()->withArgs(fn (string $message, array $context): bool => $message === 'Integration outbox event exhausted retries'
        && $context['attempts'] === OutboxWorker::MAX_ATTEMPTS
    );
    DB::table('integration_outbox_events')->insert([
        'event_name' => 'broken.event',
        'event_class' => 'App\\Nonexistent\\PermanentFailure',
        'aggregate_type' => 'Broken',
        'aggregate_id' => '1',
        'payload' => json_encode([]),
        'occurred_at' => now(),
        'status' => 'pending',
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    foreach (range(1, OutboxWorker::MAX_ATTEMPTS) as $attempt) {
        $result = app(OutboxWorker::class)->run();
        expect($result['failed'])->toBe(1)
            ->and($result['retried'])->toBe($attempt < OutboxWorker::MAX_ATTEMPTS ? 1 : 0)
            ->and($result['newly_terminal_failed'])->toBe($attempt === OutboxWorker::MAX_ATTEMPTS ? 1 : 0)
            ->and($result['outstanding_terminal_failed'])->toBe($attempt === OutboxWorker::MAX_ATTEMPTS ? 1 : 0);

        if ($attempt < OutboxWorker::MAX_ATTEMPTS) {
            $delay = OutboxWorker::BACKOFF_SECONDS[min($attempt - 1, count(OutboxWorker::BACKOFF_SECONDS) - 1)];
            $this->travel($delay + 1)->seconds();
        }
    }

    $row = DB::table('integration_outbox_events')->sole();
    expect($row->status)->toBe('failed')
        ->and($row->attempts)->toBe(OutboxWorker::MAX_ATTEMPTS)
        ->and($row->failed_reason)->not->toBeNull();
    expect(app(OutboxWorker::class)->run())->toMatchArray([
        'failed' => 0,
        'newly_terminal_failed' => 0,
        'outstanding_terminal_failed' => 1,
    ]);
});

test('a fresh claim is owned exclusively and a stale claim is recovered', function () {
    Event::fake([SectionCreated::class]);
    app(OutboxEventRecorder::class)->record(new SectionCreated(1, 2, 'A'));
    DB::table('integration_outbox_events')->update([
        'status' => 'processing',
        'claim_token' => '11111111-1111-4111-8111-111111111111',
        'claimed_at' => now(),
    ]);

    expect(app(OutboxWorker::class)->run()['published'])->toBe(0);

    DB::table('integration_outbox_events')->update([
        'claimed_at' => now()->subSeconds(OutboxWorker::CLAIM_TIMEOUT_SECONDS + 1),
    ]);

    expect(app(OutboxWorker::class)->run()['published'])->toBe(1);
    expect(DB::table('integration_outbox_events')->sole()->status)->toBe('published');
});

test('conditional finalization cannot overwrite a replacement owner', function () {
    app(OutboxEventRecorder::class)->record(new SectionCreated(1, 2, 'A'));
    Event::listen(SectionCreated::class, function (): void {
        DB::table('integration_outbox_events')->update(['claim_token' => '22222222-2222-4222-8222-222222222222']);
    });

    $result = app(OutboxWorker::class)->run();

    expect($result['published'])->toBe(0)
        ->and($result['ownership_lost'])->toBe(1);
    $row = DB::table('integration_outbox_events')->sole();
    expect($row->status)->toBe('processing')
        ->and($row->claim_token)->toBe('22222222-2222-4222-8222-222222222222');
});

test('publish command exits non-zero and reports terminal failures separately', function () {
    DB::table('integration_outbox_events')->insert([
        'event_name' => 'broken.event',
        'event_class' => 'App\\Nonexistent\\PermanentFailure',
        'aggregate_type' => 'Broken',
        'aggregate_id' => '1',
        'payload' => json_encode([]),
        'occurred_at' => now(),
        'status' => 'pending',
        'attempts' => OutboxWorker::MAX_ATTEMPTS - 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    Log::shouldReceive('channel')->once()->andReturnSelf();
    Log::shouldReceive('critical')->once();

    $this->artisan('integration-events:publish-outbox')
        ->expectsOutput('Outbox: 0 published, 0 retried, 1 newly terminal, 1 outstanding terminal, 0 ownership lost.')
        ->assertFailed();

    $this->artisan('integration-events:publish-outbox')
        ->expectsOutput('Outbox: 0 published, 0 retried, 0 newly terminal, 1 outstanding terminal, 0 ownership lost.')
        ->assertFailed();
});

test('queued projection listeners use the same five-attempt retry contract', function () {
    foreach ([new ProjectTeacherListener, new ProjectStudentListener, new ProjectEnrollmentListener] as $listener) {
        expect($listener->tries)->toBe(OutboxWorker::MAX_ATTEMPTS)
            ->and($listener->backoff)->toBe(OutboxWorker::BACKOFF_SECONDS);
    }
});
