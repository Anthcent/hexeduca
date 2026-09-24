<?php

namespace App\ModulePlatform\Exceptions;

use RuntimeException;

/**
 * Thrown for dependency-graph integrity violations: enabling a module whose
 * dependency isn't active yet, or disabling a module that active dependents
 * still rely on.
 */
class ModuleDependencyException extends RuntimeException {}
