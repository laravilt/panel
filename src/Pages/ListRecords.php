<?php

declare(strict_types=1);

namespace Laravilt\Panel\Pages;

use Illuminate\Http\Request;
use Laravilt\Tables\Table;

abstract class ListRecords extends Page
{
    /**
     * Default view mode: 'table', 'grid', or 'api'
     */
    protected string $defaultView = 'table';

    /**
     * Get the page title using the resource's plural label.
     */
    public static function getTitle(): string
    {
        $resource = static::getResource();

        if ($resource) {
            return $resource::getPluralLabel();
        }

        return parent::getTitle();
    }

    /**
     * Get the navigation label using the resource's plural label.
     */
    public static function getLabel(): string
    {
        $resource = static::getResource();

        if ($resource) {
            return $resource::getPluralLabel();
        }

        return parent::getLabel();
    }

    /**
     * Get the page heading using the resource's plural label.
     */
    public function getHeading(): string
    {
        return static::getTitle();
    }

    public function table(Table $table): Table
    {
        $resource = static::getResource();

        return $resource::table($table);
    }

    /**
     * Define header actions for this page.
     * Override this method in your page class to customize actions.
     *
     * @return array<\Laravilt\Actions\Action>
     */
    protected function headerActions(): array
    {
        return [];
    }

    /**
     * Get all header actions for this page.
     * Automatically includes CreateAction if the resource has a create page.
     *
     * @return array<mixed>
     */
    public function getHeaderActions(): array
    {
        $actions = $this->headerActions();

        // Auto-add CreateAction if resource has a create page and no create action exists
        $resource = static::getResource();
        if ($resource) {
            $pages = $resource::getPages();
            $hasCreatePage = isset($pages['create']);

            // Check if headerActions already has a CreateAction
            $hasCreateAction = collect($actions)->contains(function ($action) {
                return $action instanceof \Laravilt\Actions\CreateAction;
            });

            if ($hasCreatePage && ! $hasCreateAction) {
                // Add CreateAction that will auto-configure based on page context
                array_unshift($actions, \Laravilt\Actions\CreateAction::make());
            }
        }

        return $actions;
    }

    public function getTableQuery()
    {
        $resource = static::getResource();

        return $resource::getModel()::query();
    }

    /**
     * Check if this resource supports view toggle (table has card configuration or API enabled).
     */
    public function hasViewToggle(): bool
    {
        $resource = static::getResource();

        // If table is gridOnly, only show view toggle if API is available
        if ($resource::isGridOnly()) {
            return $resource::hasApi();
        }

        // View toggle is available if we have grid OR api options
        $hasGridOption = $resource::hasTable() && $resource::hasCardConfig();
        $hasApiOption = $resource::hasApi();

        return $hasGridOption || $hasApiOption;
    }

    /**
     * Check if this resource has API enabled.
     */
    public function hasApiOption(): bool
    {
        $resource = static::getResource();

        return $resource::hasApi();
    }

    /**
     * Check if this resource has grid option (card configuration).
     * Returns false if gridOnly is enabled (no toggle needed).
     */
    public function hasGridOption(): bool
    {
        $resource = static::getResource();

        // If gridOnly, return false as no option is needed
        if ($resource::isGridOnly()) {
            return false;
        }

        return $resource::hasTable() && $resource::hasCardConfig();
    }

    /**
     * Get the session key for storing view preference.
     */
    protected function getViewSessionKey(): string
    {
        $resource = static::getResource();

        return 'laravilt_view_preference_'.$resource::getSlug();
    }

    /**
     * Get the available views for this resource.
     *
     * @return array<string>
     */
    public function getAvailableViews(): array
    {
        $resource = static::getResource();

        // If gridOnly, grid is the only data view but API may still be available
        if ($resource::isGridOnly()) {
            $views = ['grid'];
            if ($this->hasApiOption()) {
                $views[] = 'api';
            }

            return $views;
        }

        $views = ['table'];

        if ($this->hasGridOption()) {
            $views[] = 'grid';
        }

        if ($this->hasApiOption()) {
            $views[] = 'api';
        }

        return $views;
    }

