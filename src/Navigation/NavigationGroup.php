<?php

namespace Laravilt\Panel\Navigation;

use Closure;
use Illuminate\Contracts\Support\Arrayable;

class NavigationGroup implements Arrayable
{
    protected string|Closure $label;

    protected array $items = [];

    protected bool|Closure $collapsible = true;

    protected bool|Closure $collapsed = true;

    protected ?string $icon = null;

    protected ?int $sort = null;

    /**
     * Make a new navigation group.
     */
    public static function make(string|Closure $label): static
    {
        $group = new static;
        $group->label = $label;

        return $group;
    }

    /**
     * Set the group items.
     */
    public function items(array $items): static
    {
        $this->items = $items;

        return $this;
    }

    /**
     * Get the group items.
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * Set whether the group is collapsible.
     */
    public function collapsible(bool|Closure $condition = true): static
    {
        $this->collapsible = $condition;

        return $this;
    }

    /**
     * Check if the group is collapsible.
     */
    public function isCollapsible(): bool
    {
        return $this->evaluate($this->collapsible);
    }

    /**
     * Set whether the group is collapsed by default.
     */
    public function collapsed(bool|Closure $condition = true): static
    {
        $this->collapsed = $condition;

        return $this;
    }

    /**
     * Check if the group is collapsed.
     */
    public function isCollapsed(): bool
    {
        return $this->evaluate($this->collapsed);
    }

    /**
     * Set the group icon.
     */
    public function icon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Get the group icon.
     */
    public function getIcon(): ?string
    {
        return $this->icon;
    }

    /**
     * Set the group sort order.
     */
    public function sort(?int $sort): static
    {
        $this->sort = $sort;

        return $this;
    }

    /**
     * Get the group sort order.
     */
    public function getSort(): ?int
    {
        return $this->sort;
    }

    /**
     * Get the group label.
     */
    public function getLabel(): string
    {
        return $this->evaluate($this->label);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'type' => 'group',
            'title' => $this->getLabel(),  // Frontend expects 'title'
            'icon' => $this->getIcon(),
            'collapsible' => $this->isCollapsible(),
            'collapsed' => $this->isCollapsed(),
            'items' => collect($this->items)
                ->map(fn ($item) => $item->toArray())
                ->all(),
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
