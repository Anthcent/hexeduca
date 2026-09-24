<?php

namespace App\ModulePlatform\Exceptions;

use RuntimeException;

/**
 * Thrown when trying to disable a core module (Users, Admin). Core modules
 * are always active and have no disable path.
 */
class ModuleCoreException extends RuntimeException {}
