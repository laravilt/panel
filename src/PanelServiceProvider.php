<?php

namespace Laravilt\Panel;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravilt\Panel\Events\TenantCreated;
use Laravilt\Panel\Events\TenantDatabaseCreated;
use Laravilt\Panel\Events\TenantDeleted;
use Laravilt\Panel\Events\TenantMigrated;
use Laravilt\Panel\Listeners\CreateTenantDatabaseListener;
use Laravilt\Panel\Listeners\DeleteTenantDatabaseListener;
use Laravilt\Panel\Listeners\MigrateTenantDatabaseListener;
use Laravilt\Panel\Listeners\SeedTenantDatabaseListener;
use Laravilt\Panel\Middleware\IdentifyPanel;
use Laravilt\Panel\Tenancy\MultiDatabaseManager;

class PanelServiceProvider extends ServiceProvider
{
    /**
     * Register any panel services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravilt-panel.php',
            'laravilt.panel'
        );

        // Merge tenancy config
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravilt-tenancy.php',
            'laravilt-tenancy'
        );

        $this->app->singleton(PanelRegistry::class);
        $this->app->singleton(TenantManager::class);

        // Register MultiDatabaseManager as singleton
        $this->app->singleton(MultiDatabaseManager::class, function ($app) {
            return new MultiDatabaseManager($app['db']);
        });
    }

    /**
     * Bootstrap any panel services.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'laravilt-panel');

        // Also register translations with 'panel' alias for convenience
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'panel');

        $this->publishes([
            __DIR__.'/../config/laravilt-panel.php' => config_path('laravilt/panel.php'),
        ], 'laravilt-panel-config');

        // Publish tenancy config
        $this->publishes([
            __DIR__.'/../config/laravilt-tenancy.php' => config_path('laravilt-tenancy.php'),
        ], 'laravilt-tenancy-config');

        // Publish tenancy migrations
        $this->publishes([
            __DIR__.'/../database/migrations/create_tenants_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_tenants_table.php'),
            __DIR__.'/../database/migrations/create_domains_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time() + 1).'_create_domains_table.php'),
            __DIR__.'/../database/migrations/create_tenant_users_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time() + 2).'_create_tenant_users_table.php'),
        ], 'laravilt-tenancy-migrations');

        // Register multi-database tenancy event listeners
        $this->registerTenancyEventListeners();

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/laravilt-panel'),
        ], 'laravilt-panel-lang');

        // Publish frontend views/pages
        $this->publishes([
            __DIR__.'/../resources/js/pages/laravilt' => resource_path('js/pages/laravilt'),
        ], 'laravilt-panel-views');

        // Publish frontend components
        $this->publishes([
            __DIR__.'/../resources/js/components' => resource_path('js/components/laravilt'),
        ], 'laravilt-panel-assets');

        // Publish UI components (Reka UI/Radix Vue primitives)
        $this->publishes([
            __DIR__.'/../stubs/ui' => resource_path('js/components/ui'),
        ], 'laravilt-panel-ui');

        // Publish lib utilities (utils.ts with urlIsActive, etc.)
        $this->publishes([
            __DIR__.'/../stubs/lib' => resource_path('js/lib'),
        ], 'laravilt-panel-lib');

        // Publish NavMain component
        $this->publishes([
            __DIR__.'/../stubs/components/NavMain.vue.stub' => resource_path('js/components/NavMain.vue'),
        ], 'laravilt-panel-components');

        // Publish Teams migration (for teams-based tenancy)
        $this->publishes([
            __DIR__.'/../stubs/migrations/create_teams_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_teams_table.php'),
        ], 'laravilt-teams-migration');

        // Publish Team model and HasTeams trait (for teams-based tenancy)
        $this->publishes([
            __DIR__.'/../stubs/Models/Team.php.stub' => app_path('Models/Team.php'),
        ], 'laravilt-teams-model');

        $this->publishes([
            __DIR__.'/../stubs/Concerns/HasTeams.php.stub' => app_path('Concerns/HasTeams.php'),
        ], 'laravilt-teams-trait');

        // Register panel's custom Authenticate middleware as 'panel.auth' alias
        $router = $this->app->make(\Illuminate\Routing\Router::class);
        $router->aliasMiddleware('panel.auth', Http\Middleware\Authenticate::class);

        // Configure middleware priority to ensure IdentifyPanel runs BEFORE auth middleware
        // Laravel's middleware priority reorders middleware, so we must add IdentifyPanel
        // to the priority list before AuthenticatesRequests to ensure correct panel context
        $this->configureMiddlewarePriority($router);

        $this->registerRoutes();
        $this->registerCommands();
    }

    /**
     * Configure middleware priority to ensure IdentifyPanel runs before auth middleware.
     *
     * Laravel's middleware priority system can reorder middleware even if they're
     * specified in a certain order in routes. This ensures IdentifyPanel always
     * runs before any auth middleware so the panel context is set correctly.
     */
    protected function configureMiddlewarePriority(\Illuminate\Routing\Router $router): void
    {
        $router->middlewarePriority = [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Laravilt\Panel\Middleware\IdentifyPanel::class, // Must come BEFORE AuthenticatesRequests
            \Laravilt\Panel\Middleware\InitializeTenancyBySubdomain::class, // Multi-db tenancy initialization
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ];

        // Register middleware alias for multi-database tenancy
        $router->aliasMiddleware('tenancy.subdomain', Middleware\InitializeTenancyBySubdomain::class);
        $router->aliasMiddleware('tenancy.central', Middleware\PreventAccessFromCentralDomains::class);
    }

    /**
     * Register panel routes.
     */
    protected function registerRoutes(): void
    {
        $this->app->booted(function () {
            $registry = app(PanelRegistry::class);

            foreach ($registry->all() as $panel) {
                // Register API routes FIRST with Sanctum middleware (outside panel middleware group)
                // This allows token-based authentication to work
                foreach ($panel->getResources() as $resourceClass) {
                    if ($resourceClass::hasApi()) {
                        $this->registerResourceApiRoutes($resourceClass, $panel);
                    }
                }

                // Then register panel web routes with session auth
                // IMPORTANT: IdentifyPanel must come BEFORE auth middleware so redirectTo() knows the current panel
                $middleware = $panel->getMiddleware();
                $authMiddleware = $panel->getAuthMiddleware();

                // Replace 'auth' with 'panel.auth' to use panel's custom Authenticate middleware
                $middleware = array_map(fn ($m) => $m === 'auth' ? 'panel.auth' : $m, $middleware);
                $authMiddleware = array_map(fn ($m) => $m === 'auth' ? 'panel.auth' : $m, $authMiddleware);

                // Remove panel.auth from middleware array to ensure correct order
                $middlewareWithoutAuth = array_filter($middleware, fn ($m) => $m !== 'panel.auth');

                // Build middleware stack
                // For multi-database panels, InitializeTenancyBySubdomain must come BEFORE
                // IdentifyTenant and SharePanelData because those may query the database
                $tenancyMiddleware = $panel->isMultiDatabaseTenancy()
                    ? [Middleware\InitializeTenancyBySubdomain::class]
                    : [];

                $routeMiddleware = array_merge(
                    $middlewareWithoutAuth,
                    [IdentifyPanel::class.':'.$panel->getId()],
                    $authMiddleware,
                    $tenancyMiddleware, // Must come after auth but before tenant identification
                    [Http\Middleware\HandleLocalization::class],
                    [Middleware\IdentifyTenant::class],
                    [Http\Middleware\SharePanelData::class]
                );

                // For multi-database panels, register subdomain-based routes
                if ($panel->isMultiDatabaseTenancy()) {
                    // Register subdomain-based routes for multi-database tenancy
                    $this->registerMultiDbPanelRoutes($panel, $routeMiddleware);
                }

                // Always register path-based routes (for central access and single-db mode)
                Route::middleware($routeMiddleware)
                    ->prefix($panel->getPath())
                    ->name($panel->getId().'.')
                    ->group(function () use ($panel) {
                        // Register tenant routes if tenancy is enabled
                        if ($panel->hasTenancy()) {
                            $this->registerTenantRoutes($panel);
                        }
                        // Register Select options search endpoint
                        Route::get('_select/search', [Http\Controllers\SelectOptionsController::class, 'search'])
                            ->name('select.search');
                        Route::get('_select/options', [Http\Controllers\SelectOptionsController::class, 'getOptions'])
                            ->name('select.options');
                        // Register cluster routes first (for pages that belong to clusters)
                        foreach ($panel->getClusters() as $clusterClass) {
                            $this->registerCustomClusterRoutes($clusterClass, $panel);
                        }

                        // Register page routes (pages without clusters)
                        foreach ($panel->getPages() as $pageClass) {
                            $this->registerPageRoute($pageClass, $panel);
                        }

                        // Register resource routes (without API, those are registered above)
                        foreach ($panel->getResources() as $resourceClass) {
                            $this->registerResourceRoutes($resourceClass, $panel, false);
                        }
                    });
            }

            // Register global /login route that redirects to default panel
            $this->registerDefaultLoginRoute($registry);

            // Register invitation routes (global, signed URLs)
            $this->registerInvitationRoutes();
        });
    }

