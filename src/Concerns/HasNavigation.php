<?php

namespace Laravilt\Panel\Concerns;

use Closure;
use Laravilt\Panel\Navigation\NavigationBuilder;
use Laravilt\Panel\Navigation\UserMenu;

trait HasNavigation
{
    protected ?Closure $navigationCallback = null;

    protected ?Closure $userMenuCallback = null;

    /**
     * Set the navigation callback.
     */
    public function navigation(Closure $callback): static
    {
        $this->navigationCallback = $callback;

        return $this;
    }

    /**
     * Get the navigation.
     */
    public function getNavigation(): array
    {
        if ($this->navigationCallback) {
            $builder = new NavigationBuilder;
            $builder->panel($this);
            call_user_func($this->navigationCallback, $builder);

            return collect($builder->get())
                ->map(fn ($item) => $item->toArray())
                ->all();
        }

        // Build from pages, resources, and clusters
        return collect(NavigationBuilder::fromPagesAndResources(
            $this->getPages(),
            $this->getResources(),
            $this,
            $this->getClusters()
        )->get())
            ->map(fn ($item) => $item->toArray())
            ->all();
    }

    /**
     * Set the user menu callback.
     */
    public function userMenu(Closure $callback): static
    {
        $this->userMenuCallback = $callback;

        return $this;
    }

    /**
     * Get the user menu.
     */
    public function getUserMenu(): array
    {
        $menuItems = [];

        if ($this->userMenuCallback) {
            $menu = new UserMenu;
            call_user_func($this->userMenuCallback, $menu);
            $menuItems = $menu->toArray();
        } else {
            $menuItems = UserMenu::default()->toArray();
        }

        // Add AI menu items if AI is configured
        if (method_exists($this, 'getAIMenuItems')) {
            $aiItems = $this->getAIMenuItems();
            if (! empty($aiItems)) {
                $aiMenuItems = collect($aiItems)->map(fn ($item) => $item->toArray())->all();
                // Insert AI items before the last item (logout)
                if (count($menuItems) > 0) {
                    array_splice($menuItems, -1, 0, $aiMenuItems);
                } else {
                    $menuItems = array_merge($menuItems, $aiMenuItems);
                }
            }
        }

        return $menuItems;
    }

    /**
     * Boot navigation.
     */
    protected function bootNavigation(): void
    {
        // Navigation is built on-demand
    }
}
