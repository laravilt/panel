<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Tenancy Mode
    |--------------------------------------------------------------------------
    |
    | This option controls the default tenancy mode for panels. You can choose
    | between 'single' (shared database with tenant_id scoping) or 'multi'
    | (separate database per tenant with subdomain routing).
    |
    | Supported: "single", "multi"
    |
    */

    'mode' => env('TENANCY_MODE', 'single'),

    /*
    |--------------------------------------------------------------------------
    | Central Database Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the central database where tenants, users, and
    | domains are stored. This database is shared across all tenants.
    |
    */

    'central' => [
        'connection' => env('DB_CONNECTION', 'mysql'),

        // Domains that should be treated as central (not tenant subdomains)
        'domains' => [
            'localhost',
            '127.0.0.1',
            env('APP_CENTRAL_DOMAIN', 'localhost'),
            env('APP_DOMAIN', 'localhost'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Database Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control how tenant databases are created and managed
    | in multi-database mode.
    |
    */

    'tenant' => [
        // Database name prefix for tenant databases
        'database_prefix' => env('TENANT_DB_PREFIX', 'tenant_'),

        // Database name suffix for tenant databases
        'database_suffix' => env('TENANT_DB_SUFFIX', ''),

        // Path to tenant-specific migrations
        'migrations_path' => database_path('migrations/tenant'),

        // Connection template for tenant databases
        // Uses the same driver as the main connection by default
        'connection_template' => env('TENANT_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which models are used for tenancy and which models belong
    | to the central vs tenant databases.
    |
    */

    'models' => [
        // The tenant model class
        'tenant' => \Laravilt\Panel\Models\Tenant::class,

        // The domain model class
        'domain' => \Laravilt\Panel\Models\Domain::class,

        // Models that always use the central database
        // Add your User model and any shared models here
        'central' => [
            // \App\Models\User::class,
            // \App\Models\Plan::class,
        ],

        // Models that use the tenant database
        // Add your business models here
        'tenant' => [
            // \App\Models\Customer::class,
            // \App\Models\Product::class,
            // \App\Models\Order::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant Provisioning
    |--------------------------------------------------------------------------
    |
    | Configure how new tenants are provisioned when created.
    |
    */

    'provisioning' => [
        // Automatically create database when tenant is created
        'auto_create_database' => true,

        // Automatically run migrations when database is created
        'auto_migrate' => true,

        // Automatically seed the database after migrations
        'auto_seed' => false,

        // Seeder class to run (if auto_seed is true)
        'seeder' => null, // e.g., \Database\Seeders\TenantSeeder::class

        // Queue provisioning jobs for better performance
        'queue' => false,

        // Queue name for provisioning jobs
        'queue_name' => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Subdomain Configuration
    |--------------------------------------------------------------------------
    |
    | Configure subdomain-based tenant identification for multi-database mode.
    |
    */

    'subdomain' => [
        // The base domain for tenant subdomains
        // Tenants will be accessible at: {tenant}.{domain}
        'domain' => env('APP_DOMAIN', 'localhost'),

        // Subdomains that are reserved and cannot be used by tenants
        'reserved' => [
            'www',
            'api',
            'admin',
            'app',
            'mail',
            'ftp',
            'webmail',
            'cpanel',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for tenant resolution to improve performance.
    |
    */

    'cache' => [
        // Enable tenant resolution caching
        'enabled' => true,

        // Cache TTL in seconds (default: 1 hour)
        'ttl' => 3600,

        // Cache key prefix
        'prefix' => 'laravilt_tenant_',
    ],
];
