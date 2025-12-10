<?php

namespace Laravilt\Panel\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravilt\Panel\PanelRegistry;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if (! $request->expectsJson()) {
            // Try to get the current panel from the registry first
            $panel = \Laravilt\Panel\Facades\Panel::getCurrent();

            // If no panel in registry, try to detect from URL path
            if (! $panel) {
                $panel = $this->detectPanelFromRequest($request);
            }

            if ($panel && $panel->hasLogin()) {
                return $panel->loginUrl();
            }

            // Fallback to default login route if it exists
            if (Route::has('login')) {
                return route('login');
            }

            // Last resort fallback
            return '/login';
        }

        return null;
    }

    /**
     * Try to detect the panel from the request URL path.
     */
    protected function detectPanelFromRequest(Request $request): ?\Laravilt\Panel\Panel
    {
        $registry = app(PanelRegistry::class);
        $path = trim($request->path(), '/');
        $segments = explode('/', $path);

        if (empty($segments[0])) {
            return null;
        }

        // Check if the first segment matches a panel path
        foreach ($registry->all() as $panel) {
            $panelPath = trim($panel->getPath(), '/');
            if ($panelPath === $segments[0]) {
                // Set as current panel
                $registry->setCurrent($panel->getId());
                return $panel;
            }
        }

        return null;
    }
}
