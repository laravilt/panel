<?php

namespace Laravilt\Panel\Navigation;

use Closure;
use Illuminate\Contracts\Support\Arrayable;

class NavigationItem implements Arrayable
{
    protected string|Closure $label;

    protected string|Closure|null $url = null;

    protected string|Closure|null $icon = null;

    protected string|int|Closure|null $badge = null;

    protected string|Closure|null $badgeColor = null;

    protected int|Closure $sort = 0;

    protected bool|Closure $active = false;

    protected string|Closure|null $method = null;

    protected ?string $translationKey = null;

    /**
     * Make a new navigation item.
     */
    public static function make(string|Closure $label): static
    {
        $item = new static;
        $item->label = $label;

        return $item;
    }

    /**
     * Set the item URL.
     */
    public function url(string|Closure|null $url): static
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get the item URL.
     */
    public function getUrl(): ?string
    {
        return $this->evaluate($this->url);
    }

    /**
     * Set the item icon.
     */
    public function icon(string|Closure|null $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Get the item icon.
     */
    public function getIcon(): ?string
    {
        return $this->evaluate($this->icon);
    }

    /**
     * Set the item badge.
     */
    public function badge(string|int|Closure|null $badge, string|Closure|null $color = null): static
    {
        $this->badge = $badge;
        $this->badgeColor = $color;

        return $this;
    }

    /**
     * Get the item badge.
     */
    public function getBadge(): ?string
    {
        $value = $this->evaluate($this->badge);

        return $value !== null ? (string) $value : null;
    }

    /**
     * Get the badge color.
     */
    public function getBadgeColor(): string
    {
        return $this->evaluate($this->badgeColor) ?? 'primary';
    }

    /**
     * Set the sort order.
     */
    public function sort(int|Closure $sort): static
    {
        $this->sort = $sort;

        return $this;
    }

    /**
     * Get the sort order.
     */
    public function getSort(): int
    {
        return $this->evaluate($this->sort);
    }

    /**
     * Mark as active.
     */
    public function active(bool|Closure $condition = true): static
    {
        $this->active = $condition;

        return $this;
    }

    /**
     * Check if active.
     */
    public function isActive(): bool
    {
        if ($this->evaluate($this->active)) {
            return true;
        }

        // Auto-detect active state from URL
        $url = $this->getUrl();

        if (! $url) {
            return false;
        }

        // Check if request helper is available (won't be in unit tests)
        try {
            if (! function_exists('request')) {
                return false;
            }

            $request = request();

            if (! $request) {
                return false;
            }

            return $request->is(trim($url, '/').'*');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the item label.
     */
    public function getLabel(): string
    {
        return $this->evaluate($this->label);
    }

    /**
     * Set the HTTP method.
     */
    public function method(string|Closure|null $method): static
    {
        $this->method = $method;

        return $this;
    }

    /**
     * Get the HTTP method.
     */
    public function getMethod(): ?string
    {
        return $this->evaluate($this->method);
    }

    /**
     * Set translation key for frontend translation.
     */
    public function translationKey(string $key): static
    {
        $this->translationKey = $key;

        return $this;
    }

    /**
     * Get translation key.
     */
    public function getTranslationKey(): ?string
    {
        return $this->translationKey;
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'type' => 'item',
            'title' => $this->getLabel(),  // Frontend expects 'title'
            'translationKey' => $this->translationKey,  // Send translation key to frontend
            'url' => $this->getUrl(),      // Frontend expects 'url'
            'icon' => $this->getIcon(),
            'badge' => $this->getBadge(),
            'badgeColor' => $this->getBadgeColor(),
            'sort' => $this->getSort(),
            'active' => $this->isActive(),
            'method' => $this->getMethod(),
        ];
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
