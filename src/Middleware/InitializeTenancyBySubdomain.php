<?php

namespace Laravilt\Panel\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravilt\Panel\Facades\Laravilt;
use Laravilt\Panel\Facades\Panel;
use Laravilt\Panel\Models\Domain;
use Laravilt\Panel\Models\Tenant;
use Laravilt\Panel\Tenancy\MultiDatabaseManager;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancyBySubdomain
{
    /**
     * The multi-database manager instance.
     */
    protected MultiDatabaseManager $manager;

    public function __construct(MultiDatabaseManager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Panel::getCurrent();

        // Skip if panel doesn't use multi-database tenancy
        if (! $panel?->isMultiDatabaseTenancy()) {
            return $next($request);
        }

        $host = $request->getHost();
        $baseDomain = $panel->getTenantDomain();

        // Skip if this is a central domain
        if ($panel->isCentralDomain($host)) {
            return $next($request);
        }

        // Extract subdomain
        $subdomain = $this->extractSubdomain($host, $baseDomain);

        if (! $subdomain) {
            return $this->handleNoTenant($request);
        }

        // Check if subdomain is reserved
        if ($panel->isReservedSubdomain($subdomain)) {
            return $this->handleReservedSubdomain($request, $subdomain);
        }

        // Find tenant
        $tenant = $this->resolveTenant($subdomain, $baseDomain);

        if (! $tenant) {
            return $this->handleTenantNotFound($request, $subdomain);
        }

        // Initialize tenancy
        $this->initializeTenancy($tenant);

        return $next($request);
    }

    /**
     * Extract the subdomain from the host.
     */
    protected function extractSubdomain(string $host, string $baseDomain): ?string
    {
        // Check if host ends with base domain
        if (! str_ends_with($host, $baseDomain)) {
            return null;
        }

        // Extract subdomain part
        $subdomain = rtrim(str_replace($baseDomain, '', $host), '.');

        return $subdomain ?: null;
    }

    /**
     * Resolve tenant from subdomain.
     */
    protected function resolveTenant(string $subdomain, string $baseDomain): ?Tenant
    {
        $fullDomain = "{$subdomain}.{$baseDomain}";
        $cacheEnabled = config('laravilt-tenancy.cache.enabled', true);
        $cacheTtl = config('laravilt-tenancy.cache.ttl', 3600);
        $cachePrefix = config('laravilt-tenancy.cache.prefix', 'laravilt_tenant_');

        if ($cacheEnabled) {
            return Cache::remember(
                "{$cachePrefix}domain_{$fullDomain}",
                $cacheTtl,
                fn () => $this->findTenantByDomain($fullDomain, $subdomain)
            );
        }

        return $this->findTenantByDomain($fullDomain, $subdomain);
    }

    /**
     * Find tenant by domain or subdomain.
     */
    protected function findTenantByDomain(string $fullDomain, string $subdomain): ?Tenant
    {
        $tenantModel = config('laravilt-tenancy.models.tenant', Tenant::class);
        $domainModel = config('laravilt-tenancy.models.domain', Domain::class);

        // First try to find by domain record
        $domain = $domainModel::where('domain', $fullDomain)->first();

        if ($domain) {
            return $domain->tenant;
        }

        // Fall back to finding by slug
        return $tenantModel::where('slug', $subdomain)->first();
    }

    /**
     * Initialize tenancy for the given tenant.
     */
    protected function initializeTenancy(Tenant $tenant): void
    {
        // Initialize the multi-database connection
        $this->manager->initialize($tenant);

        // Set the tenant in Laravilt facade
        Laravilt::setTenant($tenant);

        // Store tenant ID in session for later use
        session(['laravilt.tenant_id' => $tenant->getKey()]);
    }

    /**
     * Handle request when no tenant subdomain is present.
     */
    protected function handleNoTenant(Request $request): Response
    {
        // Allow access to central routes
        $panel = Panel::getCurrent();
        $centralPath = "/{$panel->getPath()}";

        // Redirect to central panel
        return redirect($centralPath);
    }

    /**
     * Handle request for a reserved subdomain.
     */
    protected function handleReservedSubdomain(Request $request, string $subdomain): Response
    {
        abort(404, "The subdomain '{$subdomain}' is reserved.");
    }

    /**
     * Handle request when tenant is not found.
     */
    protected function handleTenantNotFound(Request $request, string $subdomain): Response
    {
        abort(404, "Tenant not found for subdomain: {$subdomain}");
    }

    /**
     * Terminate the middleware.
     */
    public function terminate(Request $request, Response $response): void
    {
        // End tenancy when the request is complete
        if ($this->manager->isInitialized()) {
            $this->manager->end();
        }
    }
}
