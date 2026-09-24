<?php

namespace Modules\Enrollments\Infrastructure\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Enrollments\Infrastructure\Listeners\ProjectStudentListener;
use Modules\Users\Public\Events\UserCreated;
use Modules\Users\Public\Events\UserUpdated;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        UserCreated::class => [
            ProjectStudentListener::class,
        ],
        UserUpdated::class => [
            ProjectStudentListener::class,
        ],
    ];

    protected static $shouldDiscoverEvents = true;
}
