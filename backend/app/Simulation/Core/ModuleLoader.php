<?php

namespace App\Simulation\Core;

use App\Simulation\Contracts\SimulationModuleInterface;
use App\Simulation\Exceptions\ModuleDependencyException;

/**
 * Module Loader handles dynamic loading and unloading of modules.
 * Validates module dependencies before loading.
 */
class ModuleLoader
{
    private array $loadedModules = [];
    private array $moduleRegistry = [];

    /**
     * Register a module class for loading.
     */
    public function registerModuleClass(string $name, string $className): void
    {
        $this->moduleRegistry[$name] = $className;
    }

    /**
     * Load and instantiate a module with dependency validation.
     */
    public function loadModule(string $name): ?SimulationModuleInterface
    {
        if (!isset($this->moduleRegistry[$name])) {
            return null;
        }

        if (isset($this->loadedModules[$name])) {
            return $this->loadedModules[$name];
        }

        $className = $this->moduleRegistry[$name];
        if (!class_exists($className)) {
            return null;
        }

        // Instantiate module to check dependencies
        $module = new $className();
        
        // Validate dependencies before loading
        $this->validateDependencies($module);

        $this->loadedModules[$name] = $module;

        return $module;
    }

    /**
     * Validate module dependencies.
     */
    private function validateDependencies(SimulationModuleInterface $module): void
    {
        $moduleName = $module->getName();
        $dependencies = $module->getDependencies();

        foreach ($dependencies as $dependency) {
            // Check if dependency is registered
            if (!isset($this->moduleRegistry[$dependency])) {
                throw ModuleDependencyException::missingDependency($moduleName, $dependency);
            }

            // Check for circular dependency
            if ($this->hasCircularDependency($moduleName, $dependency)) {
                throw ModuleDependencyException::circularDependency($moduleName, $dependency);
            }

            // Ensure dependency is loaded
            if (!isset($this->loadedModules[$dependency])) {
                $this->loadModule($dependency);
            }
        }
    }

    /**
     * Check for circular dependencies.
     */
    private function hasCircularDependency(string $module, string $dependency): bool
    {
        if (!isset($this->loadedModules[$dependency])) {
            return false;
        }

        $dependencyModule = $this->loadedModules[$dependency];
        $dependencyDependencies = $dependencyModule->getDependencies();

        return in_array($module, $dependencyDependencies);
    }

    /**
     * Load multiple modules in dependency order.
     */
    public function loadModules(array $moduleNames): void
    {
        foreach ($moduleNames as $name) {
            $this->loadModule($name);
        }
    }

    /**
     * Validate all registered modules without loading.
     */
    public function validateAllDependencies(): void
    {
        foreach ($this->moduleRegistry as $name => $className) {
            if (!class_exists($className)) {
                continue;
            }

            $module = new $className();
            $this->validateDependencies($module);
        }
    }

    /**
     * Attach an already-constructed module instance (used by SimulationEngine::registerModule).
     */
    public function attachModule(SimulationModuleInterface $module): void
    {
        $name = $module->getName();
        $this->moduleRegistry[$name] = get_class($module);
        $this->loadedModules[$name] = $module;
    }

    /**
     * Unload a module.
     */
    public function unloadModule(string $name): void
    {
        if (isset($this->loadedModules[$name])) {
            $this->loadedModules[$name]->shutdown();
            unset($this->loadedModules[$name]);
        }
    }

    /**
     * Get loaded module.
     */
    public function getModule(string $name): ?SimulationModuleInterface
    {
        return $this->loadedModules[$name] ?? null;
    }

    /**
     * Get all loaded modules.
     */
    public function getLoadedModules(): array
    {
        return $this->loadedModules;
    }

    /**
     * Check if module is loaded.
     */
    public function isLoaded(string $name): bool
    {
        return isset($this->loadedModules[$name]);
    }

    /**
     * Unload all modules in reverse dependency order.
     */
    public function unloadAll(): void
    {
        // Sort modules by dependency count (unload dependents first)
        $modules = $this->loadedModules;
        uasort($modules, function($a, $b) {
            return count($b->getDependencies()) <=> count($a->getDependencies());
        });

        foreach ($modules as $module) {
            $module->shutdown();
        }
        $this->loadedModules = [];
    }
}
