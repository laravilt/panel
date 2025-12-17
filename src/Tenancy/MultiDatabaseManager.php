<?php

namespace Laravilt\Panel\Tenancy;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Laravilt\Panel\Models\Tenant;

class MultiDatabaseManager
{
    /**
     * The current tenant instance.
     */
    protected ?Tenant $currentTenant = null;

    /**
     * Whether tenancy is currently initialized.
     */
    protected bool $initialized = false;

    /**
     * The database manager instance.
     */
    protected DatabaseManager $db;

    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
    }

    /**
     * Initialize tenancy for the given tenant.
     */
    public function initialize(Tenant $tenant): void
    {
        if ($this->initialized && $this->currentTenant?->id === $tenant->id) {
            return;
        }

        $this->currentTenant = $tenant;
        $this->configureTenantConnection($tenant);
        $this->initialized = true;
    }

    /**
     * End the current tenancy.
     */
    public function end(): void
    {
        if (! $this->initialized) {
            return;
        }

        // Disconnect the tenant connection
        $this->db->disconnect('tenant');

        $this->currentTenant = null;
        $this->initialized = false;
    }

    /**
     * Configure the tenant database connection.
     */
    protected function configureTenantConnection(Tenant $tenant): void
    {
        $templateConnection = config('laravilt-tenancy.tenant.connection_template', config('database.default'));
        $template = config("database.connections.{$templateConnection}");

        if (! $template) {
            throw new \RuntimeException("Database connection template '{$templateConnection}' not found.");
        }

        $databaseName = $tenant->getDatabaseName();
        $driver = $template['driver'] ?? 'mysql';

        // For SQLite, use the full path
        if ($driver === 'sqlite') {
            $databaseName = database_path("{$databaseName}.sqlite");
        }

        // Create tenant-specific connection configuration
        $tenantConfig = array_merge($template, [
            'database' => $databaseName,
        ]);

        // Register the tenant connection
        Config::set('database.connections.tenant', $tenantConfig);

        // Purge and reconnect
        $this->db->purge('tenant');
    }

    /**
     * Get the current tenant.
     */
    public function getCurrentTenant(): ?Tenant
    {
        return $this->currentTenant;
    }

    /**
     * Check if tenancy is initialized.
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Create a new database for a tenant.
     */
    public function createDatabase(Tenant $tenant): bool
    {
        $databaseName = $tenant->getDatabaseName();
        $connection = config('laravilt-tenancy.central.connection', config('database.default'));
        $driver = config("database.connections.{$connection}.driver");

        try {
            switch ($driver) {
                case 'mysql':
                    DB::connection($connection)->statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    break;

                case 'pgsql':
                    // PostgreSQL doesn't support IF NOT EXISTS for databases
                    $exists = DB::connection($connection)
                        ->select('SELECT 1 FROM pg_database WHERE datname = ?', [$databaseName]);

                    if (empty($exists)) {
                        DB::connection($connection)->statement("CREATE DATABASE \"{$databaseName}\"");
                    }
                    break;

                case 'sqlite':
                    $path = database_path("{$databaseName}.sqlite");
                    if (! file_exists($path)) {
                        touch($path);
                    }
                    break;

                default:
                    throw new \RuntimeException("Unsupported database driver: {$driver}");
            }

            return true;
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Delete a tenant's database.
     */
    public function deleteDatabase(Tenant $tenant): bool
    {
        $databaseName = $tenant->getDatabaseName();
        $connection = config('laravilt-tenancy.central.connection', config('database.default'));
        $driver = config("database.connections.{$connection}.driver");

        try {
            switch ($driver) {
                case 'mysql':
                    DB::connection($connection)->statement("DROP DATABASE IF EXISTS `{$databaseName}`");
                    break;

                case 'pgsql':
                    // Terminate connections before dropping
                    DB::connection($connection)->statement("
                        SELECT pg_terminate_backend(pid)
                        FROM pg_stat_activity
                        WHERE datname = '{$databaseName}'
                    ");
                    DB::connection($connection)->statement("DROP DATABASE IF EXISTS \"{$databaseName}\"");
                    break;

                case 'sqlite':
                    $path = database_path("{$databaseName}.sqlite");
                    if (file_exists($path)) {
                        unlink($path);
                    }
                    break;

                default:
                    throw new \RuntimeException("Unsupported database driver: {$driver}");
            }

            return true;
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Run migrations for a tenant.
     */
    public function migrateTenant(Tenant $tenant, array $options = []): int
    {
        // Initialize the tenant connection
        $this->initialize($tenant);

        $migrationsPath = config('laravilt-tenancy.tenant.migrations_path', database_path('migrations/tenant'));

        $options = array_merge([
            '--database' => 'tenant',
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--force' => true,
        ], $options);

        return \Illuminate\Support\Facades\Artisan::call('migrate', $options);
    }

    /**
     * Rollback migrations for a tenant.
     */
    public function rollbackTenant(Tenant $tenant, array $options = []): int
    {
        $this->initialize($tenant);

        $migrationsPath = config('laravilt-tenancy.tenant.migrations_path', database_path('migrations/tenant'));

        $options = array_merge([
            '--database' => 'tenant',
            '--path' => $migrationsPath,
            '--realpath' => true,
            '--force' => true,
        ], $options);

        return \Illuminate\Support\Facades\Artisan::call('migrate:rollback', $options);
    }

    /**
     * Seed the tenant database.
     */
    public function seedTenant(Tenant $tenant, ?string $seeder = null): int
    {
        $this->initialize($tenant);

        $seeder = $seeder ?? config('laravilt-tenancy.provisioning.seeder');

        if (! $seeder) {
            return 0;
        }

        return \Illuminate\Support\Facades\Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => $seeder,
            '--force' => true,
        ]);
    }

    /**
     * Check if a database exists.
     */
    public function databaseExists(string $databaseName): bool
    {
        $connection = config('laravilt-tenancy.central.connection', config('database.default'));
        $driver = config("database.connections.{$connection}.driver");

        try {
            switch ($driver) {
                case 'mysql':
                    $result = DB::connection($connection)
                        ->select('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$databaseName]);

                    return ! empty($result);

                case 'pgsql':
                    $result = DB::connection($connection)
                        ->select('SELECT 1 FROM pg_database WHERE datname = ?', [$databaseName]);

                    return ! empty($result);

                case 'sqlite':
                    return file_exists(database_path("{$databaseName}.sqlite"));

                default:
                    return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Run a callback within a tenant's context.
     */
    public function run(Tenant $tenant, callable $callback): mixed
    {
        $previousTenant = $this->currentTenant;
        $wasInitialized = $this->initialized;

        try {
            $this->initialize($tenant);

            return $callback($tenant);
        } finally {
            if ($wasInitialized && $previousTenant) {
                $this->initialize($previousTenant);
            } else {
                $this->end();
            }
        }
    }
}
