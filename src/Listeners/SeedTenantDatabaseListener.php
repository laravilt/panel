<?php

namespace Laravilt\Panel\Listeners;

use Laravilt\Panel\Events\TenantMigrated;
use Laravilt\Panel\Tenancy\Jobs\SeedTenantDatabase;
use Laravilt\Panel\Tenancy\MultiDatabaseManager;

class SeedTenantDatabaseListener
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
     * Handle the event.
     */
    public function handle(TenantMigrated $event): void
    {
        // Check if auto-seed is enabled
        if (! config('laravilt-tenancy.provisioning.auto_seed', false)) {
            return;
        }

        $seeder = config('laravilt-tenancy.provisioning.seeder');
        if (! $seeder) {
            return;
        }

        // Check if we should queue the job
        if (config('laravilt-tenancy.provisioning.queue', false)) {
            SeedTenantDatabase::dispatch($event->tenant, $seeder);
        } else {
            // Seed synchronously
            $this->manager->seedTenant($event->tenant, $seeder);
        }
    }
}
