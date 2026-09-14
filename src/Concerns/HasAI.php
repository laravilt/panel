<?php

declare(strict_types=1);

namespace Laravilt\Panel\Concerns;

use Closure;
use Illuminate\Support\Facades\Route;
use Laravilt\AI\AIManager;
use Laravilt\AI\Builders\AIProviderBuilder;
use Laravilt\AI\Builders\GlobalSearchBuilder;
use Laravilt\AI\GlobalSearch;
use Laravilt\AI\Http\Controllers\AIController;
use Laravilt\AI\Http\Controllers\GlobalSearchController;
use Laravilt\AI\Pages\AIChat;
use Laravilt\Panel\Http\Middleware\HandleLocalization;
use Laravilt\Panel\Http\Middleware\SharePanelData;
use Laravilt\Panel\Middleware\IdentifyPanel;
use Laravilt\Panel\Navigation\NavigationItem;

trait HasAI
{
    protected ?GlobalSearchBuilder $globalSearchBuilder = null;

    protected ?AIProviderBuilder $aiProviderBuilder = null;

    /**
     * Configure global search for this panel.
     */
    public function globalSearch(?Closure $callback = null): static
    {
        $this->globalSearchBuilder = new GlobalSearchBuilder;

        if ($callback) {
            $callback($this->globalSearchBuilder);
        }

        return $this;
    }

    /**
     * Configure AI providers for this panel.
     */
    public function aiProviders(?Closure $callback = null): static
    {
        $this->aiProviderBuilder = new AIProviderBuilder;

        if ($callback) {
            $callback($this->aiProviderBuilder);
        }

        return $this;
    }

    /**
     * Check if global search is enabled.
     */
    public function hasGlobalSearch(): bool
    {
        return $this->globalSearchBuilder !== null && $this->globalSearchBuilder->isEnabled();
    }

    /**
     * Get the global search builder.
     */
    public function getGlobalSearchBuilder(): ?GlobalSearchBuilder
    {
        return $this->globalSearchBuilder;
    }

    /**
     * Check if AI providers are configured.
     */
    public function hasAIProviders(): bool
    {
        return $this->aiProviderBuilder !== null && $this->aiProviderBuilder->hasConfiguredProviders();
    }

    /**
     * Get the AI provider builder.
     */
    public function getAIProviderBuilder(): ?AIProviderBuilder
    {
        return $this->aiProviderBuilder;
    }

    /**
     * Get global search configuration for frontend.
     *
     * @return array<string, mixed>|null
     */
    public function getGlobalSearchConfig(): ?array
    {
        if (! $this->hasGlobalSearch()) {
            return null;
        }

        $config = $this->globalSearchBuilder->toArray();

        // Add endpoint if not set
        if (! $config['endpoint']) {
            $config['endpoint'] = $this->url('/global-search');
        }

        return $config;
    }

    /**
     * Get AI configuration for frontend.
     *
     * @return array<string, mixed>|null
     */
    public function getAIConfig(): ?array
    {
        if (! $this->hasAIProviders()) {
            return null;
        }

        return $this->aiProviderBuilder->toArray();
    }

    /**
     * Get global search endpoint URL.
     */
    public function getGlobalSearchEndpoint(): string
    {
        if ($this->globalSearchBuilder && $endpoint = $this->globalSearchBuilder->getEndpoint()) {
            return $endpoint;
        }

        return $this->url('/global-search');
    }

