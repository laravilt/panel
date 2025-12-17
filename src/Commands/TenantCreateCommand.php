<?php

namespace Laravilt\Panel\Commands;

use Illuminate\Console\Command;
use Laravilt\Panel\Events\TenantCreated;
use Laravilt\Panel\Models\Domain;
use Laravilt\Panel\Models\Tenant;
use Laravilt\Panel\Tenancy\MultiDatabaseManager;

class TenantCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenant:create
                            {name : The tenant name}
                            {--slug= : The tenant slug (defaults to slugified name)}
                            {--email= : The tenant email address}
                            {--domain= : The subdomain (defaults to slug)}
                            {--no-database : Do not create a database}
                            {--no-migrate : Do not run migrations}
                            {--seed : Seed the database after migrations}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new tenant';

    /**
     * Execute the console command.
     */
    public function handle(MultiDatabaseManager $manager): int
    {
        $name = $this->argument('name');
        $slug = $this->option('slug') ?: \Illuminate\Support\Str::slug($name);
        $email = $this->option('email');
        $subdomain = $this->option('domain') ?: $slug;

        $tenantModel = config('laravilt-tenancy.models.tenant', Tenant::class);
        $domainModel = config('laravilt-tenancy.models.domain', Domain::class);

        // Check if slug already exists
        if ($tenantModel::where('slug', $slug)->exists()) {
            $this->error("A tenant with slug '{$slug}' already exists.");

            return self::FAILURE;
        }

        $this->info("Creating tenant: {$name}");
        $this->newLine();

        // Create the tenant
        $tenant = $tenantModel::create([
            'name' => $name,
            'slug' => $slug,
            'email' => $email,
        ]);

        $this->components->info("Tenant created with ID: {$tenant->id}");

        // Create the subdomain
        $baseDomain = config('laravilt-tenancy.subdomain.domain', 'localhost');
        $fullDomain = "{$subdomain}.{$baseDomain}";

        $domainModel::create([
            'domain' => $fullDomain,
            'tenant_id' => $tenant->id,
            'is_primary' => true,
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        $this->components->info("Domain created: {$fullDomain}");

        // Create database if in multi-db mode
        $mode = config('laravilt-tenancy.mode', 'single');

        if ($mode === 'multi' && ! $this->option('no-database')) {
            $this->components->task('Creating database', function () use ($tenant, $manager) {
                return $manager->createDatabase($tenant);
            });

            // Run migrations
            if (! $this->option('no-migrate')) {
                $this->components->task('Running migrations', function () use ($tenant, $manager) {
                    return $manager->migrateTenant($tenant) === 0;
                });

                // Seed if requested
                if ($this->option('seed')) {
                    $this->components->task('Seeding database', function () use ($tenant, $manager) {
                        $seeder = config('laravilt-tenancy.provisioning.seeder');
                        if ($seeder) {
                            $manager->seedTenant($tenant, $seeder);
                        }

                        return true;
                    });
                }
            }
        }

        // Fire event for any listeners
        event(new TenantCreated($tenant));

        $this->newLine();
        $this->info('Tenant created successfully!');
        $this->newLine();
        $this->table(
            ['Property', 'Value'],
            [
                ['ID', $tenant->id],
                ['Name', $tenant->name],
                ['Slug', $tenant->slug],
                ['Database', $tenant->database ?? 'N/A'],
                ['Domain', $fullDomain],
            ]
        );

        return self::SUCCESS;
    }
}
