<?php

namespace Laravilt\Panel;

use Closure;
use Illuminate\Support\Traits\Conditionable;
use Illuminate\Support\Traits\Macroable;
use Laravilt\Panel\Concerns\HasAI;
use Laravilt\Panel\Concerns\HasAuth;
use Laravilt\Panel\Concerns\HasBranding;
use Laravilt\Panel\Concerns\HasClusters;
use Laravilt\Panel\Concerns\HasColors;
use Laravilt\Panel\Concerns\HasId;
use Laravilt\Panel\Concerns\HasMiddleware;
use Laravilt\Panel\Concerns\HasNavigation;
use Laravilt\Panel\Concerns\HasNotifications;
use Laravilt\Panel\Concerns\HasPages;
use Laravilt\Panel\Concerns\HasPath;
use Laravilt\Panel\Concerns\HasResources;
use Laravilt\Panel\Concerns\HasTenancy;
use Laravilt\Panel\Concerns\HasTheme;
use Laravilt\Panel\Concerns\HasWidgets;
use Laravilt\Panel\Discovery\PanelDiscovery;

class Panel
{
    use Conditionable;
    use HasAI;
    use HasAuth;
    use HasBranding;
    use HasClusters;
    use HasColors;
    use HasId;
    use HasMiddleware;
    use HasNavigation;
    use HasNotifications;
    use HasPages;
    use HasPath;
    use HasResources;
    use HasTenancy;
    use HasTheme;
    use HasWidgets;
    use Macroable;

    /**
     * Is this the default panel?
     */
    protected bool $isDefault = false;

    /**
     * Panel configuration.
     */
    protected array $configuration = [];

    /**
     * Maximum content width.
     */
    protected string|Closure|null $maxContentWidth = null;

    /**
     * Enable auto-discovery of components.
     */
    protected bool $autoDiscovery = false;

    /**
     * Create a new panel instance.
     */
    public function __construct(string $id)
    {
        $this->id($id);
        $this->path($id);
        $this->middleware(['web']);
    }

    /**
     * Make a new panel instance.
     */
    public static function make(string $id): static
    {
        return new static($id);
    }

    /**
     * Mark this panel as the default.
     */
    public function default(bool $condition = true): static
    {
        $this->isDefault = $condition;

        return $this;
    }

    /**
     * Check if this is the default panel.
     */
    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    /**
     * Enable auto-discovery of pages, resources, clusters, and widgets.
     */
    public function discoverAutomatically(bool $condition = true): static
    {
        $this->autoDiscovery = $condition;

        return $this;
    }

    /**
     * Check if auto-discovery is enabled.
     */
    public function hasAutoDiscovery(): bool
    {
        return $this->autoDiscovery;
    }

    /**
     * Register this panel.
     */
    public function register(): void
    {
        // Auto-discover components if enabled
        if ($this->autoDiscovery) {
            PanelDiscovery::discover($this);
        }

        // Bridge to plugins
        if (app()->bound('laravilt.plugins')) {
            app('laravilt.plugins')->registerWithPanel($this);
        }

        // Ensure RequirePassword middleware is added if social login requires it
        if (method_exists($this, 'ensureRequirePasswordMiddleware')) {
            $this->ensureRequirePasswordMiddleware();
        }

        app(PanelRegistry::class)->register($this);
    }

    /**
     * Get the user menu.
     */
    public function getUserMenu(): array
    {
        $menu = new \Laravilt\Panel\Navigation\UserMenu;

        // If custom user menu callback is set, use it
        if ($this->userMenuCallback) {
            call_user_func($this->userMenuCallback, $menu);
        } else {
            // Build default user menu with auth items
            if (method_exists($this, 'buildAuthUserMenu')) {
                $this->buildAuthUserMenu($menu);
            }
        }

        $menuItems = $menu->toArray();

        // Add AI menu items if AI providers are configured
        if ($this->hasAIProviders()) {
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
     * Get the panel URL.
     */
    public function url(string $path = ''): string
    {
        $panelPath = trim($this->getPath(), '/');
        $basePath = $panelPath ? '/'.$panelPath : '';

        if ($path) {
            $path = '/'.trim($path, '/');
        }

        return $basePath.$path ?: '/';
    }

    /**
     * Get the panel route name.
     */
    public function route(string $name = ''): string
    {
        $routeName = $this->getId();

        if ($name) {
            $routeName .= '.'.$name;
        }

        return $routeName;
    }

    /**
     * Set the maximum content width.
     */
    public function maxContentWidth(string|Closure|null $width): static
    {
        $this->maxContentWidth = $width;

        return $this;
    }

    /**
     * Get the maximum content width.
     */
    public function getMaxContentWidth(): string
    {
        return $this->evaluate($this->maxContentWidth) ?? config('laravilt.panel.max_content_width', '7xl');
    }

    /**
     * Boot the panel.
     */
    public function boot(): void
    {
        // Register auth pages before booting
        if (method_exists($this, 'getAuthPages')) {
            $authPages = $this->getAuthPages();
            if (! empty($authPages)) {
                $this->pages($authPages);
            }
        }

        $this->bootClusters();
        $this->bootPages();
        $this->bootWidgets();
        $this->bootResources();
        $this->bootNavigation();
        $this->registerAuthRoutes();
        $this->registerNotificationRoutes();
        $this->registerAIRoutes();
        $this->registerResourcesForGlobalSearch();
    }

    /**
     * Convert panel to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'path' => $this->getPath(),
            'colors' => $this->getColors(),
            'brandName' => $this->getBrandName(),
            'brandLogo' => $this->getBrandLogo(),
            'isDefault' => $this->isDefault(),
            'middleware' => $this->getMiddleware(),
        ];
    }

    /**
     * Evaluate a value that might be a closure.
     */
    protected function evaluate(mixed $value): mixed
    {
        if ($value instanceof Closure) {
            return $value();
        }

        return $value;
    }
}
