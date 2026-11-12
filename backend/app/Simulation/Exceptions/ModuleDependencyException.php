<?php

namespace App\Simulation\Exceptions;

use RuntimeException;

/**
 * Exception thrown when module dependencies are missing or invalid.
 */
class ModuleDependencyException extends RuntimeException
{
    public static function missingDependency(string $module, string $dependency): self
    {
        return new self(
            "Module '{$module}' requires dependency '{$dependency}' which is not registered."
        );
    }

    public static function circularDependency(string $module, string $dependency): self
    {
        return new self(
            "Circular dependency detected: '{$module}' depends on '{$dependency}' which depends on '{$module}'."
        );
    }

    public static function dependencyLoadFailed(string $module, string $dependency, string $reason): self
    {
        return new self(
            "Failed to load dependency '{$dependency}' for module '{$module}': {$reason}"
        );
    }
}
