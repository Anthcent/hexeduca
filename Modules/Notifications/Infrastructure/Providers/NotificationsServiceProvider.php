<?php

namespace Modules\Notifications\Infrastructure\Providers;

use App\ModulePlatform\Services\ModuleAccess;
use App\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Modules\Notifications\Domain\Repositories\NotificationRepositoryInterface;
use Modules\Notifications\Infrastructure\Persistence\EloquentNotificationRepository;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class NotificationsServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Notifications';

    protected string $nameLower = 'notifications';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->loadMigrationsFrom(module_path($this->name, 'Infrastructure/Database/Migrations'));
        $this->registerInertiaPages();
        $this->shareUnreadCount();
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->bind(NotificationRepositoryInterface::class, EloquentNotificationRepository::class);

        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }

    protected function registerInertiaPages(): void
    {
        $this->app->afterResolving('inertia.view-finder', function ($finder): void {
            $finder->addNamespace($this->name, module_path($this->name, 'Resources/js/Pages'));
        });
    }

    /**
     * Shares `notifications: {unreadCount, inboxUrl}` with every Inertia page
     * so the header bell can show a badge. The closure is lazy (it runs when
     * the page renders, after the tenant is resolved) and yields null when
     * the user is a guest, the module is unavailable to the current school,
     * or the user cannot view notifications; the layout then hides the badge.
     */
    protected function shareUnreadCount(): void
    {
        Inertia::share('notifications', function (): ?array {
            $user = request()->user();
            $school = $this->app->make(TenantContext::class)->current();

            if ($user === null || $school === null) {
                return null;
            }

            if (! $this->app->make(ModuleAccess::class)->allows($this->nameLower, $school) || ! $user->can('notifications.view')) {
                return null;
            }

            return [
                'unreadCount' => $this->app->make(NotificationRepositoryInterface::class)->unreadCountFor($user->id, $school->id),
                'inboxUrl' => route('notifications.index'),
            ];
        });
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        // $this->commands([]);
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        //
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/'.$this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $relativeConfigPath = config('modules.paths.generator.config.path');
        $configPath = module_path($this->name, $relativeConfigPath);

        if (is_dir($configPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relativePath = str_replace($configPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $configKey = $this->nameLower.'.'.str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $relativePath);
                    $key = ($relativePath === 'config.php') ? $this->nameLower : $configKey;

                    $this->publishes([$file->getPathname() => config_path($relativePath)], 'config');
                    $this->mergeConfigFrom($file->getPathname(), $key);
                }
            }
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
