<?php

namespace Laravilt\Panel\Listeners;

use Laravilt\Panel\Events\TenantDeleted;
use Laravilt\Panel\Tenancy\Jobs\DeleteTenantDatabase;
use Laravilt\Panel\Tenancy\MultiDatabaseManager;

class DeleteTenantDatabaseListener
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
    public function handle(TenantDeleted $event): void
    {
        // Check if we're in multi-database mode
        $mode = config('laravilt-tenancy.mode', 'single');
        if ($mode !== 'multi') {
            return;
        }

        // Check if we should queue the job
        if (config('laravilt-tenancy.provisioning.queue', false)) {
            DeleteTenantDatabase::dispatch($event->tenant);
        } else {
            // Delete synchronously
            $this->manager->deleteDatabase($event->tenant);
        }
    }
}