    /**
     * Get the current view mode from request, session, or default.
     */
    public function getCurrentView(): string
    {
        $resource = static::getResource();

        // If gridOnly, always return grid view
        if ($resource::isGridOnly()) {
            return 'grid';
        }

        $sessionKey = $this->getViewSessionKey();
        $urlView = request()->query('view');
        $availableViews = $this->getAvailableViews();

        // If URL has view param, validate and save to session
        if ($urlView !== null) {
            if (in_array($urlView, $availableViews)) {
                session()->put($sessionKey, $urlView);

                return $urlView;
            }

            return $this->defaultView;
        }

        // No URL param - check session for saved preference
        $sessionView = session()->get($sessionKey);

        if ($sessionView !== null && in_array($sessionView, $availableViews)) {
            return $sessionView;
        }

        return $this->defaultView;
    }

    /**
     * Get extra props for Inertia response.
     *
     * @return array<string, mixed>
     */
    protected function getInertiaProps(): array
    {
        $resource = static::getResource();
        $apiResourceProps = null;

        if ($this->hasApiOption()) {
            $apiResource = $resource::getApiResource();
            if ($apiResource) {
                $apiResourceProps = $apiResource->toInertiaProps();
                // Set the correct endpoint based on panel path
                $panelPath = $this->getPanel()?->getPath() ?? 'admin';
                $correctEndpoint = "/{$panelPath}/api/".$resource::getSlug();
                $correctBaseUrl = url('');
                $correctFullUrl = url($correctEndpoint);

                $apiResourceProps['endpoint'] = $correctEndpoint;
                $apiResourceProps['fullUrl'] = $correctFullUrl;

                // Also fix the OpenAPI spec with correct URLs
                if (isset($apiResourceProps['openApiSpec'])) {
                    $openApiSpec = &$apiResourceProps['openApiSpec'];

                    // Update server URL
                    if (isset($openApiSpec['servers'][0])) {
                        $openApiSpec['servers'][0]['url'] = $correctBaseUrl;
                    }

                    // Update paths with correct prefix
                    if (isset($openApiSpec['paths'])) {
                        $newPaths = [];
                        $oldBasePath = '/api/'.$resource::getSlug();
                        foreach ($openApiSpec['paths'] as $path => $methods) {
                            // Replace old path with correct panel-prefixed path
                            $newPath = str_replace($oldBasePath, $correctEndpoint, $path);
                            $newPaths[$newPath] = $methods;
                        }
                        $openApiSpec['paths'] = $newPaths;
                    }
                }
            }
        }

        // Generate API token for testing if we're showing the API view
        $apiToken = null;
        if ($this->getCurrentView() === 'api' && auth()->check()) {
            $user = auth()->user();
            // Check if user has createToken method (Sanctum's HasApiTokens trait)
            if (method_exists($user, 'createToken')) {
                // Create a temporary token for API testing (expires in 1 hour)
                $token = $user->createToken('api-tester', ['*'], now()->addHour());
                $apiToken = $token->plainTextToken;
            }
        }

        return [
            'hasViewToggle' => $this->hasViewToggle(),
            'hasGridOption' => $this->hasGridOption(),
            'hasApiOption' => $this->hasApiOption(),
            'availableViews' => $this->getAvailableViews(),
            'currentView' => $this->getCurrentView(),
            'apiResource' => $apiResourceProps,
            'apiToken' => $apiToken,
        ];
    }

    /**
     * Get the schema (table) for this page.
     * The table handles both table and grid views based on card configuration.
     */
    public function getSchema(): array
    {
        $resource = static::getResource();
        $table = $this->table(new Table);
        $table->query(fn () => $this->getTableQuery());
        $table->model($resource::getModel());
        $table->resourceSlug($resource::getSlug());

        return [$table];
    }
}