    /**
     * Register subdomain-based routes for multi-database tenancy panels.
     */
    protected function registerMultiDbPanelRoutes(Panel $panel, array $middleware): void
    {
        $domain = $panel->getTenantDomain();

        if (! $domain) {
            return;
        }

        // Register routes with subdomain matching: {tenant}.domain.com
        // Use 'subdomain' prefix to avoid conflicts with central tenant management routes
        // e.g., admin.subdomain.settings.profile instead of admin.tenant.settings.profile
        Route::middleware($middleware)
            ->domain('{tenant}.'.$domain)
            ->prefix($panel->getPath())
            ->name($panel->getId().'.subdomain.')
            ->group(function () use ($panel) {
                // Register tenant routes if tenancy is enabled
                if ($panel->hasTenancy()) {
                    $this->registerTenantRoutes($panel);
                }

                // Register Select options search endpoint
                Route::get('_select/search', [Http\Controllers\SelectOptionsController::class, 'search'])
                    ->name('select.search');
                Route::get('_select/options', [Http\Controllers\SelectOptionsController::class, 'getOptions'])
                    ->name('select.options');

                // Register cluster routes first (for pages that belong to clusters)
                foreach ($panel->getClusters() as $clusterClass) {
                    $this->registerCustomClusterRoutes($clusterClass, $panel);
                }

                // Register auth cluster routes (Settings cluster from laravilt/auth)
                // These are normally registered in HasAuth but only for central domain
                $this->registerAuthClusterRoutesForSubdomain($panel);

                // Register page routes (pages without clusters)
                foreach ($panel->getPages() as $pageClass) {
                    $this->registerPageRoute($pageClass, $panel);
                }

                // Register resource routes (without API, those are registered above)
                foreach ($panel->getResources() as $resourceClass) {
                    $this->registerResourceRoutes($resourceClass, $panel, false);
                }
            });
    }

    /**
     * Register global invitation routes for accept/decline team invitations.
     */
    protected function registerInvitationRoutes(): void
    {
        Route::middleware(['web'])
            ->group(function () {
                Route::get('invitation/{team}/{user}/{panel}/accept', [Http\Controllers\InvitationController::class, 'accept'])
                    ->name('laravilt.invitation.accept');

                Route::get('invitation/{team}/{user}/{panel}/decline', [Http\Controllers\InvitationController::class, 'decline'])
                    ->name('laravilt.invitation.decline');
            });
    }

    /**
     * Register a global /login route that redirects to the default panel's login.
     */
    protected function registerDefaultLoginRoute(PanelRegistry $registry): void
    {
        $defaultPanel = $registry->getDefault();

        if ($defaultPanel && $defaultPanel->hasLogin()) {
            Route::get('login', function () use ($defaultPanel) {
                return redirect()->route($defaultPanel->getId().'.login');
            })->name('login');
        }
    }

    /**
     * Register routes for a resource.
     */
    protected function registerResourceRoutes(string $resourceClass, Panel $panel, bool $registerApi = true): void
    {
        $slug = $resourceClass::getSlug();
        $pages = $resourceClass::getPages();
        $modelClass = $resourceClass::getModel();

        // Check if this is a simple resource (uses ManageRecords page)
        $isSimpleResource = false;
        foreach ($pages as $pageName => $pageConfig) {
            if (is_subclass_of($pageConfig['class'], \Laravilt\Panel\Pages\ManageRecords::class)) {
                $isSimpleResource = true;
                break;
            }
        }

        // Register each resource page
        foreach ($pages as $pageName => $pageConfig) {
            $pageClass = $pageConfig['class'];
            $pagePath = ltrim($pageConfig['path'], '/');

            // Build the full path for the resource page
            $fullPath = $pagePath ? $slug.'/'.$pagePath : $slug;

            // Build the route name
            $routeName = 'resources.'.$slug.'.'.$pageName;

            // Register the page route (same as registerPageRoute but for resource pages)
            $this->registerPageRoute($pageClass, $panel, $routeName, $fullPath);
        }

        // Register simple resource CRUD routes if this is a ManageRecords resource
        if ($isSimpleResource) {
            $this->registerSimpleResourceRoutes($resourceClass, $slug, $modelClass, $panel);
        }

        // Register column update route for editable columns (ToggleColumn, etc.)
        $this->registerColumnUpdateRoute($resourceClass, $slug, $modelClass, $panel);

        // Register reorder route for tables with reorderable enabled
        $this->registerReorderRoute($resourceClass, $slug, $modelClass, $panel);

        // Register relation manager routes
        $this->registerRelationManagerRoutes($resourceClass, $slug, $modelClass, $panel);

        // Register API routes if resource has API enabled (only if not already registered separately)
        if ($registerApi && $resourceClass::hasApi()) {
            $this->registerResourceApiRoutes($resourceClass, $panel);
        }
    }

