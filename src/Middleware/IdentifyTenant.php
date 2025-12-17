<?php

namespace Laravilt\Panel\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravilt\Panel\Contracts\HasDefaultTenant;
use Laravilt\Panel\Contracts\HasTenants;
use Laravilt\Panel\Facades\Laravilt;
use Laravilt\Panel\Facades\Panel;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Panel::getCurrent();

        // If panel doesn't have tenancy enabled, skip
        if (! $panel?->hasTenancy()) {
            return $next($request);
        }

        // In multi-database mode, tenant is resolved by subdomain middleware
        // On central domain, allow access to auth and registration pages
        if ($panel->isMultiDatabaseTenancy()) {
            return $this->handleMultiDatabaseTenancy($request, $next, $panel);
        }

        // Single-database mode - original logic
        return $this->handleSingleDatabaseTenancy($request, $next, $panel);
    }

    /**
     * Handle multi-database tenancy mode.
     * On central domain: Allow auth pages and tenant registration.
     * On tenant subdomain: Tenant is already identified by InitializeTenancyBySubdomain.
     */
    protected function handleMultiDatabaseTenancy(Request $request, Closure $next, $panel): Response
    {
        $user = $request->user();
        $host = $request->getHost();

        // Check if we're on a central domain
        if ($panel->isCentralDomain($host)) {
            // On central domain, allow access without tenant for:
            // - Authentication pages (login, register, password reset, etc.)
            // - Tenant registration page
            // - API routes
            $allowedPaths = [
                $panel->getPath().'/login',
                $panel->getPath().'/register',
                $panel->getPath().'/password',
                $panel->getPath().'/email',
                $panel->getPath().'/two-factor',
                $panel->getPath().'/tenant/register',
                $panel->getPath().'/auth',
                $panel->getPath().'/magic-link',
                $panel->getPath().'/otp',
            ];

            foreach ($allowedPaths as $path) {
                if ($request->is($path) || $request->is($path.'/*')) {
                    return $next($request);
                }
            }

            // If user is authenticated and on central domain, redirect to tenant registration
            // if they have no tenants, or redirect to their tenant subdomain
            if ($user && $user instanceof HasTenants) {
                $tenants = $user->getTenants($panel);

                if ($tenants->isEmpty()) {
                    // No tenants - redirect to registration (avoid loop)
                    if (! $request->is($panel->getPath().'/tenant/register*')) {
                        return redirect('/'.$panel->getPath().'/tenant/register');
                    }
                } else {
                    // Has tenants - get default or first tenant and redirect to subdomain
                    $tenant = $user instanceof HasDefaultTenant
                        ? $user->getDefaultTenant($panel) ?? $tenants->first()
                        : $tenants->first();

                    if ($tenant) {
                        $redirectUrl = $panel->getTenantUrl($tenant);
                        // Use Inertia::location() for cross-domain redirect to avoid CORS issues
                        if ($request->header('X-Inertia')) {
                            return Inertia::location($redirectUrl);
                        }

                        return redirect($redirectUrl);
                    }
                }
            }

            // Allow unauthenticated access to central domain (will be caught by auth middleware)
            return $next($request);
        }

        // On tenant subdomain - tenant should already be set by InitializeTenancyBySubdomain
        $tenant = Laravilt::getTenant();

        if ($tenant && $user && $user instanceof HasTenants) {
            // Verify user can access this tenant
            if (! $user->canAccessTenant($tenant)) {
                abort(403, 'You do not have access to this tenant.');
            }
        }

        return $next($request);
    }

    /**
     * Handle single-database tenancy mode (original logic).
     */
    protected function handleSingleDatabaseTenancy(Request $request, Closure $next, $panel): Response
    {
        $user = $request->user();

        // If no user is authenticated, skip tenant identification
        if (! $user) {
            return $next($request);
        }

        // Check if user implements HasTenants interface
        if (! $user instanceof HasTenants) {
            return $next($request);
        }

        // Get tenant from route parameter or session
        $tenant = $this->resolveTenant($request, $panel, $user);

        // If no tenant found and user has default tenant, use it
        if (! $tenant && $user instanceof HasDefaultTenant) {
            $tenant = $user->getDefaultTenant($panel);
        }

        // If still no tenant, try to resolve or create one
        if (! $tenant) {
            $tenants = $user->getTenants($panel);

            if ($tenants->isEmpty()) {
                // User has no tenants - redirect to tenant registration
                $registrationUrl = '/'.$panel->getPath().'/tenant/register';

                // Don't redirect if already on registration page
                if (! $request->is($panel->getPath().'/tenant/register*')) {
                    return redirect($registrationUrl);
                }

                // Allow access to registration page without tenant
                return $next($request);
            } else {
                // User has tenants but none selected - use first one
                $tenant = $tenants->first();
            }
        }

        // Verify user can access this tenant
        if ($tenant && ! $user->canAccessTenant($tenant)) {
            abort(403, 'You do not have access to this tenant.');
        }

        if ($tenant) {
            // Set the tenant in the manager
            Laravilt::setTenant($tenant);

            // Store tenant in session for persistence
            session()->put('laravilt.tenant_id', $tenant->getKey());
        }

        return $next($request);
    }

    /**
     * Auto-create a personal tenant for the user.
     */
    protected function createPersonalTenant($panel, $user): ?Model
    {
        try {
            $tenantModel = $panel->getTenantModel();
            $slugAttribute = $panel->getTenantSlugAttribute();

            // Create a personal team for the user
            $name = $user->name."'s Team";
            $slug = \Illuminate\Support\Str::slug($user->name).'-'.uniqid();

            $tenant = new $tenantModel;
            $tenant->name = $name;
            $tenant->{$slugAttribute} = $slug;

            // Set owner if the model has owner_id
            if (method_exists($tenant, 'owner') || isset($tenant->owner_id)) {
                $tenant->owner_id = $user->id;
            }

            $tenant->save();

            // Attach user to tenant if there's a relationship method
            $ownershipRelationship = $panel->getTenantOwnershipRelationship();
            $pluralRelationship = \Illuminate\Support\Str::plural($ownershipRelationship);

            // Try to attach via the inverse relationship (e.g., teams on user)
            if (method_exists($user, $pluralRelationship)) {
                $user->{$pluralRelationship}()->attach($tenant->id, ['role' => 'owner']);
            }

            // Set as current team if user has current_team_id
            if (isset($user->current_team_id)) {
                $user->current_team_id = $tenant->id;
                $user->save();
            }

            return $tenant;
        } catch (\Exception $e) {
            // Log error but don't fail - return null to allow graceful fallback
            \Illuminate\Support\Facades\Log::warning('Failed to auto-create tenant: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Resolve the tenant from route parameter, session, or user's current_team_id.
     */
    protected function resolveTenant(Request $request, $panel, $user): ?Model
    {
        $tenantModel = $panel->getTenantModel();
        $slugAttribute = $panel->getTenantSlugAttribute();
        $parameterName = $panel->getTenantRouteParameterName();

        // First, try to get tenant from route parameter
        $tenantSlug = $request->route($parameterName);

        if ($tenantSlug) {
            return $tenantModel::where($slugAttribute, $tenantSlug)->first();
        }

        // Then, try to get from session
        $tenantId = session('laravilt.tenant_id');

        if ($tenantId) {
            $tenant = $tenantModel::find($tenantId);

            // Verify the user still has access to this tenant
            if ($tenant && $user->canAccessTenant($tenant)) {
                return $tenant;
            }

            // Clear invalid session tenant
            session()->forget('laravilt.tenant_id');
        }

        // Try to get from user's current_team_id (if user has this attribute)
        if (property_exists($user, 'current_team_id') || isset($user->current_team_id)) {
            $currentTeamId = $user->current_team_id;

            if ($currentTeamId) {
                $tenant = $tenantModel::find($currentTeamId);

                // Verify the user still has access to this tenant
                if ($tenant && $user->canAccessTenant($tenant)) {
                    return $tenant;
                }
            }
        }

        return null;
    }
}
