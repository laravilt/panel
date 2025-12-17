<?php

namespace Laravilt\Panel\Models\Concerns;

/**
 * Trait for models that always use the central database.
 *
 * Use this trait on models that are shared across all tenants:
 * - Users (authentication is central)
 * - Plans/Subscriptions
 * - System settings
 * - Tenants themselves
 */
trait CentralModel
{
    /**
     * Boot the trait.
     */
    public static function bootCentralModel(): void
    {
        static::creating(function ($model) {
            $model->ensureCentralConnection();
        });
    }

    /**
     * Initialize the trait for an instance.
     */
    public function initializeCentralModel(): void
    {
        $this->ensureCentralConnection();
    }

    /**
     * Ensure the model uses the central database connection.
     */
    protected function ensureCentralConnection(): void
    {
        $connection = $this->getCentralConnectionName();
        if ($connection) {
            $this->setConnection($connection);
        }
    }

    /**
     * Get the central database connection name.
     */
    protected function getCentralConnectionName(): string
    {
        return config('laravilt-tenancy.central.connection', config('database.default'));
    }

    /**
     * Override getConnection to always return central connection.
     */
    public function getConnectionName(): ?string
    {
        return $this->getCentralConnectionName();
    }

    /**
     * Check if this model is configured as a central model.
     */
    public static function isCentralModel(): bool
    {
        return true;
    }
}
