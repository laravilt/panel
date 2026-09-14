<?php

namespace Laravilt\Panel\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Laravilt\Panel\Facades\Laravilt;
use Laravilt\Panel\Facades\Panel;
use Laravilt\Panel\Models\Tenant;
use Laravilt\Panel\Tenancy\TenancyMode;

/**
 * Trait for models that belong to a tenant database.
 *
 * In multi-database mode: Uses the tenant's database connection.
 * In single-database mode: Scopes queries by tenant_id.
 *
 * Use this trait on models that contain tenant-specific data like:
 * - Customers
 * - Products
 * - Orders
 * - Invoices
 */
trait BelongsToTenant
{
    /**
     * Boot the trait.
     */
    public static function bootBelongsToTenant(): void
    {
        static::creating(function ($model) {
            $model->ensureTenantConnection();

            // In single-database mode, auto-set tenant_id
            if (static::isSingleDatabaseMode() && static::hasTenantIdColumn()) {
                $tenant = Laravilt::getTenant();
                if ($tenant && ! $model->tenant_id) {
                    $model->tenant_id = $tenant->getKey();
                }
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            $model = $builder->getModel();

            // In multi-database mode, set the connection to 'tenant'
            if (static::isMultiDatabaseMode()) {
                $tenant = Laravilt::getTenant();
                // Only set connection if tenant exists AND the tenant connection is configured
                if ($tenant && static::hasTenantConnection()) {
                    // Set the connection on the query builder
                    $builder->getQuery()->connection = app('db')->connection('tenant');
                }
            }

            // In single-database mode, scope by tenant_id
            if (static::isSingleDatabaseMode() && static::hasTenantIdColumn()) {
                $tenant = Laravilt::getTenant();
                if ($tenant) {
                    $builder->where($model->getTable().'.tenant_id', $tenant->getKey());
                }
            }
        });
    }

    /**
     * Initialize the trait for an instance.
     */
    public function initializeBelongsToTenant(): void
    {
        $this->ensureTenantConnection();
    }

    /**
     * Ensure the model uses the correct database connection.
     */
    protected function ensureTenantConnection(): void
    {
        if (static::isMultiDatabaseMode()) {
            $connection = $this->getTenantConnectionName();
            // Only set connection if it exists and is configured
            if ($connection && static::hasTenantConnection()) {
                $this->setConnection($connection);
            }
        }
    }

    /**
     * Check if the tenant database connection is configured.
     */
    protected static function hasTenantConnection(): bool
    {
        return config('database.connections.tenant') !== null;
    }

    /**
     * Get the tenant's database connection name.
     */
    protected function getTenantConnectionName(): ?string
    {
        $tenant = Laravilt::getTenant();

        if (! $tenant) {
            return null;
        }

        // Return the tenant connection name (set by MultiDatabaseManager)
        return 'tenant';
    }

    /**
     * Check if we're in multi-database mode.
     */
    protected static function isMultiDatabaseMode(): bool
    {
        // First check if the current panel is in multi-database mode
        $panel = Panel::getCurrent();
        if ($panel && $panel->isMultiDatabaseTenancy()) {
            return true;
        }

        // Fall back to config setting
        $mode = config('laravilt-tenancy.mode', 'single');

        return TenancyMode::tryFrom($mode)?->isMultiDatabase() ?? false;
    }

    /**
     * Check if we're in single-database mode.
     */
    protected static function isSingleDatabaseMode(): bool
    {
        return ! static::isMultiDatabaseMode();
    }

    /**
     * Check if the model has a tenant_id column.
     */
    protected static function hasTenantIdColumn(): bool
    {
        $instance = new static;

        return in_array('tenant_id', $instance->getFillable()) ||
               Schema::hasColumn($instance->getTable(), 'tenant_id');
    }

    /**
     * Get all records without tenant scoping.
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope('tenant');
    }

    /**
     * Scope to a specific tenant.
     */
    public function scopeForTenant(Builder $query, $tenant): Builder
    {
        if (static::isSingleDatabaseMode() && static::hasTenantIdColumn()) {
            $tenantId = is_object($tenant) ? $tenant->getKey() : $tenant;

            return $query->where($this->getTable().'.tenant_id', $tenantId);
        }

        return $query;
    }

    /**
     * Get the tenant relationship (for single-database mode).
     */
    public function tenant()
    {
        if (! static::hasTenantIdColumn()) {
            return;
        }

        $tenantModel = config('laravilt-tenancy.models.tenant', Tenant::class);

        return $this->belongsTo($tenantModel, 'tenant_id');
    }
}
