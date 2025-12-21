<?php

declare(strict_types=1);

namespace Laravilt\Panel\Concerns;

use Laravilt\Panel\Panel;

/**
 * Provides panel access control for user models.
 * Use this trait in your User model to control who can access which panels.
 */
trait HasPanelAccess
{
    /**
     * Initialize the trait when user is created.
     * Automatically assigns the panel_user role to new users if enabled.
     */
    public static function bootHasPanelAccess(): void
    {
        static::created(function ($user) {
            if (config('laravilt-users.panel_user.enabled', false)) {
                $panelUserRole = config('laravilt-users.panel_user.role', 'panel_user');

                if (method_exists($user, 'assignRole')) {
                    try {
                        $user->assignRole($panelUserRole);
                    } catch (\Exception $e) {
                        // Role doesn't exist yet, skip
                    }
                }
            }
        });
    }

    /**
     * Check if the user can access a specific panel.
     */
    public function canAccessPanel(?Panel $panel = null): bool
    {
        // Super admin can access all panels
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Check if panel has specific access requirements
        if ($panel && method_exists($panel, 'getAccessRoles')) {
            $accessRoles = $panel->getAccessRoles();

            if (! empty($accessRoles) && method_exists($this, 'hasAnyRole')) {
                return $this->hasAnyRole($accessRoles);
            }
        }

        // Check panel_user role if enabled
        if (config('laravilt-users.panel_user.enabled', false)) {
            $panelUserRole = config('laravilt-users.panel_user.role', 'panel_user');

            if (method_exists($this, 'hasRole')) {
                return $this->hasRole($panelUserRole) || $this->isSuperAdmin();
            }
        }

        // Default: allow access
        return true;
    }

    /**
     * Check if the user is a super admin.
     */
    public function isSuperAdmin(): bool
    {
        if (! config('laravilt-users.super_admin.enabled', true)) {
            return false;
        }

        $superAdminRole = config('laravilt-users.super_admin.role', 'super_admin');

        if (method_exists($this, 'hasRole')) {
            return $this->hasRole($superAdminRole);
        }

        return false;
    }

    /**
     * Check if the user has panel admin privileges.
     */
    public function isPanelAdmin(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $adminRole = config('laravilt-users.admin_role', 'admin');

        if (method_exists($this, 'hasRole')) {
            return $this->hasRole($adminRole);
        }

        return false;
    }

    /**
     * Get all panels this user can access.
     *
     * @return array<string>
     */
    public function getAccessiblePanels(): array
    {
        if ($this->isSuperAdmin()) {
            // Super admin can access all panels
            if (class_exists(\Laravilt\Panel\PanelRegistry::class)) {
                $registry = app(\Laravilt\Panel\PanelRegistry::class);

                return array_keys($registry->all());
            }
        }

        // Return panels based on user's roles/permissions
        $accessiblePanels = [];

        if (class_exists(\Laravilt\Panel\PanelRegistry::class)) {
            $registry = app(\Laravilt\Panel\PanelRegistry::class);

            foreach ($registry->all() as $panelId => $panel) {
                if ($this->canAccessPanel($panel)) {
                    $accessiblePanels[] = $panelId;
                }
            }
        }

        return $accessiblePanels;
    }
}
