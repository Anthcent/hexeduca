<?php

namespace Modules\AcademicOffers\Infrastructure\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\AcademicOffers\Infrastructure\Listeners\ProjectTeacherListener;
use Modules\Users\Public\Events\UserCreated;
use Modules\Users\Public\Events\UserUpdated;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        UserCreated::class => [
            ProjectTeacherListener::class,
        ],
        UserUpdated::class => [
            ProjectTeacherListener::class,
        ],
    ];

    protected static $shouldDiscoverEvents = true;
}
