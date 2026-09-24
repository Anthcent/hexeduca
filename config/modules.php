<?php

use Nwidart\Modules\Activators\FileActivator;
use Nwidart\Modules\Providers\ConsoleServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Module Namespace
    |--------------------------------------------------------------------------
    |
    | Default module namespace.
    |
    */
    'namespace' => 'Modules',

    /*
    |--------------------------------------------------------------------------
    | Module Stubs
    |--------------------------------------------------------------------------
    |
    | Default module stubs.
    |
    */
    'stubs' => [
        // Project-specific stubs override the vendor defaults below (see
        // stubs/modules/). Files not present there fall back to nwidart's
        // defaults automatically. See sdd/module-developer-platform R1.2.
        'enabled' => true,
        'path' => base_path('stubs/modules'),
        'files' => [
            'routes/web' => 'routes/web.php',
            'routes/api' => 'routes/api.php',
            'scaffold/config' => 'config/config.php',
            'composer' => 'composer.json',
            'module-md' => 'MODULE.md',
        ],
        'replacements' => [
            'routes/web' => ['LOWER_NAME', 'STUDLY_NAME', 'KEBAB_NAME', 'MODULE_NAMESPACE', 'CONTROLLER_NAMESPACE'],
            'routes/api' => ['LOWER_NAME', 'STUDLY_NAME', 'KEBAB_NAME', 'MODULE_NAMESPACE', 'CONTROLLER_NAMESPACE'],
            'json' => ['LOWER_NAME', 'STUDLY_NAME', 'KEBAB_NAME', 'MODULE_NAMESPACE', 'PROVIDER_NAMESPACE'],
            'scaffold/config' => ['STUDLY_NAME'],
            'module-md' => ['STUDLY_NAME', 'LOWER_NAME'],
            'composer' => [
                'LOWER_NAME',
                'STUDLY_NAME',
                'VENDOR',
                'AUTHOR_NAME',
                'AUTHOR_EMAIL',
                'MODULE_NAMESPACE',
                'PROVIDER_NAMESPACE',
                'APP_FOLDER_NAME',
            ],
        ],
        'gitkeep' => true,
    ],
    'paths' => [
        /*
        |--------------------------------------------------------------------------
        | Modules path
        |--------------------------------------------------------------------------
        |
        | This path is used to save the generated module.
        | This path will also be added automatically to the list of scanned folders.
        |
        */
        'modules' => base_path('Modules'),

        /*
        |--------------------------------------------------------------------------
        | Modules assets path
        |--------------------------------------------------------------------------
        |
        | Here you may update the modules' assets path.
        |
        */
        'assets' => public_path('modules'),

        /*
        |--------------------------------------------------------------------------
        | The migrations' path
        |--------------------------------------------------------------------------
        |
        | Where you run the 'module:publish-migration' command, where do you publish the
        | the migration files?
        |
        */
        'migration' => base_path('database/migrations'),

        /*
        |--------------------------------------------------------------------------
        | The app path
        |--------------------------------------------------------------------------
        |
        | app folder name
        | for example can change it to 'src' or 'App'
        */
        'app_folder' => 'app/',

        /*
        |--------------------------------------------------------------------------
        | Generator path
        |--------------------------------------------------------------------------
        | Customise the paths where the folders will be generated.
        | Setting the generate key to false will not generate that folder
        */
        // Generator paths below follow this project's hexagonal module
        // layout (Domain/Application/Infrastructure/Public), matching
        // Modules/Users and Modules/AcademicPeriods. See
        // sdd/module-developer-platform R1.2.
        'generator' => [
            // Domain/
            'domain-entities' => ['path' => 'Domain/Entities', 'generate' => true],
            'domain-repositories' => ['path' => 'Domain/Repositories', 'generate' => true],
            'domain-events' => ['path' => 'Domain/Events', 'generate' => false],
            'domain-value-objects' => ['path' => 'Domain/ValueObjects', 'generate' => false],

            // Application/
            'application-usecases' => ['path' => 'Application/UseCases', 'generate' => true],
            'application-dtos' => ['path' => 'Application/DTOs', 'generate' => true],
            'application-services' => ['path' => 'Application/Services', 'generate' => false],

            // Infrastructure/
            'model' => ['path' => 'Infrastructure/Models', 'generate' => true],
            'infrastructure-persistence' => ['path' => 'Infrastructure/Persistence', 'generate' => true],
            'provider' => ['path' => 'Infrastructure/Providers', 'generate' => true],
            'route-provider' => ['path' => 'Infrastructure/Providers', 'generate' => true],
            'controller' => ['path' => 'Infrastructure/Http/Controllers', 'generate' => true],
            'request' => ['path' => 'Infrastructure/Http/Requests', 'generate' => false],
            'filter' => ['path' => 'Infrastructure/Http/Middleware', 'generate' => false],
            'migration' => ['path' => 'Infrastructure/Database/Migrations', 'generate' => true],
            'seeder' => ['path' => 'Infrastructure/Database/Seeders', 'generate' => true],

            // Public/ (the only layer sibling modules may depend on — see
            // tests/Architecture/Support/ModuleArchitectureValidator.php)
            'public-contracts' => ['path' => 'Public/Contracts', 'generate' => true],
            'public-dtos' => ['path' => 'Public/DTOs', 'generate' => false],
            'public-events' => ['path' => 'Public/Events', 'generate' => false],

            // Unused scaffolding for this hexagonal layout — disabled
            // rather than removed, so `module:make-*` sub-generators keep
            // working if a module opts in later.
            'actions' => ['path' => 'Application/Actions', 'generate' => false],
            'casts' => ['path' => 'Infrastructure/Casts', 'generate' => false],
            'channels' => ['path' => 'Infrastructure/Broadcasting', 'generate' => false],
            'class' => ['path' => 'Domain/Support', 'generate' => false],
            'command' => ['path' => 'Infrastructure/Console', 'generate' => false],
            'component-class' => ['path' => 'Resources/js/Components', 'generate' => false],
            'emails' => ['path' => 'Infrastructure/Emails', 'generate' => false],
            'event' => ['path' => 'Domain/Events', 'generate' => false],
            'enums' => ['path' => 'Domain/Enums', 'generate' => false],
            'exceptions' => ['path' => 'Domain/Exceptions', 'generate' => false],
            'jobs' => ['path' => 'Infrastructure/Jobs', 'generate' => false],
            'helpers' => ['path' => 'Infrastructure/Helpers', 'generate' => false],
            'interfaces' => ['path' => 'Domain/Contracts', 'generate' => false],
            'listener' => ['path' => 'Infrastructure/Listeners', 'generate' => false],
            'notifications' => ['path' => 'Infrastructure/Notifications', 'generate' => false],
            'observer' => ['path' => 'Infrastructure/Observers', 'generate' => false],
            'policies' => ['path' => 'Infrastructure/Policies', 'generate' => false],
            'repository' => ['path' => 'Infrastructure/Persistence', 'generate' => false],
            'resource' => ['path' => 'Infrastructure/Transformers', 'generate' => false],
            'rules' => ['path' => 'Application/Rules', 'generate' => false],
            'services' => ['path' => 'Application/Services', 'generate' => false],
            'scopes' => ['path' => 'Infrastructure/Models/Scopes', 'generate' => false],
            'traits' => ['path' => 'Infrastructure/Traits', 'generate' => false],
            'factory' => ['path' => 'Infrastructure/Database/Factories', 'generate' => false],
            'lang' => ['path' => 'lang', 'generate' => false],
            'assets' => ['path' => 'Resources/assets', 'generate' => false],
            'component-view' => ['path' => 'Resources/views/components', 'generate' => false],
            'views' => ['path' => 'Resources/views', 'generate' => false],

            // config/
            'config' => ['path' => 'config', 'generate' => true],

            // routes/
            'routes' => ['path' => 'routes', 'generate' => true],

            // Tests/ (capitalized to match existing modules)
            'test-feature' => ['path' => 'Tests/Feature', 'generate' => true],
            'test-unit' => ['path' => 'Tests/Unit', 'generate' => true],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Discover of Modules
    |--------------------------------------------------------------------------
    |
    | Here you configure auto discover of module
    | This is useful for simplify module providers.
    |
    */
    'auto-discover' => [
        /*
        |--------------------------------------------------------------------------
        | Migrations
        |--------------------------------------------------------------------------
        |
        | This option for register migration automatically.
        |
        */
        'migrations' => true,

        /*
        |--------------------------------------------------------------------------
        | Translations
        |--------------------------------------------------------------------------
        |
        | This option for register lang file automatically.
        |
        */
        'translations' => false,

    ],

    /*
    |--------------------------------------------------------------------------
    | Package commands
    |--------------------------------------------------------------------------
    |
    | Here you can define which commands will be visible and used in your
    | application. You can add your own commands to merge section.
    |
    */
    'commands' => ConsoleServiceProvider::defaultCommands()
        ->merge([
            // New commands go here
        ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Scan Path
    |--------------------------------------------------------------------------
    |
    | Here you define which folder will be scanned. By default will scan vendor
    | directory. This is useful if you host the package in packagist website.
    |
    */
    'scan' => [
        'enabled' => false,
        'paths' => [
            base_path('vendor/*/*'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Composer File Template
    |--------------------------------------------------------------------------
    |
    | Here is the config for the composer.json file, generated by this package
    |
    */
    'composer' => [
        'vendor' => env('MODULE_VENDOR', 'nwidart'),
        'author' => [
            'name' => env('MODULE_AUTHOR_NAME', 'Nicolas Widart'),
            'email' => env('MODULE_AUTHOR_EMAIL', 'n.widart@gmail.com'),
        ],
        'composer-output' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Choose what laravel-modules will register as custom namespaces.
    | Setting one to false will require you to register that part
    | in your own Service Provider class.
    |--------------------------------------------------------------------------
    */
    'register' => [
        'translations' => true,
        /**
         * load files on boot or register method
         */
        'files' => 'register',
    ],

    /*
    |--------------------------------------------------------------------------
    | Activators
    |--------------------------------------------------------------------------
    |
    | You can define new types of activators here, file, database, etc. The only
    | required parameter is 'class'.
    | The file activator will store the activation status in storage/installed_modules
    */
    'activators' => [
        'file' => [
            'class' => FileActivator::class,
            'statuses-file' => base_path('modules_statuses.json'),
        ],
    ],

    'activator' => 'file',
];
