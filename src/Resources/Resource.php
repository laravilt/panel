<?php

declare(strict_types=1);

namespace Laravilt\Panel\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravilt\AI\AIAgent;
use Laravilt\Schemas\Schema;
use Laravilt\Tables\ApiResource;
use Laravilt\Tables\Table;

abstract class Resource
{
    /** @var class-string<Model> */
    protected static string $model;

    protected static ?string $label = null;

    protected static ?string $pluralLabel = null;

    protected static ?string $icon = null;

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationGroup = null;

    protected static int $navigationSort = 0;

    protected static bool $navigationVisible = true;

    protected static ?string $navigationBadge = null;

    protected static ?string $navigationBadgeColor = null;

    protected static ?string $slug = null;

    protected static bool $hasApi = false;

    protected static bool $hasFlutter = false;

    protected static bool $hasAI = false;

    /**
     * Whether to show this resource's stats on the dashboard.
     */
    protected static bool $showOnDashboard = true;

    /**
     * Check if this resource should show stats on the dashboard.
     */
    public static function shouldShowOnDashboard(): bool
    {
        return static::$showOnDashboard;
    }

    public static function makeForm(): Schema
    {
        $form = new \Laravilt\Forms\Form;
        $form->model(static::$model);

        return $form;
    }

    public static function makeTable(): Table
    {
        return new Table;
    }

    public static function makeInfoList(): Schema
    {
        return new \Laravilt\Infolists\InfoList;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema;
    }

    /**
     * Create a new ApiResource instance for configuration.
     */
    public static function makeApiResource(): ApiResource
    {
        return ApiResource::make()
            ->endpoint('/api/'.static::getSlug());
    }

    /**
     * Configure the API resource for this resource.
     * Override this method in your resource to customize the API configuration.
     */
    public static function api(ApiResource $api): ApiResource
    {
        return $api;
    }

    /**
     * Get the configured API resource.
     */
    public static function getApiResource(): ?ApiResource
    {
        if (! static::hasApi()) {
            return null;
        }

        return static::api(static::makeApiResource());
    }

    /**
     * Create a new AIAgent instance for configuration.
     */
    public static function makeAIAgent(): AIAgent
    {
        return AIAgent::make()
            ->name(static::getSlug())
            ->model(static::$model)
            ->description('AI agent for '.static::getLabel().' resource');
    }

    /**
     * Configure the AI agent for this resource.
     * Override this method in your resource to customize the AI configuration.
     */
    public static function ai(AIAgent $agent): AIAgent
    {
        return $agent;
    }

    /**
     * Get the configured AI agent.
     */
    public static function getAIAgent(): ?AIAgent
    {
        if (! static::hasAI()) {
            return null;
        }

        return static::ai(static::makeAIAgent());
    }

