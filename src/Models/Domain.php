<?php

namespace Laravilt\Panel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'domains';

    /**
     * Get the database connection for the model.
     * Domains are ALWAYS stored in the central database, not in tenant databases.
     */
    public function getConnectionName()
    {
        return config('laravilt-tenancy.central.connection', config('database.default'));
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'domain',
        'tenant_id',
        'is_primary',
        'is_verified',
        'verified_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns this domain.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Mark this domain as primary.
     */
    public function makePrimary(): void
    {
        // Remove primary from other domains of the same tenant
        static::where('tenant_id', $this->tenant_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        $this->update(['is_primary' => true]);
    }

    /**
     * Mark this domain as verified.
     */
    public function verify(): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }

    /**
     * Check if this is a subdomain of the given base domain.
     */
    public function isSubdomainOf(string $baseDomain): bool
    {
        return str_ends_with($this->domain, '.'.$baseDomain);
    }

    /**
     * Extract the subdomain part from the full domain.
     */
    public function getSubdomain(string $baseDomain): ?string
    {
        if (! $this->isSubdomainOf($baseDomain)) {
            return null;
        }

        return str_replace('.'.$baseDomain, '', $this->domain);
    }

    /**
     * Create a subdomain for the given base domain.
     */
    public static function createSubdomain(Tenant $tenant, string $subdomain, string $baseDomain, bool $isPrimary = true): static
    {
        $fullDomain = $subdomain.'.'.$baseDomain;

        return static::create([
            'domain' => $fullDomain,
            'tenant_id' => $tenant->id,
            'is_primary' => $isPrimary,
            'is_verified' => true, // Subdomains are auto-verified
            'verified_at' => now(),
        ]);
    }

    /**
     * Find tenant by domain.
     */
    public static function findTenantByDomain(string $domain): ?Tenant
    {
        $domainRecord = static::where('domain', $domain)->first();

        return $domainRecord?->tenant;
    }

    /**
     * Get route key for model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'domain';
    }
}
