<?php

namespace Laravilt\Panel\Pages;

use Laravilt\Panel\Resources\Resource;
use Laravilt\Widgets\Stat;
use Laravilt\Widgets\StatsOverviewWidget;
use Laravilt\Widgets\Widget;

class Dashboard extends Page
{
    protected static ?string $title = 'Dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = -999;

    protected static ?string $slug = '';

    /**
     * The default Vue component for the dashboard.
     */
    protected static string $view = 'laravilt/Dashboard';

    /**
     * Whether to auto-generate stats widgets from resources.
     */
    protected static bool $shouldGenerateResourceStats = true;

    /**
     * The number of columns for the stats overview widget.
     */
    protected static int $statsColumns = 4;

    public static function getTitle(): string
    {
        return trans('laravilt-panel::panel.navigation.dashboard');
    }

    public static function getLabel(): string
    {
        return trans('laravilt-panel::panel.navigation.dashboard');
    }

    /**
     * Get breadcrumbs for dashboard.
     */
    public function getBreadcrumbs(): array
    {
        return [
            [
                'label' => static::getTitle(),
                'url' => null,
            ],
        ];
    }

    /**
     * Get the widgets for the dashboard.
     * Override this method to customize dashboard widgets.
     *
     * @return array<Widget|string>
     */
    public function getWidgets(): array
    {
        return [];
    }

    /**
     * Get the header widgets (displayed above the main content).
     *
     * @return array<Widget|string>
     */
    public function getHeaderWidgets(): array
    {
        $widgets = [];

        // Auto-generate resource stats if enabled
        if (static::$shouldGenerateResourceStats) {
            $resourceStats = $this->generateResourceStats();
            if (! empty($resourceStats)) {
                $widgets[] = StatsOverviewWidget::make()
                    ->stats($resourceStats)
                    ->columns(static::$statsColumns);
            }
        }

        return array_merge($widgets, $this->getWidgets());
    }

    /**
     * Get the footer widgets (displayed below the main content).
     *
     * @return array<Widget|string>
     */
    public function getFooterWidgets(): array
    {
        return [];
    }

    /**
     * Generate stats from panel resources.
     *
     * @return array<Stat>
     */
    protected function generateResourceStats(): array
    {
        $stats = [];
        $panel = $this->getPanel();
        $resources = $panel->getResources();

        foreach ($resources as $resourceClass) {
            if (! class_exists($resourceClass)) {
                continue;
            }

            // Check if resource should show on dashboard
            if (method_exists($resourceClass, 'shouldShowOnDashboard') && ! $resourceClass::shouldShowOnDashboard()) {
                continue;
            }

            $model = $resourceClass::getModel();
            if (! $model || ! class_exists($model)) {
                continue;
            }

            $label = $resourceClass::getPluralLabel() ?? $resourceClass::getLabel() ?? class_basename($model);
            $icon = $resourceClass::getNavigationIcon() ?? 'Database';
            $count = $model::count();

            // Get the list URL for the resource
            $url = null;
            if (method_exists($resourceClass, 'getUrl')) {
                try {
                    $url = $resourceClass::getUrl('list');
                } catch (\Exception $e) {
                    // URL generation might fail if routes aren't registered
                }
            }

            $stat = Stat::make($label, $count)
                ->icon($this->normalizeIcon($icon))
                ->color($this->getColorForIndex(count($stats)));

            if ($url) {
                $stat->url($url);
            }

            $stats[] = $stat;
        }

        return $stats;
    }

    /**
     * Normalize icon name (remove heroicon prefix if present).
     */
    protected function normalizeIcon(string $icon): string
    {
        // Remove heroicon prefixes
        $icon = preg_replace('/^heroicon-[os]-/', '', $icon);

        // Convert kebab-case to PascalCase for Lucide icons
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $icon)));
    }

    /**
     * Get a color based on index for variety.
     */
    protected function getColorForIndex(int $index): string
    {
        $colors = ['primary', 'success', 'warning', 'info', 'danger', 'gray'];

        return $colors[$index % count($colors)];
    }

    /**
     * Disable auto-generation of resource stats.
     */
    public static function disableResourceStats(): void
    {
        static::$shouldGenerateResourceStats = false;
    }

    /**
     * Enable auto-generation of resource stats.
     */
    public static function enableResourceStats(): void
    {
        static::$shouldGenerateResourceStats = true;
    }

    /**
     * Check if resource stats should be generated.
     */
    public static function shouldGenerateResourceStats(): bool
    {
        return static::$shouldGenerateResourceStats;
    }

    /**
     * Render the dashboard using Inertia.
     */
    public function render(?string $component = null)
    {
        // Use the $view property as the default component, or custom component if provided
        $component = $component ?? static::$view;

        // Collect and serialize widgets
        $headerWidgets = $this->serializeWidgets($this->getHeaderWidgets());
        $footerWidgets = $this->serializeWidgets($this->getFooterWidgets());

        // Get cluster navigation if this page belongs to a cluster
        $clusterClass = static::getCluster();
        $clusterNavigation = $this->getClusterNavigation();

        return \Inertia\Inertia::render($component, [
            'title' => static::getTitle(),
            'breadcrumbs' => $this->getBreadcrumbs(),
            'headerWidgets' => $headerWidgets,
            'footerWidgets' => $footerWidgets,
            'clusterNavigation' => $clusterNavigation,
            'clusterTitle' => $clusterClass ? $clusterClass::getNavigationLabel() : null,
            'clusterIcon' => $clusterClass ? $clusterClass::getNavigationIcon() : null,
        ]);
    }

    /**
     * Serialize widgets for Inertia.
     *
     * @param  array<Widget|string>  $widgets
     */
    protected function serializeWidgets(array $widgets): array
    {
        return collect($widgets)
            ->map(function ($widget) {
                // If it's a class name string, instantiate it
                if (is_string($widget) && class_exists($widget)) {
                    $widget = new $widget;
                }

                // If it's a Widget instance, convert to props
                if ($widget instanceof Widget) {
                    return $widget->toInertiaProps();
                }

                return $widget;
            })
            ->filter()
            ->values()
            ->all();
    }
}