    /**
     * Check if this resource has AI capabilities.
     */
    public static function hasAI(): bool
    {
        // Auto-detect: if ai() method is defined in child class (not just inherited), enable AI
        if (static::$hasAI) {
            return true;
        }

        // Check if ai() method is overridden in the child class
        try {
            $reflection = new \ReflectionMethod(static::class, 'ai');

            return $reflection->getDeclaringClass()->getName() === static::class;
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    public static function flutter(\Laravilt\Flutter\Flutter $flutter): \Laravilt\Flutter\Flutter
    {
        return $flutter;
    }

    /**
     * @return array<string, array{class: string, path: string}>
     */
    public static function getPages(): array
    {
        return [];
    }

    /**
     * @return array<class-string>
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * @return class-string<Model>
     */
    public static function getModel(): string
    {
        return static::$model;
    }

    /**
     * Get the Eloquent query for retrieving records.
     * Override this method to customize the query (e.g., filter by user, scope, etc.).
     */
    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query();
    }

    public static function getLabel(): string
    {
        return static::$label ?? str(class_basename(static::class))->remove('Resource')->headline()->toString();
    }

    public static function getPluralLabel(): string
    {
        return static::$pluralLabel ?? str(static::getLabel())->remove('Resource')->plural()->toString();
    }

    public static function getIcon(): ?string
    {
        return static::$icon;
    }

    public static function getNavigationIcon(): ?string
    {
        return static::$navigationIcon ?? static::$icon;
    }

    public static function getNavigationGroup(): ?string
    {
        return static::$navigationGroup;
    }

    public static function getNavigationSort(): int
    {
        return static::$navigationSort;
    }

    public static function isNavigationVisible(): bool
    {
        return static::$navigationVisible;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::$navigationBadge;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::$navigationBadgeColor;
    }

    public static function getSlug(): string
    {
        if (static::$slug) {
            return static::$slug;
        }

        return str(class_basename(static::class))
            ->replace('Resource', '')
            ->kebab()
            ->toString();
    }

    public static function getUrl($panelOrPage = null, array $parameters = []): string
    {
        // Determine the default list page name based on available pages
        $pages = static::getPages();
        $defaultListPage = array_key_exists('list', $pages) ? 'list' : 'index';

        // Support both getUrl($panel) and getUrl('list', $parameters)
        if ($panelOrPage instanceof \Laravilt\Panel\Panel) {
            $panelId = $panelOrPage->getId();
            $page = $defaultListPage;
        } elseif ($panelOrPage === null) {
            $page = $defaultListPage;
        } else {
            // Get current panel from registry, no hardcoded fallback
            $registry = app(\Laravilt\Panel\PanelRegistry::class);
            $panel = $registry->getCurrent();
            $panelId = $panel?->getId();

            // If no current panel, try to get the default panel
            if (! $panelId) {
                $defaultPanel = $registry->getDefault();
                $panelId = $defaultPanel?->getId();
            }

            // Last resort: get the first registered panel
            if (! $panelId) {
                $allPanels = $registry->all();
                $firstPanel = reset($allPanels);
                $panelId = $firstPanel ? $firstPanel->getId() : 'admin';
            }

            $page = $panelOrPage;
        }

        return route(
            "{$panelId}.resources.".static::getSlug().".{$page}",
            $parameters
        );
    }

    public static function getRouteBaseName(): string
    {
        return 'laravilt.resources.'.static::getSlug();
    }

    public static function hasApi(): bool
    {
        // Auto-detect: if api() method is defined in child class (not just inherited), enable API
        if (static::$hasApi) {
            return true;
        }

        // Check if api() method is overridden in the child class
        try {
            $reflection = new \ReflectionMethod(static::class, 'api');

            return $reflection->getDeclaringClass()->getName() === static::class;
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    public static function hasFlutter(): bool
    {
        return static::$hasFlutter;
    }

    /**
     * Check if the resource's table has a card configuration (supports grid view).
     */
    public static function hasCardConfig(): bool
    {
        if (! static::hasTable()) {
            return false;
        }

        $table = static::table(new Table);

        return $table->getCard() !== null;
    }

    /**
     * Check if the resource's table is configured for grid-only mode.
     */
    public static function isGridOnly(): bool
    {
        if (! static::hasTable()) {
            return false;
        }

        $table = static::table(new Table);

        return $table->isGridOnly();
    }

    /**
     * Check if the resource uses a Table for listing.
     */
    public static function hasTable(): bool
    {
        return method_exists(static::class, 'table') &&
               (new \ReflectionMethod(static::class, 'table'))->getDeclaringClass()->getName() !== self::class;
    }

    /**
     * Transform a model instance for API response.
     *
     * @return array<string, mixed>
     */
    public static function toApiResource(Model $record): array
    {
        return $record->toArray();
    }

    /**
     * Transform a model instance for Flutter consumption.
     *
     * @return array<string, mixed>
     */
    public static function toFlutterResource(Model $record): array
    {
        return $record->toArray();
    }

    /**
     * Get API routes configuration for this resource.
     *
     * @param  string|null  $panelPath  The panel path prefix (e.g., 'admin')
     * @return array<string, mixed>
     */
    public static function getApiRoutes(?string $panelPath = null): array
    {
        if (! static::hasApi()) {
            return [];
        }

        $slug = static::getSlug();
        $prefix = $panelPath ? "/{$panelPath}/api/{$slug}" : "/api/{$slug}";

        return [
            'index' => ['method' => 'GET', 'path' => $prefix],
            'show' => ['method' => 'GET', 'path' => "{$prefix}/{id}"],
            'store' => ['method' => 'POST', 'path' => $prefix],
            'update' => ['method' => 'PUT', 'path' => "{$prefix}/{id}"],
            'destroy' => ['method' => 'DELETE', 'path' => "{$prefix}/{id}"],
        ];
    }

    /**
     * Get the badge count for navigation.
     * Override this method to return dynamic counts (e.g., pending orders).
     */
    public static function getNavigationBadgeCount(): ?int
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function toArray(): array
    {
        return [
            'model' => static::$model,
            'label' => static::getLabel(),
            'pluralLabel' => static::getPluralLabel(),
            'icon' => static::$icon,
            'navigationIcon' => static::getNavigationIcon(),
            'navigationGroup' => static::$navigationGroup,
            'navigationSort' => static::$navigationSort,
            'navigationVisible' => static::$navigationVisible,
            'navigationBadge' => static::getNavigationBadge(),
            'navigationBadgeColor' => static::getNavigationBadgeColor(),
            'navigationBadgeCount' => static::getNavigationBadgeCount(),
            'slug' => static::getSlug(),
            'pages' => static::getPages(),
            'relations' => static::getRelations(),
            'hasApi' => static::hasApi(),
            'hasFlutter' => static::hasFlutter(),
            'hasAI' => static::hasAI(),
            'apiRoutes' => static::getApiRoutes(),
            'apiResource' => static::getApiResource()?->toInertiaProps(),
            'aiAgent' => static::getAIAgent()?->toArray(),
        ];
    }
}
