<?php

namespace Laravilt\Panel\Navigation;

use Illuminate\Contracts\Support\Arrayable;

class UserMenu implements Arrayable
{
    protected array $items = [];

    /**
     * Add a menu item.
     */
    public function item(NavigationItem $item): static
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * Add multiple items.
     */
    public function items(array $items): static
    {
        foreach ($items as $item) {
            $this->item($item);
        }

        return $this;
    }

    /**
     * Get all items.
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Build default user menu.
     */
    public static function default(): static
    {
        $menu = new static;

        $items = [];

        // Add profile link if route exists
        try {
            if (function_exists('route') && route('profile.edit', [], false)) {
                $items[] = NavigationItem::make(__('laravilt-panel::panel.user_menu.profile'))
                    ->translationKey('laravilt-panel::panel.user_menu.profile')
                    ->icon('heroicon-o-user')
                    ->url(route('profile.edit'));
            }
        } catch (\Exception $e) {
            // Route doesn't exist, skip
        }

        // Add settings link if route exists
        try {
            if (function_exists('route') && route('settings.index', [], false)) {
                $items[] = NavigationItem::make(__('laravilt-panel::panel.user_menu.settings'))
                    ->translationKey('laravilt-panel::panel.user_menu.settings')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(route('settings.index'));
            }
        } catch (\Exception $e) {
            // Route doesn't exist, skip
        }

        // Add logout link
        try {
            $logoutUrl = route('logout', [], false) ?: '#';
            $items[] = NavigationItem::make(__('laravilt-panel::panel.user_menu.logout'))
                ->translationKey('laravilt-panel::panel.user_menu.logout')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->url($logoutUrl)
                ->method('post');
        } catch (\Exception $e) {
            // Fallback to # if route doesn't exist
            $items[] = NavigationItem::make(__('laravilt-panel::panel.user_menu.logout'))
                ->translationKey('laravilt-panel::panel.user_menu.logout')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->url('#')
                ->method('post');
        }

        return $menu->items($items);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return collect($this->items)
            ->map(fn ($item) => $item->toArray())
            ->all();
    }
}
