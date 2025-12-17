<?php

namespace Laravilt\Panel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravilt\Panel\Contracts\HasDefaultTenant;
use Laravilt\Panel\Contracts\HasTenants;
use Laravilt\Panel\Facades\Panel as PanelFacade;

class TenantManager
{
    /**
     * The current tenant.
     */
    protected ?Model $tenant = null;

    /**
     * Set the current tenant.
     */
    public function setTenant(?Model $tenant): void
    {
        $this->tenant = $tenant;
    }

    /**
     * Get the current tenant.
     */
    public function getTenant(): ?Model
    {
        return $this->tenant;
    }

    /**
     * Get the current tenant's ID.
     */
    public function getTenantId(): mixed
    {
        return $this->tenant?->getKey();
    }

    /**
     * Check if a tenant is set.
     */
    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Check if the current panel uses multi-database tenancy.
     */
    public function isMultiDatabaseTenancy(): bool
    {
        $panel = PanelFacade::getCurrent();

        return $panel?->isMultiDatabaseTenancy() ?? false;
    }

    /**
     * Get all tenants for the current user.
     *
     * @return Collection<int, Model>
     */
    public function getTenants(): Collection
    {
        $user = auth()->user();
        $panel = PanelFacade::getCurrent();

        if (! $user instanceof HasTenants || ! $panel?->hasTenancy()) {
            return collect();
        }

        return $user->getTenants($panel);
    }

    /**
     * Get the default tenant for the current user.
     */
    public function getDefaultTenant(): ?Model
    {
        $user = auth()->user();
        $panel = PanelFacade::getCurrent();

        if (! $panel?->hasTenancy()) {
            return null;
        }

        if ($user instanceof HasDefaultTenant) {
            return $user->getDefaultTenant($panel);
        }

        if ($user instanceof HasTenants) {
            return $user->getTenants($panel)->first();
        }

        return null;
    }

    /**
     * Check if the current user can access the given tenant.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        $user = auth()->user();

        if (! $user instanceof HasTenants) {
            return false;
        }

        return $user->canAccessTenant($tenant);
    }

    /**
     * Get the tenant ownership relationship column name.
     */
    public function getTenantOwnershipColumn(): ?string
    {
        $panel = PanelFacade::getCurrent();

        if (! $panel?->hasTenancy()) {
            return null;
        }

        $relationship = $panel->getTenantOwnershipRelationship();

        if ($relationship === null) {
            return null;
        }

        return $relationship.'_id';
    }

    /**
     * Get the tenant model class.
     */
    public function getTenantModel(): ?string
    {
        return PanelFacade::getCurrent()?->getTenantModel();
    }

    /**
     * Check if tenancy is enabled for the current panel.
     */
    public function isTenancyEnabled(): bool
    {
        return PanelFacade::getCurrent()?->hasTenancy() ?? false;
    }

    /**
     * Get the tenant URL segment.
     */
    public function getTenantUrlSegment(): ?string
    {
        if (! $this->hasTenant()) {
            return null;
        }

        $panel = PanelFacade::getCurrent();

        if (! $panel?->hasTenancy()) {
            return null;
        }

        return $panel->getTenantUrlSegment($this->tenant);
    }
}
