<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

// HandleInertiaRequests now shares the caller's available module keys on
// every response (see App\ModulePlatform\Services\ModuleAccess), which
// queries the `modules` table — this needs migrations run.
uses(RefreshDatabase::class);

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
