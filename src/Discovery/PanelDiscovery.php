<?php

namespace Laravilt\Panel\Discovery;

use Laravilt\Panel\Panel;

class PanelDiscovery
{
    /**
     * Auto-discover and register panel components.
     */
    public static function discover(Panel $panel): void
    {
        $panelId = $panel->getId();
        $studlyId = str_replace('-', '', ucwords($panelId, '-'));

        // Discover from app/Laravilt/{PanelId}
        static::discoverFromApp($panel, $studlyId);

        // Discover from modules if they exist
        static::discoverFromModules($panel, $panelId, $studlyId);
    }

    /**
     * Discover components from app/Laravilt directory.
     */
    protected static function discoverFromApp(Panel $panel, string $studlyId): void
    {
        $basePath = app_path("Laravilt/{$studlyId}");

        if (! is_dir($basePath)) {
            return;
        }

        // Discover Pages
        $pagesPath = "{$basePath}/Pages";
        if (is_dir($pagesPath)) {
            $panel->discoverPages(
                $pagesPath,
                "App\\Laravilt\\{$studlyId}\\Pages"
            );
        }

        // Discover Resources
        $resourcesPath = "{$basePath}/Resources";
        if (is_dir($resourcesPath)) {
            $panel->discoverResources(
                $resourcesPath,
                "App\\Laravilt\\{$studlyId}\\Resources"
            );
        }

        // Discover Clusters
        $clustersPath = "{$basePath}/Clusters";
        if (is_dir($clustersPath)) {
            $panel->discoverClusters(
                $clustersPath,
                "App\\Laravilt\\{$studlyId}\\Clusters"
            );
        }

        // Discover Widgets
        $widgetsPath = "{$basePath}/Widgets";
        if (is_dir($widgetsPath)) {
            $panel->discoverWidgets(
                $widgetsPath,
                "App\\Laravilt\\{$studlyId}\\Widgets"
            );
        }
    }

    /**
     * Discover components from modules.
     */
    protected static function discoverFromModules(Panel $panel, string $panelId, string $studlyId): void
    {
        // Check if nwidart/laravel-modules is installed
        if (! class_exists(\Nwidart\Modules\Facades\Module::class)) {
            return;
        }

        $modules = \Nwidart\Modules\Facades\Module::allEnabled();

        foreach ($modules as $module) {
            $modulePath = $module->getPath();
            $moduleName = $module->getName();

            // Look for Laravilt/{PanelId} inside module
            $panelPath = "{$modulePath}/Laravilt/{$studlyId}";

            if (! is_dir($panelPath)) {
                continue;
            }

            // Discover Pages from module
            $pagesPath = "{$panelPath}/Pages";
            if (is_dir($pagesPath)) {
                $panel->discoverPages(
                    $pagesPath,
                    "Modules\\{$moduleName}\\Laravilt\\{$studlyId}\\Pages"
                );
            }

            // Discover Resources from module
            $resourcesPath = "{$panelPath}/Resources";
            if (is_dir($resourcesPath)) {
                $panel->discoverResources(
                    $resourcesPath,
                    "Modules\\{$moduleName}\\Laravilt\\{$studlyId}\\Resources"
                );
            }

            // Discover Clusters from module
            $clustersPath = "{$panelPath}/Clusters";
            if (is_dir($clustersPath)) {
                $panel->discoverClusters(
                    $clustersPath,
                    "Modules\\{$moduleName}\\Laravilt\\{$studlyId}\\Clusters"
                );
            }

            // Discover Widgets from module
            $widgetsPath = "{$panelPath}/Widgets";
            if (is_dir($widgetsPath)) {
                $panel->discoverWidgets(
                    $widgetsPath,
                    "Modules\\{$moduleName}\\Laravilt\\{$studlyId}\\Widgets"
                );
            }
        }
    }

    /**
     * Get all discoverable paths for a panel.
     */
    public static function getDiscoverablePaths(string $panelId): array
    {
        $studlyId = str_replace('-', '', ucwords($panelId, '-'));
        $paths = [];

        // App paths
        $appBasePath = app_path("Laravilt/{$studlyId}");
        if (is_dir($appBasePath)) {
            $paths['app'] = [
                'pages' => "{$appBasePath}/Pages",
                'resources' => "{$appBasePath}/Resources",
                'clusters' => "{$appBasePath}/Clusters",
                'widgets' => "{$appBasePath}/Widgets",
            ];
        }

        // Module paths
        if (class_exists(\Nwidart\Modules\Facades\Module::class)) {
            $modules = \Nwidart\Modules\Facades\Module::allEnabled();

            foreach ($modules as $module) {
                $modulePath = $module->getPath();
                $moduleName = $module->getName();
                $panelPath = "{$modulePath}/Laravilt/{$studlyId}";

                if (is_dir($panelPath)) {
                    $paths["module:{$moduleName}"] = [
                        'pages' => "{$panelPath}/Pages",
                        'resources' => "{$panelPath}/Resources",
                        'clusters' => "{$panelPath}/Clusters",
                        'widgets' => "{$panelPath}/Widgets",
                    ];
                }
            }
        }

        return $paths;
    }
}