    /**
     * Register CRUD routes for simple resources (ManageRecords).
     */
    protected function registerSimpleResourceRoutes(string $resourceClass, string $slug, string $modelClass, Panel $panel): void
    {
        // GET /{slug}/data - AJAX fetch records (handled by ManageRecords::index)
        // Using /data suffix to avoid overwriting the main page route
        Route::get($slug.'/data', function () use ($resourceClass) {
            $pages = $resourceClass::getPages();
            foreach ($pages as $pageConfig) {
                if (is_subclass_of($pageConfig['class'], \Laravilt\Panel\Pages\ManageRecords::class)) {
                    $page = app($pageConfig['class']);

                    return $page->index(request());
                }
            }

            return response()->json(['error' => 'Page not found'], 404);
        })->name('resources.'.$slug.'.data');

        // POST /{slug} - Create record
        Route::post($slug, function () use ($resourceClass) {
            $pages = $resourceClass::getPages();
            foreach ($pages as $pageConfig) {
                if (is_subclass_of($pageConfig['class'], \Laravilt\Panel\Pages\ManageRecords::class)) {
                    $page = app($pageConfig['class']);

                    return $page->store(request());
                }
            }

            return response()->json(['error' => 'Page not found'], 404);
        })->name('resources.'.$slug.'.store');

        // GET /{slug}/{id} - Get single record for view/edit modal
        Route::get($slug.'/{id}', function () use ($resourceClass) {
            // Use named route parameter to handle subdomain routes where {tenant} is also a parameter
            $id = request()->route('id');
            $pages = $resourceClass::getPages();
            foreach ($pages as $pageConfig) {
                if (is_subclass_of($pageConfig['class'], \Laravilt\Panel\Pages\ManageRecords::class)) {
                    $page = app($pageConfig['class']);

                    // For AJAX requests (non-Inertia), return JSON data
                    if (request()->wantsJson() && ! request()->header('X-Inertia')) {
                        return $page->show(request(), $id);
                    }

                    // For Inertia/browser requests, render the page with record data
                    // This allows direct URL access like /admin/customer/5
                    return $page->showRecord(request(), $id);
                }
            }

            return response()->json(['error' => 'Page not found'], 404);
        })->name('resources.'.$slug.'.show');

        // PUT /{slug}/{id} - Update record
        Route::put($slug.'/{id}', function () use ($resourceClass) {
            // Use named route parameter to handle subdomain routes where {tenant} is also a parameter
            $id = request()->route('id');
            $pages = $resourceClass::getPages();
            foreach ($pages as $pageConfig) {
                if (is_subclass_of($pageConfig['class'], \Laravilt\Panel\Pages\ManageRecords::class)) {
                    $page = app($pageConfig['class']);

                    return $page->update(request(), $id);
                }
            }

            return response()->json(['error' => 'Page not found'], 404);
        })->name('resources.'.$slug.'.update');

        // DELETE /{slug}/{id} - Delete record
        Route::delete($slug.'/{id}', function () use ($resourceClass) {
            // Use named route parameter to handle subdomain routes where {tenant} is also a parameter
            $id = request()->route('id');
            $pages = $resourceClass::getPages();
            foreach ($pages as $pageConfig) {
                if (is_subclass_of($pageConfig['class'], \Laravilt\Panel\Pages\ManageRecords::class)) {
                    $page = app($pageConfig['class']);

                    return $page->destroy(request(), $id);
                }
            }

            return response()->json(['error' => 'Page not found'], 404);
        })->name('resources.'.$slug.'.destroy');

        // POST /{slug}/bulk-delete - Bulk delete records
        Route::post($slug.'/bulk-delete', function () use ($resourceClass) {
            $pages = $resourceClass::getPages();
            foreach ($pages as $pageConfig) {
                if (is_subclass_of($pageConfig['class'], \Laravilt\Panel\Pages\ManageRecords::class)) {
                    $page = app($pageConfig['class']);

                    return $page->bulkDelete(request());
                }
            }

            return response()->json(['error' => 'Page not found'], 404);
        })->name('resources.'.$slug.'.bulk-delete');
    }

    /**
     * Register route for updating individual column values (used by ToggleColumn, etc.)
     */
    protected function registerColumnUpdateRoute(string $resourceClass, string $slug, string $modelClass, Panel $panel): void
    {
        Route::patch($slug.'/{id}/column', function () use ($resourceClass, $modelClass) {
            // Use named route parameter to handle subdomain routes where {tenant} is also a parameter
            $id = request()->route('id');
            $record = $modelClass::findOrFail($id);
            $column = request()->input('column');
            $value = request()->input('value');

            // Validate input
            if (empty($column)) {
                return back()->withErrors(['column' => 'Column name is required.']);
            }

            // Get the table configuration to find the column and its callbacks
            $table = new \Laravilt\Tables\Table;
            $table = $resourceClass::table($table);
            $columns = $table->getColumns();

            // Find the column configuration
            $columnConfig = null;
            foreach ($columns as $col) {
                if ($col->getName() === $column) {
                    $columnConfig = $col;
                    break;
                }
            }

            // Check if column exists and is editable
            if (! $columnConfig) {
                return back()->withErrors([$column => 'Column not found.']);
            }

            // Execute beforeStateUpdated callback if exists
            if ($columnConfig instanceof \Laravilt\Tables\Columns\ToggleColumn) {
                $beforeCallback = $columnConfig->getBeforeStateUpdated();
                if ($beforeCallback) {
                    $beforeCallback($record, $column, $value);
                }
            }

            // Update the record
            $record->update([$column => $value]);

            // Execute afterStateUpdated callback if exists
            if ($columnConfig instanceof \Laravilt\Tables\Columns\ToggleColumn) {
                $afterCallback = $columnConfig->getAfterStateUpdated();
                if ($afterCallback) {
                    $afterCallback($record, $column, $value);
                }
            }

            return back();
        })->name('resources.'.$slug.'.column.update');
    }

    /**
     * Register route for reordering records (used by tables with ->reorderable())
     */
    protected function registerReorderRoute(string $resourceClass, string $slug, string $modelClass, Panel $panel): void
    {
        Route::post($slug.'/reorder', function () use ($modelClass) {
            $items = request()->input('items', []);
            $column = request()->input('column', 'sort_order');

            if (empty($items)) {
                return response()->json(['error' => 'No items provided'], 400);
            }

            // Validate the column name (only allow alphanumeric and underscores)
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
                return response()->json(['error' => 'Invalid column name'], 400);
            }

            // Update each record's order
            foreach ($items as $item) {
                if (isset($item['id']) && isset($item['order'])) {
                    $modelClass::where('id', $item['id'])->update([$column => $item['order']]);
                }
            }

            // For AJAX requests, return JSON with notification data (don't use session flash)
            if (request()->wantsJson() && ! request()->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'message' => __('notifications::notifications.records_reordered', ['count' => count($items)]),
                    'reordered_count' => count($items),
                ]);
            }

            // For Inertia/full page requests, use session flash notification
            \Laravilt\Notifications\Notification::success()
                ->title(__('notifications::notifications.success'))
                ->body(__('notifications::notifications.records_reordered', ['count' => count($items)]))
                ->send();

