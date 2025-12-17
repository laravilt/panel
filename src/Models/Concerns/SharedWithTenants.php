<?php

namespace Laravilt\Panel\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Laravilt\Panel\Facades\Laravilt;

/**
 * Trait for central models that are accessible from tenant context.
 *
 * Use this trait on central models that tenants can reference but not modify:
 * - Users (tenant users are linked via pivot table)
 * - Plans (tenants subscribe to plans)
 * - Global settings
 *
 * This trait ensures the model always uses the central connection
 * but can optionally filter data based on tenant relationships.
 */
trait SharedWithTenants
{
    use CentralModel;

    /**
     * Boot the trait.
     */
    public static function bootSharedWithTenants(): void
    {
        // CentralModel boot is called automatically
    }

    /**
     * Scope to records that belong to the current tenant (via relationship).
     *
     * @param  string  $relationship  The relationship method name on this model
     */
    public function scopeForCurrentTenant(Builder $query, string $relationship = 'tenants'): Builder
    {
        $tenant = Laravilt::getTenant();

        if (! $tenant) {
            return $query;
        }

        return $query->whereHas($relationship, function ($q) use ($tenant) {
            $q->where('tenant_id', $tenant->getKey());
        });
    }

    /**
     * Check if this record is accessible by the given tenant.
     *
     * @param  mixed  $tenant  The tenant instance or ID
     * @param  string  $relationship  The relationship method name
     */
    public function isAccessibleByTenant($tenant, string $relationship = 'tenants'): bool
    {
        $tenantId = is_object($tenant) ? $tenant->getKey() : $tenant;

        if (! method_exists($this, $relationship)) {
            return false;
        }

        return $this->{$relationship}()
            ->where('tenant_id', $tenantId)
            ->exists();
    }

    /**
     * Check if this record is accessible by the current tenant.
     *
     * @param  string  $relationship  The relationship method name
     */
    public function isAccessibleByCurrentTenant(string $relationship = 'tenants'): bool
    {
        $tenant = Laravilt::getTenant();

        if (! $tenant) {
            return false;
        }

        return $this->isAccessibleByTenant($tenant, $relationship);
    }
}
