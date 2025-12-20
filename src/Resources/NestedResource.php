<?php

declare(strict_types=1);

namespace Laravilt\Panel\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * NestedResource - A resource that is nested within a parent resource.
 *
 * Similar to FilamentPHP's nested resources, this allows you to create
 * resources that are accessed through a parent resource context.
 *
 * Example URL structure: /admin/customers/{customer}/tags
 *
 * Usage:
 * ```php
 * // In CustomerResource
 * public static function getNestedResources(): array
 * {
 *     return [
 *         TagResource::class,
 *     ];
 * }
 *
 * // TagResource extends NestedResource
 * class TagResource extends NestedResource
 * {
 *     protected static string $model = Tag::class;
 *     protected static ?string $parentResource = CustomerResource::class;
 *     protected static string $parentRelationship = 'customer';
 * }
 * ```
 */
abstract class NestedResource extends Resource
{
    /**
     * The parent resource class.
     *
     * @var class-string<resource>|null
     */
    protected static ?string $parentResource = null;

    /**
     * The relationship name on this resource's model that points to the parent.
     * e.g., 'customer' for a Tag that belongsTo Customer
     */
    protected static string $parentRelationship = '';

    /**
     * The relationship name on the parent model that points to this resource's models.
     * e.g., 'tags' for Customer hasMany Tags
     * If not set, it will be auto-detected.
     */
    protected static ?string $childRelationship = null;

    /**
     * Whether to show this nested resource in the parent's navigation.
     */
    protected static bool $showInParentNavigation = true;

    /**
     * The current parent record instance (set at runtime).
     */
    protected static ?Model $parentRecord = null;

    /**
     * Get the parent resource class.
     */
    public static function getParentResource(): ?string
    {
        return static::$parentResource;
    }

    /**
     * Get the parent relationship name.
     */
    public static function getParentRelationship(): string
    {
        return static::$parentRelationship;
    }

    /**
     * Get the child relationship name (on the parent model).
     */
    public static function getChildRelationship(): string
    {
        if (static::$childRelationship) {
            return static::$childRelationship;
        }

        // Auto-detect: pluralize the model name
        return str(class_basename(static::$model))
            ->plural()
            ->camel()
            ->toString();
    }

    /**
     * Check if this resource should show in parent navigation.
     */
    public static function shouldShowInParentNavigation(): bool
    {
        return static::$showInParentNavigation;
    }

    /**
     * Set the parent record for the current request.
     */
    public static function setParentRecord(?Model $record): void
    {
        static::$parentRecord = $record;
    }

    /**
     * Get the parent record for the current request.
     */
    public static function getParentRecord(): ?Model
    {
        return static::$parentRecord;
    }

    /**
     * Get the parent record ID from the route.
     */
    public static function getParentRecordId(): ?int
    {
        $parentSlug = static::getParentResource()::getSlug();

        return (int) request()->route($parentSlug) ?: null;
    }

    /**
     * Resolve and set the parent record from the route.
     */
    public static function resolveParentRecord(): ?Model
    {
        $parentId = static::getParentRecordId();

        if (! $parentId || ! static::getParentResource()) {
            return null;
        }

        $parentModel = static::getParentResource()::getModel();
        $record = $parentModel::find($parentId);

        static::setParentRecord($record);

        return $record;
    }

    /**
     * Get the URL prefix for this nested resource.
     */
    public static function getUrlPrefix(): string
    {
        $parentResource = static::getParentResource();
        if (! $parentResource) {
            return static::getSlug();
        }

        $parentSlug = $parentResource::getSlug();

        return $parentSlug.'/{'.$parentSlug.'}/'.static::getSlug();
    }

    /**
     * Get the route name prefix for this nested resource.
     */
    public static function getRouteNamePrefix(): string
    {
        $parentResource = static::getParentResource();
        if (! $parentResource) {
            return 'resources.'.static::getSlug();
        }

        $parentSlug = $parentResource::getSlug();

        return 'resources.'.$parentSlug.'.'.static::getSlug();
    }

