<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests must not depend on compiled frontend assets (CI runs PHP
        // suites before, or without, `npm run build`).
        $this->withoutVite();

        $this->flushRedisState();
    }

    /**
     * Run every job waiting on the default queue connection, surfacing job
     * exceptions to the test. A no-op on the sync connection, where queued
     * listeners already ran inline when they were dispatched.
     */
    protected function runQueuedJobs(): void
    {
        $queue = Queue::connection();

        while ($job = $queue->pop()) {
            $job->fire();
        }
    }

    /**
     * RefreshDatabase only resets the database. When the cache or queue run
     * on Redis (the production-like CI job), rate limiter hits, cached
     * tenants, and pushed jobs would otherwise leak into later tests.
     */
    private function flushRedisState(): void
    {
        if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis') {
            return;
        }

        $connections = array_diff(array_keys(config('database.redis', [])), ['client', 'options', 'clusters']);

        foreach ($connections as $connection) {
            Redis::connection($connection)->flushdb();
        }
    }
}
