<?php

namespace Laravilt\Panel\Commands;

use Illuminate\Console\Command;
use Laravilt\Panel\Models\Tenant;
use Laravilt\Panel\Tenancy\MultiDatabaseManager;

class TenantsMigrateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenants:migrate
                            {--tenant= : Migrate only this tenant (by ID or slug)}
                            {--fresh : Drop all tables and re-run all migrations}
                            {--seed : Seed the database after migrations}
                            {--seeder= : The seeder class to run}
                            {--force : Force migrations in production}';

    /**
     * The console command description.
     */
    protected $description = 'Run migrations for all tenants or a specific tenant';

    /**
     * Execute the console command.
     */
    public function handle(MultiDatabaseManager $manager): int
    {
        $tenantModel = config('laravilt-tenancy.models.tenant', Tenant::class);

        // Get tenant(s) to migrate
        if ($tenantId = $this->option('tenant')) {
            $tenants = $tenantModel::where('id', $tenantId)
                ->orWhere('slug', $tenantId)
                ->get();

            if ($tenants->isEmpty()) {
                $this->error("Tenant '{$tenantId}' not found.");

                return self::FAILURE;
            }
        } else {
            $tenants = $tenantModel::all();
        }

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');

            return self::SUCCESS;
        }

        $this->info("Migrating {$tenants->count()} tenant(s)...");
        $this->newLine();

        $failed = 0;

        foreach ($tenants as $tenant) {
            $this->components->task(
                "Migrating tenant: {$tenant->name} ({$tenant->slug})",
                function () use ($tenant, $manager, &$failed) {
                    try {
                        $options = [];

                        if ($this->option('fresh')) {
                            $options['--fresh'] = true;
                        }

                        if ($this->option('force')) {
                            $options['--force'] = true;
                        }

                        $exitCode = $manager->migrateTenant($tenant, $options);

                        if ($exitCode !== 0) {
                            $failed++;

                            return false;
                        }

                        // Seed if requested
                        if ($this->option('seed') || $this->option('seeder')) {
                            $seeder = $this->option('seeder') ?: config('laravilt-tenancy.provisioning.seeder');
                            $manager->seedTenant($tenant, $seeder);
                        }

                        return true;
                    } catch (\Exception $e) {
                        $failed++;

                        return false;
                    }
                }
            );
        }

        $this->newLine();

        if ($failed > 0) {
            $this->error("Failed to migrate {$failed} tenant(s).");

            return self::FAILURE;
        }

        $this->info('All tenants migrated successfully.');

        return self::SUCCESS;
    }
}