    /**
     * Modify the table query to scope to the parent record.
     */
    public static function modifyQueryForParent(Builder $query): Builder
    {
        $parentRecord = static::getParentRecord();

        if (! $parentRecord) {
            return $query;
        }

        $childRelationship = static::getChildRelationship();

        // Get the IDs of related records through the parent
        $relatedIds = $parentRecord->{$childRelationship}()->pluck('id');

        return $query->whereIn('id', $relatedIds);
    }

    /**
     * Get the URL for this nested resource.
     */
    public static function getUrl($panelOrPage = null, array $parameters = []): string
    {
        $parentRecord = static::getParentRecord();
        $parentId = $parentRecord?->getKey() ?? static::getParentRecordId();

        if ($parentId && static::getParentResource()) {
            $parentSlug = static::getParentResource()::getSlug();
            $parameters[$parentSlug] = $parentId;
        }

        // Determine the default list page name based on available pages
        $pages = static::getPages();
        $defaultListPage = array_key_exists('list', $pages) ? 'list' : 'index';

        // Page name aliases for backward compatibility
        $pageAliases = [
            'list' => 'index',
        ];

        // Support both getUrl($panel) and getUrl('list', $parameters)
        if ($panelOrPage instanceof \Laravilt\Panel\Panel) {
            $panelId = $panelOrPage->getId();
            $page = $defaultListPage;
        } elseif ($panelOrPage === null) {
            $page = $defaultListPage;
        } else {
            // Get current panel from registry
            $registry = app(\Laravilt\Panel\PanelRegistry::class);
            $panel = $registry->getCurrent();
            $panelId = $panel?->getId();

            if (! $panelId) {
                $defaultPanel = $registry->getDefault();
                $panelId = $defaultPanel?->getId();
            }

            if (! $panelId) {
                $allPanels = $registry->all();
                $firstPanel = reset($allPanels);
                $panelId = $firstPanel ? $firstPanel->getId() : 'admin';
            }

            $page = $panelOrPage;

            if (! array_key_exists($page, $pages) && isset($pageAliases[$page]) && array_key_exists($pageAliases[$page], $pages)) {
                $page = $pageAliases[$page];
            }
        }

        $routeName = "{$panelId}.".static::getRouteNamePrefix().".{$page}";

        return route($routeName, $parameters);
    }

    /**
     * Get breadcrumbs for this nested resource.
     */
    public static function getBreadcrumbs(): array
    {
        $breadcrumbs = [];

        // Add parent resource breadcrumb
        $parentResource = static::getParentResource();
        if ($parentResource) {
            $breadcrumbs[] = [
                'label' => $parentResource::getPluralLabel(),
                'url' => $parentResource::getUrl(),
            ];

            // Add parent record breadcrumb
            $parentRecord = static::getParentRecord();
            if ($parentRecord) {
                $titleAttribute = $parentResource::getRecordTitleAttribute() ?? 'id';
                $breadcrumbs[] = [
                    'label' => $parentRecord->{$titleAttribute} ?? "#{$parentRecord->getKey()}",
                    'url' => $parentResource::getUrl('view', ['record' => $parentRecord->getKey()]),
                ];
            }
        }

        // Add current resource breadcrumb
        $breadcrumbs[] = [
            'label' => static::getPluralLabel(),
            'url' => static::getUrl(),
        ];

        return $breadcrumbs;
    }

    /**
     * Get the record title attribute for display.
     */
    public static function getRecordTitleAttribute(): ?string
    {
        return null;
    }

    /**
     * Get navigation items for this nested resource (shown in parent context).
     */
    public static function getNavigationItems(): array
    {
        if (! static::shouldShowInParentNavigation()) {
            return [];
        }

        return [
            [
                'label' => static::getPluralLabel(),
                'icon' => static::getNavigationIcon(),
                'url' => static::getUrl(),
                'sort' => static::getNavigationSort(),
                'badge' => static::getNavigationBadge(),
                'badgeColor' => static::getNavigationBadgeColor(),
            ],
        ];
    }
}
