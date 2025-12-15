<?php

namespace Laravilt\Panel\Clusters;

use Illuminate\Http\Request;
use Laravilt\Panel\Cluster;
use Laravilt\Panel\Facades\Laravilt;

class TenantSettings extends Cluster
{
    protected static ?string $navigationIcon = 'building-2';

    protected static ?string $navigationLabel = null;

    protected static ?string $slug = 'tenant/settings';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 99;

    public static function getNavigationLabel(): string
    {
        return __('panel::panel.tenancy.tenant_settings');
    }

    public static function getClusterTitle(): string
    {
        return __('panel::panel.tenancy.tenant_settings');
    }

    public static function getClusterDescription(): ?string
    {
        return __('panel::panel.tenancy.settings.description');
    }

    /**
     * Check if tenant settings cluster should be available.
     */
    public static function canAccess(): bool
    {
        $panel = app(\Laravilt\Panel\PanelRegistry::class)->getCurrent();

        // Only check panel configuration, not tenant existence
        // Tenant existence is checked by the middleware
        return $panel?->hasTenancy() && $panel?->hasTenantProfile();
    }

    /**
     * Handle GET request to the cluster index.
     * Redirects to the first available page in the cluster.
     */
    public function create(Request $request, ...$parameters)
    {
        $panel = app(\Laravilt\Panel\PanelRegistry::class)->getCurrent();

        if (! $panel) {
            abort(404);
        }

        // Redirect to team profile page (first page in the cluster)
        return redirect()->to('/'.$panel->getPath().'/tenant/settings/profile');
    }
}
