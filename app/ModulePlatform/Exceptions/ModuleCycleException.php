<?php

namespace App\ModulePlatform\Exceptions;

use RuntimeException;

/**
 * Thrown when `ModuleRegistry::sync()` finds a dependency cycle across the
 * scanned `Modules/*` manifests (e.g. A depends on B, B depends on A).
 */
class ModuleCycleException extends RuntimeException {}
