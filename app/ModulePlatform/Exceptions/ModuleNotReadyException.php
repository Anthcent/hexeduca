<?php

namespace App\ModulePlatform\Exceptions;

use RuntimeException;

/**
 * Thrown when trying to enable a `skeleton` maturity module. Skeleton
 * modules exist as scaffolding only and are not ready to be activated.
 */
class ModuleNotReadyException extends RuntimeException {}
