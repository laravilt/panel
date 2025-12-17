<?php

declare(strict_types=1);

namespace Laravilt\Panel\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravilt\AI\AIAgent;
use Laravilt\Panel\Facades\Laravilt;
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
     * Whether this resource is scoped to the current tenant.
     * Set to false to make this resource accessible across all tenants.
     */
    protected static bool $isScopedToTenant = true;

    /**
     * Custom tenant ownership relationship name.
     * If null, uses the panel's default tenant ownership relationship.
     */
    protected static ?string $tenantOwnershipRelationshipName = null;

    /**
     * Custom tenant relationship name on the model.
     * Used when the model has a direct relationship to the tenant model.
     */
    protected static ?string $tenantRelationshipName = null;

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
     *
     * When tenancy is enabled and this resource is scoped to tenant,
     * the query will automatically filter by the current tenant.
     * If the tenant has 'show_unassigned_records' setting enabled,
     * records with null team_id will also be included.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = static::getModel()::query();

        // Skip tenant scoping in multi-database mode - data is already isolated by database
        if (Laravilt::isMultiDatabaseTenancy()) {
            return $query;
        }

        // Apply tenant scoping if enabled (single-database mode only)
        if (static::isScopedToTenant() && Laravilt::isTenancyEnabled() && Laravilt::hasTenant()) {
            $tenantId = Laravilt::getTenantId();
            $ownershipColumn = static::getTenantOwnershipColumn();
            $showUnassigned = static::shouldShowUnassignedRecords();

            // Check if table has the ownership column (direct relationship like team_id)
            if ($ownershipColumn && static::modelHasColumn($ownershipColumn)) {
                if ($showUnassigned) {
                    // Include both tenant's records and unassigned records
                    $query->where(function ($q) use ($ownershipColumn, $tenantId) {
                        $q->where($ownershipColumn, $tenantId)
                            ->orWhereNull($ownershipColumn);
                    });
                } else {
                    $query->where($ownershipColumn, $tenantId);
                }
            }
            // Check for many-to-many relationship with tenant model
            elseif (static::hasTenantManyToManyRelationship()) {
                $relationshipName = static::getTenantManyToManyRelationshipName();
                if ($showUnassigned) {
                    // Include both tenant's records and records with no tenant associations
                    $query->where(function ($q) use ($relationshipName, $tenantId) {
                        $q->whereHas($relationshipName, function ($subQ) use ($tenantId) {
                            $subQ->where((new (Laravilt::getTenantModel()))->getTable().'.id', $tenantId);
                        })->orWhereDoesntHave($relationshipName);
                    });
                } else {
                    $query->whereHas($relationshipName, function ($q) use ($tenantId) {
                        $q->where((new (Laravilt::getTenantModel()))->getTable().'.id', $tenantId);
                    });
                }
            }
        }

        return $query;
    }

    /**
     * Check if the current tenant allows showing unassigned records.
     */
    protected static function shouldShowUnassignedRecords(): bool
    {
        $tenant = Laravilt::getTenant();

        if (! $tenant) {
            return false;
        }

        // Check if tenant model has the shouldShowUnassignedRecords method
        if (method_exists($tenant, 'shouldShowUnassignedRecords')) {
            return $tenant->shouldShowUnassignedRecords();
        }

        // Fall back to checking settings array directly
        if (isset($tenant->settings) && is_array($tenant->settings)) {
            return $tenant->settings['show_unassigned_records'] ?? false;
        }

        return false;
    }

    /**
     * Check if the model's table has a specific column.
     */
    protected static function modelHasColumn(string $column): bool
    {
        try {
            $model = new (static::getModel());
            $table = $model->getTable();

            return \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if the model has a many-to-many relationship with the tenant model.
     */
    protected static function hasTenantManyToManyRelationship(): bool
    {
        $relationshipName = static::getTenantManyToManyRelationshipName();

        if (! $relationshipName) {
            return false;
        }

        try {
            $model = new (static::getModel());

            return method_exists($model, $relationshipName) &&
                   $model->{$relationshipName}() instanceof \Illuminate\Database\Eloquent\Relations\BelongsToMany;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the many-to-many relationship name for tenant scoping.
     * Override this method if your model uses a different relationship name.
     */
    protected static function getTenantManyToManyRelationshipName(): ?string
    {
        // Default to 'teams' - override in resource if different
        return 'teams';
    }

    /**
     * Check if this resource is scoped to tenant.
     */
    public static function isScopedToTenant(): bool
    {
        return static::$isScopedToTenant;
    }

    /**
     * Set whether this resource should be scoped to tenant.
     */
    public static function scopeToTenant(bool $condition = true): void
    {
        static::$isScopedToTenant = $condition;
    }

    /**
     * Get the tenant ownership column for this resource.
     */
    public static function getTenantOwnershipColumn(): ?string
    {
        // Use custom relationship name if set
        if (static::$tenantOwnershipRelationshipName !== null) {
            return static::$tenantOwnershipRelationshipName.'_id';
        }

        // Fall back to panel's tenant ownership column
        return Laravilt::getTenantOwnershipColumn();
    }

    /**
     * Get the tenant relationship name for this resource.
     */
    public static function getTenantRelationshipName(): ?string
    {
        return static::$tenantRelationshipName;
    }

    /**
     * Associate a record with the current tenant on creation.
     */
    public static function associateRecordWithTenant(Model $record): void
    {
        if (! static::isScopedToTenant() || ! Laravilt::isTenancyEnabled() || ! Laravilt::hasTenant()) {
            return;
        }

        $tenantId = Laravilt::getTenantId();
        $ownershipColumn = static::getTenantOwnershipColumn();

        // Check for direct column (team_id) - always set if column exists and value is empty
        if ($ownershipColumn && static::modelHasColumn($ownershipColumn)) {
            $currentValue = $record->{$ownershipColumn};
            // Set tenant if value is null, empty string, or not set
            if (empty($currentValue) && $currentValue !== 0) {
                $record->{$ownershipColumn} = $tenantId;
            }
        }
    }

    /**
     * Associate a record with the current tenant via many-to-many relationship.
     * Call this after the record is saved.
     */
    public static function associateRecordWithTenantManyToMany(Model $record): void
    {
        if (! static::isScopedToTenant() || ! Laravilt::isTenancyEnabled() || ! Laravilt::hasTenant()) {
            return;
        }

        if (static::hasTenantManyToManyRelationship()) {
            $relationshipName = static::getTenantManyToManyRelationshipName();
            $tenantId = Laravilt::getTenantId();

            // Always attach current tenant if not already attached
            if (! $record->{$relationshipName}()->where('teams.id', $tenantId)->exists()) {
                $record->{$relationshipName}()->attach($tenantId);
            }
        }
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
