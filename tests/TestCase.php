<?php

namespace Laravilt\Panel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            \Laravilt\Support\SupportServiceProvider::class,
            \Laravilt\Panel\PanelServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Load tenancy configuration for SaaS tests
        config()->set('laravilt-tenancy', [
            'mode' => 'single',
            'central' => [
                'connection' => 'sqlite',
                'domains' => ['localhost', '127.0.0.1'],
            ],
            'tenant' => [
                'database_prefix' => 'tenant_',
                'database_suffix' => '',
                'migrations_path' => database_path('migrations/tenant'),
                'connection_template' => 'sqlite',
            ],
            'models' => [
                'tenant' => \Laravilt\Panel\Models\Tenant::class,
                'domain' => \Laravilt\Panel\Models\Domain::class,
                'central' => [],
                'tenant' => [],
            ],
            'provisioning' => [
                'auto_create_database' => true,
                'auto_migrate' => true,
                'auto_seed' => false,
                'seeder' => null,
                'queue' => false,
                'queue_name' => 'default',
            ],
            'subdomain' => [
                'domain' => 'localhost',
                'reserved' => ['www', 'api', 'admin', 'app', 'mail', 'ftp', 'webmail', 'cpanel'],
            ],
            'cache' => [
                'enabled' => true,
                'ttl' => 3600,
                'prefix' => 'laravilt_tenant_',
            ],
        ]);
    }
}
