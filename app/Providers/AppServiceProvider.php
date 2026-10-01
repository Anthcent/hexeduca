<?php

namespace App\Providers;

use App\AcademicPeriod\Context\AcademicPeriodContext;
use App\IntegrationEvents\Console\Commands\PublishOutboxEvents;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(AcademicPeriodContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([PublishOutboxEvents::class]);
        }

        // The super-admin holds every module permission. Only argument-less
        // abilities (plain permission names) are bypassed: policy checks
        // always receive a model or class and keep their own rules.
        Gate::before(function ($user, string $ability, array $arguments) {
            return $arguments === [] && $user->hasRole('super-admin') ? true : null;
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(config('security.rate_limits.login_per_minute'))->by(
                mb_strtolower((string) $request->input('email')).'|'.$request->ip()
            );
        });

        RateLimiter::for('registration', function (Request $request) {
            return Limit::perMinute(config('security.rate_limits.registration_per_minute'))->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?: $request->ip();
            $schoolId = app(TenantContext::class)->current()?->id;

            return Limit::perMinute(config('security.rate_limits.api_per_minute'))->by(
                $schoolId !== null ? "school:{$schoolId}|{$key}" : $key
            );
        });

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
