<?php

declare(strict_types=1);

namespace Laravilt\Panel\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Provides authorization methods for resources.
 * Integrates with Spatie Laravel Permission for permission-based access control.
 */
trait HasResourceAuthorization
{
    /**
     * The permission prefix for this resource (e.g., 'user' for permissions like 'view_any_user').
     */
    protected static ?string $permissionPrefix = null;

    /**
     * Whether to use policies for authorization instead of direct permissions.
     */
    protected static bool $usePolicies = false;

    /**
     * Get the permission prefix for this resource.
     * Defaults to the resource slug in singular form.
     */
    public static function getPermissionPrefix(): string
    {
        if (static::$permissionPrefix !== null) {
            return static::$permissionPrefix;
        }

        // Use the model name in snake_case as the permission prefix
        $modelClass = static::getModel();
        $modelName = class_basename($modelClass);

        return str($modelName)->snake()->toString();
    }

    /**
     * Get the permission separator used between prefix and suffix.
     */
    public static function getPermissionSeparator(): string
    {
        return config('laravilt-users.permissions.separator', '_');
    }

    /**
     * Get the permission case format.
     */
    public static function getPermissionCase(): string
    {
        return config('laravilt-users.permissions.case', 'snake');
    }

    /**
     * Generate a permission name for this resource.
     *
     * @param  string  $action  The action (e.g., 'view_any', 'create', 'update')
     */
    public static function getPermissionName(string $action): string
    {
        $separator = static::getPermissionSeparator();
        $prefix = static::getPermissionPrefix();
        $case = static::getPermissionCase();

        // Build the permission key
        $permission = $action.$separator.$prefix;

        // Apply case transformation
        return match ($case) {
            'kebab' => str($permission)->kebab()->toString(),
            'camel' => str($permission)->camel()->toString(),
            'pascal' => str($permission)->studly()->toString(),
            'upper_snake' => str($permission)->snake()->upper()->toString(),
            default => str($permission)->snake()->toString(),
        };
    }

    /**
     * Get all permission names for this resource.
     *
     * @return array<string, string>
     */
    public static function getPermissionNames(): array
    {
        $prefixes = config('laravilt-users.permissions.prefixes', [
            'view_any',
            'view',
            'create',
            'update',
            'delete',
            'restore',
            'force_delete',
            'replicate',
            'reorder',
        ]);

        $permissions = [];
        foreach ($prefixes as $prefix) {
            $permissions[$prefix] = static::getPermissionName($prefix);
        }

        return $permissions;
    }

    /**
     * Check if a user has a specific permission for this resource.
     */
    protected static function userCan(string $action, ?Model $record = null): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Super admin bypass - only if explicitly enabled AND using gate-based bypass
        // When bypass_permissions is true, super_admin gets all access without checking permissions
        // When bypass_permissions is false (default), super_admin respects assigned permissions
        $bypassPermissions = config('laravilt-users.super_admin.bypass_permissions', false);
        $superAdminRole = config('laravilt-users.super_admin.role', 'super_admin');

        if ($bypassPermissions && method_exists($user, 'hasRole')) {
            try {
                if ($user->hasRole($superAdminRole)) {
                    return true;
                }
            } catch (\Exception $e) {
                // Spatie Permission not installed or role doesn't exist
            }
        }

        // Use policy-based authorization if enabled
        if (static::$usePolicies && $record) {
            return Gate::allows($action, $record);
        }

        // Use permission-based authorization
        $permission = static::getPermissionName($action);
        $guardName = config('laravilt-users.guard_name', 'web');

        // Check if Spatie Permission is available
        if (method_exists($user, 'hasPermissionTo')) {
            try {
                $hasPermission = $user->hasPermissionTo($permission, $guardName);

                // Debug: Log permission check result
                if (config('app.debug')) {
                    logger()->debug('Permission check', [
                        'user_id' => $user->id,
                        'permission' => $permission,
                        'guard' => $guardName,
                        'result' => $hasPermission,
                        'user_permissions' => method_exists($user, 'getAllPermissions')
                            ? $user->getAllPermissions()->pluck('name')->toArray()
                            : 'N/A',
                        'user_roles' => method_exists($user, 'getRoleNames')
                            ? $user->getRoleNames()->toArray()
                            : 'N/A',
                    ]);
                }

                return $hasPermission;
            } catch (PermissionDoesNotExist $e) {
                // Permission doesn't exist in the database - deny access
                // Run `php artisan laravilt:secure` to generate permissions
                if (config('app.debug')) {
                    logger()->debug('Permission does not exist - access denied', [
                        'permission' => $permission,
                        'guard' => $guardName,
                    ]);
                }

                return false;
            }
        }

        // Check if a policy or gate is defined for this permission
        // If no gate/policy is defined, allow access by default
        if (Gate::has($permission)) {
            return Gate::allows($permission);
        }

        // If we reach here, no authorization system is available
        // Allow access by default - permissions package not installed
        return true;
    }

    /**
     * Check if the current user can view any records.
     */
    public static function canViewAny(): bool
    {
        return static::userCan('view_any');
    }

    /**
     * Check if the current user can view a specific record.
     */
    public static function canView(?Model $record = null): bool
    {
        return static::userCan('view', $record);
    }

    /**
     * Check if the current user can create records.
     */
    public static function canCreate(): bool
    {
        return static::userCan('create');
    }

    /**
     * Check if the current user can update a specific record.
     */
    public static function canUpdate(?Model $record = null): bool
    {
        return static::userCan('update', $record);
    }

    /**
     * Check if the current user can delete a specific record.
     */
    public static function canDelete(?Model $record = null): bool
    {
        return static::userCan('delete', $record);
    }

    /**
     * Check if the current user can restore a specific record.
     */
    public static function canRestore(?Model $record = null): bool
    {
        return static::userCan('restore', $record);
    }

    /**
     * Check if the current user can force delete a specific record.
     */
    public static function canForceDelete(?Model $record = null): bool
    {
        return static::userCan('force_delete', $record);
    }

    /**
     * Check if the current user can replicate a specific record.
     */
    public static function canReplicate(?Model $record = null): bool
    {
        return static::userCan('replicate', $record);
    }

    /**
     * Check if the current user can reorder records.
     */
    public static function canReorder(): bool
    {
        return static::userCan('reorder');
    }

    /**
     * Check if the current user can access this resource at all.
     * This is used to determine navigation visibility.
     */
    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    /**
     * Determine if navigation should be registered based on permissions.
     * Override isNavigationVisible to include permission checks.
     */
    public static function shouldRegisterNavigation(): bool
    {
        if (! static::$navigationVisible) {
            return false;
        }

        return static::canAccess();
    }
}
