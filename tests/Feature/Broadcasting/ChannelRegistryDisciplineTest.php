<?php

use App\Tenancy\Broadcasting\TenantChannel;

/**
 * Architecture-level guardrail: reads routes/channels.php source directly
 * (deterministic, framework-version-proof) rather than reflecting Reverb
 * internals, and asserts every registered channel is either tenant-scoped
 * or explicitly on the allow-list.
 *
 * Passes trivially today (only the exempt default channel exists); its
 * value is future — it fails the build the day a module registers a
 * channel like "grades.{id}" without the "school.{schoolId}" prefix.
 */
function registeredChannelNames(): array
{
    $source = file_get_contents(base_path('routes/channels.php'));

    preg_match_all('/Broadcast::channel\(\s*[\'"]([^\'"]+)[\'"]/', $source, $matches);

    return $matches[1];
}

test('every non exempt registered channel carries the tenant segment', function () {
    $channels = registeredChannelNames();

    expect($channels)->not->toBeEmpty();

    foreach ($channels as $channel) {
        expect(TenantChannel::isCompliant($channel))->toBeTrue(
            "Channel [{$channel}] is not tenant-scoped and not on the allow-list."
        );
    }
});

test('the framework default user channel is the documented exemption', function () {
    expect(TenantChannel::ALLOWED_UNSCOPED)->toContain('App.Models.User.{id}')
        ->and(TenantChannel::isCompliant('App.Models.User.{id}'))->toBeTrue();
});

test('a future non exempt channel without the tenant segment fails the build', function () {
    expect(TenantChannel::isCompliant('grades.{id}'))->toBeFalse();
});
