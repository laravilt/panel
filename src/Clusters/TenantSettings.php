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

        return $panel?->hasTenancy() && $panel?->hasTenantProfile() && Laravilt::hasTenant();
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

        // Get pages for this cluster
        $pages = $panel->getPages();

        // Find the first page that belongs to this cluster
        foreach ($pages as $page) {
            if (method_exists($page, 'getCluster') && $page::getCluster() === static::class) {
                return redirect($page::getUrl());
            }
        }

        // Default to team profile page
        return redirect('/'.$panel->getPath().'/tenant/settings/profile');
    }
}