            return back();
        })->name('resources.'.$slug.'.reorder');
    }

    /**
     * Register relation manager routes for a resource.
     */
    protected function registerRelationManagerRoutes(string $resourceClass, string $slug, string $modelClass, Panel $panel): void
    {
        $relationManagers = $resourceClass::getRelations();

        if (empty($relationManagers)) {
            return;
        }

        // Route: GET /{slug}/{id}/relations/{relationship}
        Route::get($slug.'/{id}/relations/{relationship}', function () use ($resourceClass, $modelClass) {
            // Use named route parameters to handle subdomain routes where {tenant} is also a parameter
            $id = request()->route('id');
            $relationship = request()->route('relationship');
            $record = $modelClass::findOrFail($id);

            // Find the relation manager class
            $relationManagers = $resourceClass::getRelations();
            $relationManagerClass = null;

            foreach ($relationManagers as $rmClass) {
                if ($rmClass::getRelationship() === $relationship) {
                    $relationManagerClass = $rmClass;
                    break;
                }
            }

            if (! $relationManagerClass) {
                return response()->json(['error' => 'Relation manager not found'], 404);
            }

            // Get the relationship query
            $relationQuery = $record->{$relationship}();

            // Apply pagination
            $perPage = request('per_page', 10);
            $page = request('page', 1);

            // Apply sorting if provided
            $sortColumn = request('sort');
            $sortDirection = request('direction', 'asc');

            if ($sortColumn) {
                $relationQuery->orderBy($sortColumn, $sortDirection);
            }

            // Get paginated results
            $paginated = $relationQuery->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'data' => $paginated->items(),
                'pagination' => [
                    'total' => $paginated->total(),
                    'per_page' => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'from' => $paginated->firstItem() ?? 0,
                    'to' => $paginated->lastItem() ?? 0,
                ],
            ]);
        })->name('resources.'.$slug.'.relations');

        // Route: POST /{slug}/{id}/relations/{relationship} - Create related record
        Route::post($slug.'/{id}/relations/{relationship}', function () use ($resourceClass, $modelClass) {
            // Use named route parameters to handle subdomain routes where {tenant} is also a parameter
            $id = request()->route('id');
            $relationship = request()->route('relationship');
            $record = $modelClass::findOrFail($id);

            // Find the relation manager class
            $relationManagers = $resourceClass::getRelations();
            $relationManagerClass = null;

            foreach ($relationManagers as $rmClass) {
                if ($rmClass::getRelationship() === $relationship) {
                    $relationManagerClass = $rmClass;
                    break;
                }
            }

            if (! $relationManagerClass) {
                if (request()->wantsJson()) {
                    return response()->json(['error' => 'Relation manager not found'], 404);
                }

                return back()->withErrors(['error' => 'Relation manager not found']);
            }

            // Get the relationship and create the new record
            $data = request()->all();
            $newRecord = $record->{$relationship}()->create($data);

            // Send success notification
            \Laravilt\Notifications\Notification::success()
                ->title(__('notifications::notifications.success'))
                ->body(__('notifications::notifications.record_created'))
                ->send();

            // Return JSON for AJAX requests, redirect for Inertia
            if (request()->wantsJson() && ! request()->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'data' => $newRecord,
                ]);
            }

            return back();
        })->name('resources.'.$slug.'.relations.create');

        // Route: PUT /{slug}/{id}/relations/{relationship}/{relationId} - Update related record
        Route::put($slug.'/{id}/relations/{relationship}/{relationId}', function () use ($resourceClass, $modelClass) {
            // Use named route parameters to handle subdomain routes where {tenant} is also a parameter
            $id = request()->route('id');
            $relationship = request()->route('relationship');
            $relationId = request()->route('relationId');
            $record = $modelClass::findOrFail($id);

            // Find the relation manager class
            $relationManagers = $resourceClass::getRelations();
            $relationManagerClass = null;

            foreach ($relationManagers as $rmClass) {
                if ($rmClass::getRelationship() === $relationship) {
                    $relationManagerClass = $rmClass;
                    break;
                }
            }

            if (! $relationManagerClass) {
                if (request()->wantsJson()) {
                    return response()->json(['error' => 'Relation manager not found'], 404);
                }

                return back()->withErrors(['error' => 'Relation manager not found']);
            }

            // Find and update the related record
            $relatedRecord = $record->{$relationship}()->findOrFail($relationId);
            $relatedRecord->update(request()->all());

            // Send success notification
            \Laravilt\Notifications\Notification::success()
                ->title(__('notifications::notifications.success'))
                ->body(__('notifications::notifications.record_updated'))
                ->send();

            // Return JSON for AJAX requests, redirect for Inertia
            if (request()->wantsJson() && ! request()->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'data' => $relatedRecord,
                ]);
            }

            return back();
        })->name('resources.'.$slug.'.relations.update');

        // Route: DELETE /{slug}/{id}/relations/{relationship}/{relationId} - Delete related record
        Route::delete($slug.'/{id}/relations/{relationship}/{relationId}', function () use ($resourceClass, $modelClass) {
            // Use named route parameters to handle subdomain routes where {tenant} is also a parameter
            $id = request()->route('id');
            $relationship = request()->route('relationship');
            $relationId = request()->route('relationId');
            $record = $modelClass::findOrFail($id);

            // Find the relation manager class
            $relationManagers = $resourceClass::getRelations();
            $relationManagerClass = null;

            foreach ($relationManagers as $rmClass) {
                if ($rmClass::getRelationship() === $relationship) {
                    $relationManagerClass = $rmClass;
                    break;
                }
            }

            if (! $relationManagerClass) {
                if (request()->wantsJson()) {
                    return response()->json(['error' => 'Relation manager not found'], 404);
                }

                return back()->withErrors(['error' => 'Relation manager not found']);
            }

            // Find and delete the related record
            $relatedRecord = $record->{$relationship}()->findOrFail($relationId);
            $relatedRecord->delete();

            // Send success notification
            \Laravilt\Notifications\Notification::success()
                ->title(__('notifications::notifications.success'))
                ->body(__('notifications::notifications.record_deleted'))
                ->send();

            // Return JSON for AJAX requests, redirect for Inertia
            if (request()->wantsJson() && ! request()->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                ]);
            }

            return back();
        })->name('resources.'.$slug.'.relations.delete');

        // Route: POST /{slug}/{id}/relations/{relationship}/bulk-delete - Bulk delete related records
        Route::post($slug.'/{id}/relations/{relationship}/bulk-delete', function () use ($resourceClass, $modelClass) {
            // Use named route parameters to handle subdomain routes where {tenant} is also a parameter
            $id = request()->route('id');
            $relationship = request()->route('relationship');
            $record = $modelClass::findOrFail($id);

            // Find the relation manager class
            $relationManagers = $resourceClass::getRelations();
            $relationManagerClass = null;

            foreach ($relationManagers as $rmClass) {
                if ($rmClass::getRelationship() === $relationship) {
                    $relationManagerClass = $rmClass;
                    break;
                }
            }

            if (! $relationManagerClass) {
                if (request()->wantsJson()) {
                    return response()->json(['error' => 'Relation manager not found'], 404);
                }

                return back()->withErrors(['error' => 'Relation manager not found']);
            }

            // Get IDs to delete
            $ids = request()->input('ids', []);
            if (empty($ids)) {
                if (request()->wantsJson()) {
                    return response()->json(['error' => 'No records selected'], 400);
                }

                return back()->withErrors(['error' => 'No records selected']);
            }

            // Delete the related records
            $deletedCount = $record->{$relationship}()->whereIn('id', $ids)->delete();

            // Send success notification
            \Laravilt\Notifications\Notification::success()
                ->title(__('notifications::notifications.success'))
                ->body(__('notifications::notifications.records_deleted', ['count' => $deletedCount]))
                ->send();

            // Return JSON for AJAX requests, redirect for Inertia
            if (request()->wantsJson() && ! request()->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'deleted_count' => $deletedCount,
                ]);
            }

            return back();
        })->name('resources.'.$slug.'.relations.bulk-delete');

        // Route: PATCH /{slug}/{id}/relations/{relationship}/{relationId}/column - Update single column (for toggle columns)
        Route::patch($slug.'/{id}/relations/{relationship}/{relationId}/column', function () use ($resourceClass, $modelClass) {
            // Use named route parameters to handle subdomain routes where {tenant} is also a parameter
            $id = request()->route('id');
            $relationship = request()->route('relationship');
            $relationId = request()->route('relationId');
            $record = $modelClass::findOrFail($id);

            // Find the relation manager class
            $relationManagers = $resourceClass::getRelations();
            $relationManagerClass = null;

            foreach ($relationManagers as $rmClass) {
                if ($rmClass::getRelationship() === $relationship) {
                    $relationManagerClass = $rmClass;
                    break;
                }
            }

            if (! $relationManagerClass) {
                if (request()->wantsJson()) {
                    return response()->json(['error' => 'Relation manager not found'], 404);
                }

                return back()->withErrors(['error' => 'Relation manager not found']);
            }

            // Find and update the related record's column
            $relatedRecord = $record->{$relationship}()->findOrFail($relationId);
            $column = request()->input('column');
            $value = request()->input('value');

            if ($column) {
                $relatedRecord->update([$column => $value]);
            }

            // Return JSON for AJAX requests, redirect for Inertia
            if (request()->wantsJson() && ! request()->header('X-Inertia')) {
                return response()->json([
                    'success' => true,
                    'data' => $relatedRecord,
                ]);
            }

            return back();
        })->name('resources.'.$slug.'.relations.column');
    }

    /**
     * Register API routes for a resource.
     * These routes support both session auth (for testing in browser) and Sanctum token auth.
     */
    protected function registerResourceApiRoutes(string $resourceClass, Panel $panel): void
    {
        $slug = $resourceClass::getSlug();
        $modelClass = $resourceClass::getModel();
        $panelPath = $panel->getPath();

        // API routes are prefixed with 'api/' under the panel path
        // Full path will be: /{panel}/api/{resource}
        $apiPrefix = $panelPath.'/api/'.$slug;
        $routeNamePrefix = $panel->getId().'.api.'.$slug;

        // Get the API resource to check operation configurations
        $apiResource = $resourceClass::getApiResource();

        // Register each operation with its specific middleware
        $this->registerApiEndpoints($apiPrefix, $routeNamePrefix, $resourceClass, $modelClass, $apiResource);
    }

    /**
     * Register the actual API endpoints.
     */
    protected function registerApiEndpoints(string $apiPrefix, string $routeNamePrefix, string $resourceClass, string $modelClass, ?\Laravilt\Tables\ApiResource $apiResource = null): void
    {
        // Helper to get middleware for an operation
        $getMiddleware = function (string $operation) use ($apiResource): array {
            if ($apiResource) {
                return $apiResource->getOperationMiddleware($operation);
            }

            return ['auth:sanctum'];
        };

        // Helper to check if operation is enabled
        $isEnabled = function (string $operation) use ($apiResource): bool {
            if ($apiResource) {
                return $apiResource->isOperationEnabled($operation);
            }

            return true;
        };

        // Index - GET /api/{resource}
        if ($isEnabled('list')) {
            Route::middleware($getMiddleware('list'))->get($apiPrefix, function () use ($resourceClass, $modelClass) {
                $query = $modelClass::query();

                // Apply search if provided
                if ($search = request('search')) {
                    $apiResource = $resourceClass::getApiResource();
                    if ($apiResource) {
                        $searchableColumns = $apiResource->getSearchableColumns();
                        if (! empty($searchableColumns)) {
                            $query->where(function ($q) use ($searchableColumns, $search) {
                                foreach ($searchableColumns as $column) {
                                    $q->orWhere($column, 'like', '%'.$search.'%');
                                }
                            });
                        }
                    }
                }

                // Apply sorting if provided
                if ($sort = request('sort')) {
                    $direction = request('direction', 'asc');
                    // Validate sort direction
                    if (! in_array($direction, ['asc', 'desc'])) {
                        $direction = 'asc';
                    }
                    $query->orderBy($sort, $direction);
                }

                // Apply filters
                $apiResource = $resourceClass::getApiResource();
                if ($apiResource) {
                    $allowedFilters = $apiResource->getAllowedFilters();
                    foreach ($allowedFilters as $filter) {
                        if (request()->has($filter)) {
                            $query->where($filter, request($filter));
                        }
                    }

                    // Load relationships
                    $relationships = $apiResource->getRelationships();
                    if (! empty($relationships)) {
                        $query->with($relationships);
                    }
                }

                // Paginate
                $perPage = request('per_page', 15);
                $records = $query->paginate($perPage);

                // Transform records using API resource
                if ($apiResource) {
                    $transformedRecords = $apiResource->transformRecords($records->items());

                    return response()->json([
                        'data' => $transformedRecords,
                        'meta' => [
                            'current_page' => $records->currentPage(),
                            'last_page' => $records->lastPage(),
                            'per_page' => $records->perPage(),
                            'total' => $records->total(),
                            'from' => $records->firstItem(),
                            'to' => $records->lastItem(),
                        ],
                        'links' => [
                            'first' => $records->url(1),
                            'last' => $records->url($records->lastPage()),
                            'prev' => $records->previousPageUrl(),
                            'next' => $records->nextPageUrl(),
                        ],
                    ]);
                }

                return response()->json($records);
            })->name($routeNamePrefix.'.index');
        }

        // Show - GET /api/{resource}/{id}
        if ($isEnabled('show')) {
            Route::middleware($getMiddleware('show'))->get($apiPrefix.'/{id}', function ($id) use ($resourceClass, $modelClass) {
                $record = $modelClass::findOrFail($id);

                $apiResource = $resourceClass::getApiResource();
                if ($apiResource) {
                    // Load relationships
                    $relationships = $apiResource->getRelationships();
                    if (! empty($relationships)) {
                        $record->load($relationships);
                    }

                    return response()->json([
                        'data' => $apiResource->transformRecord($record),
                    ]);
                }

                return response()->json(['data' => $record]);
            })->name($routeNamePrefix.'.show');
        }

        // Store - POST /api/{resource}
        if ($isEnabled('create')) {
            Route::middleware($getMiddleware('create'))->post($apiPrefix, function () use ($resourceClass, $modelClass) {
                $apiResource = $resourceClass::getApiResource();
                $data = request()->all();

                // Get fillable fields from API columns
                if ($apiResource) {
                    $fillableFields = $apiResource->getFillableFields();
                    if (! empty($fillableFields)) {
                        $data = array_intersect_key($data, array_flip($fillableFields));
                    }

                    // Validate if validation rules exist
                    $rules = $apiResource->getValidationRules('create');
                    if (! empty($rules)) {
                        request()->validate($rules);
                    }
                }

                $record = $modelClass::create($data);

                if ($apiResource) {
                    return response()->json([
                        'data' => $apiResource->transformRecord($record),
                        'message' => 'Record created successfully.',
                    ], 201);
                }

                return response()->json(['data' => $record, 'message' => 'Record created successfully.'], 201);
            })->name($routeNamePrefix.'.store');
        }

        // Update - PUT/PATCH /api/{resource}/{id}
        if ($isEnabled('update')) {
            Route::middleware($getMiddleware('update'))->match(['put', 'patch'], $apiPrefix.'/{id}', function ($id) use ($resourceClass, $modelClass) {
                $record = $modelClass::findOrFail($id);
                $apiResource = $resourceClass::getApiResource();
                $data = request()->all();

                // Get fillable fields from API columns
                if ($apiResource) {
                    $fillableFields = $apiResource->getFillableFields();
                    if (! empty($fillableFields)) {
                        $data = array_intersect_key($data, array_flip($fillableFields));
                    }

                    // Validate if validation rules exist
                    $rules = $apiResource->getValidationRules('update');
                    if (! empty($rules)) {
                        request()->validate($rules);
                    }
                }

                $record->update($data);

                if ($apiResource) {
                    return response()->json([
                        'data' => $apiResource->transformRecord($record->fresh()),
                        'message' => 'Record updated successfully.',
                    ]);
                }

                return response()->json(['data' => $record->fresh(), 'message' => 'Record updated successfully.']);
            })->name($routeNamePrefix.'.update');
        }

        // Destroy - DELETE /api/{resource}/{id}
        if ($isEnabled('delete')) {
            Route::middleware($getMiddleware('delete'))->delete($apiPrefix.'/{id}', function ($id) use ($modelClass) {
                $record = $modelClass::findOrFail($id);
                $record->delete();

                return response()->json([
                    'message' => 'Record deleted successfully.',
                ]);
            })->name($routeNamePrefix.'.destroy');
        }

        // Bulk Delete - DELETE /api/{resource}
        if ($isEnabled('bulkDelete')) {
            Route::middleware($getMiddleware('bulkDelete'))->delete($apiPrefix, function () use ($modelClass) {
                $ids = request()->input('ids', []);

                if (empty($ids)) {
                    return response()->json(['message' => 'No IDs provided.'], 400);
                }

                $deleted = $modelClass::whereIn('id', $ids)->delete();

                return response()->json([
                    'message' => "{$deleted} record(s) deleted successfully.",
                    'deleted_count' => $deleted,
                ]);
            })->name($routeNamePrefix.'.bulk-destroy');
        }

        // Register custom actions (these use their own middleware from ApiAction)
        if ($apiResource) {
            $this->registerCustomActionRoutes($apiResource, $apiPrefix, $routeNamePrefix, $modelClass);
        }

        // OpenAPI spec endpoint
        Route::get($apiPrefix.'/openapi.json', function () use ($resourceClass) {
            $apiResource = $resourceClass::getApiResource();
            if (! $apiResource) {
                return response()->json(['error' => 'API not configured'], 404);
            }

            return response()->json($apiResource->toOpenApi())
                ->header('Content-Type', 'application/json');
        })->name($routeNamePrefix.'.openapi-json');

        Route::get($apiPrefix.'/openapi.yaml', function () use ($resourceClass) {
            $apiResource = $resourceClass::getApiResource();
            if (! $apiResource) {
                return response('API not configured', 404);
            }

            return response($apiResource->toOpenApiYaml())
                ->header('Content-Type', 'text/yaml')
                ->header('Content-Disposition', 'attachment; filename="openapi.yaml"');
        })->name($routeNamePrefix.'.openapi-yaml');
    }

    /**
     * Register custom action routes for a resource.
     */
    protected function registerCustomActionRoutes(
        \Laravilt\Tables\ApiResource $apiResource,
        string $apiPrefix,
        string $routeNamePrefix,
        string $modelClass
    ): void {
        foreach ($apiResource->getActions() as $action) {
            $slug = $action->getSlug();
            $method = strtolower($action->getMethod());

            if ($action->doesRequireRecord()) {
                // Single record action: /api/{resource}/{id}/actions/{action}
                Route::match([$method], $apiPrefix.'/{id}/actions/'.$slug, function ($id) use ($action, $modelClass) {
                    $record = $modelClass::findOrFail($id);

                    // Validate if rules exist
                    $rules = $action->getValidationRules();
                    if (! empty($rules)) {
                        request()->validate($rules);
                    }

                    $result = $action->execute($record, request());

                    if ($result instanceof \Illuminate\Http\Response || $result instanceof \Illuminate\Http\JsonResponse) {
                        return $result;
                    }

                    return response()->json([
                        'success' => true,
                        'message' => $action->toInertiaProps()['successMessage'] ?? 'Action executed successfully.',
                        'data' => $result,
                    ]);
                })->name($routeNamePrefix.'.actions.'.$slug);
            } elseif ($action->isBulk()) {
                // Bulk action: /api/{resource}/actions/{action}
                Route::match([$method], $apiPrefix.'/actions/'.$slug, function () use ($action, $modelClass) {
                    $ids = request()->input('ids', []);

                    if (empty($ids)) {
                        return response()->json(['error' => 'No IDs provided.'], 400);
                    }

                    $records = $modelClass::whereIn('id', $ids)->get();

                    // Validate if rules exist
                    $rules = $action->getValidationRules();
                    if (! empty($rules)) {
                        request()->validate($rules);
                    }

                    $results = [];
                    foreach ($records as $record) {
                        $results[] = $action->execute($record, request());
                    }

                    return response()->json([
                        'success' => true,
                        'message' => $action->toInertiaProps()['successMessage'] ?? 'Bulk action executed successfully.',
                        'data' => $results,
                        'processed_count' => count($results),
                    ]);
                })->name($routeNamePrefix.'.actions.'.$slug);
            } else {
                // Collection action (no record): /api/{resource}/actions/{action}
                Route::match([$method], $apiPrefix.'/actions/'.$slug, function () use ($action) {
                    // Validate if rules exist
                    $rules = $action->getValidationRules();
                    if (! empty($rules)) {
                        request()->validate($rules);
                    }

                    $result = $action->execute(null, request());

                    if ($result instanceof \Illuminate\Http\Response || $result instanceof \Illuminate\Http\JsonResponse) {
                        return $result;
                    }

                    return response()->json([
                        'success' => true,
                        'message' => $action->toInertiaProps()['successMessage'] ?? 'Action executed successfully.',
                        'data' => $result,
                    ]);
                })->name($routeNamePrefix.'.actions.'.$slug);
            }
        }
    }

    /**
     * Overload to support resource routes with custom route names and paths.
     */
    protected function registerPageRoute(string $pageClass, Panel $panel, ?string $customRouteName = null, ?string $customPath = null): void
    {
        // Skip pages that belong to a cluster - they are handled by registerClusterRoutes() in HasAuth
        // This prevents route conflicts where the cluster-based route gets overwritten
        if (method_exists($pageClass, 'getCluster') && $pageClass::getCluster() !== null) {
            return;
        }

        $slug = $customPath ?? $pageClass::getSlug();
        $routeName = $customRouteName ?? ($slug ?: 'dashboard');

        Route::get($slug, [$pageClass, 'create'])->name($routeName);

        // Register POST route for form submissions and action execution
        // All pages should support POST for action execution (forms with submit actions)
        Route::post($slug, function (...$parameters) use ($pageClass, $panel) {
            $page = app($pageClass);
            $page->panel($panel);
            $page->boot();
            $page->mount();

            // If page has store method, use it (for backwards compatibility)
            if (method_exists($page, 'store')) {
                return $page->store(request());
            }

            // Otherwise, execute action (for form submissions with ->submit() actions)
            return $page->executeAction(request());
        })->name($routeName.'.store');

        // Register DELETE route if page has destroy method
        if (method_exists($pageClass, 'destroy')) {
            Route::delete($slug.'/{id}', function () use ($pageClass, $panel) {
                // Use named route parameter to handle subdomain routes where {tenant} is also a parameter
                $id = request()->route('id');
                $page = app($pageClass);
                $page->panel($panel);
                $page->boot();
                $page->mount();

                return $page->destroy(request(), $id);
            })->name($routeName.'.destroy');
        }

        // Register DELETE route for destroyAll if page has that method
        if (method_exists($pageClass, 'destroyAll')) {
            Route::post($slug.'/revoke-all', function () use ($pageClass, $panel) {
                $page = app($pageClass);
                $page->panel($panel);
                $page->boot();
                $page->mount();

                return $page->destroyAll(request());
            })->name($routeName.'.destroy-all');
        }

        // Register DELETE route for destroyOthers if page has that method
        if (method_exists($pageClass, 'destroyOthers')) {
            Route::delete($slug.'/logout-others', function () use ($pageClass, $panel) {
                $page = app($pageClass);
                $page->panel($panel);
                $page->boot();
                $page->mount();

                return $page->destroyOthers(request());
            })->name($routeName.'.logout-others');
        }
    }

    /**
     * Register routes for a custom cluster and its pages.
     */
    protected function registerCustomClusterRoutes(string $clusterClass, Panel $panel): void
    {
        $clusterSlug = $clusterClass::getSlug();
        $panelId = $panel->getId();

        // Find all pages that belong to this cluster
        $clusterPages = collect($panel->getPages())
            ->filter(fn ($pageClass) => method_exists($pageClass, 'getCluster') && $pageClass::getCluster() === $clusterClass)
            ->values();

        // Register routes for each page in the cluster
        foreach ($clusterPages as $pageClass) {
            $pageSlug = $pageClass::getSlug();
            $pagePath = "{$clusterSlug}/{$pageSlug}";

            // GET route - use 'create' method for dashboard-style pages
            Route::get($pagePath, [$pageClass, 'create'])
                ->name("{$clusterSlug}.{$pageSlug}");

            // POST route for form submissions
            Route::post($pagePath, function () use ($pageClass, $panel) {
                $page = app($pageClass);
                $page->panel($panel);
                $page->boot();
                $page->mount();

                if (method_exists($page, 'store')) {
                    return $page->store(request());
                }

                return $page->executeAction(request());
            })->name("{$clusterSlug}.{$pageSlug}.store");
        }

        // Register cluster index route (redirect to first page)
        if ($clusterPages->isNotEmpty()) {
            $firstPage = $clusterPages->first();
            $firstPageSlug = $firstPage::getSlug();

            Route::get($clusterSlug, function () use ($panelId, $clusterSlug, $firstPageSlug) {
                return redirect()->route("{$panelId}.{$clusterSlug}.{$firstPageSlug}");
            })->name($clusterSlug);
        }
    }

    /**
     * Register auth cluster routes for subdomain (multi-db tenancy).
     *
     * The Settings cluster and its pages are registered by HasAuth trait for central domain.
     * This method registers the same routes for subdomain access in multi-db mode.
     */
    protected function registerAuthClusterRoutesForSubdomain(Panel $panel): void
    {
        // Find pages that belong to a cluster but are not in $panel->getClusters()
        // These are typically auth pages from laravilt/auth package
        $authClusterPages = collect($panel->getPages())
            ->filter(function ($pageClass) use ($panel) {
                if (! method_exists($pageClass, 'getCluster')) {
                    return false;
                }

                $cluster = $pageClass::getCluster();

                // Only include pages with a cluster that's not already in panel clusters
                return $cluster !== null && ! in_array($cluster, $panel->getClusters());
            })
            ->groupBy(fn ($pageClass) => $pageClass::getCluster());

        // Register routes for each auth cluster
        foreach ($authClusterPages as $clusterClass => $pages) {
            if (! class_exists($clusterClass)) {
                continue;
            }

            $clusterSlug = $clusterClass::getSlug();

            // Register routes for each page in the cluster
            foreach ($pages as $pageClass) {
                $pageSlug = $pageClass::getSlug();
                $pagePath = "{$clusterSlug}/{$pageSlug}";

                // Determine which methods exist on the page
                $reflection = new \ReflectionClass($pageClass);

                // GET route (index, create, or edit)
                if ($reflection->hasMethod('index')) {
                    Route::get($pagePath, [$pageClass, 'index'])
                        ->name("{$clusterSlug}.{$pageSlug}.index");
                } elseif ($reflection->hasMethod('edit')) {
                    Route::get($pagePath, [$pageClass, 'edit'])
                        ->name("{$clusterSlug}.{$pageSlug}.edit");
                } elseif ($reflection->hasMethod('create')) {
                    Route::get($pagePath, [$pageClass, 'create'])
                        ->name("{$clusterSlug}.{$pageSlug}");
                }

                // POST route (store)
                if ($reflection->hasMethod('store')) {
                    Route::post($pagePath, [$pageClass, 'store'])
                        ->name("{$clusterSlug}.{$pageSlug}.store");
                }

                // DELETE route (destroy)
                if ($reflection->hasMethod('destroy')) {
                    Route::delete($pagePath, [$pageClass, 'destroy'])
                        ->name("{$clusterSlug}.{$pageSlug}.destroy");
                }
            }

            // Register cluster index route (redirect to first page)
            if ($pages->isNotEmpty()) {
                $firstPage = $pages->first();
                $firstPageSlug = $firstPage::getSlug();
                $panelId = $panel->getId();

                Route::get($clusterSlug, function () use ($panelId, $clusterSlug, $firstPageSlug) {
                    return redirect()->route("{$panelId}.tenant.{$clusterSlug}.{$firstPageSlug}");
                })->name($clusterSlug);
            }
        }

        // Register additional auth-specific routes that need special handling
        $this->registerAuthSpecialRoutesForSubdomain($panel);
    }

    /**
     * Register special auth routes for subdomain (2FA, API tokens, Passkeys, etc.)
     */
    protected function registerAuthSpecialRoutesForSubdomain(Panel $panel): void
    {
        // Two-Factor Authentication routes
        if ($panel->hasTwoFactor() && class_exists(\Laravilt\Auth\Pages\Profile\ManageTwoFactor::class)) {
            $twoFactorPage = \Laravilt\Auth\Pages\Profile\ManageTwoFactor::class;
            $cluster = $twoFactorPage::getCluster();

            if ($cluster) {
                $clusterSlug = $cluster::getSlug();
                $twoFactorSlug = $twoFactorPage::getSlug();
                $twoFactorPath = "{$clusterSlug}/{$twoFactorSlug}";

                Route::post("{$twoFactorPath}/enable", [$twoFactorPage, 'enable'])
                    ->name('two-factor.enable');

                Route::post("{$twoFactorPath}/confirm", [$twoFactorPage, 'confirm'])
                    ->name('two-factor.confirm');

                Route::post("{$twoFactorPath}/cancel", [$twoFactorPage, 'cancel'])
                    ->name('two-factor.cancel');

                Route::delete("{$twoFactorPath}/disable", [$twoFactorPage, 'disable'])
                    ->name('two-factor.disable');

                Route::post("{$twoFactorPath}/recovery-codes", [$twoFactorPage, 'regenerateRecoveryCodes'])
                    ->name('two-factor.recovery-codes');
            }
        }

        // API Tokens routes
        if ($panel->hasApiTokens() && class_exists(\Laravilt\Auth\Pages\Profile\ManageApiTokens::class)) {
            $apiTokensPage = \Laravilt\Auth\Pages\Profile\ManageApiTokens::class;
            $cluster = $apiTokensPage::getCluster();

            if ($cluster) {
                $clusterSlug = $cluster::getSlug();
                $apiTokensSlug = $apiTokensPage::getSlug();
                $apiTokensPath = "{$clusterSlug}/{$apiTokensSlug}";

                Route::post("{$apiTokensPath}/store", [\Laravilt\Auth\Http\Controllers\ApiTokenController::class, 'store'])
                    ->name('api-tokens.store');

                Route::delete("{$apiTokensPath}/{token}", [\Laravilt\Auth\Http\Controllers\ApiTokenController::class, 'destroy'])
                    ->name('api-tokens.destroy');

                Route::post("{$apiTokensPath}/revoke-all", [\Laravilt\Auth\Http\Controllers\ApiTokenController::class, 'revokeAll'])
                    ->name('api-tokens.revoke-all');
            }
        }

        // Passkeys routes
        if ($panel->hasPasskeys() && class_exists(\Laravilt\Auth\Pages\Profile\ManagePasskeys::class)) {
            $passkeysPage = \Laravilt\Auth\Pages\Profile\ManagePasskeys::class;
            $cluster = $passkeysPage::getCluster();

            if ($cluster) {
                $clusterSlug = $cluster::getSlug();
                $passkeysSlug = $passkeysPage::getSlug();
                $passkeysPath = "{$clusterSlug}/{$passkeysSlug}";

                Route::get("{$passkeysPath}/register-options", [\Laravilt\Auth\Http\Controllers\PasskeyController::class, 'registerOptions'])
                    ->name('passkeys.register-options');

                Route::post("{$passkeysPath}/register", [\Laravilt\Auth\Http\Controllers\PasskeyController::class, 'register'])
                    ->name('passkeys.register');

                Route::delete("{$passkeysPath}/{credentialId}", [\Laravilt\Auth\Http\Controllers\PasskeyController::class, 'destroy'])
                    ->name('passkeys.destroy');
            }
        }
    }

    /**
     * Register Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\MakePanelCommand::class,
                Commands\MakePageCommand::class,
                Commands\MakeResourceCommand::class,
                Commands\MakeRelationManagerCommand::class,
                Commands\MakeClusterCommand::class,
                Commands\TenantsMigrateCommand::class,
                Commands\TenantCreateCommand::class,
                Commands\TenantDeleteCommand::class,
            ]);
        }
    }

    /**
     * Register multi-database tenancy event listeners.
     */
    protected function registerTenancyEventListeners(): void
    {
        // Only register if in multi-database mode
        if (config('laravilt-tenancy.mode') !== 'multi') {
            return;
        }

        // Create database when tenant is created
        Event::listen(TenantCreated::class, CreateTenantDatabaseListener::class);

        // Run migrations when database is created
        Event::listen(TenantDatabaseCreated::class, MigrateTenantDatabaseListener::class);

        // Seed database when migrations complete
        Event::listen(TenantMigrated::class, SeedTenantDatabaseListener::class);

        // Delete database when tenant is deleted
        Event::listen(TenantDeleted::class, DeleteTenantDatabaseListener::class);
    }

    /**
     * Register tenant routes for a panel.
     */
    protected function registerTenantRoutes(Panel $panel): void
    {
        // Tenant switch route (always available when tenancy is enabled)
        Route::post('tenant/switch', [Http\Controllers\TenantController::class, 'switch'])
            ->name('tenant.switch');

        // Tenant list API route
        Route::get('tenant/list', [Http\Controllers\TenantController::class, 'index'])
            ->name('tenant.list');

        // Tenant registration routes (if enabled)
        if ($panel->hasTenantRegistration()) {
            $registrationPage = $panel->getTenantRegistrationPage();

            // Check if using custom page class or default
            if (is_string($registrationPage) && class_exists($registrationPage) && $registrationPage !== 'default') {
                // Custom page class - use its routes
                $this->registerCustomTenantPage($registrationPage, 'tenant/register', 'tenant.register', $panel);
            } else {
                // Default: Use the RegisterTenant page class
                Route::get('tenant/register', [Pages\Tenancy\RegisterTenant::class, 'create'])
                    ->name('tenant.register');

                Route::post('tenant/register', [Pages\Tenancy\RegisterTenant::class, 'store'])
                    ->name('tenant.register.store');
            }
        }

        // Tenant settings/profile routes (if enabled)
        if ($panel->hasTenantProfile()) {
            $profilePage = $panel->getTenantProfilePage();

            // Check if using custom page class or default
            if (is_string($profilePage) && class_exists($profilePage) && $profilePage !== 'default') {
                // Custom page class - use its routes
                $this->registerCustomTenantPage($profilePage, 'tenant/settings', 'tenant.settings', $panel);
            } else {
                // Default: Use the cluster-based pages
                $this->registerTenantSettingsCluster($panel);
            }
        }
    }

    /**
     * Register the TenantSettings cluster and its pages.
     */
    protected function registerTenantSettingsCluster(Panel $panel): void
    {
        // Register pages with the panel for cluster navigation
        $panel->pages([
            Clusters\TenantSettings::class,
            Pages\TenantSettings\TeamProfile::class,
            Pages\TenantSettings\TeamMembers::class,
        ]);

        // Register cluster index route (redirects to first page)
        Route::get('tenant/settings', [Clusters\TenantSettings::class, 'create'])
            ->name('tenant.settings');

        // Team Profile page routes
        Route::get('tenant/settings/profile', [Pages\TenantSettings\TeamProfile::class, 'create'])
            ->name('tenant.settings.profile');

        Route::post('tenant/settings/profile', [Pages\TenantSettings\TeamProfile::class, 'store'])
            ->name('tenant.settings.profile.store');

        Route::delete('tenant/settings/profile', [Pages\TenantSettings\TeamProfile::class, 'destroy'])
            ->name('tenant.settings.profile.destroy');

        // Team Members page routes
        Route::get('tenant/settings/members', [Pages\TenantSettings\TeamMembers::class, 'create'])
            ->name('tenant.settings.members');

        Route::post('tenant/settings/members', [Pages\TenantSettings\TeamMembers::class, 'store'])
            ->name('tenant.settings.members.store');

        Route::patch('tenant/settings/members/{member}/role', [Pages\TenantSettings\TeamMembers::class, 'updateRole'])
            ->name('tenant.settings.members.update-role');

        Route::delete('tenant/settings/members/{member}', [Pages\TenantSettings\TeamMembers::class, 'destroy'])
            ->name('tenant.settings.members.destroy');
    }

    /**
     * Register routes for a custom tenant page class.
     */
    protected function registerCustomTenantPage(string $pageClass, string $path, string $routeName, Panel $panel): void
    {
        // Check if the page class has static methods for route registration
        if (method_exists($pageClass, 'registerRoutes')) {
            $pageClass::registerRoutes($path, $routeName);
        } else {
            // Default: Register GET for show and POST for store
            Route::get($path, [$pageClass, 'show'])->name($routeName);

            if (method_exists($pageClass, 'store')) {
                Route::post($path, [$pageClass, 'store'])->name($routeName.'.store');
            }

            if (method_exists($pageClass, 'update')) {
                Route::patch($path, [$pageClass, 'update'])->name($routeName.'.update');
            }

            if (method_exists($pageClass, 'destroy')) {
                Route::delete($path, [$pageClass, 'destroy'])->name($routeName.'.destroy');
            }
        }
    }
}
