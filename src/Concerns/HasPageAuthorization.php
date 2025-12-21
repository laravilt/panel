<?php

declare(strict_types=1);

namespace Laravilt\Panel\Concerns;

use Illuminate\Support\Facades\Gate;

/**
 * Provides authorization methods for panel pages.
 * Use this trait in custom pages to protect them with permissions.
 */
trait HasPageAuthorization
{
    /**
     * The permission required to access this page.
     * Override this in your page class to set a specific permission.
     */
    protected static ?string $permission = null;

    /**
     * Get the permission name for this page.
     * If not set, generates one based on the page class name.
     */
    public static function getPagePermission(): ?string
    {
        if (static::$permission !== null) {
            return static::$permission;
        }

        // Generate permission from class name: SettingsPage -> view_settings_page
        $className = class_basename(static::class);
        $permissionName = str($className)
            ->snake()
            ->prepend('view_')
            ->toString();

        return $permissionName;
    }

    /**
     * Check if the current user can access this page.
     */
    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Super admin bypass
        if (config('laravilt-users.super_admin.enabled', true)) {
            $superAdminRole = config('laravilt-users.super_admin.role', 'super_admin');
            if (method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
                return true;
            }
        }

        $permission = static::getPagePermission();

        if (! $permission) {
            return true;
        }

        // Check permission
        if (method_exists($user, 'hasPermissionTo')) {
            return $user->hasPermissionTo($permission);
        }

        // Fallback to Gate
        return Gate::allows($permission);
    }

    /**
     * Determine if navigation should be registered based on permissions.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
