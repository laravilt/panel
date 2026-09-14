<?php

namespace Laravilt\Panel;

use Illuminate\Support\Str;

abstract class Cluster
{
    /**
     * The cluster's navigation icon.
     */
    protected static ?string $navigationIcon = null;

    /**
     * The cluster's navigation label.
     */
    protected static ?string $navigationLabel = null;

    /**
     * The cluster's navigation sort order.
     */
    protected static ?int $navigationSort = null;

    /**
     * The cluster's navigation group.
     */
    protected static ?string $navigationGroup = null;

    /**
     * The cluster's slug.
     */
    protected static ?string $slug = null;

    /**
     * Whether the cluster should register navigation.
     */
    protected static bool $shouldRegisterNavigation = true;

    /**
     * The cluster's breadcrumb label.
     */
    protected static ?string $clusterBreadcrumb = null;

    /**
     * Get the cluster's navigation icon.
     */
    public static function getNavigationIcon(): ?string
    {
        return static::$navigationIcon;
    }

    /**
     * Get the cluster's navigation label.
     */
    public static function getNavigationLabel(): string
    {
        return static::$navigationLabel ?? Str::title(Str::kebab(class_basename(static::class)));
    }

    /**
     * Get the cluster's navigation sort order.
     */
    public static function getNavigationSort(): ?int
    {
        return static::$navigationSort;
    }

    /**
     * Get the cluster's navigation group.
     */
    public static function getNavigationGroup(): ?string
    {
        return static::$navigationGroup;
    }

    /**
     * Get the cluster's slug.
     */
    public static function getSlug(): string
    {
        return static::$slug ?? Str::slug(Str::kebab(class_basename(static::class)));
    }

    /**
     * Check if the cluster should register navigation.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return static::$shouldRegisterNavigation;
    }

    /**
     * Get the cluster's breadcrumb label.
     */
    public static function getClusterBreadcrumb(): string
    {
        return static::$clusterBreadcrumb ?? static::getNavigationLabel();
    }

    /**
     * Get the panel instance.
     */
    public function getPanel(): ?Panel
    {
        $registry = app(PanelRegistry::class);

        foreach ($registry->all() as $panel) {
            if ($this->belongsToPanel($panel)) {
                return $panel;
            }
        }

        return null;
    }

    /**
     * Check if the cluster belongs to a panel.
     */
    protected function belongsToPanel(Panel $panel): bool
    {
        return in_array(static::class, $panel->getClusters());
    }
}
