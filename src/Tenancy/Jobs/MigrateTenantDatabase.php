<?php

namespace Laravilt\Panel\Tenancy\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Laravilt\Panel\Events\TenantMigrated;
use Laravilt\Panel\Events\TenantMigrationFailed;
use Laravilt\Panel\Models\Tenant;
use Laravilt\Panel\Tenancy\MultiDatabaseManager;

class MigrateTenantDatabase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The tenant instance.
     */
    public Tenant $tenant;

    /**
     * Migration options.
     */
    public array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(Tenant $tenant, array $options = [])
    {
        $this->tenant = $tenant;
        $this->options = $options;

        // Use configured queue
        $this->onQueue(config('laravilt-tenancy.provisioning.queue_name', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(MultiDatabaseManager $manager): void
    {
        try {
            $exitCode = $manager->migrateTenant($this->tenant, $this->options);

            if ($exitCode === 0) {
                event(new TenantMigrated($this->tenant));
            } else {
                event(new TenantMigrationFailed($this->tenant, new \RuntimeException("Migration failed with exit code: {$exitCode}")));
            }
        } catch (\Throwable $e) {
            event(new TenantMigrationFailed($this->tenant, $e));
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        event(new TenantMigrationFailed($this->tenant, $exception));
    }
}