    /**
     * Register AI and global search routes for this panel.
     */
    protected function registerAIRoutes(): void
    {
        $panelPath = $this->getPath();
        $panelId = $this->getId();

        // Build the full middleware stack (same as panel routes)
        $middleware = $this->getMiddleware();
        $authMiddleware = $this->getAuthMiddleware();

        // Replace 'auth' with 'panel.auth' in middleware arrays
        $middleware = array_map(fn ($m) => $m === 'auth' ? 'panel.auth' : $m, $middleware);
        $authMiddleware = array_map(fn ($m) => $m === 'auth' ? 'panel.auth' : $m, $authMiddleware);

        // Remove panel.auth from base middleware to ensure correct order
        $middlewareWithoutAuth = array_filter($middleware, fn ($m) => $m !== 'panel.auth');

        // Full middleware stack with panel identification, auth, localization and data sharing
        $fullMiddleware = array_merge(
            $middlewareWithoutAuth,
            [IdentifyPanel::class.':'.$panelId],
            $authMiddleware,
            [HandleLocalization::class],
            [SharePanelData::class]
        );

        // Register global search route if enabled
        if ($this->hasGlobalSearch()) {
            Route::middleware($fullMiddleware)
                ->prefix($panelPath)
                ->name($panelId.'.')
                ->group(function () {
                    $controller = GlobalSearchController::class;

                    Route::get('/global-search', [$controller, 'search'])->name('global-search');
                    Route::get('/global-search/resources', [$controller, 'resources'])->name('global-search.resources');
                });
        }

        // Register AI routes if providers are configured
        if ($this->hasAIProviders()) {
            $panel = $this;

            // Register AI Chat page route
            Route::middleware($fullMiddleware)
                ->prefix($panelPath)
                ->name($panelId.'.')
                ->group(function () use ($panel) {
                    $pageClass = AIChat::class;

                    Route::get('/ai', function () use ($pageClass, $panel) {
                        $page = new $pageClass;
                        $page->panel($panel);

                        return $page->render();
                    })->name('ai');
                });

            // Register AI API routes
            Route::middleware($fullMiddleware)
                ->prefix($panelPath.'/ai')
                ->name($panelId.'.ai.')
                ->group(function () {
                    $controller = AIController::class;

                    Route::get('/config', [$controller, 'config'])->name('config');
                    Route::get('/resources', [$controller, 'resources'])->name('resources');
                    Route::post('/chat', [$controller, 'chat'])->name('chat');
                    Route::post('/stream', [$controller, 'stream'])->name('stream');

                    // Session routes (methods in AIController)
                    Route::get('/sessions', [$controller, 'sessions'])->name('sessions.index');
                    Route::post('/sessions', [$controller, 'createSession'])->name('sessions.store');
                    Route::get('/sessions/{session}', [$controller, 'session'])->name('sessions.show');
                    Route::patch('/sessions/{session}', [$controller, 'updateSession'])->name('sessions.update');
                    Route::delete('/sessions/{session}', [$controller, 'deleteSession'])->name('sessions.destroy');
                });
        }
    }

    /**
     * Register resources for global search.
     */
    protected function registerResourcesForGlobalSearch(): void
    {
        if (! $this->hasGlobalSearch()) {
            return;
        }

        $globalSearch = app(GlobalSearch::class);
        $builder = $this->globalSearchBuilder;
        $excludedResources = $builder->getExcludedResources();

        // Configure global search from builder
        $globalSearch->limit($builder->getLimit());

        if ($builder->usesAI()) {
            $globalSearch->useAI(true);
        }

        // Register AI manager from panel's providers
        if ($this->hasAIProviders()) {
            $aiManager = app(AIManager::class);

            foreach ($this->aiProviderBuilder->getProviders() as $provider) {
                $aiManager->addProvider($provider);
            }

            if ($defaultProvider = $this->aiProviderBuilder->getDefaultProviderName()) {
                $aiManager->setDefaultProvider($defaultProvider);
            }
        }

        // Auto-register resources that have AI enabled
        foreach ($this->getResources() as $resourceClass) {
            // Skip excluded resources
            if (in_array($resourceClass, $excludedResources)) {
                continue;
            }

            /** @var \Laravilt\Panel\Resources\Resource $resourceClass */
            if ($resourceClass::hasAI()) {
                $agent = $resourceClass::getAIAgent();

                if ($agent && $searchable = $agent->getSearchable()) {
                    // Build URL pattern manually to avoid route generation during boot
                    $panelPath = $this->getPath();
                    $resourceSlug = $resourceClass::getSlug();
                    $urlPattern = "/{$panelPath}/{$resourceSlug}/{id}";

                    $globalSearch->registerResource(
                        resource: $resourceSlug,
                        model: $resourceClass::getModel(),
                        searchable: $searchable,
                        label: $resourceClass::getPluralLabel(),
                        icon: $resourceClass::getNavigationIcon(),
                        url: $urlPattern
                    );
                }
            }
        }
    }

    /**
     * Get AI-related menu items for user menu.
     *
     * @return array<NavigationItem>
     */
    public function getAIMenuItems(): array
    {
        if (! $this->hasAIProviders()) {
            return [];
        }

        return [
            NavigationItem::make(__('laravilt-ai::ai.chat.title'))
                ->translationKey('laravilt-ai::ai.chat.title')
                ->icon('Sparkles')
                ->url($this->url('/ai')),
        ];
    }
}
