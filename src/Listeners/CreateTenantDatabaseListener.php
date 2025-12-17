<?php

namespace Laravilt\Panel\Listeners;

use Laravilt\Panel\Events\TenantCreated;
use Laravilt\Panel\Tenancy\Jobs\CreateTenantDatabase;
use Laravilt\Panel\Tenancy\MultiDatabaseManager;

class CreateTenantDatabaseListener
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
    public function handle(TenantCreated $event): void
    {
        // Check if auto-create is enabled
        if (! config('laravilt-tenancy.provisioning.auto_create_database', true)) {
            return;
        }

        // Check if we're in multi-database mode
        $mode = config('laravilt-tenancy.mode', 'single');
        if ($mode !== 'multi') {
            return;
        }

        // Check if we should queue the job
        if (config('laravilt-tenancy.provisioning.queue', false)) {
            CreateTenantDatabase::dispatch($event->tenant);
        } else {
            // Create synchronously
            $this->manager->createDatabase($event->tenant);
        }
    }
}
