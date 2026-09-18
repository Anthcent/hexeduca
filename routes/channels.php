<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Tenant-Scoped Channel Naming Convention
|--------------------------------------------------------------------------
|
| Every school-scoped private channel MUST follow the shape
| "school.{schoolId}.{resource}.{id}" (the "private-" prefix is implicit).
| {schoolId} is the numeric schools.id and MUST always be the first segment
| after the type prefix, so it is greppable and machine-checkable by
| tests/Feature/Broadcasting/ChannelRegistryDisciplineTest.php.
|
| Authorize with App\Tenancy\Broadcasting\TenantChannel::authorize($user,
| $schoolId) ONLY. Never authorize against TenantContext inside a channel
| closure: TenantContext is request-scoped and is not bound when a
| broadcast is emitted from a queued job or console command, while
| $user->school_id is valid in every execution context.
|
| Example (kept commented — no live business channel exists yet):
|
| use App\Tenancy\Broadcasting\TenantChannel;
| Broadcast::channel(TenantChannel::pattern('grades'), function ($user, $schoolId, $id) {
|     return TenantChannel::authorize($user, $schoolId);
| });
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
