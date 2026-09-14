<?php

declare(strict_types=1);

namespace Laravilt\Panel\Concerns;

use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

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

        // Super admin bypass - only if explicitly enabled
        // When bypass_permissions is true, super_admin gets all access without checking permissions
        // When bypass_permissions is false (default), super_admin respects assigned permissions
        if (config('laravilt-users.super_admin.bypass_permissions', false)) {
            $superAdminRole = config('laravilt-users.super_admin.role', 'super_admin');
            if (method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
                return true;
            }
        }

        $permission = static::getPagePermission();

        if (! $permission) {
            return true;
        }

        // Check permission using Spatie Permission
        $guardName = config('laravilt-users.guard_name', 'web');

        if (method_exists($user, 'hasPermissionTo')) {
            try {
                return $user->hasPermissionTo($permission, $guardName);
            } catch (PermissionDoesNotExist $e) {
                // Permission doesn't exist in the database - deny access
                // Run `php artisan laravilt:secure` to generate permissions
                return false;
            }
        }

        // Check if a gate is defined for this permission
        if (Gate::has($permission)) {
            return Gate::allows($permission);
        }

        // No authorization system available - allow access by default
        return true;
    }

    /**
     * Determine if navigation should be registered based on permissions.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
