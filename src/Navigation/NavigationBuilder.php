<?php

namespace Laravilt\Panel\Navigation;

use Closure;
use Laravilt\Panel\Panel;

class NavigationBuilder
{
    protected array $groups = [];

    protected array $items = [];

    protected ?Panel $panel = null;

    /**
     * Set the panel instance.
     */
    public function panel(Panel $panel): static
    {
        $this->panel = $panel;

        return $this;
    }

    /**
     * Add a navigation group.
     */
    public function group(string $label, array|Closure $items): static
    {
        $this->groups[] = NavigationGroup::make($label)
            ->items($this->evaluate($items));

        return $this;
    }

    /**
     * Add a navigation item.
     */
    public function item(NavigationItem|string $item): static
    {
        if (is_string($item)) {
            $item = NavigationItem::make($item);
        }

        $this->items[] = $item;

        return $this;
    }

    /**
     * Add multiple navigation items.
     */
    public function items(array $items): static
    {
        foreach ($items as $item) {
            $this->item($item);
        }

        return $this;
    }

    /**
     * Get all navigation groups.
     */
    public function getGroups(): array
    {
        return $this->groups;
    }

    /**
     * Get all navigation items (ungrouped).
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Get all navigation (groups + items).
     */
    public function get(): array
    {
        $navigation = [];

        // Add ungrouped items first
        foreach ($this->items as $item) {
            $navigation[] = $item;
        }

        // Add groups
        foreach ($this->groups as $group) {
            $navigation[] = $group;
        }

        return $navigation;
    }

    /**
     * Build from pages.
     */
    public static function fromPages(array $pages, Panel $panel): static
    {
        return static::fromPagesAndResources($pages, [], $panel);
    }

    /**
     * Build from pages and resources.
     */
    public static function fromPagesAndResources(array $pages, array $resources, Panel $panel): static
    {
        $builder = new static;
        $builder->panel($panel);

        // Combine pages and resources for navigation
        $navigationItems = collect($pages)
            ->filter(function ($page) {
                // Skip clusters - they don't register in navigation
                if (is_subclass_of($page, \Laravilt\Panel\Cluster::class)) {
                    return false;
                }

                return $page::shouldRegisterNavigation();
            });

        // Add resources to navigation items
        $resourceItems = collect($resources)
            ->filter(fn ($resource) => $resource::isNavigationVisible());

        // Merge and group by navigation group
        $grouped = $navigationItems
            ->merge($resourceItems)
            ->groupBy(fn ($item) => $item::getNavigationGroup() ?? '__ungrouped')
            ->map(function ($items, $group) {
                return $items->sortBy(fn ($item) => $item::getNavigationSort());
            });

        // Add ungrouped items
        if ($grouped->has('__ungrouped')) {
            foreach ($grouped->get('__ungrouped') as $item) {
                $navItem = NavigationItem::make($item::getLabel())
                    ->icon($item::getNavigationIcon())
                    ->url($item::getUrl($panel))
                    ->sort($item::getNavigationSort());

                // Add badge if resource has it
                if (method_exists($item, 'getNavigationBadge')) {
                    $badge = $item::getNavigationBadge();
                    $badgeColor = method_exists($item, 'getNavigationBadgeColor') ? $item::getNavigationBadgeColor() : null;
                    if ($badge !== null) {
                        $navItem->badge($badge, $badgeColor);
                    }
                }

                $builder->item($navItem);
            }

            $grouped->forget('__ungrouped');
        }

        // Add grouped items
        foreach ($grouped as $groupLabel => $items) {
            $groupItems = [];

            foreach ($items as $item) {
                $navItem = NavigationItem::make($item::getLabel())
                    ->icon($item::getNavigationIcon())
                    ->url($item::getUrl($panel))
                    ->sort($item::getNavigationSort());

                // Add badge if resource has it
                if (method_exists($item, 'getNavigationBadge')) {
                    $badge = $item::getNavigationBadge();
                    $badgeColor = method_exists($item, 'getNavigationBadgeColor') ? $item::getNavigationBadgeColor() : null;
                    if ($badge !== null) {
                        $navItem->badge($badge, $badgeColor);
                    }
                }

                $groupItems[] = $navItem;
            }

            $builder->group($groupLabel, $groupItems);
        }

        return $builder;
    }

    /**
     * Evaluate a closure or return the value.
     */
    protected function evaluate(mixed $value): mixed
    {
        if ($value instanceof Closure) {
            return $value();
        }

        return $value;
    }
}
