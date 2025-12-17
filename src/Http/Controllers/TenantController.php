<?php

namespace Laravilt\Panel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Laravilt\Panel\Contracts\HasTenants;
use Laravilt\Panel\Facades\Laravilt;
use Laravilt\Panel\Facades\Panel;

class TenantController extends Controller
{
    /**
     * Switch to a different tenant.
     */
    public function switch(Request $request)
    {
        $request->validate([
            'tenant_id' => ['required'],
        ]);

        $panel = Panel::getCurrent();

        if (! $panel?->hasTenancy()) {
            return back()->withErrors(['tenant' => 'Tenancy is not enabled for this panel.']);
        }

        $user = $request->user();

        if (! $user instanceof HasTenants) {
            return back()->withErrors(['tenant' => 'User does not support tenancy.']);
        }

        $tenantModel = $panel->getTenantModel();
        $tenant = $tenantModel::find($request->input('tenant_id'));

        if (! $tenant) {
            return back()->withErrors(['tenant' => 'Tenant not found.']);
        }

        if (! $user->canAccessTenant($tenant)) {
            return back()->withErrors(['tenant' => 'You do not have access to this tenant.']);
        }

        // Update session with new tenant
        session()->put('laravilt.tenant_id', $tenant->getKey());

        // Set the tenant in the manager
        Laravilt::setTenant($tenant);

        // Get the redirect URL - stay on current page if possible
        $redirectUrl = $request->input('redirect_to');

        // If no explicit redirect, try to use the referer header
        if (! $redirectUrl) {
            $referer = $request->header('Referer');
            if ($referer) {
                $refererPath = parse_url($referer, PHP_URL_PATH);
                $panelPath = '/'.$panel->getPath();

                // Only use referer if it's within the same panel
                if ($refererPath && str_starts_with($refererPath, $panelPath)) {
                    $redirectUrl = $refererPath;
                }
            }
        }

        // Default to panel dashboard if no valid redirect URL
        if (! $redirectUrl) {
            $redirectUrl = '/'.$panel->getPath();
        }

        return redirect($redirectUrl);
    }

    /**
     * Get available tenants for the current user.
     */
    public function index(Request $request)
    {
        $panel = Panel::getCurrent();

        if (! $panel?->hasTenancy()) {
            return response()->json(['tenants' => []]);
        }

        $user = $request->user();

        if (! $user instanceof HasTenants) {
            return response()->json(['tenants' => []]);
        }

        $tenants = $user->getTenants($panel);
        $currentTenant = Laravilt::getTenant();
        $slugAttribute = $panel->getTenantSlugAttribute();

        $tenantData = $tenants->map(function ($tenant) use ($currentTenant, $slugAttribute, $panel) {
            $name = method_exists($tenant, 'getTenantName')
                ? $tenant->getTenantName()
                : ($tenant->name ?? $tenant->{$slugAttribute});

            $avatar = method_exists($tenant, 'getTenantAvatarUrl')
                ? $tenant->getTenantAvatarUrl()
                : null;

            return [
                'id' => $tenant->getKey(),
                'name' => $name,
                'slug' => $tenant->{$slugAttribute},
                'avatar' => $avatar,
                'url' => $panel->getTenantUrl($tenant),
                'is_current' => $currentTenant && $currentTenant->getKey() === $tenant->getKey(),
            ];
        });

        return response()->json([
            'tenants' => $tenantData->values(),
            'current' => $currentTenant ? [
                'id' => $currentTenant->getKey(),
                'name' => method_exists($currentTenant, 'getTenantName')
                    ? $currentTenant->getTenantName()
                    : ($currentTenant->name ?? $currentTenant->{$slugAttribute}),
                'slug' => $currentTenant->{$slugAttribute},
                'avatar' => method_exists($currentTenant, 'getTenantAvatarUrl')
                    ? $currentTenant->getTenantAvatarUrl()
                    : null,
            ] : null,
        ]);
    }
}
