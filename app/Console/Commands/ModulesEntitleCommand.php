<?php

namespace App\Console\Commands;

use App\ModulePlatform\Exceptions\ModuleNotFoundException;
use App\ModulePlatform\Services\ModuleRegistry;
use App\Tenancy\Models\School;
use Illuminate\Console\Command;

/**
 * Thin CLI wrapper over `ModuleRegistry::entitle()` / `revoke()`. The
 * `{school}` argument is the school's `subdomain` (stable and
 * human-typeable from a terminal, unlike a raw numeric id).
 */
class ModulesEntitleCommand extends Command
{
    protected $signature = 'modules:entitle {key : The module key, e.g. academic} {school : The school subdomain} {--revoke : Revoke instead of grant}';

    protected $description = 'Grant (or --revoke) a school\'s entitlement to an optional module.';

    public function handle(ModuleRegistry $registry): int
    {
        $key = strtolower($this->argument('key'));
        $subdomain = $this->argument('school');

        $school = School::query()->where('subdomain', $subdomain)->first();

        if ($school === null) {
            $this->components->error("No school found with subdomain [{$subdomain}].");

            return self::FAILURE;
        }

        try {
            if ($this->option('revoke')) {
                $registry->revoke($key, $school);
                $this->components->info("Module [{$key}] revoked for school [{$subdomain}].");
            } else {
                $registry->entitle($key, $school);
                $this->components->info("Module [{$key}] entitled to school [{$subdomain}].");
            }
        } catch (ModuleNotFoundException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
