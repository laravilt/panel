<?php

namespace Laravilt\Panel\Tenancy;

use Laravilt\Panel\Facades\Panel;
use Laravilt\Panel\Models\Concerns\BelongsToTenant;
use Laravilt\Panel\Models\Concerns\CentralModel;

class ModelResolver
{
    /**
     * Cached model classifications.
     */
    protected static array $cache = [];

    /**
     * Determine if a model belongs to the tenant database.
     */
    public static function isTenantModel(string $modelClass): bool
    {
        $cacheKey = "tenant:{$modelClass}";

        if (isset(static::$cache[$cacheKey])) {
            return static::$cache[$cacheKey];
        }

        // Check trait first (highest priority)
        if (static::usesTrait($modelClass, BelongsToTenant::class)) {
            return static::$cache[$cacheKey] = true;
        }

        // Check panel configuration
        $panel = Panel::getCurrent();
        if ($panel && in_array($modelClass, $panel->getTenantModels())) {
            return static::$cache[$cacheKey] = true;
        }

        // Check global configuration
        $tenantModels = config('laravilt-tenancy.models.tenant', []);
        if (in_array($modelClass, $tenantModels)) {
            return static::$cache[$cacheKey] = true;
        }

        return static::$cache[$cacheKey] = false;
    }

    /**
     * Determine if a model belongs to the central database.
     */
    public static function isCentralModel(string $modelClass): bool
    {
        $cacheKey = "central:{$modelClass}";

        if (isset(static::$cache[$cacheKey])) {
            return static::$cache[$cacheKey];
        }

        // Check trait first (highest priority)
        if (static::usesTrait($modelClass, CentralModel::class)) {
            return static::$cache[$cacheKey] = true;
        }

        // Check panel configuration
        $panel = Panel::getCurrent();
        if ($panel && in_array($modelClass, $panel->getCentralModels())) {
            return static::$cache[$cacheKey] = true;
        }

        // Check global configuration
        $centralModels = config('laravilt-tenancy.models.central', []);
        if (in_array($modelClass, $centralModels)) {
            return static::$cache[$cacheKey] = true;
        }

        // Default: models without explicit configuration are central
        return static::$cache[$cacheKey] = ! static::isTenantModel($modelClass);
    }

    /**
     * Get the database connection name for a model.
     */
    public static function getConnectionName(string $modelClass): string
    {
        if (static::isTenantModel($modelClass)) {
            return 'tenant';
        }

        return config('laravilt-tenancy.central.connection', config('database.default'));
    }

    /**
     * Get all configured tenant models.
     */
    public static function getTenantModels(): array
    {
        $models = config('laravilt-tenancy.models.tenant', []);

        $panel = Panel::getCurrent();
        if ($panel) {
            $models = array_merge($models, $panel->getTenantModels());
        }

        return array_unique($models);
    }

    /**
     * Get all configured central models.
     */
    public static function getCentralModels(): array
    {
        $models = config('laravilt-tenancy.models.central', []);

        $panel = Panel::getCurrent();
        if ($panel) {
            $models = array_merge($models, $panel->getCentralModels());
        }

        return array_unique($models);
    }

    /**
     * Register a model as a tenant model at runtime.
     */
    public static function registerTenantModel(string $modelClass): void
    {
        static::$cache["tenant:{$modelClass}"] = true;
        static::$cache["central:{$modelClass}"] = false;
    }

    /**
     * Register a model as a central model at runtime.
     */
    public static function registerCentralModel(string $modelClass): void
    {
        static::$cache["central:{$modelClass}"] = true;
        static::$cache["tenant:{$modelClass}"] = false;
    }

    /**
     * Clear the model cache.
     */
    public static function clearCache(): void
    {
        static::$cache = [];
    }

    /**
     * Check if a class uses a specific trait.
     */
    protected static function usesTrait(string $class, string $trait): bool
    {
        if (! class_exists($class)) {
            return false;
        }

        return in_array($trait, class_uses_recursive($class));
    }
}
