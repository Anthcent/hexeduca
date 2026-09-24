<?php

namespace App\ModulePlatform\Exceptions;

use RuntimeException;

/**
 * Thrown when a module key has no row in the `modules` registry table.
 */
class ModuleNotFoundException extends RuntimeException {}
