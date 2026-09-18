<?php

namespace App\Tenancy\Observers;

use App\Tenancy\Models\School;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates ResolveTenant's "tenant:school:{subdomain}" cache entry
 * synchronously whenever a School is saved or deleted, so admin changes
 * (e.g. deactivation, subdomain rename) take effect immediately instead of
 * waiting out the 5-minute cache TTL.
 *
 * KNOWN LIMITATION (accepted foundation-stage risk): if the triggering
 * School write runs inside an outer DB::transaction(), this "saved" event
 * — and therefore this forget() call — fires before that transaction
 * commits. A concurrent request that cache-misses in that narrow window
 * re-queries under read-committed isolation, observes the pre-change row,
 * and re-caches the stale value for a fresh TTL. No production traffic
 * exists yet; revisit with DB::afterCommit()-based invalidation if this
 * becomes operationally relevant.
 */
class SchoolCacheObserver
{
    public function saved(School $school): void
    {
        Cache::forget("tenant:school:{$school->subdomain}");

        $originalSubdomain = $school->getOriginal('subdomain');

        if ($originalSubdomain !== null && $originalSubdomain !== $school->subdomain) {
            Cache::forget("tenant:school:{$originalSubdomain}");
        }
    }

    public function deleted(School $school): void
    {
        Cache::forget("tenant:school:{$school->subdomain}");
    }
}
