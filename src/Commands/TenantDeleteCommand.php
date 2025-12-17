<?php

namespace Laravilt\Panel\Commands;

use Illuminate\Console\Command;
use Laravilt\Panel\Events\TenantDeleted;
use Laravilt\Panel\Models\Tenant;
use Laravilt\Panel\Tenancy\MultiDatabaseManager;

class TenantDeleteCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenant:delete
                            {tenant : The tenant ID or slug}
                            {--force : Force deletion without confirmation}
                            {--keep-database : Do not delete the database}';

    /**
     * The console command description.
     */
    protected $description = 'Delete a tenant and optionally their database';

    /**
     * Execute the console command.
     */
    public function handle(MultiDatabaseManager $manager): int
    {
        $tenantId = $this->argument('tenant');
        $tenantModel = config('laravilt-tenancy.models.tenant', Tenant::class);

        // Find the tenant
        $tenant = $tenantModel::where('id', $tenantId)
            ->orWhere('slug', $tenantId)
            ->first();

        if (! $tenant) {
            $this->error("Tenant '{$tenantId}' not found.");

            return self::FAILURE;
        }

        $this->warn("About to delete tenant: {$tenant->name} ({$tenant->slug})");

        if ($tenant->database) {
            $this->warn("Database: {$tenant->database}");
        }

        if (! $this->option('force')) {
            if (! $this->confirm('Are you sure you want to delete this tenant?')) {
                $this->info('Deletion cancelled.');

                return self::SUCCESS;
            }
        }

        $this->newLine();

        // Delete database if in multi-db mode and not keeping it
        $mode = config('laravilt-tenancy.mode', 'single');

        if ($mode === 'multi' && $tenant->database && ! $this->option('keep-database')) {
            $this->components->task('Deleting database', function () use ($tenant, $manager) {
                return $manager->deleteDatabase($tenant);
            });
        }

        // Delete domains
        $this->components->task('Deleting domains', function () use ($tenant) {
            $tenant->domains()->delete();

            return true;
        });

        // Detach users
        $this->components->task('Detaching users', function () use ($tenant) {
            if (method_exists($tenant, 'users')) {
                $tenant->users()->detach();
            }

            return true;
        });

        // Delete the tenant
        $this->components->task('Deleting tenant', function () use ($tenant) {
            $tenant->delete();

            return true;
        });

        // Fire event for any listeners
        event(new TenantDeleted($tenant));

        $this->newLine();
        $this->info('Tenant deleted successfully!');

        return self::SUCCESS;
    }
}
