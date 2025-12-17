<?php

namespace Laravilt\Panel\Listeners;

use Laravilt\Panel\Events\TenantDatabaseCreated;
use Laravilt\Panel\Tenancy\Jobs\MigrateTenantDatabase;
use Laravilt\Panel\Tenancy\MultiDatabaseManager;

class MigrateTenantDatabaseListener
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
    public function handle(TenantDatabaseCreated $event): void
    {
        // Check if auto-migrate is enabled
        if (! config('laravilt-tenancy.provisioning.auto_migrate', true)) {
            return;
        }

        // Check if we should queue the job
        if (config('laravilt-tenancy.provisioning.queue', false)) {
            MigrateTenantDatabase::dispatch($event->tenant);
        } else {
            // Migrate synchronously
            $this->manager->migrateTenant($event->tenant);
        }
    }
}
