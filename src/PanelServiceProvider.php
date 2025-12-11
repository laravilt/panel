<?php

namespace Laravilt\Panel;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravilt\Panel\Middleware\IdentifyPanel;

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

        $this->app->singleton(PanelRegistry::class);
    }

    /**
     * Bootstrap any panel services.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'laravilt-panel');

        $this->publishes([
            __DIR__.'/../config/laravilt-panel.php' => config_path('laravilt/panel.php'),
        ], 'laravilt-panel-config');

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
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            \Illuminate\Contracts\Session\Middleware\AuthenticatesSessions::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ];
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

                Route::middleware(array_merge(
                    $middlewareWithoutAuth,
                    [IdentifyPanel::class.':'.$panel->getId()],
                    $authMiddleware,
                    [Http\Middleware\HandleLocalization::class],
                    [Http\Middleware\SharePanelData::class]
                ))
                    ->prefix($panel->getPath())
                    ->name($panel->getId().'.')
                    ->group(function () use ($panel) {
                        // Register page routes
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
        Route::get($slug.'/{id}', function ($id) use ($resourceClass) {
            if (request()->wantsJson() && ! request()->header('X-Inertia')) {
                $pages = $resourceClass::getPages();
                foreach ($pages as $pageConfig) {
                    if (is_subclass_of($pageConfig['class'], \Laravilt\Panel\Pages\ManageRecords::class)) {
                        $page = app($pageConfig['class']);

                        return $page->show(request(), $id);
                    }
                }
            }

            return response()->json(['error' => 'Page not found'], 404);
        })->name('resources.'.$slug.'.show');

        // PUT /{slug}/{id} - Update record
        Route::put($slug.'/{id}', function ($id) use ($resourceClass) {
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
        Route::delete($slug.'/{id}', function ($id) use ($resourceClass) {
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
        Route::patch($slug.'/{id}/column', function ($id) use ($resourceClass, $modelClass) {
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
     * Register relation manager routes for a resource.
     */
    protected function registerRelationManagerRoutes(string $resourceClass, string $slug, string $modelClass, Panel $panel): void
    {
        $relationManagers = $resourceClass::getRelations();

        if (empty($relationManagers)) {
            return;
        }

        // Route: GET /{slug}/{id}/relations/{relationship}
        Route::get($slug.'/{id}/relations/{relationship}', function ($id, $relationship) use ($resourceClass, $modelClass) {
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
        Route::post($slug.'/{id}/relations/{relationship}', function ($id, $relationship) use ($resourceClass, $modelClass) {
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
        Route::put($slug.'/{id}/relations/{relationship}/{relationId}', function ($id, $relationship, $relationId) use ($resourceClass, $modelClass) {
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
        Route::delete($slug.'/{id}/relations/{relationship}/{relationId}', function ($id, $relationship, $relationId) use ($resourceClass, $modelClass) {
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
        Route::post($slug.'/{id}/relations/{relationship}/bulk-delete', function ($id, $relationship) use ($resourceClass, $modelClass) {
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
        Route::patch($slug.'/{id}/relations/{relationship}/{relationId}/column', function ($id, $relationship, $relationId) use ($resourceClass, $modelClass) {
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
            Route::delete($slug.'/{id}', function ($id) use ($pageClass, $panel) {
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
     * Register Artisan commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\InstallCommand::class,
                Commands\MakePanelCommand::class,
                Commands\MakePageCommand::class,
                Commands\MakeResourceCommand::class,
                Commands\MakeRelationManagerCommand::class,
                Commands\MakeClusterCommand::class,
            ]);
        }
    }
}
