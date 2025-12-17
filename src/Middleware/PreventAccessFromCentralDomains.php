<?php

namespace Laravilt\Panel\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravilt\Panel\Facades\Panel;
use Symfony\Component\HttpFoundation\Response;

class PreventAccessFromCentralDomains
{
    /**
     * Handle an incoming request.
     *
     * This middleware ensures that tenant-specific routes cannot be accessed
     * from central domains in multi-database tenancy mode.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Panel::getCurrent();

        // Skip if panel doesn't use multi-database tenancy
        if (! $panel?->isMultiDatabaseTenancy()) {
            return $next($request);
        }

        $host = $request->getHost();

        // If accessing from a central domain, redirect to login or show error
        if ($panel->isCentralDomain($host)) {
            return $this->handleCentralDomainAccess($request);
        }

        return $next($request);
    }

    /**
     * Handle access attempt from a central domain.
     */
    protected function handleCentralDomainAccess(Request $request): Response
    {
        $panel = Panel::getCurrent();

        // Redirect to the panel's central landing page
        return redirect("/{$panel->getPath()}");
    }
}
