<?php

namespace Laravilt\Panel\Tenancy\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravilt\Panel\Events\TenantDatabaseCreated;
use Laravilt\Panel\Events\TenantDatabaseCreationFailed;
use Laravilt\Panel\Models\Tenant;
use Laravilt\Panel\Tenancy\MultiDatabaseManager;

class CreateTenantDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The tenant instance.
     */
    public Tenant $tenant;

    /**
     * Create a new job instance.
     */
    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;

        // Use configured queue
        $this->onQueue(config('laravilt-tenancy.provisioning.queue_name', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(MultiDatabaseManager $manager): void
    {
        $success = $manager->createDatabase($this->tenant);

        if ($success) {
            event(new TenantDatabaseCreated($this->tenant));
        } else {
            event(new TenantDatabaseCreationFailed($this->tenant));
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        event(new TenantDatabaseCreationFailed($this->tenant, $exception));
    }
}
