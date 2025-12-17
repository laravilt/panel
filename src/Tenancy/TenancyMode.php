<?php

namespace Laravilt\Panel\Tenancy;

enum TenancyMode: string
{
    /**
     * Single database tenancy mode.
     * All tenants share the same database with tenant_id scoping.
     * Uses path-based routing: /panel/{tenant-slug}/...
     */
    case Single = 'single';

    /**
     * Multi-database tenancy mode.
     * Each tenant has their own database.
     * Uses subdomain-based routing: tenant.domain.com/panel/...
     */
    case MultiDatabase = 'multi';

    /**
     * Check if this is multi-database mode.
     */
    public function isMultiDatabase(): bool
    {
        return $this === self::MultiDatabase;
    }

    /**
     * Check if this is single database mode.
     */
    public function isSingle(): bool
    {
        return $this === self::Single;
    }

    /**
     * Get the default tenancy mode.
     */
    public static function default(): self
    {
        return self::Single;
    }

    /**
     * Get a human-readable label for this mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single Database',
            self::MultiDatabase => 'Multi-Database',
        };
    }

    /**
     * Get a description for this mode.
     */
    public function description(): string
    {
        return match ($this) {
            self::Single => 'All tenants share the same database with row-level isolation using tenant_id.',
            self::MultiDatabase => 'Each tenant has their own database with complete data isolation.',
        };
    }
}
